<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class FitbitClient
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;
    private ?string $accessToken = null;
    private Client $httpClient;

    public function __construct(string $clientId, string $clientSecret, string $redirectUri)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->redirectUri = $redirectUri;
        $this->httpClient = new Client(['base_uri' => 'https://api.fitbit.com/']);
    }

    public function getAuthorizationUrl(string $scope = 'activity heartrate sleep profile'): string
    {
        $queryParameters = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => $scope,
        ]);

        return 'https://www.fitbit.com/oauth2/authorize?' . $queryParameters;
    }

    /**
     * @throws GuzzleException
     */
    public function requestAccessToken(string $authorizationCode): array
    {
        $response = $this->httpClient->post('oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$this->clientId}:{$this->clientSecret}"),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => [
                'client_id' => $this->clientId,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri,
                'code' => $authorizationCode,
            ]
        ]);

        $responseData = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $this->accessToken = $responseData['access_token'] ?? null;

        return $responseData;
    }

    /**
     * @throws GuzzleException
     */
    public function get(string $endpoint): array
    {
        if ($this->accessToken === null) {
            throw new \RuntimeException('Access token is not set. Call requestAccessToken() first.');
        }

        $response = $this->httpClient->get($endpoint, [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
            ]
        ]);

        return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
