# Fix Docker Desktop "overlayfs/snapshots ... no such file or directory" build errors
Set-Location $PSScriptRoot

Write-Host "Stopping Compose stack..." -ForegroundColor Cyan
docker compose down 2>$null

Write-Host "Pruning broken BuildKit cache (this is safe)..." -ForegroundColor Cyan
docker builder prune -af
docker system prune -f

Write-Host "Rebuilding app image without cache..." -ForegroundColor Cyan
docker compose build --no-cache app

if ($LASTEXITCODE -eq 0) {
    Write-Host "Starting services..." -ForegroundColor Cyan
    docker compose up -d
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "Pet Pantry:  http://localhost:8080" -ForegroundColor Green
        Write-Host "Login:       http://localhost:8080/login" -ForegroundColor Green
        Write-Host "phpMyAdmin:  http://localhost:8081" -ForegroundColor Green
        docker compose ps
    }
} else {
    Write-Host "Build failed. Restart Docker Desktop, then run this script again." -ForegroundColor Red
    exit 1
}
