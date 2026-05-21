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

| What | URL |
|------|-----|
| **Home** | http://localhost |
| **Login** | http://localhost/login |
| **Also works** | http://localhost:8080/login |
| **phpMyAdmin** (optional) | http://localhost:8081 |
| **MySQL** (from your PC) | `127.0.0.1:3307` |

`start-docker.ps1` stops old Windows Apache on port 80 (if present) so Docker can use `http://localhost/login`. No XAMPP is used by this project.

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
| `app` | Symfony + Nginx + PHP (port **8080**) |
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
