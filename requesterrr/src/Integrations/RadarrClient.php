<?php
declare(strict_types=1);

namespace Requesterrr\Integrations;

use Requesterrr\Support\HttpClient;

final class RadarrClient
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $rootFolderPath,
        private readonly int $qualityProfile1080p,
        private readonly int $qualityProfile4k
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '' && $this->rootFolderPath !== '';
    }

    /**
     * @return array{success:bool,error:string,status:int,body:string}
     */
    public function requestMovie(
        string $title,
        int $tmdbId,
        ?int $year,
        string $quality,
        ?string $imdbId
    ): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Radarr is not configured. Check RADARR_URL, RADARR_API_KEY, and RADARR_ROOT_FOLDER.',
                'status' => 0,
                'body' => '',
            ];
        }

        $qualityProfileId = $quality === '4k' ? $this->qualityProfile4k : $this->qualityProfile1080p;

        $payload = [
            'title' => $title,
            'qualityProfileId' => $qualityProfileId,
            'tmdbId' => $tmdbId,
            'rootFolderPath' => $this->rootFolderPath,
            'monitored' => true,
            'minimumAvailability' => 'released',
            'addOptions' => [
                'searchForMovie' => true,
            ],
        ];

        if ($year !== null && $year > 0) {
            $payload['year'] = $year;
        }
        if ($imdbId !== null && $imdbId !== '') {
            $payload['imdbId'] = $imdbId;
        }

        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/api/v3/movie', [
            'headers' => ['X-Api-Key' => $this->apiKey],
            'json' => $payload,
        ]);

        return [
            'success' => $response['ok'],
            'error' => $response['ok'] ? '' : $this->buildError($response),
            'status' => $response['status'],
            'body' => $response['body'],
        ];
    }

    /**
     * @param array{status:int,body:string,error:string} $response
     */
    private function buildError(array $response): string
    {
        $body = trim((string) ($response['body'] ?? ''));
        if ($body !== '' && strlen($body) > 280) {
            $body = substr($body, 0, 280) . '...';
        }
        $curlError = trim((string) ($response['error'] ?? ''));
        if ($curlError !== '') {
            return 'Radarr request failed: ' . $curlError;
        }
        return 'Radarr request failed (HTTP ' . (int) ($response['status'] ?? 0) . '): ' . $body;
    }
}

