## Arquitetura

O código de domínio vive num `app/` único, no layout padrão do Laravel —
`app/Http/Controllers`, `app/Models`, `app/Services`, `app/Enums` e afins.
Rotas em `routes/web.php` (mais `routes/auth.php` e `routes/api.php`),
migrations em `database/migrations`, traduções em `lang/{en,pt,es}.json` e as
páginas Vue em `resources/js/Pages`.
