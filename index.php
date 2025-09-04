<?php
require __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;
use Namelivia\Fitbit\Api\Api;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = AppFactory::create();

// --- Fitbit App Credentials ---
$clientId     = getenv('FITBIT_CLIENT_ID');
$clientSecret = getenv('FITBIT_CLIENT_SECRET');
$redirectUri  = getenv('FITBIT_REDIRECT_URI');
$scope        = 'activity heartrate sleep profile';

// Instantiate Fitbit SDK API
$fitbit = new Api($clientId, $clientSecret);

// 1. Start OAuth flow
$app->get('/auth', function (Request $request, Response $response) use ($fitbit, $redirectUri, $scope) {
    $authUrl = $fitbit->getAuthorizationUrl($redirectUri, $scope);
    return $response->withHeader('Location', $authUrl)->withStatus(302);
});

// 2. Handle callback
$app->get('/callback', function (Request $request, Response $response) use ($fitbit, $redirectUri) {
    $params = $request->getQueryParams();
    if (!isset($params['code'])) {
        $response->getBody()->write("Error: no code returned.");
        return $response->withStatus(400);
    }

    $code = $params['code'];

    // Exchange code for access token
    $tokens = $fitbit->getAccessToken($code, $redirectUri);
    $accessToken = $tokens['access_token'];

    // Use SDK methods to get profile, steps, heart rate, sleep, etc.
    $fitbit->setAccessToken($accessToken);

    $profile   = $fitbit->getProfile();
    $activities = $fitbit->getActivities(date('Y-m-d'));
    $steps      = $fitbit->getSteps(date('Y-m-d'));
    $heartRate  = $fitbit->getHeartRate(date('Y-m-d'));
    $sleep      = $fitbit->getSleep(date('Y-m-d'));

    $data = [
        'profile'    => $profile,
        'activities' => $activities,
        'steps'      => $steps,
        'heartRate'  => $heartRate,
        'sleep'      => $sleep,
    ];

    $response->getBody()->write("<h1>Fitbit Data</h1><pre>" . print_r($data, true) . "</pre>");
    return $response;
});

$app->run();
