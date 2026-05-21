# Symfony Server Startup Script with SSL Certificate Support
# This script sets up the required environment variables for Google OAuth2 SSL verification

Write-Host "Setting up SSL certificate for OAuth2..." -ForegroundColor Green

$certPath = "D:\Downloads\php-8.4.16-nts-Win32-vs17-x64\cacert.pem"
$projectCertPath = "$PSScriptRoot\cacert.pem"

# Ensure certificate exists in project directory
if (-not (Test-Path $projectCertPath)) {
    if (Test-Path $certPath) {
        Copy-Item $certPath -Destination $projectCertPath -Force
        Write-Host "✓ Certificate copied to project directory" -ForegroundColor Green
    } else {
        Write-Host "✗ WARNING: Certificate file not found at $certPath" -ForegroundColor Red
        Write-Host "  Please run the SSL setup again" -ForegroundColor Yellow
        exit 1
    }
}

# Set environment variable for current session
$env:CURL_CA_BUNDLE = $certPath

Write-Host ""
Write-Host "Starting Symfony development server..." -ForegroundColor Green
Write-Host "CURL_CA_BUNDLE=$($env:CURL_CA_BUNDLE)" -ForegroundColor Cyan
Write-Host "Project Certificate: $projectCertPath" -ForegroundColor Cyan
Write-Host ""

# Verify the certificate file exists
if (Test-Path $certPath) {
    Write-Host "✓ Certificate file verified: $certPath" -ForegroundColor Green
} else {
    Write-Host "✗ WARNING: Certificate file not found at $certPath" -ForegroundColor Yellow
}

Write-Host ""

# Start the Symfony server
symfony serve:start

Read-Host "Press Enter to exit"
