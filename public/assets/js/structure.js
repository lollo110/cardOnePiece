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

        const renderSuggestions = (suggestions, query) => {
            suggestionsBox.innerHTML = '';

            const list = document.createElement('div');
            list.className = 'suggestions-list';

            suggestions.forEach((suggestion) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = suggestion;
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
        const noCommentsText = discussion.dataset.noCommentsText ?? 'No comments yet for this topic and language.';
        const statusLoadingTemplate = discussion.dataset.statusLoading ?? 'Loading __TOPIC__ comments for __LANGUAGE__...';
        const statusShowingTemplate = discussion.dataset.statusShowing ?? 'Showing __TOPIC__ comments for __LANGUAGE__.';
        const statusPostingTemplate = discussion.dataset.statusPosting ?? 'Posting in __TOPIC__ / __LANGUAGE__...';
        const loadErrorText = discussion.dataset.errorLoad ?? 'Unable to load comments.';
        const postErrorText = discussion.dataset.errorPost ?? 'Unable to post comment.';

        let currentDiscussion = discussion.dataset.defaultDiscussion ?? 'trading';
        let currentLanguage = discussion.dataset.defaultLanguage ?? 'english';
        let requestSequence = 0;

        const discussionLabel = () => typeSelect?.selectedOptions[0]?.textContent?.trim() ?? 'Trading';
        const languageLabel = () => languageButtons.find((button) => button.dataset.language === currentLanguage)?.textContent?.trim() ?? currentLanguage;
        const renderStatus = (template) => template
            .replaceAll('__TOPIC__', discussionLabel())
            .replaceAll('__LANGUAGE__', languageLabel());

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
                empty.textContent = noCommentsText;
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

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!commentsUrl || !(form instanceof HTMLFormElement)) {
                return;
            }

            const formData = new FormData(form);
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

                const textarea = form.querySelector('textarea');
                if (textarea instanceof HTMLTextAreaElement) {
                    textarea.value = '';
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
