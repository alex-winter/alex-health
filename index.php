<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Slim\Factory\AppFactory;
use App\Services\FitbitClient;
use App\Http\RequestHandlers\AuthRequestHandler;
use App\Http\RequestHandlers\AuthCallbackRequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$fitbitConfig = require __DIR__ . '/config.php';

$fitbitClient = new FitbitClient(
    $fitbitConfig['clientId'],
    $fitbitConfig['clientSecret'],
    $fitbitConfig['redirectUri'],
);

$app = AppFactory::create();

$app->get('/auth', AuthRequestHandler::class);

$app->get('/callback', AuthCallbackRequestHandler::class);



$app->run();
