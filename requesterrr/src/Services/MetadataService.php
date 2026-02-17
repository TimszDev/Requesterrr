<?php
declare(strict_types=1);

namespace Requesterrr\Services;

use Requesterrr\Integrations\ImdbClient;
use Requesterrr\Integrations\TmdbClient;

final class MetadataService
{
    public function __construct(
        private readonly TmdbClient $tmdbClient,
        private readonly ImdbClient $imdbClient
    ) {
    }

    /**
     * @return array{success:bool,error:string,results:array<int,array<string,mixed>>}
     */
    public function search(string $query, int $limit = 18): array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return ['success' => true, 'error' => '', 'results' => []];
        }

        $tmdbResponse = $this->tmdbClient->searchMulti($trimmed, 1);
        if (!$tmdbResponse['success']) {
            return ['success' => false, 'error' => $tmdbResponse['error'], 'results' => []];
        }

        $results = [];
        $mergeMap = [];

        foreach ($tmdbResponse['results'] as $result) {
            if (!is_array($result)) {
                continue;
            }

            $mediaType = (string) ($result['media_type'] ?? '');
            if (!in_array($mediaType, ['movie', 'tv'], true)) {
                continue;
            }

            $title = (string) ($result['title'] ?? $result['name'] ?? '');
            if ($title === '') {
                continue;
            }

            $date = (string) ($result['release_date'] ?? $result['first_air_date'] ?? '');
            $year = null;
            if (preg_match('/^[0-9]{4}/', $date, $matches)) {
                $year = (int) $matches[0];
            }

            $tmdbId = isset($result['id']) ? (int) $result['id'] : 0;
            if ($tmdbId <= 0) {
                continue;
            }

            $posterPath = isset($result['poster_path']) ? (string) $result['poster_path'] : null;
            $poster = $this->tmdbClient->buildPosterUrl($posterPath);
            $overview = (string) ($result['overview'] ?? '');
            $mergeKey = $this->buildMergeKey($title, $year);

            $normalized = [
                'id' => $mediaType . ':' . $tmdbId,
                'media_type' => $mediaType,
                'title' => $title,
                'year' => $year,
                'poster' => $poster,
                'overview' => $overview,
                'tmdb_id' => $tmdbId,
                'imdb_id' => null,
                'sources' => ['tmdb'],
            ];

            $results[] = $normalized;
            $mergeMap[$mergeKey] = count($results) - 1;
        }

        $imdbResponse = $this->imdbClient->searchSuggestions($trimmed);
        if ($imdbResponse['success']) {
            foreach ($imdbResponse['results'] as $imdbItem) {
                if (!is_array($imdbItem)) {
                    continue;
                }

                $title = (string) ($imdbItem['title'] ?? '');
                $year = isset($imdbItem['year']) ? (int) $imdbItem['year'] : null;
                $mergeKey = $this->buildMergeKey($title, $year);
                $imdbId = (string) ($imdbItem['imdb_id'] ?? '');

                if (isset($mergeMap[$mergeKey])) {
                    $index = $mergeMap[$mergeKey];
                    $results[$index]['imdb_id'] = $results[$index]['imdb_id'] ?: $imdbId;
                    $sources = $results[$index]['sources'];
                    if (is_array($sources) && !in_array('imdb', $sources, true)) {
                        $sources[] = 'imdb';
                    }
                    $results[$index]['sources'] = $sources;
                    continue;
                }

                // Keep only IMDb results that are likely resolvable later.
                $mediaType = (string) ($imdbItem['media_type'] ?? 'movie');
                if ($title === '' || $imdbId === '') {
                    continue;
                }

                $results[] = [
                    'id' => 'imdb:' . $imdbId,
                    'media_type' => $mediaType,
                    'title' => $title,
                    'year' => $year,
                    'poster' => (string) ($imdbItem['poster'] ?? ''),
                    'overview' => '',
                    'tmdb_id' => null,
                    'imdb_id' => $imdbId,
                    'sources' => ['imdb'],
                ];
            }
        }

        usort(
            $results,
            static function (array $a, array $b): int {
                $aSourceCount = is_array($a['sources']) ? count($a['sources']) : 0;
                $bSourceCount = is_array($b['sources']) ? count($b['sources']) : 0;
                if ($aSourceCount === $bSourceCount) {
                    return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
                }
                return $bSourceCount <=> $aSourceCount;
            }
        );

        return ['success' => true, 'error' => '', 'results' => array_slice($results, 0, max(1, $limit))];
    }

    /**
     * @param array<string,mixed> $selection
     * @return array{success:bool,error:string,item:array<string,mixed>|null}
     */
    public function resolveSelection(array $selection): array
    {
        $mediaType = strtolower(trim((string) ($selection['media_type'] ?? $selection['type'] ?? '')));
        if (!in_array($mediaType, ['movie', 'tv'], true)) {
            return ['success' => false, 'error' => 'Invalid media type.', 'item' => null];
        }

        $tmdbId = isset($selection['tmdb_id']) ? (int) $selection['tmdb_id'] : 0;
        $imdbId = trim((string) ($selection['imdb_id'] ?? ''));
        $title = trim((string) ($selection['title'] ?? ''));
        $year = isset($selection['year']) ? (int) $selection['year'] : null;

        if ($tmdbId <= 0 && $imdbId !== '') {
            $find = $this->tmdbClient->findByImdbId($imdbId);
            if ($find['success'] && is_array($find['result'])) {
                $tmdbId = (int) ($find['result']['id'] ?? 0);
                $foundType = (string) ($find['result']['media_type'] ?? '');
                if (in_array($foundType, ['movie', 'tv'], true)) {
                    $mediaType = $foundType;
                }
            }
        }

        if ($tmdbId <= 0 && $title !== '') {
            $search = $this->tmdbClient->searchTypeByTitle($mediaType, $title, $year);
            if ($search['success'] && is_array($search['result'])) {
                $tmdbId = (int) ($search['result']['id'] ?? 0);
            }
        }

        if ($tmdbId <= 0) {
            return ['success' => false, 'error' => 'Unable to resolve this title to TMDB.', 'item' => null];
        }

        $details = $this->tmdbClient->getDetails($mediaType, $tmdbId);
        if (!$details['success'] || !is_array($details['result'])) {
            return ['success' => false, 'error' => $details['error'], 'item' => null];
        }

        $result = $details['result'];

        $resolvedTitle = (string) ($result['title'] ?? $result['name'] ?? $title);
        $date = (string) ($result['release_date'] ?? $result['first_air_date'] ?? '');
        $resolvedYear = $year;
        if (preg_match('/^[0-9]{4}/', $date, $matches)) {
            $resolvedYear = (int) $matches[0];
        }

        $externalIds = isset($result['external_ids']) && is_array($result['external_ids'])
            ? $result['external_ids']
            : [];

        $resolvedImdbId = trim((string) ($externalIds['imdb_id'] ?? $imdbId));
        $resolvedTvdbId = isset($externalIds['tvdb_id']) ? (int) $externalIds['tvdb_id'] : null;
        if ($resolvedTvdbId !== null && $resolvedTvdbId <= 0) {
            $resolvedTvdbId = null;
        }

        $poster = $this->tmdbClient->buildPosterUrl(
            isset($result['poster_path']) ? (string) $result['poster_path'] : null
        );

        $normalized = [
            'media_type' => $mediaType,
            'title' => $resolvedTitle,
            'year' => $resolvedYear,
            'tmdb_id' => $tmdbId,
            'imdb_id' => $resolvedImdbId !== '' ? $resolvedImdbId : null,
            'tvdb_id' => $resolvedTvdbId,
            'poster' => $poster,
            'overview' => (string) ($result['overview'] ?? ''),
            'seasons' => [],
        ];

        if ($mediaType === 'tv') {
            $normalized['seasons'] = $this->normalizeSeasons($result['seasons'] ?? []);
        }

        return ['success' => true, 'error' => '', 'item' => $normalized];
    }

    /**
     * @param mixed $seasonsRaw
     * @return array<int,array{season_number:int,name:string,episode_count:int}>
     */
    private function normalizeSeasons(mixed $seasonsRaw): array
    {
        if (!is_array($seasonsRaw)) {
            return [];
        }

        $seasons = [];
        foreach ($seasonsRaw as $season) {
            if (!is_array($season)) {
                continue;
            }

            $seasonNumber = isset($season['season_number']) ? (int) $season['season_number'] : -1;
            if ($seasonNumber <= 0) {
                continue;
            }

            $seasons[] = [
                'season_number' => $seasonNumber,
                'name' => (string) ($season['name'] ?? ('Season ' . $seasonNumber)),
                'episode_count' => isset($season['episode_count']) ? (int) $season['episode_count'] : 0,
            ];
        }

        usort($seasons, static fn (array $a, array $b): int => $a['season_number'] <=> $b['season_number']);
        return $seasons;
    }

    private function buildMergeKey(string $title, ?int $year): string
    {
        $normalizedTitle = strtolower(trim(preg_replace('/\s+/', ' ', $title) ?? $title));
        return $normalizedTitle . '|' . ($year ?? 0);
    }
}

