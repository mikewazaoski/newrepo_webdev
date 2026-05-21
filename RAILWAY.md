# Deploying The Pet Pantry to Railway

## Files added for deployment

| File | Purpose |
|------|---------|
| `Dockerfile` | Builds PHP 8.2, Nginx, Composer, and Encore assets |
| `docker/nginx/nginx-main.conf` | Main Nginx configuration |
| `docker/nginx/default.conf` | Symfony server block (`public/` as web root) |
| `entrypoint.sh` | Port setup, cache warmup, migrations, starts Nginx + PHP-FPM |
| `.dockerignore` | Keeps Docker image small |
| `railway.toml` | Tells Railway to use the Dockerfile and port **8080** |

## Port

- Default: **8080** (`ENV PORT=8080`, `EXPOSE 8080`)
- Railway can override with its own `PORT` variable; `entrypoint.sh` uses `${PORT:-8080}`

## Railway setup (dashboard)

1. **New Project** → Deploy from GitHub → select this repo.
2. **Add MySQL** database service.
3. On the **app service**, set **Variables**:

   | Variable | Example / notes |
   |----------|-----------------|
   | `APP_ENV` | `prod` |
   | `APP_DEBUG` | `0` |
   | `APP_SECRET` | Random 32+ char string |
   | `DATABASE_URL` | Reference from MySQL service (`${{MySQL.MYSQL_URL}}`) |
   | `DEFAULT_URI` | `https://your-app.up.railway.app` |
   | `MAILER_DSN` | Your SMTP URL |
   | `CORS_ALLOW_ORIGIN` | Regex for your mobile/web clients |
   | `GOOGLE_CLIENT_ID` | If using Google login |
   | `GOOGLE_CLIENT_SECRET` | If using Google login |
   | `TRUSTED_PROXIES` | `REMOTE_ADDR` |
   | `PORT` | `8080` (optional; already default in image) |

4. **Networking** → Generate domain → set `DEFAULT_URI` to that HTTPS URL.
5. Deploy. Migrations run automatically on startup when `DATABASE_URL` is set.

**Note:** Do not upload a `.env` file to Railway. The Docker image excludes it on purpose; `entrypoint.sh` creates `/app/.env` from the variables above when the container starts.

## Optional: persistent uploads

Product images in `public/uploads/images/` are lost on redeploy unless you add a **Railway Volume** mounted to `/app/public/uploads`.

## Test after deploy

- `https://YOUR-DOMAIN.up.railway.app/`
- `https://YOUR-DOMAIN.up.railway.app/api/mobile/products`

## Local Docker test

```bash
docker build -t pet-pantry .
docker run -p 8080:8080 -e APP_SECRET=local_test_secret -e DATABASE_URL="mysql://..." pet-pantry
```

Open http://localhost:8080
