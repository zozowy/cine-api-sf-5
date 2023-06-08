<?php

namespace App\Services;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ApiPosterUrlService
{
    public const API_END_POINT = 'title/find?q=';

    /**
     * @var HttpClientInterface
     */
    private $client;

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var array<mixed>
     */
    private $movieApiParams;

    /**
     * @var CacheInterface
     */
    private $cacheInterface;

    /**
     * Undocumented function
     *
     * @param HttpClientInterface $client
     * @param LoggerInterface $logger
     * @param CacheInterface $cacheInterface
     * @param array<mixed> $movieApiParams
     */
    public function __construct(HttpClientInterface $client, LoggerInterface $logger, CacheInterface $cacheInterface, array $movieApiParams)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->movieApiParams = $movieApiParams;
        $this->cacheInterface = $cacheInterface;
    }

    // retrieve poster url published
    public function getPoster(string $title, ?int $movieId): mixed
    {
        if (null === $movieId) {
            return null;
        }

        $cachedImage = $this->cacheInterface->get('cache_image_url_'.$movieId, function (ItemInterface $item, $title) {
            $response = $this->client->request('GET', $this->movieApiParams['url'].'/'.self::API_END_POINT.$title, [
                'headers' => [
                    'X-RapidAPI-Host' => $this->movieApiParams['host'],
                    'X-RapidAPI-Key' => $this->movieApiParams['key'],
                ],
            ]);
            if (200 !== $response->getStatusCode()) {
                $this->logger->error(sprintf('API : error when calling the '.self::API_END_POINT.' API : %s', $response->getContent(false)));

                return null;
            }
            $response = $this->getResponseDecode($response);
            if (null === $response) {
                return null;
            }
            $item->expiresAfter(20);
            $item->set($response);

            return $response;
        });

        return $cachedImage;
    }

    private function getResponseDecode(?ResponseInterface $response): ?string
    {
        $results = json_decode($response->getContent(), true);

        if (!isset($results['results'])) {
            return null;
        }
        if (!isset($results['results'][0]['image'])) {
            return null;
        }
        if (!isset($results['results'][0]['image']['url'])) {
            return null;
        }
        return $results['results'][0]['image']['url'];
    }
}
