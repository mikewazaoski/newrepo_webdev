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
| `DEFAULT_URI` | `https://YOUR-APP.up.railway.app` (after generating a domain) |
| `TRUSTED_PROXIES` | `REMOTE_ADDR` |
| `MESSENGER_TRANSPORT_DSN` | `sync://` |
| `MAILER_DSN` | `null://null` (or your SMTP DSN) |

4. **Networking** on the app service → **Generate Domain** → set `DEFAULT_URI` to that HTTPS URL.
5. Redeploy the app service.

The container runs `bin/railway-env.php` on startup. It accepts any of:

- `DATABASE_URL` (recommended: `${{MySQL.MYSQL_URL}}`)
- `MYSQL_PRIVATE_URL` / `MYSQL_URL` / `MYSQL_PUBLIC_URL`
- `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE` (auto-built URL)

## Linking MySQL to the app

In the app service **Variables** tab, use **Add variable reference**:

1. Variable name: `DATABASE_URL`
2. Value: `${{MySQL.MYSQL_URL}}` (autocomplete helps pick the MySQL service)

Do **not** copy the raw URL by hand unless necessary — references stay in sync when Railway rotates credentials.

**Delete** any old `DATABASE_URL` that contains `127.0.0.1`, `localhost`, or `@mysql:` — those only work in local Docker.

## Verify after deploy

Open:

```
https://YOUR-DOMAIN.up.railway.app/api/mobile/health
```

| Response | Meaning |
|----------|---------|
| `"database": "connected"` | Ready — login and registration work |
| `"message": "DATABASE_URL is not configured"` | Add `DATABASE_URL=${{MySQL.MYSQL_URL}}` on the **app** service and redeploy |
| `"database_url_set": true` but disconnected | MySQL service down, wrong URL, or check deploy logs for `Database OK for PHP-FPM` |

In **Deploy logs** you should see:

- `railway-env: wrote .env with DATABASE_URL for ...`
- `Database OK for PHP-FPM`

If you see `WARNING: No DATABASE_URL` or `Cannot connect to database`, fix variables and redeploy.

## Files used for deployment

| File | Purpose |
|------|---------|
| `Dockerfile` | PHP 8.2, Nginx, Composer, Encore assets |
| `entrypoint.sh` | Env resolution, cache, DB check, migrations |
| `bin/railway-env.php` | Maps Railway MySQL vars → Symfony `DATABASE_URL` + PHP-FPM env |
| `docker/php-fpm/zz-railway.conf` | Passes env vars into PHP-FPM |
| `railway.toml` | Dockerfile build, health check on `/`, port **8080** |

## Port

- Default: **8080** (`ENV PORT=8080`, `EXPOSE 8080`)
- Railway may set `PORT`; `entrypoint.sh` uses `${PORT:-8080}`

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| Deploy crashes / `exit 1` on database | **Add MySQL** to the project. On the app service, set `DATABASE_URL` = `${{MySQL.MYSQL_URL}}`. **Delete** any `DATABASE_URL` with `127.0.0.1`, `localhost`, or `@mysql:` (Docker-only). |
| **500 on register or login** | Open `/api/mobile/health` — if `"database":"disconnected"`, MySQL is not linked. Add MySQL, set `DATABASE_URL=${{MySQL.MYSQL_URL}}`, redeploy. Check deploy logs for `Database OK for PHP-FPM`. |
| `Unable to read "/app/.env"` | Redeploy latest image (entrypoint creates `.env` at startup). |
| Migrations fail | Check deploy logs; ensure MySQL and app are in the **same Railway project**. |
| 502 / app not listening | Railway sets `PORT` automatically — do not hardcode Docker port 8080 in variables. |

## Optional: persistent uploads

Product images in `public/uploads/images/` are lost on redeploy unless you add a **Railway Volume** mounted to `/app/public/uploads`.

## Test after deploy

- `https://YOUR-DOMAIN.up.railway.app/`
- `https://YOUR-DOMAIN.up.railway.app/api/mobile/health`
- `https://YOUR-DOMAIN.up.railway.app/api/mobile/products`

## Mobile app (React Native)

See **[MOBILE_API.md](MOBILE_API.md)** for all customer-facing JSON endpoints, auth flow, and fetch examples.

## Local Docker test

```bash
docker build -t pet-pantry .
docker run -p 8080:8080 \
  -e APP_SECRET=local_test_secret \
  -e DATABASE_URL="mysql://user:pass@host:3306/db?serverVersion=8.0&charset=utf8mb4" \
  pet-pantry
```

Open http://localhost:8080
