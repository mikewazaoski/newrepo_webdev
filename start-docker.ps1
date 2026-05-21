# Build and start Pet Pantry with Docker Compose
Set-Location $PSScriptRoot

Write-Host "Building and starting Pet Pantry (app + MySQL + phpMyAdmin)..." -ForegroundColor Cyan
docker compose up --build -d

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "Pet Pantry:  http://localhost:8080" -ForegroundColor Green
    Write-Host "Login:       http://localhost:8080/login" -ForegroundColor Green
    Write-Host "phpMyAdmin:  http://localhost:8081" -ForegroundColor Green
    Write-Host "MySQL:       127.0.0.1:3307 (user pets_user / pets_password)" -ForegroundColor Green
    Write-Host ""
    Write-Host "NOTE: http://localhost/login (port 80) is XAMPP, not this app." -ForegroundColor Yellow
    Write-Host "      Use :8080 above, or see DOCKER.md to use port 80 with Docker." -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Logs: docker compose logs -f app" -ForegroundColor Gray
} else {
    Write-Host "Docker Compose failed. Check Docker Desktop is running." -ForegroundColor Red
    exit 1
}
