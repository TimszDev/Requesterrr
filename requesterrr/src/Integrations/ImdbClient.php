<?php
declare(strict_types=1);

namespace Requesterrr\Integrations;

use Requesterrr\Support\HttpClient;

final class ImdbClient
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly string $baseUrl,
        private readonly bool $enabled
    ) {
    }

    /**
     * @return array{success:bool,error:string,results:array<int,array<string,mixed>>}
     */
    public function searchSuggestions(string $query): array
    {
        if (!$this->enabled) {
            return ['success' => true, 'error' => '', 'results' => []];
        }

        $trimmed = trim($query);
        if ($trimmed === '') {
            return ['success' => true, 'error' => '', 'results' => []];
        }

        $firstChar = strtolower(substr(preg_replace('/[^a-zA-Z0-9]/', '', $trimmed) ?: 'a', 0, 1));
        if ($firstChar === '') {
            $firstChar = 'a';
        }

        $url = rtrim($this->baseUrl, '/') . '/' . $firstChar . '/' . rawurlencode($trimmed) . '.json';
        $response = $this->httpClient->request('GET', $url, [
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (!$response['ok']) {
            return ['success' => false, 'error' => $this->buildError($response), 'results' => []];
        }

        $json = is_array($response['json']) ? $response['json'] : [];
        $items = isset($json['d']) && is_array($json['d']) ? $json['d'] : [];
        $results = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $id = (string) ($item['id'] ?? '');
            $title = (string) ($item['l'] ?? '');
            $year = isset($item['y']) ? (int) $item['y'] : null;
            $typeLabel = strtolower((string) ($item['q'] ?? ''));
            $mediaType = str_contains($typeLabel, 'tv') ? 'tv' : 'movie';

            $image = null;
            if (isset($item['i']) && is_array($item['i']) && isset($item['i']['imageUrl'])) {
                $image = (string) $item['i']['imageUrl'];
            }

            if ($id === '' || $title === '') {
                continue;
            }

            $results[] = [
                'imdb_id' => $id,
                'title' => $title,
                'year' => $year,
                'media_type' => $mediaType,
                'type_label' => $typeLabel,
                'poster' => $image,
            ];
        }

        return ['success' => true, 'error' => '', 'results' => $results];
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
            return 'IMDb request failed: ' . $curlError;
        }

        return 'IMDb request failed (HTTP ' . (int) ($response['status'] ?? 0) . '): ' . $body;
    }
}

