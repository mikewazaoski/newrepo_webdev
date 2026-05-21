# Start Pet Pantry — Docker + MySQL only
Set-Location $PSScriptRoot

Write-Host "Starting Pet Pantry (Docker)..." -ForegroundColor Cyan
docker compose up --build -d

if ($LASTEXITCODE -ne 0) {
    Write-Host "Failed. Start Docker Desktop first." -ForegroundColor Red
    exit 1
}

Start-Sleep -Seconds 8
Write-Host ""
Write-Host "App:         http://localhost:8080" -ForegroundColor Green
Write-Host "Login:       http://localhost:8080/login" -ForegroundColor Green
Write-Host "phpMyAdmin:  http://localhost:8081  (MySQL admin)" -ForegroundColor Green
Write-Host "MySQL:       127.0.0.1:3307  (user: pets_user, pass: pets_password)" -ForegroundColor Green
Write-Host ""
Write-Host "Logs: docker compose logs -f app" -ForegroundColor Gray
