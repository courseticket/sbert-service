<?php
declare(strict_types=1);

namespace SbertService\Lib;

use Cake\Cache\Cache;
use Cake\Http\Client;
use Cake\Http\Exception\InternalErrorException;
use RestApi\Lib\Exception\DetailedException;

class SbertService
{
    public const MOCK_EMPTY_VECTORIZATION = 'MOCK_EMPTY_VECTORIZATION';

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
        if (env(SbertService::MOCK_EMPTY_VECTORIZATION, '') === SbertService::MOCK_EMPTY_VECTORIZATION) {
            return [];
        }
        if (!$this->bearerToken) {
            $this->bearerToken = env('SBERT_AUTH_TOKEN', '');
        }
        if (!$this->bearerToken) {
            throw new InternalErrorException('Bearer token is missing. Try setting env SBERT_AUTH_TOKEN');
        }
        $this->_httpClient->setConfig(['headers' => ['Authorization' => 'Bearer ' . $this->bearerToken]]);
        $endpointQuery = $domain . self::ENDPOINT;
        $envEndpoint = env('SBERT_VECTORIZE_API', '');
        if ($envEndpoint) {
            $endpointQuery = $envEndpoint;
        }
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
            throw new InternalErrorException('Error vectorising from: '
                . $endpointQuery . ' with content "' . $text . '" with response: '
                . $response->getStringBody());
        }
        return $body['data'][0]['vector'];
    }

    public function vectoriseDirectToPy(string $path, array $payload): array
    {
        $body = json_encode($payload);
        if (!$body) {
            throw new DetailedException('Invalid sbert payload', 400);
        }
        $cacheKey = '_vectorizeDirecToPy_' . md5($path . '_' . $body);
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
