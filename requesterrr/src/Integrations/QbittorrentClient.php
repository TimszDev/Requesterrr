<?php
declare(strict_types=1);

namespace Requesterrr\Integrations;

use Requesterrr\Support\HttpClient;

final class QbittorrentClient
{
    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly string $baseUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly string $cookieFile,
        private readonly bool $verifySsl
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->username !== '' && $this->password !== '';
    }

    /**
     * @return array{success:bool,error:string}
     */
    public function authenticate(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'qBittorrent credentials are not configured.'];
        }

        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/api/v2/auth/login', [
            'form' => [
                'username' => $this->username,
                'password' => $this->password,
            ],
            'cookie_file' => $this->cookieFile,
            'verify_ssl' => $this->verifySsl,
        ]);

        if (!$response['ok']) {
            return ['success' => false, 'error' => $this->buildError($response)];
        }

        $body = strtolower(trim($response['body']));
        if (!str_contains($body, 'ok')) {
            return ['success' => false, 'error' => 'qBittorrent login failed: unexpected response body.'];
        }

        return ['success' => true, 'error' => ''];
    }

    /**
     * @return array{success:bool,error:string,torrents:array<int,array<string,mixed>>}
     */
    public function getCompletedTorrents(): array
    {
        $auth = $this->authenticate();
        if (!$auth['success']) {
            return ['success' => false, 'error' => $auth['error'], 'torrents' => []];
        }

        $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/api/v2/torrents/info', [
            'query' => ['filter' => 'completed'],
            'cookie_file' => $this->cookieFile,
            'verify_ssl' => $this->verifySsl,
        ]);

        if (!$response['ok']) {
            return ['success' => false, 'error' => $this->buildError($response), 'torrents' => []];
        }

        $json = is_array($response['json']) ? $response['json'] : [];
        return ['success' => true, 'error' => '', 'torrents' => $json];
    }

    /**
     * @param array<int,string> $hashes
     * @return array{success:bool,error:string}
     */
    public function pauseTorrents(array $hashes): array
    {
        $uniqueHashes = array_values(array_unique(array_filter(array_map('trim', $hashes), static fn (string $hash): bool => $hash !== '')));
        if (count($uniqueHashes) === 0) {
            return ['success' => true, 'error' => ''];
        }

        $auth = $this->authenticate();
        if (!$auth['success']) {
            return ['success' => false, 'error' => $auth['error']];
        }

        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/api/v2/torrents/pause', [
            'form' => ['hashes' => implode('|', $uniqueHashes)],
            'cookie_file' => $this->cookieFile,
            'verify_ssl' => $this->verifySsl,
        ]);

        if (!$response['ok']) {
            return ['success' => false, 'error' => $this->buildError($response)];
        }

        return ['success' => true, 'error' => ''];
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
            return 'qBittorrent request failed: ' . $curlError;
        }
        return 'qBittorrent request failed (HTTP ' . (int) ($response['status'] ?? 0) . '): ' . $body;
    }
}

