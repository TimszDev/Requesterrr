<?php
declare(strict_types=1);

namespace Requesterrr\Integrations;

use Requesterrr\Support\HttpClient;

final class SonarrClient
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $rootFolderPath,
        private readonly int $qualityProfile1080p,
        private readonly int $qualityProfile4k,
        private readonly int $languageProfileId
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->apiKey !== '' && $this->rootFolderPath !== '';
    }

    /**
     * @param array<int,array{seasonNumber:int,monitored:bool}> $seasons
     * @return array{success:bool,error:string,status:int,body:string}
     */
    public function requestSeries(
        string $title,
        int $tvdbId,
        string $quality,
        array $seasons
    ): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Sonarr is not configured. Check SONARR_URL, SONARR_API_KEY, and SONARR_ROOT_FOLDER.',
                'status' => 0,
                'body' => '',
            ];
        }

        $qualityProfileId = $quality === '4k' ? $this->qualityProfile4k : $this->qualityProfile1080p;

        $payload = [
            'title' => $title,
            'tvdbId' => $tvdbId,
            'qualityProfileId' => $qualityProfileId,
            'languageProfileId' => $this->languageProfileId,
            'rootFolderPath' => $this->rootFolderPath,
            'seasonFolder' => true,
            'monitored' => true,
            'seasons' => $seasons,
            'addOptions' => [
                'searchForMissingEpisodes' => true,
            ],
        ];

        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/api/v3/series', [
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
            return 'Sonarr request failed: ' . $curlError;
        }
        return 'Sonarr request failed (HTTP ' . (int) ($response['status'] ?? 0) . '): ' . $body;
    }
}

