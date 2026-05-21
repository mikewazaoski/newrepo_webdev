<?php
// Test SSL certificate setup for Google OAuth2

$cafile = 'D:\Downloads\php-8.4.16-nts-Win32-vs17-x64\cacert.pem';

echo "=== SSL Certificate Test ===\n";
echo "CA File: " . $cafile . "\n";
echo "File exists: " . (file_exists($cafile) ? 'YES' : 'NO') . "\n";
echo "File readable: " . (is_readable($cafile) ? 'YES' : 'NO') . "\n";

// Set environment for this request
putenv('CURL_CA_BUNDLE=' . $cafile);

echo "\nTesting connection to Google OAuth2 endpoint...\n";

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_CAINFO => $cafile,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_NOBODY => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$result = curl_exec($ch);
$errno = curl_errno($ch);
$errmsg = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

if ($errno === 0) {
    echo "✅ SSL Connection: OK (HTTP $http_code)\n";
    echo "\n✅ Google OAuth2 authentication should now work!\n";
} else {
    echo "❌ SSL Error Code: $errno\n";
    echo "❌ Error Message: $errmsg\n";
}

echo "\ncURL SSL Version: " . (curl_version()['ssl_version'] ?? 'unknown') . "\n";
?>
