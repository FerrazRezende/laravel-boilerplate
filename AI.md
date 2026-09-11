# Assistente de IA

O módulo opcional **Ai** traz o [Laravel AI SDK](https://laravel.com/docs/ai-sdk)
já configurado, com dois chats prontos: um balão flutuante no app autenticado e
um chat na landing page. Preencheu uma key de provider no `.env`, funciona.

Ele só existe no projeto se ele foi criado com `boilerplate new --ai`.

## Ligando

```bash
# .env
AI_DEFAULT=anthropic
ANTHROPIC_API_KEY=sk-ant-...
```

`AI_DEFAULT` escolhe com qual provider o assistente fala; o `config/ai.php`
lista todos os suportados (OpenAI, Gemini, Groq, Mistral, Bedrock, Ollama e
outros). Sem key, os dois chats informam que não estão configurados — não
quebram, não devolvem 500.

Depois de mexer no `.env`: `make octane-reload`.

## As duas portas

| | Balão no app | Chat na landing |
|---|---|---|
| Rota | `POST /ai/chat` | `POST /ai/public-chat` |
| Quem alcança | Usuário autenticado | Qualquer visitante |
| Gate | `auth` + flag `ai` | Presença da key |
| Throttle | 30/min | **5/min por IP** |
| Histórico enviado | Completo (até 20 turnos) | Últimos 6 turnos |

### Por que o chat público não usa a flag `ai`

Porque as feature flags deste projeto resolvem **por usuário** — o
`Feature::define()` recebe um `User`, e as estratégias de rollout (percentual,
lista de usuários, bypass de admin) só fazem sentido com alguém na frente.
Visitante não tem usuário, então o gate recusaria todo mundo.

Pelo mesmo motivo a landing page não pode consultar `activeFeatures`: essa prop
só é preenchida quando há usuário. Quem diz se o chat aparece é a prop
`aiEnabled`, calculada no `HandleInertiaRequests` a partir da key configurada.

O balão do app, esse sim, usa `feature:ai` normalmente — ali há usuário, e
liga/desliga pela tela `/system/features`.

## O agente

`Modules/Ai/app/Agents/Assistant.php`. É uma classe com `instructions()` — a
persona e o escopo — e `messages()`, o histórico da conversa.

```php
$response = (new Assistant($historico))->stream('Como funciona o RBAC aqui?');
```

O `stream()` devolvido de uma rota já sai como **Server-Sent Events**, então o
frontend consome com `fetch` puro, sem depender de pacote npm nenhum. Para
trocar de modelo, os atributos do SDK resolvem:

```php
#[Provider(Lab::Anthropic)]
#[Model('claude-sonnet-5')]
class Assistant implements Agent { /* … */ }
```

## Histórico: no navegador, não no banco

O SDK traz um concern `RemembersConversations` que persiste a conversa em duas
tabelas. Aqui o histórico fica no navegador e viaja junto em cada turno.

**Por quê.** O chat público não tem usuário em quem pendurar uma conversa, e
persistir exigiria publicar as migrations do pacote em `database/migrations` —
um diretório compartilhado, o que tornaria este módulo bem mais difícil de
remover limpo. Manter os dois caminhos sem estado deixa o módulo uma folha.

**O custo.** A conversa se perde ao recarregar a página.

**Como trocar**, se o seu projeto quiser histórico persistente no chat
autenticado: publique as migrations do pacote, use o concern
`RemembersConversations` no agente e passe a guardar o `conversationId`. Vale
lembrar do aviso da doc do SDK: `continue()` **não** verifica se a conversa
pertence a quem está pedindo — autorize antes.

## O que protege a porta pública

Um agente exposto a visitante é uma forma de alguém gastar a sua conta de API,
e a doc do SDK diz explicitamente que ele não traz proteção nenhuma para esse
caso. O que existe aqui:

- **Throttle de 5 requests por minuto por IP** na rota.
- **Histórico validado e limitado** — no máximo 20 mensagens, papel restrito a
  `user`/`assistant` e tamanho máximo por mensagem. O histórico vem do
  navegador, então não é confiável: sem o limite de papel, dava para injetar
  uma mensagem `system` e reescrever as instruções do agente.
- **Só os últimos 6 turnos** vão para o provider no caminho público, o que
  limita o tamanho de cada chamada.
- **Instruções escopadas ao produto**, com ordem explícita de recusar o que
  não tem a ver com a aplicação.

Isso reduz o risco, não elimina. Para um projeto com tráfego real, considere
também um teto de gasto no painel do provider.

## Um detalhe que engana

O provider é chamado **depois** que os headers da resposta já foram enviados —
é streaming. Então uma key inválida não vira um erro HTTP: vira um `200` cujo
stream termina sem um único delta. O sintoma é silêncio.

Por isso o cliente trata "stream terminou e a resposta está vazia" como erro e
mostra uma mensagem, em vez de deixar um balão em branco na tela. Se você mexer
no `useChat.ts`, mantenha essa checagem.

## Testando sem gastar

```php
Ai::fakeAgent(Assistant::class, ['Resposta de mentira.']);
```

É o que o `Modules/Ai/tests/Feature/ChatTest.php` faz. Nenhum teste deste
projeto chama uma API de verdade.

## Removendo

Se o projeto foi criado com `--ai` e você mudou de ideia:

```bash
php scripts/remove-feature.php Ai
composer remove laravel/ai
```

Isso apaga o módulo, os dois chats, a prop `aiEnabled`, o `config/ai.php` e o
bloco de keys do `.env`.
