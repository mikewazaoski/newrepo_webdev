# Start Pet Pantry — Docker + MySQL (frees port 80 from old Apache if needed)
Set-Location $PSScriptRoot

function Test-Port80BlockedByApache {
    Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue |
        ForEach-Object { Get-Process -Id $_.OwningProcess -ErrorAction SilentlyContinue } |
        Where-Object { $_.ProcessName -eq 'httpd' }
}

if (Test-Port80BlockedByApache) {
    Write-Host "Port 80 is used by old Apache (not Docker). Freeing it..." -ForegroundColor Yellow
    $freeScript = Join-Path $PSScriptRoot "scripts\free-port-80.ps1"
    Start-Process powershell -Verb RunAs -Wait -ArgumentList @(
        "-NoProfile", "-ExecutionPolicy", "Bypass", "-File", "`"$freeScript`""
    )
}

Write-Host "Starting Docker..." -ForegroundColor Cyan
docker compose up --build -d

if ($LASTEXITCODE -ne 0) {
    Write-Host "Docker failed. Is Docker Desktop running?" -ForegroundColor Red
    exit 1
}

Start-Sleep -Seconds 12

$code80 = curl.exe -s -o NUL -w "%{http_code}" http://localhost/login 2>$null
$server = (curl.exe -sI http://localhost/login 2>$null | Select-String "Server:").ToString()

Write-Host ""
if ($code80 -eq "200" -and $server -notmatch "Apache") {
    Write-Host "Login:  http://localhost/login" -ForegroundColor Green
    Write-Host "Home:   http://localhost/" -ForegroundColor Green
} else {
    Write-Host "Login:  http://localhost:8080/login" -ForegroundColor Green
    if ($server -match "Apache") {
        Write-Host ""
        Write-Host "Port 80 still shows Apache. Click Yes on the Admin prompt when asked," -ForegroundColor Yellow
        Write-Host "or uninstall XAMPP: Settings -> Apps -> XAMPP -> Uninstall" -ForegroundColor Yellow
    }
}
Write-Host "Also:    http://localhost:8080/login" -ForegroundColor Gray
Write-Host "MySQL:   127.0.0.1:3307 (pets_user / pets_password)" -ForegroundColor Gray
Write-Host "Logs:    docker compose logs -f app" -ForegroundColor Gray
