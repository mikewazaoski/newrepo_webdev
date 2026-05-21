# Fix "Apache 404" on http://localhost/login — stops XAMPP Apache and starts Docker
# Double-click or run:  Right-click -> Run with PowerShell -> Run as Administrator

#Requires -RunAsAdministrator

$ErrorActionPreference = "Continue"
Set-Location $PSScriptRoot

Write-Host "=== Pet Pantry: fix localhost/login ===" -ForegroundColor Cyan

# 1. Stop Apache (XAMPP) on port 80
Write-Host "`n[1/4] Stopping Apache (XAMPP)..." -ForegroundColor Yellow
foreach ($svc in @('Apache2.4', 'apache', 'wampapache64')) {
    $s = Get-Service -Name $svc -ErrorAction SilentlyContinue
    if ($s) {
        if ($s.Status -eq 'Running') {
            Stop-Service -Name $svc -Force
            Write-Host "  Stopped service: $svc" -ForegroundColor Green
        }
        Set-Service -Name $svc -StartupType Disabled
        Write-Host "  Disabled startup: $svc" -ForegroundColor Green
    }
}
Get-Process -Name httpd -ErrorAction SilentlyContinue | ForEach-Object {
    Stop-Process -Id $_.Id -Force
    Write-Host "  Stopped httpd PID $($_.Id)" -ForegroundColor Green
}

Start-Sleep -Seconds 2

# 2. Start Docker stack
Write-Host "`n[2/4] Starting Docker Compose..." -ForegroundColor Yellow
docker compose up -d
if ($LASTEXITCODE -ne 0) {
    Write-Host "Docker failed. Start Docker Desktop first." -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host "`n[3/4] Waiting for app..." -ForegroundColor Yellow
Start-Sleep -Seconds 12

# 3. Test URLs
$code80 = curl.exe -s -o NUL -w "%{http_code}" http://localhost/login 2>$null
$code8080 = curl.exe -s -o NUL -w "%{http_code}" http://localhost:8080/login 2>$null

Write-Host "`n[4/4] Results:" -ForegroundColor Yellow
if ($code80 -eq "200") {
    Write-Host "  OK  http://localhost/login  (HTTP 200)" -ForegroundColor Green
    $openUrl = "http://localhost/login"
} elseif ($code8080 -eq "200") {
    Write-Host "  OK  http://localhost:8080/login  (HTTP 200)" -ForegroundColor Green
    Write-Host "  Port 80 still blocked — use :8080 or uninstall XAMPP from Settings -> Apps" -ForegroundColor Yellow
    $openUrl = "http://localhost:8080/login"
} else {
    Write-Host "  App not ready yet. Try: docker compose logs -f app" -ForegroundColor Red
    $openUrl = "http://localhost:8080/login"
}

Write-Host "`nOpening browser..." -ForegroundColor Cyan
Start-Process $openUrl
Read-Host "`nPress Enter to close"
