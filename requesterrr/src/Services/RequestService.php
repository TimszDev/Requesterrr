<?php
declare(strict_types=1);

namespace Requesterrr\Services;

use Requesterrr\Integrations\RadarrClient;
use Requesterrr\Integrations\SonarrClient;
use Requesterrr\Support\Config;
use Requesterrr\Support\Storage;

final class RequestService
{
    public function __construct(
        private readonly Config $config,
        private readonly MetadataService $metadataService,
        private readonly RadarrClient $radarrClient,
        private readonly SonarrClient $sonarrClient,
        private readonly Storage $storage
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{success:bool,error:string,data:array<string,mixed>|null}
     */
    public function submit(array $payload): array
    {
        $quality = strtolower(trim((string) ($payload['quality'] ?? '')));
        if (!in_array($quality, ['1080p', '4k'], true)) {
            return ['success' => false, 'error' => 'Quality must be 1080p or 4k.', 'data' => null];
        }

        $resolved = $this->metadataService->resolveSelection([
            'media_type' => $payload['media_type'] ?? $payload['type'] ?? '',
            'tmdb_id' => $payload['tmdb_id'] ?? null,
            'imdb_id' => $payload['imdb_id'] ?? null,
            'title' => $payload['title'] ?? '',
            'year' => $payload['year'] ?? null,
        ]);

        if (!$resolved['success'] || !is_array($resolved['item'])) {
            return ['success' => false, 'error' => $resolved['error'], 'data' => null];
        }

        $item = $resolved['item'];
        $mediaType = (string) ($item['media_type'] ?? '');
        if ($mediaType === 'movie') {
            return $this->submitMovie($item, $quality);
        }

        if ($mediaType === 'tv') {
            return $this->submitSeries($item, $quality, $payload);
        }

        return ['success' => false, 'error' => 'Unsupported media type.', 'data' => null];
    }

    /**
     * @param array<string,mixed> $item
     * @return array{success:bool,error:string,data:array<string,mixed>|null}
     */
    private function submitMovie(array $item, string $quality): array
    {
        $title = (string) ($item['title'] ?? '');
        $tmdbId = (int) ($item['tmdb_id'] ?? 0);
        $year = isset($item['year']) ? (int) $item['year'] : null;
        $imdbId = isset($item['imdb_id']) ? (string) $item['imdb_id'] : null;

        if ($title === '' || $tmdbId <= 0) {
            return ['success' => false, 'error' => 'Movie title or TMDB id is missing.', 'data' => null];
        }

        $requestResult = $this->radarrClient->requestMovie($title, $tmdbId, $year, $quality, $imdbId);
        $status = $requestResult['success'] ? 'queued' : 'failed';

        $this->storage->logRequest([
            'media_type' => 'movie',
            'title' => $title,
            'release_year' => $year,
            'tmdb_id' => $tmdbId,
            'imdb_id' => $imdbId,
            'tvdb_id' => null,
            'quality' => $quality,
            'season_selection' => null,
            'target_client' => 'radarr',
            'status' => $status,
            'response_json' => $requestResult['body'] ?? null,
        ]);

        if (!$requestResult['success']) {
            return ['success' => false, 'error' => $requestResult['error'], 'data' => null];
        }

        return [
            'success' => true,
            'error' => '',
            'data' => [
                'message' => 'Movie request sent to Radarr. It should flow to qBittorrent automatically.',
                'media_type' => 'movie',
                'title' => $title,
                'quality' => $quality,
                'tmdb_id' => $tmdbId,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $payload
     * @return array{success:bool,error:string,data:array<string,mixed>|null}
     */
    private function submitSeries(array $item, string $quality, array $payload): array
    {
        $title = (string) ($item['title'] ?? '');
        $tvdbId = isset($item['tvdb_id']) ? (int) $item['tvdb_id'] : 0;
        $tmdbId = isset($item['tmdb_id']) ? (int) $item['tmdb_id'] : 0;
        $imdbId = isset($item['imdb_id']) ? (string) $item['imdb_id'] : null;
        $seasonMode = strtolower(trim((string) ($payload['season_mode'] ?? 'all')));
        $selectedSeasons = isset($payload['seasons']) && is_array($payload['seasons']) ? $payload['seasons'] : [];

        if ($title === '' || $tvdbId <= 0) {
            return [
                'success' => false,
                'error' => 'Unable to request TV series because tvdb_id is missing. Ensure TMDB has TVDB mapping.',
                'data' => null,
            ];
        }

        $availableSeasons = isset($item['seasons']) && is_array($item['seasons']) ? $item['seasons'] : [];
        $seasonPayload = $this->buildSeasonPayload($availableSeasons, $seasonMode, $selectedSeasons);
        if (count($seasonPayload) === 0) {
            return ['success' => false, 'error' => 'No valid seasons selected.', 'data' => null];
        }

        $requestResult = $this->sonarrClient->requestSeries($title, $tvdbId, $quality, $seasonPayload);
        $status = $requestResult['success'] ? 'queued' : 'failed';

        $this->storage->logRequest([
            'media_type' => 'tv',
            'title' => $title,
            'release_year' => isset($item['year']) ? (int) $item['year'] : null,
            'tmdb_id' => $tmdbId > 0 ? $tmdbId : null,
            'imdb_id' => $imdbId,
            'tvdb_id' => $tvdbId,
            'quality' => $quality,
            'season_selection' => json_encode($seasonPayload),
            'target_client' => 'sonarr',
            'status' => $status,
            'response_json' => $requestResult['body'] ?? null,
        ]);

        if (!$requestResult['success']) {
            return ['success' => false, 'error' => $requestResult['error'], 'data' => null];
        }

        return [
            'success' => true,
            'error' => '',
            'data' => [
                'message' => 'TV request sent to Sonarr. It should flow to qBittorrent automatically.',
                'media_type' => 'tv',
                'title' => $title,
                'quality' => $quality,
                'tvdb_id' => $tvdbId,
                'season_mode' => $seasonMode,
            ],
        ];
    }

    /**
     * @param array<int,array{season_number:int,name:string,episode_count:int}> $availableSeasons
     * @param array<int,mixed> $selectedSeasons
     * @return array<int,array{seasonNumber:int,monitored:bool}>
     */
    private function buildSeasonPayload(array $availableSeasons, string $seasonMode, array $selectedSeasons): array
    {
        $numbers = [];
        foreach ($availableSeasons as $season) {
            $number = (int) ($season['season_number'] ?? 0);
            if ($number > 0) {
                $numbers[] = $number;
            }
        }

        if (count($numbers) === 0) {
            return [];
        }

        $monitorMap = [];
        if ($seasonMode === 'all') {
            foreach ($numbers as $number) {
                $monitorMap[$number] = true;
            }
        } else {
            $selected = [];
            foreach ($selectedSeasons as $selectedSeason) {
                $number = (int) $selectedSeason;
                if ($number > 0) {
                    $selected[$number] = true;
                }
            }

            foreach ($numbers as $number) {
                $monitorMap[$number] = isset($selected[$number]);
            }
        }

        $payload = [];
        foreach ($monitorMap as $seasonNumber => $monitored) {
            $payload[] = [
                'seasonNumber' => (int) $seasonNumber,
                'monitored' => (bool) $monitored,
            ];
        }

        return $payload;
    }
}

