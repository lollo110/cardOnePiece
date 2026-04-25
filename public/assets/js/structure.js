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
