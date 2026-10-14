# Handi docs

| File | What it holds |
|---|---|
| [api.md](api.md) | Every endpoint with real request/response examples, errors, auth, Postman how-to |
| [handi-api.postman_collection.json](handi-api.postman_collection.json) | Postman collection — 55 requests, token and ids saved automatically |
| [openapi.json](openapi.json) | OpenAPI 3.1 spec (also live at `/docs/api.json`; import into Postman/Insomnia if you prefer) |
| [database.md](database.md) | Tables, columns, relationships and the pick rules |
| [decisions.md](decisions.md) | Product and technical decisions, with reasons |
| [design-tokens.md](design-tokens.md) | Colors, fonts, spacing, radius, shadows from the app design |
| [screens.md](screens.md) | Every app screen and the API it uses |
| [deployment.md](deployment.md) | Server, Nginx, scheduler, storage, EAS builds |

Interactive API docs run inside the backend at **http://localhost:8000/docs/api** (Scramble). Regenerate the static spec with `php artisan scramble:export --path=../docs/openapi.json`.
