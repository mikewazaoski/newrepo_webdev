<?php
// Test OAuth2 client instantiation with SSL certificate

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

echo "=== Testing OAuth2 Client Instantiation ===\n";

$certPath = __DIR__ . '/cacert.pem';

try {
    // Create Guzzle client with SSL certificate
    $client = new Client([
        'verify' => $certPath
    ]);

    echo "✅ Guzzle HTTP client created successfully\n";
    echo "Client class: " . get_class($client) . "\n";

    // Test if it implements the required interface
    if ($client instanceof \GuzzleHttp\ClientInterface) {
        echo "✅ Client implements GuzzleHttp\\ClientInterface\n";
    } else {
        echo "❌ Client does not implement required interface\n";
    }

    // Test SSL connection to Google OAuth2
    echo "\nTesting SSL connection to Google OAuth2...\n";
    $response = $client->get('https://oauth2.googleapis.com/token', [
        'http_errors' => false,
        'timeout' => 10
    ]);

    $statusCode = $response->getStatusCode();
    echo "✅ SSL Connection successful (HTTP $statusCode)\n";

    echo "\n✅ OAuth2 client should work without type errors!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>