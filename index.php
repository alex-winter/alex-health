<?php
require __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;
use Namelivia\Fitbit\ServiceProvider;
use Namelivia\Fitbit\OAuth\Persistence\InMemoryPersistence;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app = AppFactory::create();

// --- Fitbit App Credentials ---
$clientId     = getenv('FITBIT_CLIENT_ID');
$clientSecret = getenv('FITBIT_CLIENT_SECRET');
$redirectUri  = getenv('FITBIT_REDIRECT_URI');
$scope        = 'activity heartrate sleep profile';

// --- Token persistence (in-memory for now; later can use DB) ---
$tokenPersistence = new InMemoryPersistence();

// --- Build the Fitbit client ---
$fitbit = (new ServiceProvider())->build(
    $tokenPersistence,
    $clientId,
    $clientSecret,
    $redirectUri
);

// 1. Route: Start OAuth flow
$app->get('/auth', function (Request $request, Response $response) use ($fitbit, $redirectUri, $scope) {
    $authUrl = $fitbit->getAuthorizationUrl($scope);
    return $response->withHeader('Location', $authUrl)->withStatus(302);
});

// 2. Route: Handle callback
$app->get('/callback', function (Request $request, Response $response) use ($fitbit) {
    $params = $request->getQueryParams();
    if (!isset($params['code'])) {
        $response->getBody()->write("Error: no code returned.");
        return $response->withStatus(400);
    }

    $code = $params['code'];

    // Exchange code for access token
    $fitbit->requestAccessToken($code);

    // Fetch data
    $profile   = $fitbit->get('/1/user/-/profile.json');
    $activities = $fitbit->get('/1/user/-/activities/date/' . date('Y-m-d') . '.json');
    $steps      = $fitbit->get('/1/user/-/activities/steps/date/' . date('Y-m-d') . '/1d.json');
    $heartRate  = $fitbit->get('/1/user/-/activities/heart/date/' . date('Y-m-d') . '/1d.json');
    $sleep      = $fitbit->get('/1.2/user/-/sleep/date/' . date('Y-m-d') . '.json');

    $data = [
        'profile'    => json_decode($profile, true),
        'activities' => json_decode($activities, true),
        'steps'      => json_decode($steps, true),
        'heartRate'  => json_decode($heartRate, true),
        'sleep'      => json_decode($sleep, true),
    ];

    $response->getBody()->write("<h1>Fitbit Data</h1><pre>" . print_r($data, true) . "</pre>");
    return $response;
});

$app->run();
