<?php
// index.php

require __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Load config
$config = require __DIR__ . '/config.php';

$app = AppFactory::create();

$app->get('/', function ($request, $response) {
    $response->getBody()->write("Slim app is running. Go to /auth to start.");
    return $response;
});

// Start OAuth flow
$app->get('/auth', function (Request $request, Response $response) use ($config) {
    $query = http_build_query([
        'response_type' => 'code',
        'client_id'     => $config['clientId'],
        'redirect_uri'  => $config['redirectUri'],
        'scope'         => $config['scope'],
    ]);

    return $response
        ->withHeader('Location', $config['authUrl'] . '?' . $query)
        ->withStatus(302);
});

// Handle callback
$app->get('/callback', function (Request $request, Response $response) use ($config) {
    $params = $request->getQueryParams();
    if (!isset($params['code'])) {
        $response->getBody()->write("Error: no code returned.");
        return $response;
    }

    $code = $params['code'];

    $client = new Client();
    $basicAuth = base64_encode("{$config['clientId']}:{$config['clientSecret']}");

    $tokenRes = $client->post($config['tokenUrl'], [
        'headers' => [
            'Authorization' => "Basic $basicAuth",
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ],
        'form_params' => [
            'client_id'     => $config['clientId'],
            'grant_type'    => 'authorization_code',
            'redirect_uri'  => $config['redirectUri'],
            'code'          => $code,
        ]
    ]);

    $tokens = json_decode($tokenRes->getBody()->getContents(), true);
    $accessToken = $tokens['access_token'];

    $profileRes = $client->get("https://api.fitbit.com/1/user/-/profile.json", [
        'headers' => [
            'Authorization' => "Bearer $accessToken",
        ]
    ]);

    $profile = json_decode($profileRes->getBody()->getContents(), true);

    $response->getBody()->write("<h1>Fitbit Profile Data</h1><pre>" . print_r($profile, true) . "</pre>");
    return $response;
});

$app->run();
