<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;

class AuthRequestHandler extends ServerRequestInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $authorizationUrl = $fitbitClient->getAuthorizationUrl(
            $fitbitConfig['scope']
        );
    
        return $response
            ->withHeader('Location', $authorizationUrl)
            ->withStatus(302);
    }
}