document.addEventListener('DOMContentLoaded', () => {
    const grid = document.querySelector('[data-card-grid]');
    const searchInput = document.querySelector('[data-search-input]');
    const suggestionsBox = document.querySelector('[data-suggestions]');

    if (searchInput && suggestionsBox) {
        let suggestTimeout;

        const clearSuggestions = () => {
            suggestionsBox.innerHTML = '';
            suggestionsBox.hidden = true;
        };

        searchInput.addEventListener('input', () => {
            window.clearTimeout(suggestTimeout);

            const query = searchInput.value.trim();

            if (query.length < 2) {
                clearSuggestions();
                return;
            }

            suggestTimeout = window.setTimeout(async () => {
                let suggestions = [];

                try {
                    const response = await fetch(`${searchInput.dataset.suggestUrl}?q=${encodeURIComponent(query)}`);
                    suggestions = await response.json();
                } catch (error) {
                    clearSuggestions();
                    return;
                }

                if (!suggestions.length) {
                    clearSuggestions();
                    return;
                }

                suggestionsBox.innerHTML = '';
                suggestions.forEach((suggestion) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = suggestion;
                    suggestionsBox.append(button);
                });
                suggestionsBox.hidden = false;
            }, 250);
        });

        suggestionsBox.addEventListener('click', (event) => {
            if (!(event.target instanceof HTMLButtonElement)) {
                return;
            }

            searchInput.value = event.target.textContent ?? '';
            clearSuggestions();
            searchInput.form?.submit();
        });

        document.addEventListener('click', (event) => {
            if (!suggestionsBox.contains(event.target) && event.target !== searchInput) {
                clearSuggestions();
            }
        });
    }

    const priceLanguage = document.querySelector('[data-price-language]');
    const marketChart = document.querySelector('[data-market-chart]');
    const priceMessage = document.querySelector('[data-price-message]');

    const renderMarketChart = (chart) => {
        if (!marketChart) {
            return;
        }

        const line = marketChart.querySelector('[data-chart-line]');
        const pointLayer = marketChart.querySelector('[data-chart-points]');
        const labels = marketChart.querySelector('[data-chart-labels]');
        const points = (chart.points ?? [])
            .map((point) => ({
                ...point,
                value: Number(point.value),
            }))
            .filter((point) => Number.isFinite(point.value));

        if (!line || !pointLayer || !labels) {
            return;
        }

        pointLayer.innerHTML = '';
        labels.innerHTML = '';

        if (!chart.available || !points.length) {
            line.setAttribute('points', '');
            if (priceMessage) {
                priceMessage.textContent = chart.message ?? 'No price data is available for this language.';
            }
            marketChart.classList.add('is-empty');
            return;
        }

        marketChart.classList.remove('is-empty');
        if (priceMessage) {
            priceMessage.textContent = `Daily price history for ${chart.label}.`;
        }

        const values = points.map((point) => Number(point.value));
        const min = Math.min(...values);
        const max = Math.max(...values);
        const isFlat = max === min;
        const spread = isFlat ? 1 : max - min;
        const width = 560;
        const left = 60;
        const bottom = 220;
        const height = 180;
        const coordinates = points.map((point, index) => {
            const x = left + (points.length === 1 ? width / 2 : (index / (points.length - 1)) * width);
            const y = isFlat ? bottom - (height / 2) : bottom - ((point.value - min) / spread) * height;
            return { x, y, point };
        });

        if (coordinates.length === 1) {
            line.setAttribute('points', `${left},${coordinates[0].y} ${left + width},${coordinates[0].y}`);
        } else {
            line.setAttribute('points', coordinates.map(({ x, y }) => `${x},${y}`).join(' '));
        }

        coordinates.forEach(({ x, y, point }) => {
            const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
            circle.setAttribute('cx', String(x));
            circle.setAttribute('cy', String(y));
            circle.setAttribute('r', '6');
            pointLayer.append(circle);

            const label = document.createElement('span');
            const value = document.createElement('strong');
            const name = document.createElement('small');
            value.textContent = `${point.value} ${chart.currency}`;
            name.textContent = point.label;
            label.append(value, name);
            labels.append(label);
        });
    };

    if (marketChart?.dataset.initialChart) {
        try {
            renderMarketChart(JSON.parse(marketChart.dataset.initialChart));
        } catch (error) {
            marketChart.classList.add('is-empty');
        }
    }

    priceLanguage?.addEventListener('change', async () => {
        if (!priceLanguage.dataset.priceUrl) {
            return;
        }

        const url = new URL(priceLanguage.dataset.priceUrl, window.location.origin);
        url.searchParams.set('language', priceLanguage.value);

        if (priceMessage) {
            priceMessage.textContent = 'Loading price data...';
        }

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                },
            });
            renderMarketChart(await response.json());
        } catch (error) {
            if (priceMessage) {
                priceMessage.textContent = 'Unable to load price data.';
            }
        }
    });

    const setupLocalComments = (scopeSelector, formSelector, listSelector, prefix) => {
        const scope = document.querySelector(scopeSelector);

        if (!scope) {
            return;
        }

        const form = scope.querySelector(formSelector);
        const textarea = form?.querySelector('textarea');
        const list = scope.querySelector(listSelector);
        const key = `${prefix}:${scope.dataset.commentsScope ?? scope.dataset.topic ?? 'global'}`;

        if (!form || !textarea || !list) {
            return;
        }

        const readComments = () => JSON.parse(window.localStorage.getItem(key) ?? '[]');
        const writeComments = (comments) => window.localStorage.setItem(key, JSON.stringify(comments));
        const renderComments = () => {
            const comments = readComments();
            list.innerHTML = '';

            if (!comments.length) {
                const empty = document.createElement('p');
                empty.className = 'muted';
                empty.textContent = 'No comments yet.';
                list.append(empty);
                return;
            }

            comments.forEach((comment) => {
                const item = document.createElement('article');
                item.className = 'comment-item';
                const text = document.createElement('p');
                const meta = document.createElement('small');
                text.textContent = comment.text;
                meta.textContent = comment.date;
                item.append(text, meta);
                list.append(item);
            });
        };

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const text = textarea.value.trim();

            if (!text) {
                return;
            }

            writeComments([
                { text, date: new Date().toLocaleString() },
                ...readComments(),
            ]);
            textarea.value = '';
            renderComments();
        });

        renderComments();
    };

    setupLocalComments('[data-comments-scope]', '[data-card-comment-form]', '[data-card-comments]', 'card-comments');

    document.querySelectorAll('[data-topic]').forEach((topic) => {
        setupLocalComments(`[data-topic="${topic.dataset.topic}"]`, '[data-blog-form]', '[data-blog-comments]', 'blog-comments');
    });

    if (!grid) {
        return;
    }

    grid.classList.add('is-loaded');

    const colorFilter = document.querySelector('[data-card-filter="color"]');
    const rarityFilter = document.querySelector('[data-card-filter="rarity"]');
    const resetButton = document.querySelector('[data-card-filter-reset]');
    const cards = Array.from(grid.querySelectorAll('.card-item'));

    const applyFilters = () => {
        const color = colorFilter?.value ?? '';
        const rarity = rarityFilter?.value ?? '';

        cards.forEach((card) => {
            const matchesColor = !color || card.dataset.color === color;
            const matchesRarity = !rarity || card.dataset.rarity === rarity;

            card.classList.toggle('is-hidden', !matchesColor || !matchesRarity);
        });
    };

    colorFilter?.addEventListener('change', applyFilters);
    rarityFilter?.addEventListener('change', applyFilters);
    resetButton?.addEventListener('click', () => {
        if (colorFilter) {
            colorFilter.value = '';
        }

        if (rarityFilter) {
            rarityFilter.value = '';
        }

        applyFilters();
    });
});
