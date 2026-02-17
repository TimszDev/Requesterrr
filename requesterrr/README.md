# requesterrr

PHP webservice + single-page UI to:
- search TMDB + IMDb suggestions
- request movies (Radarr) and shows (Sonarr)
- choose quality (`1080p` / `4k`)
- choose entire series or specific seasons for TV
- rely on Sonarr/Radarr to pass to qBittorrent
- monitor qBittorrent completion, stop seeding, and refresh Plex libraries

## Folder Layout

- `public/index.php`: landing page UI
- `public/api/search.php`: live title search
- `public/api/details.php`: resolves selected title details
- `public/api/request.php`: sends request to Sonarr/Radarr
- `worker/process_downloads.php`: qBittorrent completion monitor + Plex refresh
- `src/*`: integrations, services, config/bootstrap
- `data/requesterrr.sqlite`: local request/torrent tracking storage

## Setup

1. Copy `.env.example` to `.env` and fill API keys/URLs/passwords.
2. Ensure Sonarr and Radarr are configured with qBittorrent as download client.
3. Serve `public` as web root.

### Run (PHP built-in server)

```powershell
cd requesterrr
php -S localhost:8090 -t public
```

Then open:

`http://localhost:8090`

## Worker (auto stop seeding + Plex refresh)

Run manually:

```powershell
cd requesterrr
php worker/process_downloads.php
```

Expected behavior:
- Finds completed torrents in qBittorrent.
- Pauses newly completed torrents (stops seeding).
- Marks them in SQLite so they are not reprocessed.
- Refreshes Plex sections defined by `PLEX_LIBRARY_SECTION_IDS`.

Set this script on a schedule (Task Scheduler / cron) every 1-5 minutes.

## API Notes

- Search endpoint combines TMDB + IMDb suggestion data.
- Requests are local-tracked first (SQLite) then sent to Sonarr/Radarr.
- TV requests require `tvdb_id` resolution from TMDB external IDs.

## Health Check

`GET /api/health.php` returns basic config readiness flags.

