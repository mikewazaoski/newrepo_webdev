# Deploying The Pet Pantry to Railway

## Quick setup (required)

1. **New Project** → Deploy from GitHub → this repo.
2. **Add MySQL** (`+ New` → Database → MySQL). Note the service name (e.g. `MySQL`).
3. On the **app service** (not MySQL), open **Variables** and add:

| Variable | Value |
|----------|--------|
| `APP_ENV` | `prod` |
| `APP_DEBUG` | `0` |
| `APP_SECRET` | Random 32+ character string |
| `DATABASE_URL` | `${{MySQL.MYSQL_URL}}` — replace `MySQL` with your MySQL service name |
| `DEFAULT_URI` | `https://YOUR-APP.up.railway.app` (optional — auto-detected from `RAILWAY_PUBLIC_DOMAIN` if omitted) |
| `TRUSTED_PROXIES` | `REMOTE_ADDR` |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` |
| `MAILER_DSN` | `null://null` (or your SMTP DSN) |

Optional (creates admin on first deploy if no user with that email exists):

| Variable | Value |
|----------|--------|
| `ADMIN_EMAIL` | Your login email |
| `ADMIN_PASSWORD` | Your login password |
| `ADMIN_USERNAME` | `admin` (optional) |
| `ADMIN_NAME` | `Administrator` (optional) |

4. **Networking** on the app service → **Generate Domain** → set `DEFAULT_URI` to that HTTPS URL.
5. Redeploy the app service.

The container runs `bin/railway-env.php` on startup. It accepts any of:

- `DATABASE_URL` (recommended: `${{MySQL.MYSQL_URL}}`)
- `MYSQL_URL` (if you reference that directly)
- `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE` (auto-built URL)

## Linking MySQL to the app

In the app service **Variables** tab, use **Add variable reference**:

1. Variable name: `DATABASE_URL`
2. Value: `${{MySQL.MYSQL_URL}}` (autocomplete helps pick the MySQL service)

Do **not** copy the raw URL by hand unless necessary — references stay in sync when Railway rotates credentials.

## Files used for deployment

| File | Purpose |
|------|---------|
| `Dockerfile` | PHP 8.2, Nginx, Composer, Encore assets |
| `entrypoint.sh` | Env resolution, cache, DB check, migrations |
| `bin/railway-env.php` | Maps Railway MySQL vars → Symfony `DATABASE_URL` |
| `docker/php-fpm/zz-railway.conf` | Passes env vars into PHP-FPM |
| `railway.toml` | Dockerfile build, health check on `/`, port **8080** |

## Port

- Default: **8080** (`ENV PORT=8080`, `EXPOSE 8080`)
- Railway may set `PORT`; `entrypoint.sh` uses `${PORT:-8080}`

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Deploy crashes / `exit 1` on database | **Add MySQL** to the project. On the app service, set `DATABASE_URL` = `${{MySQL.MYSQL_URL}}`. **Delete** any `DATABASE_URL` with `127.0.0.1`, `localhost`, or `@mysql:` (Docker-only). |
| `Unable to read "/app/.env"` | Redeploy latest image (entrypoint creates `.env` at startup). |
| Migrations fail | Check deploy logs; ensure MySQL and app are in the **same Railway project**. |
| **500 on /login** (or all pages) | Set `DEFAULT_URI` to your HTTPS domain, or redeploy latest code (`bin/railway-env.php` auto-fills it). Ensure deploy logs show **cache warmup OK** (not suppressed). Set `DATABASE_URL=\${{MySQL.MYSQL_URL}}`. |
| 502 / app not listening | Railway sets `PORT` automatically — do not hardcode Docker port 8080 in variables. |

## Optional: persistent uploads

Product images in `public/uploads/images/` are lost on redeploy unless you add a **Railway Volume** mounted to `/app/public/uploads`.

## Test after deploy

- `https://YOUR-DOMAIN.up.railway.app/`
- `https://YOUR-DOMAIN.up.railway.app/api/mobile/products`

## Local Docker test

```bash
docker build -t pet-pantry .
docker run -p 8080:8080 \
  -e APP_SECRET=local_test_secret \
  -e DATABASE_URL="mysql://user:pass@host:3306/db?serverVersion=8.0&charset=utf8mb4" \
  pet-pantry
```

Open http://localhost:8080
