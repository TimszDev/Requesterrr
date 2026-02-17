<?php
declare(strict_types=1);

namespace Requesterrr\Integrations;

use Requesterrr\Support\HttpClient;

final class PlexClient
{
    /**
     * @param array<int,string> $librarySectionIds
     */
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly array $librarySectionIds
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->token !== '' && count($this->librarySectionIds) > 0;
    }

    /**
     * @return array{success:bool,error:string,refreshed:int}
     */
    public function refreshLibraries(): array
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'error' => 'Plex is not fully configured. Set PLEX_URL, PLEX_TOKEN, and PLEX_LIBRARY_SECTION_IDS.',
                'refreshed' => 0,
            ];
        }

        $refreshedCount = 0;
        foreach ($this->librarySectionIds as $sectionId) {
            $endpoint = rtrim($this->baseUrl, '/') . '/library/sections/' . rawurlencode($sectionId) . '/refresh';
            $response = $this->httpClient->request('GET', $endpoint, [
                'query' => ['X-Plex-Token' => $this->token],
                'headers' => ['Accept' => 'application/json'],
            ]);

            if ($response['ok']) {
                $refreshedCount++;
            }
        }

        if ($refreshedCount === 0) {
            return ['success' => false, 'error' => 'Failed to refresh Plex libraries.', 'refreshed' => 0];
        }

        return ['success' => true, 'error' => '', 'refreshed' => $refreshedCount];
    }
}

