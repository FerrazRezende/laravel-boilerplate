# Laravel Boilerplate

Boilerplate para novos projetos Laravel com feature flags, RBAC, Redis, Horizon e RustFS já configurados.

## Stack

- **Laravel 12** (PHP 8.3+) servido por **Octane/Swoole**
- **Inertia.js** para o front-end (sem API separada)
- **PostgreSQL** como banco principal
- **Redis** para cache e sessão
- **Redis + Laravel Horizon** para filas (dashboard em `/horizon`)
- **Reverb** para broadcasting em tempo real
- **RustFS** (S3-compatível) para armazenamento de arquivos
- **Laravel Pennant** para feature flags
- **Spatie Permission** para RBAC
- **KSUIDs** como chave primária de todos os models (27 caracteres, ordenáveis por data de criação, seguros para expor em URLs — em vez de inteiros auto-incrementais)

Todos os serviços rodam via Docker Compose; nada disso precisa estar instalado localmente.

## Arquitetura

O código de domínio é organizado em módulos (`Modules/Identity`, `Profile`,
`Presence`, `FeatureFlags`, `Permissions`), não num `app/` único — cada
domínio tem seus próprios controllers, models, rotas, migrations e traduções,
isolados dos demais. Para o mapa completo, os diagramas e como criar um
módulo novo, veja [MODULES.md](MODULES.md).

## Criando um novo projeto

Instale o instalador uma vez por máquina:

```bash
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

Como o app roda em Octane, alterações em PHP só valem depois de recarregar os workers:

```bash
make octane-reload
```

## Testes

```bash
make test
```
