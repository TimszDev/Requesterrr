<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use Requesterrr\App;

$projectRoot = dirname(__DIR__);
$config = App::config($projectRoot);
$appName = $config->getString('APP_NAME', 'Requesterrr');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName, ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="./assets/styles.css">
</head>
<body>
    <main class="page-shell">
        <section class="search-shell">
            <h1 class="title"><?= htmlspecialchars($appName, ENT_QUOTES) ?></h1>
            <p class="subtitle">What show or movie are you looking for?</p>
            <div class="search-input-wrap">
                <input
                    id="searchInput"
                    type="text"
                    placeholder="Search title..."
                    autocomplete="off"
                    spellcheck="false"
                >
            </div>
            <p id="searchStatus" class="status-line">Start typing to search TMDB + IMDb.</p>
        </section>

        <section id="resultsSection" class="results-section">
            <div id="resultsGrid" class="results-grid"></div>
        </section>

        <section id="selectionPanel" class="selection-panel hidden">
            <div class="selection-header">
                <img id="selectedPoster" class="selected-poster" src="" alt="Poster">
                <div class="selected-meta">
                    <h2 id="selectedTitle">Selected title</h2>
                    <p id="selectedMetaLine"></p>
                    <p id="selectedOverview"></p>
                </div>
            </div>

            <div class="request-options">
                <div class="option-group">
                    <label>Quality</label>
                    <div class="quality-options">
                        <button class="quality-btn active" data-quality="1080p" type="button">1080p</button>
                        <button class="quality-btn" data-quality="4k" type="button">4k</button>
                    </div>
                </div>

                <div id="seasonGroup" class="option-group hidden">
                    <label>Seasons (TV only)</label>
                    <div class="season-mode-row">
                        <label class="season-mode">
                            <input type="radio" name="seasonMode" value="all" checked>
                            Entire series
                        </label>
                        <label class="season-mode">
                            <input type="radio" name="seasonMode" value="custom">
                            Select seasons
                        </label>
                    </div>
                    <div id="seasonList" class="season-list"></div>
                </div>

                <div class="actions-row">
                    <button id="submitRequestButton" type="button">Send Request</button>
                </div>
                <p id="requestStatus" class="status-line"></p>
            </div>
        </section>
    </main>

    <script>
        window.REQUESTERRR = {
            appName: <?= json_encode($appName, JSON_UNESCAPED_SLASHES) ?>
        };
    </script>
    <script src="./assets/app.js"></script>
</body>
</html>

