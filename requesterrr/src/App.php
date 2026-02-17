<?php
declare(strict_types=1);

namespace Requesterrr;

use Requesterrr\Integrations\ImdbClient;
use Requesterrr\Integrations\PlexClient;
use Requesterrr\Integrations\QbittorrentClient;
use Requesterrr\Integrations\RadarrClient;
use Requesterrr\Integrations\SonarrClient;
use Requesterrr\Integrations\TmdbClient;
use Requesterrr\Services\MetadataService;
use Requesterrr\Services\RequestService;
use Requesterrr\Support\Config;
use Requesterrr\Support\Env;
use Requesterrr\Support\HttpClient;
use Requesterrr\Support\Storage;

final class App
{
    private static bool $booted = false;
    private static ?Config $config = null;
    private static ?HttpClient $httpClient = null;
    private static ?Storage $storage = null;
    private static ?TmdbClient $tmdbClient = null;
    private static ?ImdbClient $imdbClient = null;
    private static ?MetadataService $metadataService = null;
    private static ?RadarrClient $radarrClient = null;
    private static ?SonarrClient $sonarrClient = null;
    private static ?QbittorrentClient $qbittorrentClient = null;
    private static ?PlexClient $plexClient = null;
    private static ?RequestService $requestService = null;

    public static function boot(string $projectRoot): void
    {
        if (self::$booted) {
            return;
        }

        Env::load($projectRoot . '/.env');
        Env::load($projectRoot . '/.env.local');
        date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'UTC');

