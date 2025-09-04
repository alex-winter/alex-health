<?php
// config.php
require __DIR__ . '/vendor/autoload.php';

return [
    'clientId'     => $_ENV['FITBIT_CLIENT_ID'],
    'clientSecret' => $_ENV['FITBIT_CLIENT_SECRET'],
    'redirectUri'  => $_ENV['FITBIT_REDIRECT_URI'],
    'authUrl'      => $_ENV['FITBIT_AUTH_URL'],
    'tokenUrl'     => $_ENV['FITBIT_TOKEN_URL'],
    'scope'        => $_ENV['FITBIT_SCOPE'],
];
