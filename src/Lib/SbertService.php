<?php
declare(strict_types=1);

namespace SbertService\Lib;

use Cake\Cache\Cache;
use Cake\Http\Client;
use Cake\Http\Exception\InternalErrorException;
use RestApi\Lib\Exception\DetailedException;

class SbertService
{
    private const ENDPOINT = '/edu/api/v1/ai/sbert/vectorise';
    private const CACHE_GROUP = 'default';
    private Client $_httpClient;
    private string $bearerToken = '';

    public function __construct()
    {
        $this->_httpClient = new Client(['timeout' => 30]);
    }

    public function setToken(string $bearerToken): SbertService
    {
        $this->bearerToken = $bearerToken;
        return $this;
    }

    public function vectoriseViaProxy(string $text, string $domain): array
    {
        if (!$this->bearerToken) {
            $this->bearerToken = env('SBERT_AUTH_TOKEN', '');
        }
        if (!$this->bearerToken) {
            throw new InternalErrorException('Bearer token is missing');
        }
        $this->_httpClient->setConfig(['headers' => ['Authorization' => 'Bearer ' . $this->bearerToken]]);
        $endpointQuery = $domain . self::ENDPOINT;
        $body = [
            'vectorise' => [$text]
        ];
        try {
            $response = $this->_httpClient->post($endpointQuery, $body);
        } catch (Client\Exception\NetworkException $e) {
            $message = $e->getMessage();
            $count = substr_count($message, 'cURL Error (28) Operation timed out');
            if ($count > 0) {
                throw new DetailedException($message, 408, $e);
            }
            throw $e;

        }
        $body = $response->getJson();
        if (!$response->isSuccess() || !isset($body['data'][0]['vector'])) {
            throw new InternalErrorException('Error vectorising the content: '
                . $response->getStringBody());
        }
        return $body['data'][0]['vector'];
    }

    public function vectorizeDirecToPy(string $path, array $payload): array
    {
        $body = json_encode($payload);
        $cacheKey = '_vectorizeDirecToPy_' . $path . '_' . $body;
        $res = Cache::read($cacheKey, self::CACHE_GROUP);
        if (is_array($res)) {
            return $res;
        }
        $domain = env('SBERT_DOMAIN', 'http://sbert-eduplex-nginx-svc:5000');
        try {
            $options = ['headers' => ['Content-Type'=> 'application/json']];
            $res = $this->_httpClient
                ->get($domain . $path, ['_content' => $body], $options);
        } catch (\Exception $e) {
            throw new DetailedException(
                'Invalid sbert request: ' . $domain . $path . ' ' . $e->getMessage(),
                500
            );
        }
        if ($res->getJson()) {
            $toret = $res->getJson();
            Cache::write($cacheKey, $toret, self::CACHE_GROUP);
            return $toret;
        }
        throw new DetailedException(
            'Invalid sbert response: ' . $res->getStringBody(),
            500
        );
    }
}
