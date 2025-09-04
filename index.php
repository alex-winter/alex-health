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

        $userProfile = $profileData['user'] ?? [];
        $dailySteps = $stepsData['activities-steps'][0]['value'] ?? 'N/A';
        $heartRateSummary = $heartRateData['activities-heart'][0]['value'] ?? [];
        $sleepSummary = $sleepData['summary'] ?? [];

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fitbit Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h1 class="mb-4">Fitbit Dashboard</h1>

    <div class="card mb-4">
        <div class="card-header">Profile</div>
        <div class="card-body">
            <p><strong>Name:</strong> {$userProfile['fullName']}</p>
            <p><strong>Age:</strong> {$userProfile['age']}</p>
            <p><strong>Height:</strong> {$userProfile['height']} cm</p>
            <p><strong>Weight:</strong> {$userProfile['weight']} kg</p>
            <img src="{$userProfile['avatar150']}" alt="Avatar" class="img-thumbnail">
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Today’s Activity</div>
        <div class="card-body">
            <p><strong>Steps:</strong> {$dailySteps}</p>
            <p><strong>Resting Heart Rate:</strong> {$heartRateSummary['restingHeartRate'] ?? 'N/A'} bpm</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Sleep Summary</div>
        <div class="card-body">
            <p><strong>Total Sleep:</strong> {$sleepSummary['totalMinutesAsleep'] ?? 'N/A'} minutes</p>
            <p><strong>Efficiency:</strong> {$sleepSummary['sleepEfficiency'] ?? 'N/A'}%</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Raw Data</div>
        <div class="card-body">
            <pre>{$htmlspecialchars(print_r(['activities' => $activitiesData], true))}</pre>
        </div>
    </div>
</div>
</body>
</html>
HTML;

        $response->getBody()->write($html);
        return $response;

    } catch (\Throwable $exception) {
        $response->getBody()->write('Error fetching Fitbit data: ' . $exception->getMessage());
        return $response->withStatus(500);
    }
});


$app->run();
