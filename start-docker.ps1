# Start Pet Pantry — Docker + MySQL (http://localhost:8080)
Set-Location $PSScriptRoot

Write-Host "Starting Docker..." -ForegroundColor Cyan
docker compose up --build -d

if ($LASTEXITCODE -ne 0) {
    Write-Host "Docker failed. Is Docker Desktop running?" -ForegroundColor Red
    exit 1
}

Start-Sleep -Seconds 12

$code80 = curl.exe -s -o NUL -w "%{http_code}" http://localhost/login 2>$null
$code8080 = curl.exe -s -o NUL -w "%{http_code}" http://localhost:8080/login 2>$null

Write-Host ""
if ($code80 -eq "200") {
    Write-Host "Login:  http://localhost/login" -ForegroundColor Green
    Write-Host "Home:   http://localhost/" -ForegroundColor Green
} elseif ($code8080 -eq "200") {
    Write-Host "Login:  http://localhost:8080/login" -ForegroundColor Green
} else {
    Write-Host "App starting... try http://localhost/login in a moment" -ForegroundColor Yellow
    Write-Host "Logs: docker compose logs -f app" -ForegroundColor Gray
}
Write-Host "Also:     http://localhost:8080/login" -ForegroundColor Gray
Write-Host "phpMyAdmin: http://localhost:8081" -ForegroundColor Gray
Write-Host "MySQL:      127.0.0.1:3307 (pets_user / pets_password)" -ForegroundColor Gray
