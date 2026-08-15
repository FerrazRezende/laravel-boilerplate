# Laravel Boilerplate

Boilerplate para novos projetos Laravel com feature flags, RBAC, Redis, RabbitMQ e MinIO já configurados.

## Stack

- **Laravel 12** (PHP 8.3+)
- **Inertia.js** para o front-end (sem API separada)
- **PostgreSQL** como banco principal
- **Redis** para cache e sessão
- **RabbitMQ** como fila (`laravel/queue-rabbitmq`)
- **Reverb** para broadcasting em tempo real
- **MinIO** (S3-compatível) para armazenamento de arquivos
- **Laravel Pennant** para feature flags
- **Spatie Permission** para RBAC

Todos os serviços rodam via Docker Compose; nada disso precisa estar instalado localmente.

## Criando um novo projeto

Instale o instalador uma vez por máquina (via HTTPS, usando o token do [GitHub CLI](https://cli.github.com) já autenticado):

```bash
composer global config github-oauth.github.com "$(gh auth token)"
composer global config repositories.boilerplate vcs https://github.com/FerrazRezende/boilerplate-installer.git
composer global require ferrazrezende/boilerplate-installer
```

Depois, para cada novo projeto:

```bash
boilerplate new meu-app
```

Para incluir o [Laravel AI SDK](https://laravel.com/docs/ai-sdk) já configurado:

```bash
boilerplate new meu-app --ai
```

Isso baixa este repositório, gera `.env`, roda `composer install`/`npm install` e, com `--ai`, instala e publica o `laravel/ai`.

## Rodando localmente

```bash
make setup   # build + up + composer install + migrate + npm build
make up      # sobe os containers
make down    # derruba os containers
```

Veja `make help` para a lista completa de comandos (`shell`, `artisan`, `migrate`, `test`, `pint`, ...).

## Testes

```bash
make test
```
