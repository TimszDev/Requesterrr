<?php
declare(strict_types=1);

namespace Requesterrr\Support;

use PDO;
use PDOException;

final class Storage
{
    private PDO $pdo;

    public function __construct(string $sqlitePath)
    {
        $directory = dirname($sqlitePath);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        $this->pdo = new PDO('sqlite:' . $sqlitePath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initialize();
    }

    private function initialize(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS request_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                media_type TEXT NOT NULL,
                title TEXT NOT NULL,
                release_year INTEGER NULL,
                tmdb_id INTEGER NULL,
                imdb_id TEXT NULL,
                tvdb_id INTEGER NULL,
                quality TEXT NOT NULL,
                season_selection TEXT NULL,
                target_client TEXT NOT NULL,
                status TEXT NOT NULL,
                response_json TEXT NULL,
                created_at TEXT NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS processed_torrents (
                torrent_hash TEXT PRIMARY KEY,
                torrent_name TEXT NOT NULL,
                torrent_category TEXT NULL,
                paused_at TEXT NOT NULL
            )'
        );
    }

    public function logRequest(array $requestData): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO request_logs
            (
                media_type, title, release_year, tmdb_id, imdb_id, tvdb_id,
                quality, season_selection, target_client, status, response_json, created_at
            )
            VALUES
            (
                :media_type, :title, :release_year, :tmdb_id, :imdb_id, :tvdb_id,
                :quality, :season_selection, :target_client, :status, :response_json, :created_at
            )'
        );

        $statement->execute([
            ':media_type' => (string) ($requestData['media_type'] ?? ''),
            ':title' => (string) ($requestData['title'] ?? ''),
            ':release_year' => (int) ($requestData['release_year'] ?? 0),
            ':tmdb_id' => isset($requestData['tmdb_id']) ? (int) $requestData['tmdb_id'] : null,
            ':imdb_id' => $requestData['imdb_id'] ?? null,
            ':tvdb_id' => isset($requestData['tvdb_id']) ? (int) $requestData['tvdb_id'] : null,
            ':quality' => (string) ($requestData['quality'] ?? ''),
            ':season_selection' => $requestData['season_selection'] ?? null,
            ':target_client' => (string) ($requestData['target_client'] ?? ''),
            ':status' => (string) ($requestData['status'] ?? 'unknown'),
            ':response_json' => $requestData['response_json'] ?? null,
            ':created_at' => gmdate('c'),
        ]);
    }

    public function wasTorrentProcessed(string $torrentHash): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM processed_torrents WHERE torrent_hash = :hash LIMIT 1'
        );
        $statement->execute([':hash' => $torrentHash]);
        return (bool) $statement->fetchColumn();
    }

    public function markTorrentProcessed(string $torrentHash, string $torrentName, ?string $category): void
    {
        $statement = $this->pdo->prepare(
            'INSERT OR REPLACE INTO processed_torrents
            (torrent_hash, torrent_name, torrent_category, paused_at)
            VALUES (:hash, :name, :category, :paused_at)'
        );
        $statement->execute([
            ':hash' => $torrentHash,
            ':name' => $torrentName,
            ':category' => $category,
            ':paused_at' => gmdate('c'),
        ]);
    }
}

