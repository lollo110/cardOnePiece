const initializeStructure = () => {
    const searchInput = document.querySelector('[data-search-input]');
    const suggestionsBox = document.querySelector('[data-suggestions]');
    const discussion = document.querySelector('[data-card-discussion]');
    const cardResults = document.querySelector('[data-card-results]');

    if (searchInput && suggestionsBox && searchInput.dataset.jsReady !== 'true') {
        searchInput.dataset.jsReady = 'true';
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

        let currentDiscussion = discussion.dataset.defaultDiscussion ?? 'trading';
        let currentLanguage = discussion.dataset.defaultLanguage ?? 'english';
        let requestSequence = 0;

        const discussionLabel = () => typeSelect?.selectedOptions[0]?.textContent?.trim() ?? 'Trading';
        const languageLabel = () => languageButtons.find((button) => button.dataset.language === currentLanguage)?.textContent?.trim() ?? currentLanguage;

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

            if (!comments.length) {
                const empty = document.createElement('p');
                empty.className = 'muted';
                empty.textContent = 'No comments yet for this topic and language.';
                list.append(empty);
                return;
            }

            comments.forEach((comment) => {
                const item = document.createElement('article');
                item.className = 'comment-item';
                const text = document.createElement('p');
                const meta = document.createElement('small');
                text.textContent = comment.content;
                meta.textContent = `${comment.authorName} - ${comment.createdAt}`;
                item.append(text, meta);
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
            setStatus(`Loading ${discussionLabel()} comments for ${languageLabel()}...`);

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
                setStatus(`Showing ${payload.discussionLabel ?? discussionLabel()} comments for ${payload.languageLabel ?? languageLabel()}.`);
            } catch (error) {
                if (currentRequest !== requestSequence) {
                    return;
                }

                setStatus(error.message || 'Unable to load comments.');
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

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!commentsUrl || !(form instanceof HTMLFormElement)) {
                return;
            }

            const formData = new FormData(form);
            formData.set('discussion_type', currentDiscussion);
            formData.set('language', currentLanguage);
            setStatus(`Posting in ${discussionLabel()} / ${languageLabel()}...`);

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

                const textarea = form.querySelector('textarea');
                if (textarea instanceof HTMLTextAreaElement) {
                    textarea.value = '';
                }

                renderComments(payload.comments ?? []);
                if (payload.notice) {
                    setStatus(payload.notice);
                    return;
                }

                setStatus(`Showing ${payload.discussionLabel ?? discussionLabel()} comments for ${payload.languageLabel ?? languageLabel()}.`);
            } catch (error) {
                setStatus(error.message || 'Unable to post comment.');
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
    const resetButton = cardResults.querySelector('[data-card-filter-reset]');

    const syncCollectionState = (rarity) => {
        cardResults.dataset.rarity = rarity;

        if (!(collectionForm instanceof HTMLFormElement)) {
            return;
        }

        let rarityInput = collectionForm.querySelector('input[name="rarity"]');

        if (rarity !== '') {
            if (!(rarityInput instanceof HTMLInputElement)) {
                rarityInput = document.createElement('input');
                rarityInput.type = 'hidden';
                rarityInput.name = 'rarity';
                collectionForm.append(rarityInput);
            }

            rarityInput.value = rarity;
            return;
        }

        rarityInput?.remove();
    };

    const replaceResults = (html, rarity) => {
        cardResults.innerHTML = html;
        cardResults.dataset.jsReady = 'false';
        syncCollectionState(rarity);

        const url = new URL(window.location.href);
        if (rarity !== '') {
            url.searchParams.set('rarity', rarity);
            url.searchParams.delete('page');
        } else {
            url.searchParams.delete('rarity');
        }

        window.history.replaceState({}, '', url);
        initializeStructure();
    };

    const fetchResults = async (rarity) => {
        if (!filterUrl) {
            syncCollectionState(rarity);
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

            replaceResults(payload.html ?? '', rarity);
        } catch (error) {
            syncCollectionState(rarity);
            collectionForm?.requestSubmit();
        }
    };

    rarityFilter?.addEventListener('change', () => {
        void fetchResults(rarityFilter.value);
    });

    resetButton?.addEventListener('click', () => {
        if (rarityFilter instanceof HTMLSelectElement) {
            rarityFilter.value = '';
        }

        void fetchResults('');
    });
};

document.addEventListener('DOMContentLoaded', initializeStructure);
document.addEventListener('turbo:load', initializeStructure);
