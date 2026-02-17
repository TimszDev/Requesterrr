(() => {
    const searchInput = document.getElementById('searchInput');
    const searchStatus = document.getElementById('searchStatus');
    const resultsGrid = document.getElementById('resultsGrid');

    const selectionPanel = document.getElementById('selectionPanel');
    const selectedPoster = document.getElementById('selectedPoster');
    const selectedTitle = document.getElementById('selectedTitle');
    const selectedMetaLine = document.getElementById('selectedMetaLine');
    const selectedOverview = document.getElementById('selectedOverview');

    const seasonGroup = document.getElementById('seasonGroup');
    const seasonList = document.getElementById('seasonList');
    const submitRequestButton = document.getElementById('submitRequestButton');
    const requestStatus = document.getElementById('requestStatus');

    const qualityButtons = Array.from(document.querySelectorAll('.quality-btn'));
    const seasonModeInputs = Array.from(document.querySelectorAll('input[name="seasonMode"]'));

    let searchTimeout = null;
    let activeResults = [];
    let selectedItem = null;
    let selectedDetails = null;
    let selectedQuality = '1080p';

    function setSearchStatus(message) {
        searchStatus.textContent = message || '';
    }

    function setRequestStatus(message, isError = false) {
        requestStatus.textContent = message || '';
        requestStatus.style.color = isError ? '#ffb4b4' : '#b9c7d1';
    }

    function buildCard(item, index) {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'result-card';
        card.dataset.index = String(index);

        const poster = item.poster
            ? `<img class="result-image" src="${item.poster}" alt="">`
            : `<div class="result-no-image">No image</div>`;

        const sourceTags = Array.isArray(item.sources) ? item.sources : [];
        const tagsHtml = sourceTags.map((source) => `<span class="tag">${source.toUpperCase()}</span>`).join('');

        card.innerHTML = `
            <div class="result-image-wrap">${poster}</div>
            <div class="result-body">
                <h3 class="result-title">${escapeHtml(item.title || 'Unknown title')}</h3>
                <p class="result-meta">
                    ${(item.year || 'Unknown year')} • ${(item.media_type || 'unknown').toUpperCase()}
                </p>
                <div class="tag-row">${tagsHtml}</div>
            </div>
        `;

        card.addEventListener('click', () => selectSearchItem(index));
        return card;
    }

    function renderResults(items) {
        activeResults = items || [];
        resultsGrid.innerHTML = '';
        if (activeResults.length === 0) {
            return;
        }

        activeResults.forEach((item, index) => {
            const card = buildCard(item, index);
            resultsGrid.appendChild(card);
        });
    }

    async function searchTitles(query) {
        if (query.trim().length < 2) {
            renderResults([]);
            setSearchStatus('Type at least 2 characters.');
            return;
        }

        setSearchStatus('Searching...');

        try {
            const response = await fetch(`./api/search.php?q=${encodeURIComponent(query)}&limit=18`);
            const payload = await response.json();

            if (!response.ok || !payload.success) {
                renderResults([]);
                setSearchStatus(payload.error || 'Search failed.');
                return;
            }

            renderResults(payload.results || []);
            setSearchStatus(`Found ${(payload.results || []).length} results.`);
        } catch (error) {
            renderResults([]);
            setSearchStatus('Search failed. Check server/API setup.');
        }
    }

    function debounceSearch() {
        const value = searchInput.value;
        if (searchTimeout !== null) {
            clearTimeout(searchTimeout);
        }

        searchTimeout = setTimeout(() => {
            searchTitles(value);
        }, 250);
    }

    function markSelectedCard(index) {
        Array.from(resultsGrid.querySelectorAll('.result-card')).forEach((card) => {
            const cardIndex = Number(card.dataset.index || '-1');
            card.classList.toggle('selected', cardIndex === index);
        });
    }

    async function selectSearchItem(index) {
        const item = activeResults[index];
        if (!item) {
            return;
        }

        markSelectedCard(index);
        selectedItem = item;
        selectedDetails = null;
        setRequestStatus('Loading title details...');
        selectionPanel.classList.remove('hidden');

        try {
            const response = await fetch('./api/details.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    media_type: item.media_type,
                    tmdb_id: item.tmdb_id,
                    imdb_id: item.imdb_id,
                    title: item.title,
                    year: item.year
                })
            });

            const payload = await response.json();
            if (!response.ok || !payload.success || !payload.item) {
                setRequestStatus(payload.error || 'Failed to resolve selected title.', true);
                return;
            }

            selectedDetails = payload.item;
            renderSelectionPanel(payload.item);
            setRequestStatus('Choose quality and submit request.');
        } catch (error) {
            setRequestStatus('Failed to resolve details.', true);
        }
    }

    function renderSelectionPanel(item) {
        selectedPoster.src = item.poster || '';
        selectedPoster.style.display = item.poster ? 'block' : 'none';

        selectedTitle.textContent = item.title || 'Unknown title';
        selectedMetaLine.textContent = `${(item.year || 'Unknown year')} • ${(item.media_type || 'unknown').toUpperCase()}`;
        selectedOverview.textContent = item.overview || 'No overview available.';

        const isTv = item.media_type === 'tv';
        seasonGroup.classList.toggle('hidden', !isTv);

        if (isTv) {
            renderSeasonOptions(item.seasons || []);
        } else {
            seasonList.innerHTML = '';
        }
    }

    function renderSeasonOptions(seasons) {
        seasonList.innerHTML = '';
        if (!Array.isArray(seasons) || seasons.length === 0) {
            seasonList.innerHTML = '<span class="status-line">No season data available.</span>';
            return;
        }

        seasons.forEach((season) => {
            const seasonNumber = Number(season.season_number || 0);
            const label = season.name || `Season ${seasonNumber}`;
            const episodeCount = Number(season.episode_count || 0);
            const pill = document.createElement('label');
            pill.className = 'season-pill';
            pill.innerHTML = `
                <input type="checkbox" data-season-number="${seasonNumber}" checked>
                <span>${escapeHtml(label)} (${episodeCount} eps)</span>
            `;
            seasonList.appendChild(pill);
        });

        syncSeasonInputState();
    }

    function selectedSeasonMode() {
        const selected = seasonModeInputs.find((input) => input.checked);
        return selected ? selected.value : 'all';
    }

    function syncSeasonInputState() {
        const mode = selectedSeasonMode();
        const disabled = mode !== 'custom';
        Array.from(seasonList.querySelectorAll('input[type="checkbox"]')).forEach((input) => {
            input.disabled = disabled;
        });
    }

    function getSelectedSeasons() {
        return Array.from(seasonList.querySelectorAll('input[type="checkbox"]'))
            .filter((input) => input.checked)
            .map((input) => Number(input.dataset.seasonNumber || '0'))
            .filter((n) => n > 0);
    }

    function setQuality(quality) {
        selectedQuality = quality;
        qualityButtons.forEach((button) => {
            button.classList.toggle('active', button.dataset.quality === quality);
        });
    }

    async function submitRequest() {
        if (!selectedDetails) {
            setRequestStatus('Select a title first.', true);
            return;
        }

        const payload = {
            media_type: selectedDetails.media_type,
            tmdb_id: selectedDetails.tmdb_id,
            imdb_id: selectedDetails.imdb_id,
            title: selectedDetails.title,
            year: selectedDetails.year,
            quality: selectedQuality
        };

        if (selectedDetails.media_type === 'tv') {
            const seasonMode = selectedSeasonMode();
            payload.season_mode = seasonMode;
            if (seasonMode === 'custom') {
                payload.seasons = getSelectedSeasons();
                if (payload.seasons.length === 0) {
                    setRequestStatus('Select at least one season or choose Entire series.', true);
                    return;
                }
            } else {
                payload.seasons = [];
            }
        }

        submitRequestButton.disabled = true;
        setRequestStatus('Sending request...');

        try {
            const response = await fetch('./api/request.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                setRequestStatus(result.error || 'Request failed.', true);
                return;
            }

            const message = result.data && result.data.message
                ? result.data.message
                : 'Request sent successfully.';
            setRequestStatus(message, false);
        } catch (error) {
            setRequestStatus('Request failed due to server/network issue.', true);
        } finally {
            submitRequestButton.disabled = false;
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    searchInput.addEventListener('input', debounceSearch);
    qualityButtons.forEach((button) => {
        button.addEventListener('click', () => setQuality(button.dataset.quality || '1080p'));
    });
    seasonModeInputs.forEach((input) => {
        input.addEventListener('change', syncSeasonInputState);
    });
    submitRequestButton.addEventListener('click', submitRequest);

    setSearchStatus('Start typing to search TMDB + IMDb.');
    setRequestStatus('');
    setQuality('1080p');
})();

