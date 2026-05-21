#Requires -RunAsAdministrator
# Frees port 80 for Docker by stopping/disabling the Windows Apache service (leftover from XAMPP install).
# Does not install or configure XAMPP — only stops Apache blocking Docker.

$ErrorActionPreference = "Continue"

Write-Host "Freeing port 80 for Docker..." -ForegroundColor Cyan

foreach ($svc in @('Apache2.4', 'apache', 'wampapache64', 'wampapache')) {
    $s = Get-Service -Name $svc -ErrorAction SilentlyContinue
    if (-not $s) { continue }
    if ($s.Status -eq 'Running') {
        Stop-Service -Name $svc -Force
        Write-Host "  Stopped: $svc" -ForegroundColor Green
    }
    Set-Service -Name $svc -StartupType Disabled
    Write-Host "  Disabled: $svc" -ForegroundColor Green
}

Get-Process -Name httpd -ErrorAction SilentlyContinue | ForEach-Object {
    Stop-Process -Id $_.Id -Force
    Write-Host "  Stopped httpd PID $($_.Id)" -ForegroundColor Green
}

# If XAMPP exists, move Apache to port 8082 so it does not reclaim port 80 later
$xamppConf = @(
    "C:\xampp\apache\conf\httpd.conf",
    "D:\xampp\apache\conf\httpd.conf",
    "$env:ProgramFiles\XAMPP\apache\conf\httpd.conf"
)
foreach ($conf in $xamppConf) {
    if (-not (Test-Path $conf)) { continue }
    $text = Get-Content $conf -Raw
    if ($text -match 'Listen\s+80\b') {
        (Get-Content $conf) -replace '^(Listen\s+)80\s*$', '${1}8082' | Set-Content -Path $conf
        Write-Host "  Changed Apache Listen 80 -> 8082 in $conf" -ForegroundColor Green
    }
}

Start-Sleep -Seconds 2
$blockers = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue |
    ForEach-Object { Get-Process -Id $_.OwningProcess -ErrorAction SilentlyContinue } |
    Where-Object { $_.ProcessName -eq 'httpd' }

if ($blockers) {
    Write-Host "Port 80 still has httpd. Uninstall 'XAMPP' from Settings -> Apps." -ForegroundColor Yellow
    exit 1
}

Write-Host "Port 80 is free for Docker." -ForegroundColor Green
exit 0
