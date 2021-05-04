<?php

require('./vendor/autoload.php');

use GuzzleHttp\Client;
use Microsoft\Graph\Graph;

function getApiClient() {

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $guzzle = new Client();
    $tenantId = $_ENV['TENANT'];
    $clientId = $_ENV['CLIENT'];
    $clientSecret = $_ENV['SECRET'];

    $url = 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/token?api-version=1.0';

    try {
        $token = json_decode($guzzle->post($url, [
            'form_params' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'resource' => 'https://graph.microsoft.com/',
                'grant_type' => 'client_credentials',
            ],
        ])->getBody()->getContents());

        $accessToken = $token->access_token;

        $graph = new Graph();
        $graph->setAccessToken($accessToken);
        return $graph;
    } catch(\Exception $e) {
        throw new Exception($e->getMessage());
    }
}