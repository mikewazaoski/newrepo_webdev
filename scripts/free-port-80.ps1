# Stop Windows Apache/XAMPP processes listening on port 80 so Docker can bind it.
param([switch]$Quiet)

function Write-Info($msg) {
    if (-not $Quiet) { Write-Host $msg -ForegroundColor Cyan }
}

$listeners = Get-NetTCPConnection -LocalPort 80 -State Listen -ErrorAction SilentlyContinue
if (-not $listeners) {
    Write-Info "Port 80 is free."
    return
}

$stopped = $false
foreach ($conn in $listeners) {
    $proc = Get-Process -Id $conn.OwningProcess -ErrorAction SilentlyContinue
    if (-not $proc) { continue }

    $name = $proc.ProcessName
    # Skip Docker itself
    if ($name -match 'com\.docker|docker|wsl|vmcompute') {
        Write-Info "Port 80 already used by Docker ($name) — OK."
        continue
    }

    if ($name -match 'httpd|apache') {
        Write-Info "Stopping $name (PID $($proc.Id)) — was blocking port 80 (XAMPP/WAMP)."
        Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
        $stopped = $true
    } else {
        Write-Host "Port 80 is used by $name (PID $($proc.Id)). Stop it manually or set HTTP_PORT=8080 only." -ForegroundColor Yellow
    }
}

if ($stopped) {
    Start-Sleep -Seconds 2
    Write-Info "Port 80 should now be free for Docker."
}
