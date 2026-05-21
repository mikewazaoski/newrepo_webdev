#Requires -RunAsAdministrator
<#
  Stops Apache/httpd on port 80 (leftover XAMPP/WAMP) so Docker can use http://localhost
  Run: Right-click PowerShell -> Run as administrator -> .\scripts\stop-local-apache.ps1
#>
Write-Host "Stopping Apache/httpd on port 80..." -ForegroundColor Cyan

Get-Process -Name httpd -ErrorAction SilentlyContinue | ForEach-Object {
    Write-Host "  Stopping $($_.ProcessName) PID $($_.Id)"
    Stop-Process -Id $_.Id -Force
}

foreach ($svc in @('Apache2.4', 'apache', 'wampapache64', 'wampapache')) {
    $s = Get-Service -Name $svc -ErrorAction SilentlyContinue
    if ($s -and $s.Status -eq 'Running') {
        Write-Host "  Stopping service $svc"
        Stop-Service -Name $svc -Force
    }
}

Start-Sleep -Seconds 2
$on80 = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue
if ($on80) {
    Write-Host "Port 80 is still in use. Uninstall XAMPP: Settings -> Apps -> XAMPP -> Uninstall" -ForegroundColor Yellow
} else {
    Write-Host "Port 80 is free. Run: docker compose up -d" -ForegroundColor Green
}
