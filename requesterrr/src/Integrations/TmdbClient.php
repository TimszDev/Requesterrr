<?php
declare(strict_types=1);

namespace Requesterrr\Integrations;

use Requesterrr\Support\HttpClient;

final class TmdbClient
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $imageBaseUrl
    ) {
    }

    /**
     * @return array{success:bool,error:string,results:array<int,array<string,mixed>>}
     */
    public function searchMulti(string $query, int $page = 1): array
    {
        if ($this->apiKey === '') {
            return ['success' => false, 'error' => 'TMDB_API_KEY is missing.', 'results' => []];
        }

        $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/search/multi', [
            'query' => [
                'api_key' => $this->apiKey,
                'query' => $query,
                'include_adult' => 'false',
                'language' => 'en-US',
                'page' => max(1, $page),
            ],
        ]);

        if (!$response['ok']) {
            return ['success' => false, 'error' => $this->buildError($response), 'results' => []];
        }

        $json = is_array($response['json']) ? $response['json'] : [];
        $results = isset($json['results']) && is_array($json['results']) ? $json['results'] : [];

        return ['success' => true, 'error' => '', 'results' => $results];
    }

    /**
     * @return array{success:bool,error:string,result:array<string,mixed>|null}
     */
    public function searchTypeByTitle(string $type, string $title, ?int $year = null): array
    {
        if (!in_array($type, ['movie', 'tv'], true)) {
            return ['success' => false, 'error' => 'Invalid TMDB type.', 'result' => null];
        }

        if ($this->apiKey === '') {
            return ['success' => false, 'error' => 'TMDB_API_KEY is missing.', 'result' => null];
        }

        $queryParams = [
            'api_key' => $this->apiKey,
            'query' => $title,
            'include_adult' => 'false',
            'language' => 'en-US',
            'page' => 1,
        ];

        if ($type === 'movie' && $year !== null && $year > 0) {
            $queryParams['year'] = $year;
        }
        if ($type === 'tv' && $year !== null && $year > 0) {
            $queryParams['first_air_date_year'] = $year;
        }

        $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/search/' . $type, [
            'query' => $queryParams,
        ]);

        if (!$response['ok']) {
            return ['success' => false, 'error' => $this->buildError($response), 'result' => null];
        }

        $json = is_array($response['json']) ? $response['json'] : [];
        $results = isset($json['results']) && is_array($json['results']) ? $json['results'] : [];
        $first = $results[0] ?? null;
        if (!is_array($first)) {
            return ['success' => false, 'error' => 'No TMDB match found.', 'result' => null];
        }

        return ['success' => true, 'error' => '', 'result' => $first];
    }

    /**
     * @return array{success:bool,error:string,result:array<string,mixed>|null}
     */
    public function getDetails(string $type, int $tmdbId): array
    {
        if (!in_array($type, ['movie', 'tv'], true)) {
            return ['success' => false, 'error' => 'Invalid TMDB type.', 'result' => null];
        }

        if ($this->apiKey === '') {
            return ['success' => false, 'error' => 'TMDB_API_KEY is missing.', 'result' => null];
        }

        $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/' . $type . '/' . $tmdbId, [
            'query' => [
                'api_key' => $this->apiKey,
                'language' => 'en-US',
                'append_to_response' => 'external_ids',
            ],
        ]);

        if (!$response['ok']) {
            return ['success' => false, 'error' => $this->buildError($response), 'result' => null];
        }

        $json = is_array($response['json']) ? $response['json'] : null;
        if (!is_array($json)) {
            return ['success' => false, 'error' => 'Invalid TMDB details response.', 'result' => null];
        }

        return ['success' => true, 'error' => '', 'result' => $json];
    }

    /**
     * @return array{success:bool,error:string,result:array<string,mixed>|null}
     */
    public function findByImdbId(string $imdbId): array
    {
        if ($this->apiKey === '') {
            return ['success' => false, 'error' => 'TMDB_API_KEY is missing.', 'result' => null];
        }

        $response = $this->httpClient->request(
            'GET',
            rtrim($this->baseUrl, '/') . '/find/' . rawurlencode($imdbId),
            [
                'query' => [
                    'api_key' => $this->apiKey,
                    'external_source' => 'imdb_id',
                ],
            ]
        );

        if (!$response['ok']) {
            return ['success' => false, 'error' => $this->buildError($response), 'result' => null];
        }

        $json = is_array($response['json']) ? $response['json'] : [];
        $movie = isset($json['movie_results'][0]) && is_array($json['movie_results'][0]) ? $json['movie_results'][0] : null;
        if ($movie !== null) {
            return ['success' => true, 'error' => '', 'result' => ['media_type' => 'movie'] + $movie];
        }

        $tv = isset($json['tv_results'][0]) && is_array($json['tv_results'][0]) ? $json['tv_results'][0] : null;
        if ($tv !== null) {
            return ['success' => true, 'error' => '', 'result' => ['media_type' => 'tv'] + $tv];
        }

        return ['success' => false, 'error' => 'No TMDB item matched the IMDb id.', 'result' => null];
    }

    public function buildPosterUrl(?string $posterPath, string $size = 'w342'): ?string
    {
        if (!is_string($posterPath) || trim($posterPath) === '') {
            return null;
        }

        return rtrim($this->imageBaseUrl, '/') . '/' . $size . $posterPath;
    }

    /**
     * @param array{status:int,body:string,error:string} $response
     */
    private function buildError(array $response): string
    {
        $body = trim((string) ($response['body'] ?? ''));
        if ($body !== '' && strlen($body) > 250) {
            $body = substr($body, 0, 250) . '...';
        }

        $curlError = trim((string) ($response['error'] ?? ''));
        if ($curlError !== '') {
            return 'TMDB request failed: ' . $curlError;
        }

        return 'TMDB request failed (HTTP ' . (int) ($response['status'] ?? 0) . '): ' . $body;
    }
}

