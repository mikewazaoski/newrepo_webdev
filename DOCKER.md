# Docker deployment (Pet Pantry)

Run the full stack locally: **Symfony app**, **MySQL**, and **phpMyAdmin**.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows/Mac) or Docker Engine + Compose (Linux)
- At least **4 GB RAM** free for Docker (first build compiles PHP extensions and runs `npm run build`)
- **No Apache on port 80** — if you see `Apache/2.4.58 (Win64)` errors, run **`Run-Fix-As-Admin.bat`** (or right‑click `FIX-LOCALHOST-LOGIN.ps1` → Run as administrator)

## Quick start

From the project root:

```bash
docker compose up --build -d
```

First build may take **10–20 minutes**. Later starts are much faster.

## URLs

| Service | URL |
|---------|-----|
| **Pet Pantry app** | http://localhost |
| **Login page** | http://localhost/login |
| **Alternate port** | http://localhost:8080 |
| **phpMyAdmin** | http://localhost:8081 |
| **MySQL** (from host) | `127.0.0.1:3307` |

### Database credentials (Docker)

| Field | Value |
|-------|--------|
| Host (inside Docker) | `mysql` |
| Host (from your PC) | `127.0.0.1` |
| Port (from your PC) | `3307` |
| Database | `pets_db` |
| User | `pets_user` |
| Password | `pets_password` |

## Useful commands

```bash
# View logs
docker compose logs -f app

# Stop everything
docker compose down

# Stop and remove database volume (fresh DB)
docker compose down -v

# Rebuild app image after code changes
docker compose up --build -d app
```

## Optional: custom ports / secrets

```bash
cp .env.docker.example .env.docker
# Edit .env.docker, then:
docker compose --env-file .env.docker up --build -d
```

## What runs on startup

The `app` container (`entrypoint.sh`):

1. Writes `/app/.env` from environment variables
2. Warms Symfony cache
3. Waits for MySQL (via Compose healthcheck)
4. Runs Doctrine migrations
5. Starts PHP-FPM + Nginx on port **8080**

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `overlayfs/snapshots ... no such file or directory` | Corrupted Docker cache. Run `.\docker-fix-build.ps1` or restart Docker Desktop, then `docker builder prune -af` and `docker compose build --no-cache app` |
| Port 80 in use | Set `HTTP_PORT=8080` in `.env.docker` (app only on :8080) |
| Port 8080 in use | Set `APP_PORT=8090` in `.env.docker` |
| Build fails at `npm run build` | Ensure `package-lock.json` is committed; run `npm ci && npm run build` locally |
| Database connection error | Run `docker compose ps` — `mysql` must be **healthy** before `app` starts |
| Permission errors on uploads | `docker compose exec app chown -R www-data:www-data var public/uploads` |

## Production note

This Compose file is for **local Docker deployment**. For cloud hosting, use [RAILWAY.md](RAILWAY.md).
