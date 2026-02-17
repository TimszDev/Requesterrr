<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Requesterrr\App;

$projectRoot = dirname(__DIR__);

try {
    $storage = App::storage($projectRoot);
    $qbitClient = App::qbittorrentClient($projectRoot);
    $plexClient = App::plexClient($projectRoot);

    $torrentsResult = $qbitClient->getCompletedTorrents();
    if (!$torrentsResult['success']) {
        $payload = [
            'success' => false,
            'error' => $torrentsResult['error'],
            'paused' => 0,
            'plex_refreshed' => 0,
        ];
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit(1);
    }

    $pauseHashes = [];
    $pauseRows = [];

    foreach ($torrentsResult['torrents'] as $torrent) {
        if (!is_array($torrent)) {
            continue;
        }

        $hash = trim((string) ($torrent['hash'] ?? ''));
        if ($hash === '') {
            continue;
        }

        if ($storage->wasTorrentProcessed($hash)) {
            continue;
        }

        $progress = isset($torrent['progress']) ? (float) $torrent['progress'] : 0.0;
        if ($progress < 1.0) {
            continue;
        }

        $state = strtolower((string) ($torrent['state'] ?? ''));
        if (str_contains($state, 'paused')) {
            $storage->markTorrentProcessed(
                $hash,
                (string) ($torrent['name'] ?? 'Unknown'),
                isset($torrent['category']) ? (string) $torrent['category'] : null
            );
            continue;
        }

        $pauseHashes[] = $hash;
        $pauseRows[] = [
            'hash' => $hash,
            'name' => (string) ($torrent['name'] ?? 'Unknown'),
            'category' => isset($torrent['category']) ? (string) $torrent['category'] : null,
        ];
    }

    $pausedCount = 0;
    if (count($pauseHashes) > 0) {
        $pauseResult = $qbitClient->pauseTorrents($pauseHashes);
        if ($pauseResult['success']) {
            foreach ($pauseRows as $row) {
                $storage->markTorrentProcessed($row['hash'], $row['name'], $row['category']);
                $pausedCount++;
            }
        } else {
            $payload = [
                'success' => false,
                'error' => $pauseResult['error'],
                'paused' => 0,
                'plex_refreshed' => 0,
            ];
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            exit(1);
        }
    }

    $plexRefreshed = 0;
    if ($pausedCount > 0) {
        $plexResult = $plexClient->refreshLibraries();
        if ($plexResult['success']) {
            $plexRefreshed = (int) ($plexResult['refreshed'] ?? 0);
        }
    }

    $payload = [
        'success' => true,
        'paused' => $pausedCount,
        'plex_refreshed' => $plexRefreshed,
    ];
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $throwable) {
    $payload = [
        'success' => false,
        'error' => $throwable->getMessage(),
        'paused' => 0,
        'plex_refreshed' => 0,
    ];
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(1);
}

