const initializeStructure = () => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const searchInput = document.querySelector('[data-search-input]');
    const suggestionsBox = document.querySelector('[data-suggestions]');
    const discussion = document.querySelector('[data-card-discussion]');
    const cardResults = document.querySelector('[data-card-results]');
    const carousels = Array.from(document.querySelectorAll('[data-card-carousel]'));
    const deckBuilder = document.querySelector('[data-deck-builder]');
    const siteHeader = document.querySelector('.site-header');
    const menuToggle = document.querySelector('[data-site-menu-toggle]');
    const siteMenu = document.querySelector('[data-site-menu]');
    const priceSwitcher = document.querySelector('[data-card-price-switcher]');

    document.body.classList.add('is-booted');

    if (!reducedMotion) {
        Array.from(document.querySelectorAll('.page > *')).forEach((element, index) => {
            if (!(element instanceof HTMLElement) || element.dataset.pageMotionReady === 'true') {
                return;
            }

            element.dataset.pageMotionReady = 'true';
            element.classList.add('page-motion-item');
            element.style.setProperty('--page-motion-delay', `${Math.min(index, 8) * 90}ms`);
        });

        if (document.body.dataset.pointerMotionReady !== 'true') {
            document.body.dataset.pointerMotionReady = 'true';
            let pointerFrame = null;
            document.addEventListener('pointermove', (event) => {
                if (pointerFrame !== null) {
                    return;
                }

                pointerFrame = window.requestAnimationFrame(() => {
                    document.documentElement.style.setProperty('--pointer-x', `${event.clientX}px`);
                    document.documentElement.style.setProperty('--pointer-y', `${event.clientY}px`);
                    pointerFrame = null;
                });
            });
        }

        const revealSelectors = [
            '.page > *',
            '.page section',
            '.page article',
            '.page h1',
            '.page h2',
            '.page h3',
            '.page p',
            '.page form',
            '.page label',
            '.page input',
            '.page select',
            '.page textarea',
            '.page button',
            '.page a.card-detail-link',
            '.page a.back-link',
            '.page .card-item',
            '.page .card-image',
            '.page .card-content > *',
            '.page .topic-card',
            '.page .topic-card > *',
            '.page .home-top-card',
            '.page .home-top-card > *',
            '.page .section-heading',
            '.page .section-heading > *',
            '.page .detail-list > div',
            '.page .card-tags span',
            '.page .language-button',
            '.page .card-price-language-select',
            '.page .comment-item',
            '.page .notification-item',
            '.page .info-page-section',
        ].join(',');
        const revealTargets = Array.from(document.querySelectorAll(revealSelectors))
            .filter((target, index, targets) => target instanceof HTMLElement && targets.indexOf(target) === index);

        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            });
        }, {
            threshold: 0.14,
            rootMargin: '0px 0px -8% 0px',
        });

        const revealParentRank = new Map();
        revealTargets.forEach((target, index) => {
            if (!(target instanceof HTMLElement) || target.dataset.revealReady === 'true') {
                return;
            }

            const parent = target.closest('section, article, form, .detail-summary, .detail-panel, .card-item, .topic-card, .home-top-card, .page') ?? document.body;
            const parentCount = revealParentRank.get(parent) ?? 0;
            revealParentRank.set(parent, parentCount + 1);

            target.dataset.revealReady = 'true';
            target.classList.add('reveal-on-scroll');
            target.style.setProperty('--reveal-delay', `${Math.min(parentCount, 12) * 70}ms`);
            revealObserver.observe(target);
        });
    }

    if (!reducedMotion) {
        Array.from(document.querySelectorAll('.detail-summary, .detail-panel, .home-hero, .collection-tools, .topic-card, .card-item')).forEach((element) => {
            if (!(element instanceof HTMLElement) || element.dataset.tiltReady === 'true') {
                return;
            }

            element.dataset.tiltReady = 'true';
            element.classList.add('tilt-ready');

            element.addEventListener('pointermove', (event) => {
                const rect = element.getBoundingClientRect();
                const x = ((event.clientX - rect.left) / rect.width - 0.5) * 2;
                const y = ((event.clientY - rect.top) / rect.height - 0.5) * 2;

                element.style.setProperty('--tilt-x', `${(-y * 5).toFixed(2)}deg`);
                element.style.setProperty('--tilt-y', `${(x * 6).toFixed(2)}deg`);
                element.style.setProperty('--lift', '-8px');
            });

            element.addEventListener('pointerleave', () => {
                element.style.removeProperty('--tilt-x');
                element.style.removeProperty('--tilt-y');
                element.style.removeProperty('--lift');
            });
        });

        Array.from(document.querySelectorAll('button, .card-detail-link, .back-link, .site-auth a, .site-nav a, .topic-card a, .card-content a')).forEach((element) => {
            if (!(element instanceof HTMLElement) || element.dataset.rippleReady === 'true') {
                return;
            }

            element.dataset.rippleReady = 'true';
            element.classList.add('js-ripple');
            element.addEventListener('pointerdown', (event) => {
                const rect = element.getBoundingClientRect();
                const ripple = document.createElement('span');
                const size = Math.max(rect.width, rect.height);

                ripple.className = 'ripple-dot';
                ripple.style.width = `${size}px`;
                ripple.style.height = `${size}px`;
                ripple.style.left = `${event.clientX - rect.left - size / 2}px`;
                ripple.style.top = `${event.clientY - rect.top - size / 2}px`;
                element.append(ripple);
                window.setTimeout(() => ripple.remove(), 650);
            });
        });
    }

    if (priceSwitcher && priceSwitcher.dataset.jsReady !== 'true') {
        priceSwitcher.dataset.jsReady = 'true';
        const priceDisplays = Array.from(document.querySelectorAll('[data-card-price-display]'));
        const priceChart = document.querySelector('[data-card-price-chart]');
        const rangeButtons = Array.from(document.querySelectorAll('[data-price-range-button]'));
        const emptyHistoryText = priceSwitcher.dataset.emptyHistory ?? priceChart?.dataset.emptyHistory ?? 'No price history yet.';
        const historyUrl = priceSwitcher.dataset.priceHistoryUrl ?? '';
        const pageLocale = document.documentElement.lang || undefined;
        let activeRange = priceSwitcher.dataset.priceHistoryRange ?? 'all';
        let priceHistory = [];

        try {
            priceHistory = JSON.parse(priceSwitcher.dataset.cardPriceHistory ?? '[]');
        } catch (error) {
            priceHistory = [];
        }

        const formatCurrency = (price) => `\u20ac${Number(price).toFixed(2)}`;

        const formatChartDate = (date, range) => {
            const parsedDate = new Date(`${date}T00:00:00`);

            if (Number.isNaN(parsedDate.getTime())) {
                return date;
            }

            const options = range === 'all' || range === '1y'
                ? { month: 'short', year: 'numeric' }
                : { month: 'short', day: 'numeric' };

            return new Intl.DateTimeFormat(pageLocale, options).format(parsedDate);
        };

        const setActiveRange = (range) => {
            activeRange = range;
            priceSwitcher.dataset.priceHistoryRange = range;
            rangeButtons.forEach((button) => {
                if (!(button instanceof HTMLButtonElement)) {
                    return;
                }

                const isActive = button.dataset.range === range;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        };

        const renderPriceChart = (languageKey) => {
            if (!(priceChart instanceof HTMLElement)) {
                return;
            }

            const history = priceHistory.find((entry) => entry.language_key === languageKey);
            const points = Array.isArray(history?.points)
                ? history.points
                    .map((point) => ({
                        ...point,
                        priceValue: Number(point.price),
                        dateValue: new Date(`${point.date}T00:00:00`).getTime(),
                    }))
                    .filter((point) => Number.isFinite(point.priceValue) && Number.isFinite(point.dateValue))
                    .sort((a, b) => a.dateValue - b.dateValue)
                : [];
            priceChart.innerHTML = '';

            if (!points.length) {
                const empty = document.createElement('p');
                empty.className = 'muted';
                empty.textContent = emptyHistoryText;
                priceChart.append(empty);
                return;
            }

            const svgNamespace = 'http://www.w3.org/2000/svg';
            const width = 960;
            const height = 420;
            const plotLeft = 54;
            const plotRight = 820;
            const plotTop = 34;
            const plotBottom = 342;
            const plotWidth = plotRight - plotLeft;
            const plotHeight = plotBottom - plotTop;
            const prices = points.map((point) => point.priceValue);
            const minPrice = Math.min(...prices);
            const maxPrice = Math.max(...prices);
            const minDate = Math.min(...points.map((point) => point.dateValue));
            const maxDate = Math.max(...points.map((point) => point.dateValue));
            const rawRange = maxPrice - minPrice;
            const pricePadding = rawRange === 0 ? Math.max(maxPrice * 0.06, 1) : rawRange * 0.18;
            const chartMin = Math.max(0, minPrice - pricePadding);
            const chartMax = maxPrice + pricePadding;
            const chartRange = Math.max(chartMax - chartMin, 1);
            const dateRange = Math.max(maxDate - minDate, 1);
            const xForPoint = (point) => points.length === 1
                ? plotLeft + plotWidth / 2
                : plotLeft + ((point.dateValue - minDate) / dateRange) * plotWidth;
            const yForPrice = (price) => plotBottom - ((price - chartMin) / chartRange) * plotHeight;
            const pathData = points.map((point, index) => {
                const command = index === 0 ? 'M' : 'L';
                return `${command} ${xForPoint(point).toFixed(2)} ${yForPrice(point.priceValue).toFixed(2)}`;
            }).join(' ');
            const lastPoint = points[points.length - 1];
            const lastX = xForPoint(lastPoint);
            const lastY = yForPrice(lastPoint.priceValue);

            const svg = document.createElementNS(svgNamespace, 'svg');
            svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
            svg.setAttribute('role', 'img');
            svg.setAttribute('aria-label', `${history.language_label} price history`);

            const gridGroup = document.createElementNS(svgNamespace, 'g');
            gridGroup.setAttribute('class', 'price-chart-grid');

            for (let index = 0; index <= 4; index++) {
                const ratio = index / 4;
                const y = plotTop + ratio * plotHeight;
                const price = chartMax - ratio * chartRange;
                const line = document.createElementNS(svgNamespace, 'line');
                line.setAttribute('x1', String(plotLeft));
                line.setAttribute('x2', String(plotRight));
                line.setAttribute('y1', y.toFixed(2));
                line.setAttribute('y2', y.toFixed(2));
                gridGroup.append(line);

                const label = document.createElementNS(svgNamespace, 'text');
                label.setAttribute('x', String(plotRight + 42));
                label.setAttribute('y', String(y + 6));
                label.setAttribute('class', 'price-chart-side-label');
                label.textContent = formatCurrency(price);
                gridGroup.append(label);
            }

            const verticalTicks = Math.min(6, Math.max(1, points.length - 1));

            for (let index = 0; index <= verticalTicks; index++) {
                const ratio = verticalTicks === 0 ? 0 : index / verticalTicks;
                const x = plotLeft + ratio * plotWidth;
                const line = document.createElementNS(svgNamespace, 'line');
                line.setAttribute('x1', x.toFixed(2));
                line.setAttribute('x2', x.toFixed(2));
                line.setAttribute('y1', String(plotTop));
                line.setAttribute('y2', String(plotBottom));
                gridGroup.append(line);
            }

            svg.append(gridGroup);

            const axis = document.createElementNS(svgNamespace, 'path');
            axis.setAttribute('d', `M ${plotLeft} ${plotTop} V ${plotBottom} H ${plotRight}`);
            axis.setAttribute('class', 'price-chart-axis');
            svg.append(axis);

            const currentLine = document.createElementNS(svgNamespace, 'line');
            currentLine.setAttribute('x1', String(plotLeft));
            currentLine.setAttribute('x2', String(plotRight));
            currentLine.setAttribute('y1', lastY.toFixed(2));
            currentLine.setAttribute('y2', lastY.toFixed(2));
            currentLine.setAttribute('class', 'price-chart-current-line');
            svg.append(currentLine);

            const line = document.createElementNS(svgNamespace, 'path');
            line.setAttribute('d', pathData);
            line.setAttribute('class', 'price-chart-line');
            line.setAttribute('pathLength', '1');
            svg.append(line);

            points.forEach((point) => {
                const dot = document.createElementNS(svgNamespace, 'circle');
                dot.setAttribute('cx', xForPoint(point).toFixed(2));
                dot.setAttribute('cy', yForPrice(point.priceValue).toFixed(2));
                dot.setAttribute('r', points.length > 18 ? '2.2' : '3.4');
                dot.setAttribute('class', 'price-chart-dot');

                const title = document.createElementNS(svgNamespace, 'title');
                title.textContent = `${point.date}: ${formatCurrency(point.priceValue)}`;
                dot.append(title);
                svg.append(dot);
            });

            const currentBadge = document.createElementNS(svgNamespace, 'g');
            currentBadge.setAttribute('class', 'price-chart-current-badge');
            const badgeText = formatCurrency(lastPoint.priceValue);
            const badgeWidth = Math.max(86, badgeText.length * 10 + 28);
            const badgeHeight = 32;
            const badge = document.createElementNS(svgNamespace, 'rect');
            badge.setAttribute('x', String(plotRight + 28));
            badge.setAttribute('y', String(Math.max(plotTop, Math.min(plotBottom - badgeHeight, lastY - badgeHeight / 2))));
            badge.setAttribute('width', String(badgeWidth));
            badge.setAttribute('height', String(badgeHeight));
            badge.setAttribute('rx', '3');
            currentBadge.append(badge);

            const badgeLabel = document.createElementNS(svgNamespace, 'text');
            badgeLabel.setAttribute('x', String(plotRight + 44));
            badgeLabel.setAttribute('y', String(Number(badge.getAttribute('y')) + 21));
            badgeLabel.textContent = badgeText;
            currentBadge.append(badgeLabel);
            svg.append(currentBadge);

            const firstDate = document.createElementNS(svgNamespace, 'text');
            firstDate.setAttribute('x', String(plotLeft));
            firstDate.setAttribute('y', String(height - 30));
            firstDate.setAttribute('class', 'price-chart-date');
            firstDate.textContent = formatChartDate(points[0].date, activeRange);
            svg.append(firstDate);

            const lastDate = document.createElementNS(svgNamespace, 'text');
            lastDate.setAttribute('x', String(plotRight));
            lastDate.setAttribute('y', String(height - 30));
            lastDate.setAttribute('text-anchor', 'end');
            lastDate.setAttribute('class', 'price-chart-date');
            lastDate.textContent = formatChartDate(lastPoint.date, activeRange);
            svg.append(lastDate);

            priceChart.append(svg);
        };

        const fetchPriceHistory = async (range) => {
            if (!historyUrl) {
                setActiveRange(range);
                renderPriceChart(priceSwitcher.value);
                return;
            }

            if (priceChart instanceof HTMLElement) {
                priceChart.classList.add('is-loading');
            }

            try {
                const url = new URL(historyUrl, window.location.origin);
                url.searchParams.set('range', range);

                const response = await fetch(url.toString(), {
                    headers: { Accept: 'application/json' },
                });
                const payload = await response.json();

                if (!response.ok || !Array.isArray(payload.history)) {
                    return;
                }

                priceHistory = payload.history;
                setActiveRange(typeof payload.range === 'string' ? payload.range : range);
                renderPriceChart(priceSwitcher.value);
            } catch (error) {
                renderPriceChart(priceSwitcher.value);
            } finally {
                if (priceChart instanceof HTMLElement) {
                    priceChart.classList.remove('is-loading');
                }
            }
        };

        const updateSelectedPrice = () => {
            const selectedOption = priceSwitcher.selectedOptions?.[0];

            if (!selectedOption) {
                return;
            }

            priceDisplays.forEach((display) => {
                display.textContent = selectedOption.dataset.price ?? '';
            });

            renderPriceChart(selectedOption.value);
        };

        priceSwitcher.addEventListener('change', updateSelectedPrice);
        rangeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const range = button instanceof HTMLElement ? button.dataset.range ?? 'all' : 'all';
                fetchPriceHistory(range);
            });
        });
        setActiveRange(activeRange);
        updateSelectedPrice();
    }

    if (siteHeader && menuToggle && siteMenu && menuToggle.dataset.jsReady !== 'true') {
        menuToggle.dataset.jsReady = 'true';

        const setMenuOpen = (isOpen) => {
            siteHeader.classList.toggle('is-menu-open', isOpen);
            menuToggle.classList.toggle('is-open', isOpen);
            menuToggle.setAttribute('aria-expanded', String(isOpen));
        };

        menuToggle.addEventListener('click', () => {
            setMenuOpen(!siteHeader.classList.contains('is-menu-open'));
        });

        siteMenu.addEventListener('click', (event) => {
            if (event.target instanceof HTMLAnchorElement) {
                setMenuOpen(false);
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setMenuOpen(false);
            }
        });

        window.addEventListener('resize', () => {
            if (window.matchMedia('(min-width: 901px)').matches) {
                setMenuOpen(false);
            }
        });
    }

    if (searchInput && suggestionsBox && searchInput.dataset.jsReady !== 'true') {
        searchInput.dataset.jsReady = 'true';
        let suggestTimeout;

        const clearSuggestions = () => {
            suggestionsBox.innerHTML = '';
            suggestionsBox.hidden = true;
        };

        const renderSuggestions = (suggestions, query) => {
            suggestionsBox.innerHTML = '';

            const list = document.createElement('div');
            list.className = 'suggestions-list';

            suggestions.forEach((suggestion) => {
                const label = typeof suggestion === 'string' ? suggestion : suggestion.label;
                const url = typeof suggestion === 'string' ? '' : suggestion.url;

                if (!label) {
                    return;
                }

                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = label;

                if (url) {
                    button.dataset.url = url;
                }

                list.append(button);
            });

            const footer = document.createElement('div');
            footer.className = 'suggestions-footer';

            const viewAllLink = document.createElement('a');
            viewAllLink.className = 'suggestions-view-all';
            viewAllLink.href = `${searchInput.form?.action ?? '/cards'}?q=${encodeURIComponent(query)}`;
            viewAllLink.textContent = searchInput.dataset.viewAllLabel ?? 'View all results';

            footer.append(viewAllLink);
            suggestionsBox.append(list, footer);
            suggestionsBox.hidden = false;
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

                renderSuggestions(suggestions, query);
            }, 250);
        });

        suggestionsBox.addEventListener('click', (event) => {
            if (!(event.target instanceof HTMLButtonElement)) {
                return;
            }

            if (event.target.dataset.url) {
                window.location.href = event.target.dataset.url;
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

    if (deckBuilder && deckBuilder.dataset.jsReady !== 'true') {
        deckBuilder.dataset.jsReady = 'true';
        const search = deckBuilder.querySelector('[data-deck-card-search]');
        const collectionSelect = deckBuilder.querySelector('[data-deck-card-collection]');
        const quantityInput = deckBuilder.querySelector('[data-deck-card-quantity]');
        const results = deckBuilder.querySelector('[data-deck-card-results]');
        const list = deckBuilder.querySelector('[data-deck-card-list]');
        const searchUrl = deckBuilder.dataset.searchUrl;
        const addLabel = deckBuilder.dataset.addLabel ?? 'Add';
        const removeLabel = deckBuilder.dataset.removeLabel ?? 'Remove';
        const emptyText = deckBuilder.dataset.emptyText ?? 'No cards added yet.';
        const noResultsText = deckBuilder.dataset.noResultsText ?? 'No cards found.';
        let searchTimeout = null;
        let selectedCards = [];

        const selectedQuantity = () => {
            if (!(quantityInput instanceof HTMLInputElement)) {
                return 1;
            }

            return Math.max(1, Math.min(99, Number.parseInt(quantityInput.value || '1', 10) || 1));
        };

        const selectedCollection = () => collectionSelect instanceof HTMLSelectElement ? collectionSelect.value : '';

        const renderSelectedCards = () => {
            if (!(list instanceof HTMLElement)) {
                return;
            }

            list.innerHTML = '';

            if (!selectedCards.length) {
                const empty = document.createElement('p');
                empty.className = 'muted';
                empty.textContent = emptyText;
                list.append(empty);
                return;
            }

            selectedCards.forEach((card, index) => {
                const item = document.createElement('article');
                item.className = 'deck-builder-selected-card';

                if (card.image) {
                    const image = document.createElement('img');
                    image.src = card.image;
                    image.alt = card.name;
                    image.loading = 'lazy';
                    item.append(image);
                }

                const content = document.createElement('div');
                const title = document.createElement('h3');
                const meta = document.createElement('p');
                title.textContent = card.name;
                meta.className = 'deck-meta';
                meta.textContent = [card.cardNumber, card.rarity, card.set].filter(Boolean).join(' - ');
                content.append(title, meta);

                const quantity = document.createElement('input');
                quantity.type = 'number';
                quantity.min = '1';
                quantity.max = '99';
                quantity.value = String(card.quantity);
                quantity.setAttribute('aria-label', 'Quantity');
                quantity.addEventListener('change', () => {
                    card.quantity = Math.max(1, Math.min(99, Number.parseInt(quantity.value || '1', 10) || 1));
                    renderSelectedCards();
                });

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.textContent = removeLabel;
                remove.addEventListener('click', () => {
                    selectedCards = selectedCards.filter((_, cardIndex) => cardIndex !== index);
                    renderSelectedCards();
                });

                const cardIdInput = document.createElement('input');
                cardIdInput.type = 'hidden';
                cardIdInput.name = `deck_cards[${index}][card_id]`;
                cardIdInput.value = String(card.id);

                const quantityHidden = document.createElement('input');
                quantityHidden.type = 'hidden';
                quantityHidden.name = `deck_cards[${index}][quantity]`;
                quantityHidden.value = String(card.quantity);

                item.append(content, quantity, remove, cardIdInput, quantityHidden);
                list.append(item);
            });
        };

        const addCard = (card) => {
            const existing = selectedCards.find((selectedCard) => selectedCard.id === card.id);

            if (existing) {
                existing.quantity = Math.min(99, existing.quantity + selectedQuantity());
            } else {
                selectedCards.push({
                    ...card,
                    quantity: selectedQuantity(),
                });
            }

            renderSelectedCards();
        };

        const renderResults = (cards) => {
            if (!(results instanceof HTMLElement)) {
                return;
            }

            results.innerHTML = '';

            if (!cards.length) {
                const empty = document.createElement('p');
                empty.className = 'muted';
                empty.textContent = noResultsText;
                results.append(empty);
                return;
            }

            cards.forEach((card) => {
                const item = document.createElement('article');
                item.className = 'deck-builder-result-card';

                if (card.image) {
                    const image = document.createElement('img');
                    image.src = card.image;
                    image.alt = card.name;
                    image.loading = 'lazy';
                    item.append(image);
                }

                const content = document.createElement('div');
                const title = document.createElement('h3');
                const meta = document.createElement('p');
                title.textContent = card.name;
                meta.className = 'deck-meta';
                meta.textContent = [card.cardNumber, card.rarity, card.set].filter(Boolean).join(' - ');
                content.append(title, meta);

                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = addLabel;
                button.addEventListener('click', () => addCard(card));

                item.append(content, button);
                results.append(item);
            });
        };

        const searchCards = () => {
            window.clearTimeout(searchTimeout);
            const query = search instanceof HTMLInputElement ? search.value.trim() : '';

            if (!searchUrl || query.length < 2) {
                results && (results.innerHTML = '');
                return;
            }

            searchTimeout = window.setTimeout(async () => {
                try {
                    const url = new URL(searchUrl, window.location.origin);
                    url.searchParams.set('q', query);

                    if (selectedCollection() !== '') {
                        url.searchParams.set('collection', selectedCollection());
                    }

                    const response = await fetch(url.toString(), {
                        headers: { Accept: 'application/json' },
                    });
                    const cards = await response.json();

                    if (!response.ok || !Array.isArray(cards)) {
                        renderResults([]);
                        return;
                    }

                    renderResults(cards);
                } catch (error) {
                    renderResults([]);
                }
            }, 220);
        };

        search?.addEventListener('input', searchCards);
        collectionSelect?.addEventListener('change', searchCards);

        renderSelectedCards();
    }

    carousels.forEach((carousel) => {
        if (carousel.dataset.jsReady === 'true') {
            return;
        }

        carousel.dataset.jsReady = 'true';
        const track = carousel.querySelector('[data-card-carousel-track]');
        const slides = Array.from(carousel.querySelectorAll('.card-carousel-slide'));
        const usesFlatSlides = carousel.querySelector('.card-carousel-price') !== null || carousel.classList.contains('card-carousel-recent');
        const pixelRatio = Math.max(1, window.devicePixelRatio || 1);

        if (!(track instanceof HTMLElement) || slides.length <= 1) {
            return;
        }

        slides.forEach((slide) => {
            const clone = slide.cloneNode(true);
            clone.setAttribute('aria-hidden', 'true');
            clone.dataset.carouselClone = 'true';
            clone.querySelectorAll('a, button, input, textarea, select').forEach((element) => {
                element.setAttribute('tabindex', '-1');
            });
            track.append(clone);
        });

        let offset = 0;
        let lastFrame = null;
        let isPaused = false;
        let pointerStart = null;

        const slideStep = () => {
            const currentSlides = Array.from(track.querySelectorAll('.card-carousel-slide'));
            const firstSlide = currentSlides[0];
            const secondSlide = currentSlides[1];

            if (firstSlide instanceof HTMLElement && secondSlide instanceof HTMLElement) {
                return secondSlide.offsetLeft - firstSlide.offsetLeft;
            }

            if (firstSlide instanceof HTMLElement) {
                const style = window.getComputedStyle(track);
                const gap = Number.parseFloat(style.columnGap || style.gap || '0');

                return firstSlide.offsetWidth + (Number.isFinite(gap) ? gap : 0);
            }

            return 0;
        };

        const recyclePassedSlides = () => {
            let step = slideStep();

            while (step > 0 && offset >= step) {
                offset -= step;
                const firstSlide = track.querySelector('.card-carousel-slide');

                if (!(firstSlide instanceof HTMLElement)) {
                    return;
                }

                track.append(firstSlide);
                step = slideStep();
            }
        };

        const updateDepth = () => {
            if (usesFlatSlides) {
                Array.from(track.querySelectorAll('.card-carousel-slide')).forEach((slide) => {
                    if (!(slide instanceof HTMLElement)) {
                        return;
                    }

                    slide.style.opacity = '1';
                    slide.style.zIndex = '';
                    slide.style.transform = 'translate3d(0, 0, 0)';
                });

                return;
            }

            const carouselRect = carousel.getBoundingClientRect();
            const center = carouselRect.left + carouselRect.width / 2;

            Array.from(track.querySelectorAll('.card-carousel-slide')).forEach((slide) => {
                if (!(slide instanceof HTMLElement)) {
                    return;
                }

                const slideRect = slide.getBoundingClientRect();
                const slideCenter = slideRect.left + slideRect.width / 2;
                const distance = Math.min(Math.abs(slideCenter - center) / (carouselRect.width / 2), 1);
                const scale = 1 - distance * 0.16;

                slide.style.opacity = String(1 - distance * 0.34);
                slide.style.zIndex = String(Math.round((1 - distance) * 10));
                slide.style.transform = `translateZ(-${distance * 90}px) scale(${scale})`;
            });
        };

        const move = (timestamp) => {
            if (lastFrame === null) {
                lastFrame = timestamp;
            }

            const elapsed = timestamp - lastFrame;
            lastFrame = timestamp;

            if (!isPaused) {
                offset += elapsed * 0.035;
                recyclePassedSlides();
                const renderedOffset = Math.round(offset * pixelRatio) / pixelRatio;
                track.style.setProperty('--carousel-offset', `-${renderedOffset}px`);
            }

            updateDepth();
            window.requestAnimationFrame(move);
        };

        const pause = () => {
            isPaused = true;
        };

        const resume = () => {
            isPaused = false;
            lastFrame = null;
        };

        carousel.addEventListener('mouseenter', pause);
        carousel.addEventListener('mouseleave', resume);
        carousel.addEventListener('focusin', pause);
        carousel.addEventListener('focusout', resume);
        carousel.addEventListener('pointerdown', (event) => {
            pause();
            pointerStart = {
                x: event.clientX,
                y: event.clientY,
            };
        });
        carousel.addEventListener('pointerup', (event) => {
            const slide = event.target instanceof Element ? event.target.closest('.card-carousel-slide') : null;
            const link = slide?.querySelector('.card-carousel-link');

            if (!(link instanceof HTMLAnchorElement) || event.target instanceof HTMLAnchorElement) {
                pointerStart = null;
                return;
            }

            const moved = pointerStart
                ? Math.hypot(event.clientX - pointerStart.x, event.clientY - pointerStart.y)
                : 0;
            pointerStart = null;

            if (moved <= 8) {
                window.location.href = link.href;
            }
        });
        window.addEventListener('resize', () => {
            const step = slideStep();
            offset = step > 0 ? offset % step : 0;
            const renderedOffset = Math.round(offset * pixelRatio) / pixelRatio;
            track.style.setProperty('--carousel-offset', `-${renderedOffset}px`);
            updateDepth();
        });

        window.requestAnimationFrame(move);
    });

    if (discussion && discussion.dataset.jsReady !== 'true') {
        discussion.dataset.jsReady = 'true';
        const commentsUrl = discussion.dataset.commentsUrl;
        const typeSelect = discussion.querySelector('[data-card-discussion-type]');
        const typeInput = discussion.querySelector('[data-card-discussion-type-input]');
        const languageInput = discussion.querySelector('[data-card-discussion-language-input]');
        const status = discussion.querySelector('[data-card-discussion-status]');
        const form = discussion.querySelector('[data-card-discussion-form]');
        const list = discussion.querySelector('[data-card-comments]');
        const languageButtons = Array.from(discussion.querySelectorAll('[data-card-language-button]'));
        const noCommentsText = discussion.dataset.noCommentsText ?? 'No comments yet for this topic and language.';
        const statusLoadingTemplate = discussion.dataset.statusLoading ?? 'Loading __TOPIC__ comments for __LANGUAGE__...';
        const statusShowingTemplate = discussion.dataset.statusShowing ?? 'Showing __TOPIC__ comments for __LANGUAGE__.';
        const statusPostingTemplate = discussion.dataset.statusPosting ?? 'Posting in __TOPIC__ / __LANGUAGE__...';
        const loadErrorText = discussion.dataset.errorLoad ?? 'Unable to load comments.';
        const postErrorText = discussion.dataset.errorPost ?? 'Unable to post comment.';
        const replyCommentLabel = discussion.dataset.replyCommentLabel ?? 'Reply to this comment';
        const replyAnswerLabel = discussion.dataset.replyAnswerLabel ?? 'Reply to this answer';
        const replyPlaceholderTemplate = discussion.dataset.replyPlaceholder ?? 'Reply to __AUTHOR__';
        const replySubmitText = discussion.dataset.replySubmit ?? 'Post reply';
        const repliedToLabel = discussion.dataset.repliedToLabel ?? 'replied to';
        const editLabel = discussion.dataset.editLabel ?? 'Modify';
        const deleteLabel = discussion.dataset.deleteLabel ?? 'Delete';
        const saveLabel = discussion.dataset.saveLabel ?? 'Save';
        const editPlaceholder = discussion.dataset.editPlaceholder ?? 'Update your comment';
        const deleteConfirm = discussion.dataset.deleteConfirm ?? 'Delete this comment?';

        let currentDiscussion = discussion.dataset.defaultDiscussion ?? 'trading';
        let currentLanguage = discussion.dataset.defaultLanguage ?? 'english';
        let requestSequence = 0;

        const discussionLabel = () => typeSelect?.selectedOptions[0]?.textContent?.trim() ?? 'Trading';
        const languageLabel = () => languageButtons.find((button) => button.dataset.language === currentLanguage)?.textContent?.trim() ?? currentLanguage;
        const renderStatus = (template) => template
            .replaceAll('__TOPIC__', discussionLabel())
            .replaceAll('__LANGUAGE__', languageLabel());
        const commentMeta = (comment) => {
            if (comment.parentAuthorName) {
                return `${comment.authorName} ${repliedToLabel} ${comment.parentAuthorName} - ${comment.createdAt}`;
            }

            return `${comment.authorName} - ${comment.createdAt}`;
        };

        const setLanguageButtons = () => {
            languageButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.language === currentLanguage);
            });
        };

        const renderComments = (comments) => {
            if (!list) {
                return;
            }

            list.innerHTML = '';

            if (!Array.isArray(comments) || !comments.length) {
                const empty = document.createElement('p');
                empty.className = 'muted';
                empty.textContent = noCommentsText;
                list.append(empty);
                return;
            }

            const renderBranch = (branch, target, depth = 1) => {
                const branchList = document.createElement('div');
                branchList.className = 'comment-list comment-list-nested';

                branch.forEach((comment) => {
                    const item = document.createElement('article');
                    item.className = 'comment-item';

                    const text = document.createElement('p');
                    const meta = document.createElement('small');
                    text.textContent = comment.content;
                    meta.textContent = commentMeta(comment);
                    item.append(text, meta, createManageBox(comment), createReplyBox(comment, depth));

                    if (Array.isArray(comment.children) && comment.children.length) {
                        renderBranch(comment.children, item, depth + 1);
                    }

                    branchList.append(item);
                });

                target.append(branchList);
            };

            const createReplyBox = (comment, depth = 0) => {
                const details = document.createElement('details');
                details.className = 'comment-reply-box';

                const summary = document.createElement('summary');
                summary.textContent = depth === 0 ? replyCommentLabel : replyAnswerLabel;

                const replyForm = document.createElement('form');
                replyForm.className = 'comment-form comment-form-inline';
                replyForm.dataset.cardReplyForm = 'true';

                const token = form?.querySelector('input[name="_token"]');
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = '_token';
                tokenInput.value = token instanceof HTMLInputElement ? token.value : '';

                const parentInput = document.createElement('input');
                parentInput.type = 'hidden';
                parentInput.name = 'parent_id';
                parentInput.value = String(comment.id ?? '');

                const label = document.createElement('label');
                label.textContent = '';

                const textarea = document.createElement('textarea');
                textarea.name = 'content';
                textarea.rows = 3;
                textarea.maxLength = 2000;
                textarea.minLength = 3;
                textarea.required = true;
                textarea.placeholder = replyPlaceholderTemplate.replaceAll('__AUTHOR__', comment.authorName ?? '');

                const button = document.createElement('button');
                button.type = 'submit';
                button.textContent = replySubmitText;

                label.append(textarea);
                replyForm.append(tokenInput, parentInput, label, button);
                details.append(summary, replyForm);

                return details;
            };

            const createManageBox = (comment) => {
                const actions = document.createElement('div');
                actions.className = 'comment-actions';

                if (!comment.canManage) {
                    return actions;
                }

                const editDetails = document.createElement('details');
                editDetails.className = 'comment-edit-box';

                const editSummary = document.createElement('summary');
                editSummary.textContent = editLabel;

                const editForm = document.createElement('form');
                editForm.className = 'comment-form comment-form-inline';
                editForm.dataset.cardManageForm = 'true';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'edit';

                const commentInput = document.createElement('input');
                commentInput.type = 'hidden';
                commentInput.name = 'comment_id';
                commentInput.value = String(comment.id ?? '');

                const token = form?.querySelector('input[name="_token"]');
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = '_token';
                tokenInput.value = token instanceof HTMLInputElement ? token.value : '';

                const label = document.createElement('label');
                const textarea = document.createElement('textarea');
                textarea.name = 'content';
                textarea.rows = 3;
                textarea.maxLength = 2000;
                textarea.minLength = 3;
                textarea.required = true;
                textarea.placeholder = editPlaceholder;
                textarea.value = comment.content ?? '';

                const saveButton = document.createElement('button');
                saveButton.type = 'submit';
                saveButton.textContent = saveLabel;

                label.append(textarea);
                editForm.append(actionInput, commentInput, tokenInput, label, saveButton);
                editDetails.append(editSummary, editForm);

                const deleteForm = document.createElement('form');
                deleteForm.dataset.cardManageForm = 'true';

                const deleteActionInput = document.createElement('input');
                deleteActionInput.type = 'hidden';
                deleteActionInput.name = 'action';
                deleteActionInput.value = 'delete';

                const deleteCommentInput = document.createElement('input');
                deleteCommentInput.type = 'hidden';
                deleteCommentInput.name = 'comment_id';
                deleteCommentInput.value = String(comment.id ?? '');

                const deleteTokenInput = document.createElement('input');
                deleteTokenInput.type = 'hidden';
                deleteTokenInput.name = '_token';
                deleteTokenInput.value = token instanceof HTMLInputElement ? token.value : '';

                const deleteButton = document.createElement('button');
                deleteButton.className = 'comment-danger-button';
                deleteButton.type = 'submit';
                deleteButton.textContent = deleteLabel;

                deleteForm.append(deleteActionInput, deleteCommentInput, deleteTokenInput, deleteButton);
                actions.append(editDetails, deleteForm);

                return actions;
            };

            comments.forEach((comment) => {
                const item = document.createElement('article');
                item.className = 'comment-item';
                const text = document.createElement('p');
                const meta = document.createElement('small');
                text.textContent = comment.content;
                meta.textContent = commentMeta(comment);
                item.append(text, meta, createManageBox(comment), createReplyBox(comment, 0));

                if (Array.isArray(comment.children) && comment.children.length) {
                    renderBranch(comment.children, item, 1);
                }

                list.append(item);
            });
        };

        const setStatus = (message) => {
            if (status) {
                status.textContent = message;
            }
        };

        const syncHiddenInputs = () => {
            if (typeInput) {
                typeInput.value = currentDiscussion;
            }

            if (languageInput) {
                languageInput.value = currentLanguage;
            }
        };

        const loadComments = async () => {
            if (!commentsUrl) {
                return;
            }

            const currentRequest = ++requestSequence;
            const url = new URL(commentsUrl, window.location.origin);
            url.searchParams.set('discussion', currentDiscussion);
            url.searchParams.set('language', currentLanguage);
            setStatus(renderStatus(statusLoadingTemplate));

            try {
                const response = await fetch(url.toString(), {
                    headers: {
                        Accept: 'application/json',
                    },
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.error ?? 'Unable to load comments.');
                }

                if (currentRequest !== requestSequence) {
                    return;
                }

                renderComments(payload.comments ?? []);
                setStatus(renderStatus(statusShowingTemplate));
            } catch (error) {
                if (currentRequest !== requestSequence) {
                    return;
                }

                setStatus(error.message || loadErrorText);
            }
        };

        if (discussion.dataset.initialComments) {
            try {
                renderComments(JSON.parse(discussion.dataset.initialComments));
            } catch (error) {
                renderComments([]);
            }
        }

        setLanguageButtons();
        syncHiddenInputs();

        typeSelect?.addEventListener('change', () => {
            currentDiscussion = typeSelect.value;
            syncHiddenInputs();
            void loadComments();
        });

        languageButtons.forEach((button) => {
            button.addEventListener('click', () => {
                currentLanguage = button.dataset.language ?? currentLanguage;
                setLanguageButtons();
                syncHiddenInputs();
                void loadComments();
            });
        });

        const submitComment = async (submitForm) => {
            if (!commentsUrl || !(submitForm instanceof HTMLFormElement)) {
                return;
            }

            const formData = new FormData(submitForm);
            formData.set('discussion_type', currentDiscussion);
            formData.set('language', currentLanguage);
            setStatus(renderStatus(statusPostingTemplate));

            try {
                const response = await fetch(commentsUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                    },
                    body: formData,
                });
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.error ?? 'Unable to post comment.');
                }

                const textarea = submitForm.querySelector('textarea');
                if (textarea instanceof HTMLTextAreaElement) {
                    textarea.value = '';
                }

                if (submitForm !== form) {
                    submitForm.closest('details')?.removeAttribute('open');
                }

                renderComments(payload.comments ?? []);
                if (payload.notice) {
                    setStatus(payload.notice);
                    return;
                }

                setStatus(renderStatus(statusShowingTemplate));
            } catch (error) {
                setStatus(error.message || postErrorText);
            }
        };

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            await submitComment(form);
        });

        list?.addEventListener('submit', async (event) => {
            const submittedForm = event.target;

            if (!(submittedForm instanceof HTMLFormElement)) {
                return;
            }

            if (submittedForm.dataset.cardManageForm === 'true') {
                event.preventDefault();

                if (new FormData(submittedForm).get('action') === 'delete' && !window.confirm(deleteConfirm)) {
                    return;
                }

                await submitComment(submittedForm);
                return;
            }

            if (submittedForm.dataset.cardReplyForm === 'true') {
                event.preventDefault();
                await submitComment(submittedForm);
            }
        });
    }

    if (!cardResults || cardResults.dataset.jsReady === 'true') {
        return;
    }

    cardResults.dataset.jsReady = 'true';
    const filterUrl = cardResults.dataset.filterUrl;
    const collectionForm = document.querySelector('#collection-options');
    const grid = cardResults.querySelector('[data-card-grid]');

    if (grid) {
        grid.classList.add('is-loaded');
    }

    const rarityFilter = cardResults.querySelector('[data-card-filter="rarity"]');
    const collectionFilter = cardResults.querySelector('[data-card-filter="collection"]');
    const resetButton = cardResults.querySelector('[data-card-filter-reset]');

    const syncCollectionState = (rarity, collection) => {
        cardResults.dataset.rarity = rarity;
        cardResults.dataset.collection = collection;

        if (!(collectionForm instanceof HTMLFormElement)) {
            return;
        }

        let rarityInput = collectionForm.querySelector('input[name="rarity"]');
        let collectionInput = collectionForm.querySelector('input[name="collection"]');

        if (rarity !== '') {
            if (!(rarityInput instanceof HTMLInputElement)) {
                rarityInput = document.createElement('input');
                rarityInput.type = 'hidden';
                rarityInput.name = 'rarity';
                collectionForm.append(rarityInput);
            }

            rarityInput.value = rarity;
        } else {
            rarityInput?.remove();
        }

        if (collection !== '') {
            if (!(collectionInput instanceof HTMLInputElement)) {
                collectionInput = document.createElement('input');
                collectionInput.type = 'hidden';
                collectionInput.name = 'collection';
                collectionForm.append(collectionInput);
            }

            collectionInput.value = collection;
        } else {
            collectionInput?.remove();
        }
    };

    const replaceResults = (html, rarity, collection) => {
        cardResults.innerHTML = html;
        cardResults.dataset.jsReady = 'false';
        syncCollectionState(rarity, collection);

        const url = new URL(window.location.href);
        if (rarity !== '') {
            url.searchParams.set('rarity', rarity);
        } else {
            url.searchParams.delete('rarity');
        }

        if (collection !== '') {
            url.searchParams.set('collection', collection);
        } else {
            url.searchParams.delete('collection');
        }

        if (rarity !== '' || collection !== '') {
            url.searchParams.delete('page');
        }

        window.history.replaceState({}, '', url);
        initializeStructure();
    };

    const fetchResults = async (rarity, collection) => {
        if (!filterUrl) {
            syncCollectionState(rarity, collection);
            collectionForm?.requestSubmit();
            return;
        }

        const sortSelect = collectionForm?.querySelector('select[name="sort"]');
        const url = new URL(filterUrl, window.location.origin);
        url.searchParams.set('q', cardResults.dataset.query ?? '');
        url.searchParams.set('sort', sortSelect instanceof HTMLSelectElement ? sortSelect.value : (cardResults.dataset.sort ?? 'relevance'));

        if (rarity !== '') {
            url.searchParams.set('rarity', rarity);
        }

        if (collection !== '') {
            url.searchParams.set('collection', collection);
        }

        try {
            const response = await fetch(url.toString(), {
                headers: {
                    Accept: 'application/json',
                },
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.error ?? 'Unable to filter cards.');
            }

            replaceResults(payload.html ?? '', rarity, collection);
        } catch (error) {
            syncCollectionState(rarity, collection);
            collectionForm?.requestSubmit();
        }
    };

    rarityFilter?.addEventListener('change', () => {
        void fetchResults(rarityFilter.value, collectionFilter instanceof HTMLSelectElement ? collectionFilter.value : '');
    });

    collectionFilter?.addEventListener('change', () => {
        void fetchResults(rarityFilter instanceof HTMLSelectElement ? rarityFilter.value : '', collectionFilter.value);
    });

    resetButton?.addEventListener('click', () => {
        if (rarityFilter instanceof HTMLSelectElement) {
            rarityFilter.value = '';
        }

        if (collectionFilter instanceof HTMLSelectElement) {
            collectionFilter.value = '';
        }

        void fetchResults('', '');
    });
};

document.addEventListener('DOMContentLoaded', initializeStructure);
document.addEventListener('turbo:load', initializeStructure);
