<?php

declare(strict_types=1);

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;

class AuthRequestHandler extends ServerRequestInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryParameters = $request->getQueryParams();
        $authorizationCode = $queryParameters['code'] ?? null;
    
        if ($authorizationCode === null) {
            return $this->handleNoAuthorizationCodeReturned();
        }
    
        try {
            $tokenData = $fitbitClient->requestAccessToken($authorizationCode);
    
            file_put_contents(__DIR__ . '/tokens.json', json_encode($tokenData));
    
            return $this->getSuccessResponse();
        } catch (\Throwable $e) {
            
           $this->getFitbitClientErrorResponse($e);
        }
    }

    private function getSuccessResponse(): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();

        $response->getBody()->write(
            json_encode(['success' => true])
        );

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function getFitbitClientErrorResponse(\Throwable $e): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();

        $response->getBody()->write(
            json_encode(['error' => $e->getMessage()])
        );
        
        return $response
            ->withStatus(500)
            ->withHeader('Content-Type', 'application/json');
    }

    private function getNoAuthorizationCodeReturnedResponse(): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json')
            ->write(json_encode(['error' => 'No authorization code returned']));
    }
}