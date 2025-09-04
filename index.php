<?php
// index.php

require __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;
use Fitbit\Fitbit;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = AppFactory::create();

// --- Fitbit App Credentials ---
$clientId     = getenv('FITBIT_CLIENT_ID');
$clientSecret = getenv('FITBIT_CLIENT_SECRET');
$redirectUri  = getenv('FITBIT_REDIRECT_URI');
$scope        = 'activity heartrate sleep profile';

// Initialize Fitbit SDK
$fitbit = new Fitbit($clientId, $clientSecret);

// 1. Route: Start OAuth flow
$app->get('/auth', function (Request $request, Response $response) use ($fitbit, $redirectUri, $scope) {
    $authUrl = $fitbit->getAuthorizationUrl($redirectUri, $scope);
    return $response->withHeader('Location', $authUrl)->withStatus(302);
});

// 2. Route: Handle callback
$app->get('/callback', function (Request $request, Response $response) use ($fitbit, $redirectUri) {
    $params = $request->getQueryParams();
    if (!isset($params['code'])) {
        $response->getBody()->write("Error: no code returned.");
        return $response;
    }

    $code = $params['code'];

    // Exchange code for access token
    $tokens = $fitbit->getAccessToken($code, $redirectUri);
    $accessToken = $tokens['access_token'];

    // Use access token to call Fitbit API
    $client = new Client();
    $profileRes = $client->get("https://api.fitbit.com/1/user/-/profile.json", [
        'headers' => [
            'Authorization' => "Bearer $accessToken",
        ]
    ]);
    $profile = json_decode($profileRes->getBody()->getContents(), true);

    // Retrieve additional data (e.g., steps, heart rate)
    $stepsRes = $client->get("https://api.fitbit.com/1/user/-/activities/steps/date/today/1d.json", [
        'headers' => [
            'Authorization' => "Bearer $accessToken",
        ]
    ]);
    $steps = json_decode($stepsRes->getBody()->getContents(), true);

    $heartRateRes = $client->get("https://api.fitbit.com/1/user/-/activities/heart/date/today/1d.json", [
        'headers' => [
            'Authorization' => "Bearer $accessToken",
        ]
    ]);
    $heartRate = json_decode($heartRateRes->getBody()->getContents(), true);

    // Combine all data
    $data = [
        'profile' => $profile,
        'steps'   => $steps,
        'heartRate' => $heartRate,
    ];

    $response->getBody()->write("<h1>Fitbit Data</h1><pre>" . print_r($data, true) . "</pre>");
    return $response;
});

$app->run();
