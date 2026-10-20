# Laravel Boilerplate

Boilerplate para novos projetos Laravel com feature flags, RBAC, Redis, Horizon e RustFS já configurados.

## Stack

- **Laravel 13** (PHP 8.5+) servido por **Octane/Swoole**
- **Inertia.js** para o front-end (sem API separada)
- **PostgreSQL** como banco principal
- **Redis** para cache e sessão
- **Redis + Laravel Horizon** para filas — `high`, `medium` e `low`, cada uma com seu pool de workers (dashboard em `/horizon`)
- **Reverb** para broadcasting em tempo real
- **RustFS** (S3-compatível) para armazenamento de arquivos
- **Laravel Pennant** para feature flags
- **Spatie Permission** para RBAC
- **KSUIDs** como chave primária de todos os models (27 caracteres, ordenáveis por data de criação, seguros para expor em URLs — em vez de inteiros auto-incrementais)

Todos os serviços rodam via Docker Compose; nada disso precisa estar instalado localmente.

## Arquitetura

O código de domínio é organizado em módulos (`Modules/Identity`, `Profile`,
`Presence`, `FeatureFlags`, `Permissions`, mais os opcionais `Observability` e
`Ai`), não num `app/` único — cada domínio tem seus próprios controllers,
models, rotas, migrations e traduções, isolados dos demais. Para o mapa
completo, os diagramas e como criar um módulo novo, veja
[MODULES.md](MODULES.md).

Duas partes têm documentação própria: [QUEUES.md](QUEUES.md) para filas,
Horizon e progresso de jobs, e [AI.md](AI.md) para o assistente.

## Criando um novo projeto

Instale o instalador uma vez por máquina:

```bash
composer global require ferrazrezende/boilerplate-installer
```

Depois, para cada novo projeto:

```bash
boilerplate new meu-app
```

Isso baixa este repositório, copia `.env.example` para `.env`, roda
`composer install`/`npm install` e remove os módulos opcionais que você não
pediu. Eles moram aqui ligados, para serem desenvolvidos, revisados e testados
como o resto — o instalador é quem tira.

| Flag | O que faz |
|------|-----------|
| `--obs` | Mantém o módulo **Observability**: progresso de job ao vivo e a tela `/system/jobs`, com as filas, a capacidade de cada uma e o que está rodando |
| `--ai` | Mantém o assistente: [Laravel AI SDK](https://laravel.com/docs/ai-sdk) com chat na landing page e dentro do app |
| `--mvc` | Gera no layout MVC plano do Laravel, sem a camada de módulos |
| `--ref` | Branch, tag ou commit do boilerplate a baixar (padrão: `main`) |
| `--force`, `-f` | Sobrescreve o diretório de destino se ele já existir |

As flags combinam:

```bash
boilerplate new meu-app --obs --ai
```

## Rodando localmente

```bash
make setup   # build + up + composer install + migrate + npm build
make up      # sobe os containers
make down    # derruba os containers
```

Veja `make help` para a lista completa de comandos (`shell`, `artisan`, `migrate`, `test`, `pint`, ...).

Como o app roda em Octane, alterações em PHP só valem depois de recarregar os workers:

```bash
make octane-reload
```

## Imagem de produção

O `docker compose` usa o estágio `dev` do `Dockerfile`, com Composer e Node
dentro e o projeto montado por cima. Para deploy, o estágio final é outro:

```bash
docker build -t meu-app .   # estágio production
```

Ele leva só o código, o `vendor` sem pacotes de dev e os assets já
compilados, e roda como `www-data`. O `.env` fica de fora pelo
`.dockerignore` — a configuração entra por variáveis de ambiente do
orquestrador. Depois de subir, rode `php artisan migrate --force` e, para
criar o primeiro admin, use o tinker: o `UserSeeder` não roda em produção.

## Testes

```bash
make test
```
