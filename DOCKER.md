# Docker + MySQL (Pet Pantry)

Run the app with **Docker only** — no XAMPP, no local PHP, no local MySQL install.

## Requirements

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) running

## Start

```powershell
cd D:\Desktop\pantry\pets
docker compose up -d
```

Or double-click / run:

```powershell
.\start-docker.ps1
```

First build takes 10–20 minutes. Later starts are fast.

## URLs

Docker maps **host port 8080** → **container port 80** (`8080:80`).

| What | URL |
|------|-----|
| **Home** | http://localhost:8080 |
| **Login** | http://localhost:8080/login |
| **phpMyAdmin** (optional) | http://localhost:8081 |
| **MySQL** (from your PC) | `127.0.0.1:3307` |

### Database (Docker MySQL)

| | |
|--|--|
| Host (inside containers) | `mysql` |
| Host (from Windows) | `127.0.0.1` |
| Port | `3307` |
| Database | `pets_db` |
| User | `pets_user` |
| Password | `pets_password` |

## Commands

```powershell
docker compose logs -f app    # app logs
docker compose down           # stop
docker compose down -v        # stop + delete database data
docker compose up --build -d  # rebuild after code changes
```

## Services

| Container | Role |
|-----------|------|
| `app` | Symfony + Nginx + PHP (published as **8080:80**) |
| `mysql` | MySQL 8 database |
| `phpmyadmin` | Web UI for MySQL (port **8081**) |

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Build cache error | `docker builder prune -af` then `docker compose build --no-cache app` |
| Port 8080 in use | In `.env.docker`: `APP_PORT=8090` |
| DB connection failed | `docker compose ps` — wait until `mysql` is **healthy** |

## Cloud deploy

For Railway, see [RAILWAY.md](RAILWAY.md).
