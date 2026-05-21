#Requires -RunAsAdministrator
<#
.SYNOPSIS
  Configure XAMPP Apache to proxy http://localhost to Pet Pantry Docker (port 8080).
  Fixes "404 Not Found" on http://localhost/login when XAMPP uses port 80.
#>
$ErrorActionPreference = "Stop"
$ProjectRoot = Split-Path $PSScriptRoot -Parent
$ProxyConf = Join-Path $ProjectRoot "docker\xampp-proxy-docker.conf"
$ProxyInclude = "Include `"$($ProxyConf -replace '\\','/')`""

$xamppRoots = @(
    "C:\xampp",
    "D:\xampp",
    "$env:ProgramFiles\XAMPP",
    "${env:ProgramFiles(x86)}\XAMPP"
)

$httpdConf = $null
foreach ($root in $xamppRoots) {
    $candidate = Join-Path $root "apache\conf\httpd.conf"
    if (Test-Path $candidate) {
        $httpdConf = $candidate
        break
    }
}

if (-not $httpdConf) {
    Write-Host "Could not find XAMPP httpd.conf. Install XAMPP or edit apache\conf\httpd.conf manually." -ForegroundColor Red
    Write-Host "Add at the end: $ProxyInclude" -ForegroundColor Yellow
    exit 1
}

Write-Host "Using: $httpdConf" -ForegroundColor Cyan
$content = Get-Content $httpdConf -Raw

$modules = @(
    @{ Name = "proxy_module"; Line = "LoadModule proxy_module modules/mod_proxy.so" },
    @{ Name = "proxy_http_module"; Line = "LoadModule proxy_http_module modules/mod_proxy_http.so" }
)

foreach ($mod in $modules) {
    if ($content -notmatch [regex]::Escape($mod.Name)) {
        Add-Content -Path $httpdConf -Value $mod.Line
        Write-Host "Enabled $($mod.Name)" -ForegroundColor Green
    }
}

if ($content -notmatch [regex]::Escape("xampp-proxy-docker.conf")) {
    Add-Content -Path $httpdConf -Value ""
    Add-Content -Path $httpdConf -Value "# Pet Pantry — proxy to Docker on port 8080"
    Add-Content -Path $httpdConf -Value $ProxyInclude
    Write-Host "Added proxy Include." -ForegroundColor Green
} else {
    Write-Host "Proxy Include already present." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Restart Apache in XAMPP Control Panel, then ensure Docker is running:" -ForegroundColor Cyan
Write-Host "  docker compose up -d" -ForegroundColor White
Write-Host ""
Write-Host "Open: http://localhost/login" -ForegroundColor Green
