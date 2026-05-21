# Build and start Pet Pantry with Docker Compose
Set-Location $PSScriptRoot

Write-Host "Building and starting Pet Pantry..." -ForegroundColor Cyan
docker compose up --build -d

if ($LASTEXITCODE -ne 0) {
    Write-Host "Docker Compose failed. Is Docker Desktop running?" -ForegroundColor Red
    exit 1
}

Start-Sleep -Seconds 5
$code80 = curl.exe -s -o NUL -w "%{http_code}" http://localhost/login 2>$null

Write-Host ""
if ($code80 -eq "200") {
    Write-Host "Pet Pantry:  http://localhost/" -ForegroundColor Green
    Write-Host "Login:       http://localhost/login" -ForegroundColor Green
} else {
    Write-Host "Login:       http://localhost:8080/login" -ForegroundColor Green
    Write-Host "(Port 80 not responding — check: docker compose ps)" -ForegroundColor Yellow
}
Write-Host "phpMyAdmin:  http://localhost:8081" -ForegroundColor Green
Write-Host "MySQL:       127.0.0.1:3307 (pets_user / pets_password)" -ForegroundColor Green
Write-Host ""
Write-Host "Logs: docker compose logs -f app" -ForegroundColor Gray