        self::$booted = true;
    }

    public static function config(string $projectRoot): Config
    {
        if (self::$config instanceof Config) {
            return self::$config;
        }

        $defaults = [
            'APP_NAME' => getenv('APP_NAME') ?: 'Requesterrr',
            'APP_DEBUG' => getenv('APP_DEBUG') ?: 'false',

            'TMDB_API_KEY' => getenv('TMDB_API_KEY') ?: '',
            'TMDB_BASE_URL' => getenv('TMDB_BASE_URL') ?: 'https://api.themoviedb.org/3',
            'TMDB_IMAGE_BASE_URL' => getenv('TMDB_IMAGE_BASE_URL') ?: 'https://image.tmdb.org/t/p',

            'IMDB_SUGGEST_BASE_URL' => getenv('IMDB_SUGGEST_BASE_URL') ?: 'https://v3.sg.media-imdb.com/suggestion',
            'IMDB_ENABLED' => getenv('IMDB_ENABLED') ?: 'true',

            'RADARR_URL' => getenv('RADARR_URL') ?: '',
            'RADARR_API_KEY' => getenv('RADARR_API_KEY') ?: '',
            'RADARR_ROOT_FOLDER' => getenv('RADARR_ROOT_FOLDER') ?: '',
            'RADARR_QUALITY_PROFILE_1080P' => getenv('RADARR_QUALITY_PROFILE_1080P') ?: '1',
            'RADARR_QUALITY_PROFILE_4K' => getenv('RADARR_QUALITY_PROFILE_4K') ?: '1',

            'SONARR_URL' => getenv('SONARR_URL') ?: '',
            'SONARR_API_KEY' => getenv('SONARR_API_KEY') ?: '',
            'SONARR_ROOT_FOLDER' => getenv('SONARR_ROOT_FOLDER') ?: '',
            'SONARR_QUALITY_PROFILE_1080P' => getenv('SONARR_QUALITY_PROFILE_1080P') ?: '1',
            'SONARR_QUALITY_PROFILE_4K' => getenv('SONARR_QUALITY_PROFILE_4K') ?: '1',
            'SONARR_LANGUAGE_PROFILE_ID' => getenv('SONARR_LANGUAGE_PROFILE_ID') ?: '1',

            'QBIT_URL' => getenv('QBIT_URL') ?: '',
            'QBIT_USERNAME' => getenv('QBIT_USERNAME') ?: '',
            'QBIT_PASSWORD' => getenv('QBIT_PASSWORD') ?: '',
            'QBIT_VERIFY_SSL' => getenv('QBIT_VERIFY_SSL') ?: 'false',

            'PLEX_URL' => getenv('PLEX_URL') ?: 'http://127.0.0.1:32400',
            'PLEX_TOKEN' => getenv('PLEX_TOKEN') ?: '',
            'PLEX_LIBRARY_SECTION_IDS' => getenv('PLEX_LIBRARY_SECTION_IDS') ?: '',

            'SQLITE_PATH' => getenv('SQLITE_PATH') ?: ($projectRoot . '/data/requesterrr.sqlite'),
            'QBIT_COOKIE_FILE' => getenv('QBIT_COOKIE_FILE') ?: ($projectRoot . '/data/qbittorrent_cookie.txt'),
        ];

        self::$config = new Config($defaults);
        return self::$config;
    }

    public static function httpClient(): HttpClient
    {
        if (!(self::$httpClient instanceof HttpClient)) {
            self::$httpClient = new HttpClient();
        }
        return self::$httpClient;
    }

    public static function storage(string $projectRoot): Storage
    {
        if (!(self::$storage instanceof Storage)) {
            self::$storage = new Storage(self::config($projectRoot)->getString('SQLITE_PATH'));
        }
        return self::$storage;
    }

    public static function tmdbClient(string $projectRoot): TmdbClient
    {
        if (!(self::$tmdbClient instanceof TmdbClient)) {
            $config = self::config($projectRoot);
            self::$tmdbClient = new TmdbClient(
                self::httpClient(),
                $config->getString('TMDB_API_KEY'),
                $config->getString('TMDB_BASE_URL'),
                $config->getString('TMDB_IMAGE_BASE_URL')
            );
        }
        return self::$tmdbClient;
    }

    public static function imdbClient(string $projectRoot): ImdbClient
    {
        if (!(self::$imdbClient instanceof ImdbClient)) {
            $config = self::config($projectRoot);
            self::$imdbClient = new ImdbClient(
                self::httpClient(),
                $config->getString('IMDB_SUGGEST_BASE_URL'),
                $config->getBool('IMDB_ENABLED', true)
            );
        }
        return self::$imdbClient;
    }

    public static function metadataService(string $projectRoot): MetadataService
    {
        if (!(self::$metadataService instanceof MetadataService)) {
            self::$metadataService = new MetadataService(
                self::tmdbClient($projectRoot),
                self::imdbClient($projectRoot)
            );
        }
        return self::$metadataService;
    }

    public static function radarrClient(string $projectRoot): RadarrClient
    {
        if (!(self::$radarrClient instanceof RadarrClient)) {
            $config = self::config($projectRoot);
            self::$radarrClient = new RadarrClient(
                self::httpClient(),
                $config->getString('RADARR_URL'),
                $config->getString('RADARR_API_KEY'),
                $config->getString('RADARR_ROOT_FOLDER'),
                $config->getInt('RADARR_QUALITY_PROFILE_1080P', 1),
                $config->getInt('RADARR_QUALITY_PROFILE_4K', 1)
            );
        }
        return self::$radarrClient;
    }

    public static function sonarrClient(string $projectRoot): SonarrClient
    {
        if (!(self::$sonarrClient instanceof SonarrClient)) {
            $config = self::config($projectRoot);
            self::$sonarrClient = new SonarrClient(
                self::httpClient(),
                $config->getString('SONARR_URL'),
                $config->getString('SONARR_API_KEY'),
                $config->getString('SONARR_ROOT_FOLDER'),
                $config->getInt('SONARR_QUALITY_PROFILE_1080P', 1),
                $config->getInt('SONARR_QUALITY_PROFILE_4K', 1),
                $config->getInt('SONARR_LANGUAGE_PROFILE_ID', 1)
            );
        }
        return self::$sonarrClient;
    }

    public static function qbittorrentClient(string $projectRoot): QbittorrentClient
    {
        if (!(self::$qbittorrentClient instanceof QbittorrentClient)) {
            $config = self::config($projectRoot);
            self::$qbittorrentClient = new QbittorrentClient(
                self::httpClient(),
                $config->getString('QBIT_URL'),
                $config->getString('QBIT_USERNAME'),
                $config->getString('QBIT_PASSWORD'),
                $config->getString('QBIT_COOKIE_FILE'),
                $config->getBool('QBIT_VERIFY_SSL', false)
            );
        }
        return self::$qbittorrentClient;
    }

    public static function plexClient(string $projectRoot): PlexClient
    {
        if (!(self::$plexClient instanceof PlexClient)) {
            $config = self::config($projectRoot);
            self::$plexClient = new PlexClient(
                self::httpClient(),
                $config->getString('PLEX_URL'),
                $config->getString('PLEX_TOKEN'),
                $config->getList('PLEX_LIBRARY_SECTION_IDS')
            );
        }
        return self::$plexClient;
    }

    public static function requestService(string $projectRoot): RequestService
    {
        if (!(self::$requestService instanceof RequestService)) {
            self::$requestService = new RequestService(
                self::config($projectRoot),
                self::metadataService($projectRoot),
                self::radarrClient($projectRoot),
                self::sonarrClient($projectRoot),
                self::storage($projectRoot)
            );
        }
        return self::$requestService;
    }
}

