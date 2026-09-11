# Filas e progresso de jobs

Este boilerplate roda filas em Redis, supervisionadas pelo [Horizon](https://laravel.com/docs/horizon).
Em cima disso, o módulo opcional **Observability** responde uma pergunta que o
Horizon não responde: *quanto falta para este job terminar?*

O módulo só existe no projeto se ele foi criado com `boilerplate new --obs`.
O resto desta página — filas, Horizon, como despachar um job — vale sempre.

## As filas

Três filas na conexão `redis`, cada uma com seu próprio supervisor no
`config/horizon.php`:

| Fila     | Para que serve                                   | Workers (local) | Workers (produção) | Timeout |
|----------|--------------------------------------------------|-----------------|--------------------|---------|
| `high`   | Trabalho que alguém está esperando na tela        | 1–3             | 2–10               | 60s     |
| `medium` | O resto. É a fila padrão de quem não escolhe uma  | 1–2             | 1–6                | 120s    |
| `low`    | Trabalho em lote que ninguém está olhando         | 1               | 1–3                | 600s    |

Um supervisor por fila, e não um supervisor cobrindo as três: um supervisor
compartilhado move os processos dele conforme a carga, então uma pilha de job
`low` consegue tomar todos os workers da máquina. Pool fixo significa que cada
fila tem capacidade própria — e é isso que faz a coluna de capacidade na tela
querer dizer alguma coisa, já que o teto é um número e não uma negociação.

Dentro do pool, o `auto` escala entre `minProcesses` e `maxProcesses` pelo tempo
que a fila leva para esvaziar.

```php
ImportaPlanilha::dispatch($arquivo)->onQueue('low');
```

Quem não chama `onQueue()` cai em `medium` (`REDIS_QUEUE` no `.env`). Vale
escolher `high` quando tem gente parada esperando o resultado, e `low` quando o
job é longo e ninguém sente se ele demorar mais dez minutos. No boilerplate, o
único trabalho na `high` é a atualização de status do Presence — meia dúzia de
campos indo para quem está com a tela aberta.

```
QUEUE_CONNECTION=redis     # .env
REDIS_QUEUE=medium
```

```bash
make horizon           # sobe o Horizon
make horizon-status
```

O painel fica em `/horizon`, restrito a `is_admin` pelo gate `viewHorizon`
definido em `app/Providers/HorizonServiceProvider.php`.

Acrescentar uma quarta fila é acrescentar um supervisor: a tela `/system/jobs`
lê o plano de provisionamento do próprio Horizon, então ela aparece lá sozinha,
sem nenhuma lista de filas para manter em dia.

## Por que o progresso não vive dentro do Horizon

Parece o lugar óbvio, mas não dá. O Horizon publica exatamente duas coisas —
um stub de service provider e o `config/horizon.php`. Nenhuma view, nenhum
asset. A interface inteira é um `dist/app.js` já compilado, e a única view é
uma `layout.blade.php` que só emite:

```blade
{{ Laravel\Horizon\Horizon::css() }}
{{ Laravel\Horizon\Horizon::js() }}
<div id="horizon" v-cloak>
```

Sem `@yield`, sem `@stack`, sem `@include` — zero ponto de extensão. Acrescentar
uma coluna de progresso ali significaria editar JavaScript compilado dentro de
`vendor/`, que o próximo `composer update` sobrescreve.

O caminho inverso funciona: a tela `/system/jobs` fica ao lado do Horizon e
linka para ele. O Horizon continua sendo a ferramenta de triagem e de jobs
falhos; a tela nova é só a visão ao vivo.

## Reportando progresso

```php
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Observability\Traits\TracksProgress;

class ImportaPlanilha implements ShouldQueue
{
    use Queueable, TracksProgress;

    public function __construct(private readonly string $arquivo)
    {
        $this->initializeTracksProgress();
    }

    public function progressLabel(): string
    {
        return __('Importando planilha');
    }

    public function handle(): void
    {
        $linhas = $this->linhas();

        foreach ($linhas as $i => $linha) {
            $this->importa($linha);
            $this->progress($i + 1, count($linhas));
        }

        $this->clearProgress();
    }

    public function failed(): void
    {
        $this->clearProgress();
    }
}
```

Três coisas a saber:

- **`progress()` aceita contagem ou percentual.** `progress(30, 120)` e
  `progress(25)` são a mesma coisa.
- **Chamar em laço apertado é seguro.** As escritas são limitadas a uma por
  segundo, então um job sobre 100 mil linhas não afoga a fila que ele está
  reportando. 0% e 100% sempre passam, para a barra começar e terminar honesta.
- **Reportar nunca derruba o job.** Se o Redis cair, `progress()` engole o erro
  e o trabalho continua. Progresso é diagnóstico, não parte da transação.

Chame `initializeTracksProgress()` no construtor e `clearProgress()` no fim e
no `failed()`, senão a barra fica congelada em 60% até o TTL expirar.

## Como o progresso chega na tela

```
job chama progress()
   ├─ grava em job-progress:{ksuid} no Redis (TTL renovado a cada escrita)
   ├─ entra num sorted set por horário de início
   └─ emite JobProgressUpdated (ShouldBroadcastNow, do próprio worker)
         ├─ canal jobs-admin        → sempre
         └─ canal jobs.{userId}     → só se havia usuário no dispatch
```

**O evento é `ShouldBroadcastNow`, não `ShouldBroadcast`.** Um `ShouldBroadcast`
comum empilha um `BroadcastEvent` na fila — a mesma fila que o job que está
reportando está ocupando. Cada atualização entra atrás do trabalho que ela
descreve, e a barra só anda quando o worker libera, que é exatamente quando ela
deixa de ter utilidade. Com um pool pequeno o efeito é uma tela que fica parada
o job inteiro e pisca no fim. Mandar inline custa ao worker uma chamada HTTP
para o Reverb, limitada a uma por segundo pelo throttle e dentro do mesmo
`try/catch` — broadcaster fora do ar continua não derrubando job nenhum.

O id é um **KSUID gerado no construtor** e serializado junto com o job. Isso é
o que faz um retry continuar na mesma barra em vez de abrir uma segunda — o id
da fila muda a cada tentativa, este não.

O TTL é renovado a cada escrita, então worker morto não deixa lixo: a entrada
expira sozinha e some do índice na próxima leitura. Não existe job de limpeza,
de propósito.

O canal do usuário só aparece quando havia alguém autenticado no momento do
dispatch. Job de schedule ou de comando simplesmente não tem dono, e segue
funcionando — a captura é opcional justamente para não acoplar a trait à
autenticação.

## A tela

`/system/jobs`, restrita a admin, atrás da flag `observability`. Duas partes:

**As filas.** Uma linha por fila, com quantos workers o Horizon tem de pé contra
o teto configurado (`3/3`), quantos jobs estão reservados por um worker agora,
quantos esperam e a estimativa de tempo para esvaziar. Os números de fila vêm do
Redis direto e os de worker vêm do Horizon: com o Horizon parado a tela mostra
`0` workers e a fila crescendo, que é justamente o que se quer ver nessa hora —
não uma tela vazia. A lista de filas sai do plano de provisionamento do Horizon,
então ela nunca discorda do `config/horizon.php`.

Esses números não têm evento por trás, então essa é a única parte da tela que
faz polling: um reload parcial de cinco em cinco segundos, só da prop `queues`,
preservando o estado para não derrubar a assinatura do websocket junto.

**Os jobs.** Label, fila, percentual e horário de início de cada job em execução,
ao vivo pelo websocket. Traz também um botão que dispara um job de demonstração
na fila escolhida — num projeto recém-criado não há fila nenhuma rodando, e uma
tela vazia não demonstra nada. Disparar três demos na `low`, que tem um worker
só, é o jeito mais rápido de ver uma fila passar da capacidade dela.

## Os dois endereços do Reverb

O navegador e o PHP não chegam no Reverb pelo mesmo endereço quando o stack é
Docker. O navegador fala com a porta publicada no host (`localhost:8080`); o
PHP fala com o serviço na rede do Compose (`reverb:8080`). Publicar em
`localhost` de dentro do container `app` ou `horizon` bate no próprio container,
onde não tem ninguém ouvindo.

Por isso o projeto tem um `config/broadcasting.php` próprio, cuja única razão de
existir é esta linha:

```php
'host' => env('REVERB_BACKEND_HOST') ?: env('REVERB_HOST'),
```

O default do framework usa `REVERB_HOST` dos dois lados, e a falha resultante é
silenciosa da pior forma: o websocket do navegador conecta, o canal autoriza, o
job roda até o fim — e nenhum evento sai, porque o `progress()` engole o erro de
broadcast de propósito. Fora do Docker, onde os dois endereços são o mesmo,
basta `REVERB_HOST` e o fallback cuida do resto.

`BroadcastAddressTest` fixa essa separação, porque um `php artisan config:publish
broadcasting` restaura o default do framework e derruba tudo de novo sem avisar.

## Ao criar um canal novo

A armadilha que já custou um bug silencioso neste projeto: **nunca escreva
`private-` no nome do canal.** O `PrivateChannel` acrescenta no servidor, o
`private()` do Echo acrescenta no cliente, e o Laravel remove exatamente um
antes de casar com o `routes/channels.php`. Escreva você e ele chega dobrado —
a assinatura autoriza normalmente e nenhum evento é entregue.

```php
// servidor
new PrivateChannel('jobs-admin')
Broadcast::channel('jobs-admin', fn ($user) => (bool) $user->is_admin);
```

```ts
// cliente
echo.private('jobs-admin')
```

Um teste não pega isso por HTTP: o `phpunit.xml` roda com
`BROADCAST_CONNECTION=null`, e o broadcaster nulo autoriza tudo sem olhar o
`channels.php`. Por isso `JobsScreenTest` e `PresenceChannelTest` comparam os
nomes como texto, fixando cada nome do cliente ao evento que o alimenta.

## O que ficou de fora

Pulse, Prometheus/Grafana, métricas de CPU/RAM, histórico e gráfico de
throughput. Profundidade de fila e tempo até esvaziar continuam sendo do
Horizon — a tela lê o `WorkloadRepository` dele em vez de recalcular, e o que
ela acrescenta é o recorte por capacidade ao lado do progresso dos jobs.
