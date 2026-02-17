<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use Requesterrr\App;

try {
    $projectRoot = dirname(__DIR__, 2);
    $config = App::config($projectRoot);

    requesterrr_json_response([
        'success' => true,
        'app_name' => $config->getString('APP_NAME', 'Requesterrr'),
        'checks' => [
            'tmdb_configured' => $config->getString('TMDB_API_KEY') !== '',
            'radarr_configured' => App::radarrClient($projectRoot)->isConfigured(),
            'sonarr_configured' => App::sonarrClient($projectRoot)->isConfigured(),
            'qbittorrent_configured' => App::qbittorrentClient($projectRoot)->isConfigured(),
            'plex_configured' => App::plexClient($projectRoot)->isConfigured(),
        ],
    ]);
} catch (Throwable $throwable) {
    requesterrr_json_response([
        'success' => false,
        'error' => $throwable->getMessage(),
    ], 500);
}

