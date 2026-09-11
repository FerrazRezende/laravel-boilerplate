# Filas e progresso de jobs

Este boilerplate roda filas em Redis, supervisionadas pelo [Horizon](https://laravel.com/docs/horizon).
Em cima disso, o módulo opcional **Observability** responde uma pergunta que o
Horizon não responde: *quanto falta para este job terminar?*

O módulo só existe no projeto se ele foi criado com `boilerplate new --obs`.
O resto desta página — filas, Horizon, como despachar um job — vale sempre.

## A fila

Uma fila só, `default`, na conexão `redis`, com um supervisor. É o suficiente
para a maioria dos projetos, e dividir em várias filas é uma decisão que vale
tomar quando existir um motivo concreto (um tipo de job lento afogando os
rápidos, por exemplo), não antes.

```
QUEUE_CONNECTION=redis     # .env
```

```bash
make horizon           # sobe o Horizon
make horizon-status
```

O painel fica em `/horizon`, restrito a `is_admin` pelo gate `viewHorizon`
definido em `app/Providers/HorizonServiceProvider.php`.

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
   └─ emite JobProgressUpdated
         ├─ canal jobs-admin        → sempre
         └─ canal jobs.{userId}     → só se havia usuário no dispatch
```

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

`/system/jobs`, restrita a admin, atrás da flag `observability`. Mostra label,
percentual e horário de início de cada job em execução, atualizando ao vivo
pelo websocket. Traz também um botão que dispara um job de demonstração — num
projeto recém-criado não há fila nenhuma rodando, e uma tela vazia não
demonstra nada.

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

Pulse, Prometheus/Grafana, métricas de CPU/RAM e painel de capacidade de fila.
Profundidade de fila e tempo até esvaziar já existem no Horizon (`api/workload`)
e não vale reimplementar sem um motivo.
