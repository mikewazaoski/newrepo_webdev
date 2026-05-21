# Docker deployment (Pet Pantry)

Run the full stack locally: **Symfony app**, **MySQL**, and **phpMyAdmin**.

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows/Mac) or Docker Engine + Compose (Linux)
- At least **4 GB RAM** free for Docker (first build compiles PHP extensions and runs `npm run build`)

## Quick start

From the project root:

```bash
docker compose up --build -d
```

First build may take **10–20 minutes**. Later starts are much faster.

## URLs

| Service | URL |
|---------|-----|
| **Pet Pantry app** | http://localhost:8080 |
| **Login page** | http://localhost:8080/login |
| **phpMyAdmin** | http://localhost:8081 |
| **MySQL** (from host) | `127.0.0.1:3307` |

### `localhost/login` shows Apache 404?

**Port 80** is XAMPP Apache; the app runs in Docker on **port 8080**.

**Quick fix (no XAMPP change):** http://localhost:8080/login

**Fix `http://localhost/login` (recommended on Windows + XAMPP):**

1. Start Docker: `docker compose up -d`
2. **Run PowerShell as Administrator:**
   ```powershell
   cd D:\Desktop\pantry\pets
   .\scripts\install-xampp-proxy.ps1
   ```
3. In **XAMPP Control Panel** → **Stop** Apache → **Start** Apache
4. Open http://localhost/login

This proxies XAMPP (port 80) to Docker (port 8080).

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
| Port 8080 in use | Set `APP_PORT=8090` in `.env.docker` or environment |
| Build fails at `npm run build` | Ensure `package-lock.json` is committed; run `npm ci && npm run build` locally |
| Database connection error | Run `docker compose ps` — `mysql` must be **healthy** before `app` starts |
| Permission errors on uploads | `docker compose exec app chown -R www-data:www-data var public/uploads` |

## Production note

This Compose file is for **local Docker deployment**. For cloud hosting, use [RAILWAY.md](RAILWAY.md).
