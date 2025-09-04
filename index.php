<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;
use App\FitbitClient;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Load Fitbit configuration directly from config.php
$fitbitConfig = require __DIR__ . '/config.php';

// Create Fitbit client
$fitbitClient = new FitbitClient(
    $fitbitConfig['clientId'],
    $fitbitConfig['clientSecret'],
    $fitbitConfig['redirectUri']
);

$app = AppFactory::create();

// Route: start OAuth flow
$app->get('/auth', function (Request $request, Response $response) use ($fitbitClient, $fitbitConfig): Response {
    $authorizationUrl = $fitbitClient->getAuthorizationUrl($fitbitConfig['scope']);
    return $response
        ->withHeader('Location', $authorizationUrl)
        ->withStatus(302);
});

// Route: handle OAuth callback
$app->get('/callback', function (Request $request, Response $response) use ($fitbitClient): Response {
    $queryParameters = $request->getQueryParams();
    $authorizationCode = $queryParameters['code'] ?? null;

    if ($authorizationCode === null) {
        $response->getBody()->write("Error: no authorization code returned.");
        return $response->withStatus(400);
    }

    try {
        $tokenData = $fitbitClient->requestAccessToken($authorizationCode);

        $profileData = $fitbitClient->get('/1/user/-/profile.json');
        $activitiesData = $fitbitClient->get('/1/user/-/activities/date/' . date('Y-m-d') . '.json');
        $stepsData = $fitbitClient->get('/1/user/-/activities/steps/date/' . date('Y-m-d') . '/1d.json');
        $heartRateData = $fitbitClient->get('/1/user/-/activities/heart/date/' . date('Y-m-d') . '/1d.json');
        $sleepData = $fitbitClient->get('/1.2/user/-/sleep/date/' . date('Y-m-d') . '.json');

        $allFitbitData = [
            'token_data' => $tokenData,
            'profile' => $profileData,
            'activities' => $activitiesData,
            'steps' => $stepsData,
            'heart_rate' => $heartRateData,
            'sleep' => $sleepData,
        ];

        $response->getBody()->write('<pre>' . print_r($allFitbitData, true) . '</pre>');
        return $response;

    } catch (\Throwable $exception) {
        $response->getBody()->write('Error fetching Fitbit data: ' . $exception->getMessage());
        return $response->withStatus(500);
    }
});

$app->run();
