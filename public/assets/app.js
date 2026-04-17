/* Hut — client-side interactivity */

document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const basePath = String(window.HUT_BASE_PATH || '').replace(/\/$/, '');
    const withBase = path => {
        const normalizedPath = String(path || '').startsWith('/') ? String(path) : `/${path}`;
        return `${basePath}${normalizedPath}`;
    };

    // ── Burger nav toggle ────────────────────────────────────────────────────
    const navBurger = document.querySelector('[data-nav-burger]');
    const mainNav   = document.getElementById('mainNav');
    if (navBurger && mainNav) {
        navBurger.addEventListener('click', () => {
            const isOpen = mainNav.classList.toggle('nav--open');
            navBurger.setAttribute('aria-expanded', String(isOpen));
        });
        mainNav.querySelectorAll('.nav__link').forEach(link => {
            link.addEventListener('click', () => {
                mainNav.classList.remove('nav--open');
                navBurger.setAttribute('aria-expanded', 'false');
            });
        });
    }

    // ── Browse filter: auto-submit on pill checkbox change ─────────────────
    document.querySelector('[data-browse-filters]')?.querySelectorAll('.filters__pill input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', () => cb.closest('form').submit());
    });

    // ── Browse search autocomplete ──────────────────────────────────────────
    document.querySelectorAll('[data-autocomplete]').forEach(root => {
        const input = root.querySelector('[data-autocomplete-input]');
        const menu = root.querySelector('[data-autocomplete-menu]');

        if (!input || !menu) {
            return;
        }

        let activeIndex = -1;
        let currentItems = [];
        let debounceId = null;
        let abortController = null;

        const closeMenu = () => {
            activeIndex = -1;
            currentItems = [];
            menu.hidden = true;
            menu.innerHTML = '';
            input.setAttribute('aria-expanded', 'false');
        };

        const setActiveItem = index => {
            const items = menu.querySelectorAll('.search-autocomplete__item');
            items.forEach((item, itemIndex) => {
                item.classList.toggle('search-autocomplete__item--active', itemIndex === index);
            });
            activeIndex = index;
        };

        const renderItems = items => {
            currentItems = items;
            activeIndex = -1;

            if (!items.length) {
                menu.innerHTML = '<div class="search-autocomplete__empty">No matches found</div>';
                menu.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                return;
            }

            menu.innerHTML = '';

            items.forEach((item, index) => {
                const rank = item.rank ? `#${item.rank}` : 'Unranked';
                const year = item.yearpublished ? `(${item.yearpublished})` : '';

                const link = document.createElement('a');
                link.className = 'search-autocomplete__item';
                link.href = withBase(`/games/${item.id}`);
                link.dataset.index = String(index);
                link.setAttribute('role', 'option');

                const title = document.createElement('span');
                title.className = 'search-autocomplete__title';
                title.textContent = item.name;

                const meta = document.createElement('span');
                meta.className = 'search-autocomplete__meta';
                meta.textContent = [rank, year].filter(Boolean).join(' ');

                link.append(title, meta);
                menu.append(link);
            });

            menu.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        };

        const fetchItems = async query => {
            if (abortController) {
                abortController.abort();
            }

            abortController = new AbortController();

            try {
                const response = await fetch(withBase(`/games/suggestions?q=${encodeURIComponent(query)}`), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: abortController.signal,
                });

                if (!response.ok) {
                    closeMenu();
                    return;
                }

                const data = await response.json();
                renderItems(Array.isArray(data.items) ? data.items : []);
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Autocomplete failed', error);
                    closeMenu();
                }
            }
        };

        input.addEventListener('input', () => {
            const query = input.value.trim();

            if (debounceId) {
                window.clearTimeout(debounceId);
            }

            if (query.length < 2) {
                closeMenu();
                return;
            }

            debounceId = window.setTimeout(() => {
                fetchItems(query);
            }, 180);
        });

        input.addEventListener('keydown', event => {
            const items = menu.querySelectorAll('.search-autocomplete__item');

            if (event.key === 'Escape') {
                closeMenu();
                return;
            }

            if (!items.length) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                const nextIndex = activeIndex < items.length - 1 ? activeIndex + 1 : 0;
                setActiveItem(nextIndex);
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                const nextIndex = activeIndex > 0 ? activeIndex - 1 : items.length - 1;
                setActiveItem(nextIndex);
                return;
            }

            if (event.key === 'Enter' && activeIndex >= 0) {
                event.preventDefault();
                window.location.href = currentItems[activeIndex] ? withBase(`/games/${currentItems[activeIndex].id}`) : input.form.action;
            }
        });

        menu.addEventListener('mouseover', event => {
            const item = event.target.closest('.search-autocomplete__item');
            if (!item) {
                return;
            }
            setActiveItem(Number(item.dataset.index));
        });

        document.addEventListener('click', event => {
            if (!root.contains(event.target)) {
                closeMenu();
            }
        });
    });

    // ── Select / deselect game ──────────────────────────────────────────────
    document.querySelectorAll('.btn--select').forEach(btn => {
        btn.addEventListener('click', async () => {
            const gameId = btn.dataset.gameId;
            try {
                const res  = await fetch(withBase(`/games/${gameId}/select`), {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken,
                    },
                });
                const data = await res.json();
                if (data.selected) {
                    btn.textContent = '✓ In hut';
                    btn.classList.add('btn--select--active');
                } else {
                    btn.textContent = '+ Add to hut';
                    btn.classList.remove('btn--select--active');
                }
            } catch (e) {
                console.error('Select toggle failed', e);
            }
        });
    });

    // ── Hearts ───────────────────────────────────────────────────────────────
    document.querySelectorAll('[data-heart-button]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const gameId = btn.dataset.gameId;
            if (!gameId) {
                return;
            }

            try {
                const res = await fetch(withBase(`/games/${gameId}/heart`), {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken,
                    },
                });
                const data = await res.json();

                btn.classList.toggle('btn--heart--active', data.hearted === true);
                btn.textContent = data.hearted === true ? '♥ Hearted' : '♥ Heart';

                document.querySelectorAll(`.heart-controls[data-game-id="${gameId}"] .heart-tally`).forEach(node => {
                    node.textContent = String(data.hearts);
                });

                document.querySelectorAll(`[data-hearted-by="${gameId}"]`).forEach(node => {
                    if (node.dataset.heartedMode === 'names') {
                        node.textContent = data.heartedBy;
                    } else {
                        node.textContent = `Hearted by: ${data.heartedBy}`;
                    }
                });

                document.querySelectorAll(`.heart-controls[data-game-id="${gameId}"] .heart-summary`).forEach(node => {
                    const labelNode = node.querySelector('.heart-label');
                    if (labelNode) {
                        labelNode.textContent = `heart${data.hearts === 1 ? '' : 's'}`;
                    }
                });

                const collectionCard = document.querySelector(`.collection-card .heart-controls[data-game-id="${gameId}"]`)?.closest('.collection-card');
                if (collectionCard) {
                    collectionCard.dataset.hearts = String(data.hearts);
                }
            } catch (e) {
                console.error('Heart toggle failed', e);
            }
        });
    });

    // ── Collection filters ─────────────────────────────────────────────────
    const collectionGrid = document.querySelector('[data-collection-grid]');
    if (collectionGrid) {
        const countEl = document.querySelector('[data-collection-count]');
        let complexityFilter = '';

        const filterCards = () => {
            const bpSel  = document.querySelector('[data-collection-filter="bestplayers"]')?.value  || '';
            const mpSel  = document.querySelector('[data-collection-filter="maxplayers"]')?.value   || '';
            const mptSel = document.querySelector('[data-collection-filter="maxplaytime"]')?.value  || '';

            let visible = 0;
            collectionGrid.querySelectorAll('.collection-card').forEach(card => {
                const bp  = Number(card.dataset.bestplayercount);
                const mp  = Number(card.dataset.maxplayers);
                const mpt = Number(card.dataset.maxplaytime);
                const cx  = Number(card.dataset.complexity);

                let show = true;

                if (bpSel !== '') {
                    const bpVal = Number(bpSel);
                    show = show && (bpVal === 6 ? bp >= 6 : bp === bpVal);
                }
                if (mpSel !== '') {
                    show = show && (mp >= Number(mpSel));
                }
                if (mptSel !== '') {
                    if (mptSel === '121+') {
                        show = show && (mpt > 120);
                    } else {
                        show = show && (mpt > 0 && mpt <= Number(mptSel));
                    }
                }
                if (complexityFilter === 'light')   { show = show && (cx > 0 && cx <= 1.8); }
                if (complexityFilter === 'medium')  { show = show && (cx > 1.8 && cx <= 2.8); }
                if (complexityFilter === 'complex') { show = show && (cx > 2.8); }

                card.hidden = !show;
                if (show) visible++;
            });

            if (countEl) {
                countEl.textContent = `${visible} game${visible !== 1 ? 's' : ''}`;
            }
        };

        document.querySelectorAll('[data-collection-filter]').forEach(el => {
            el.addEventListener('change', filterCards);
        });

        document.querySelectorAll('[data-complexity-filters] .filter-seg').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('[data-complexity-filters] .filter-seg').forEach(b => {
                    b.classList.remove('filter-seg--active');
                });
                btn.classList.add('filter-seg--active');
                complexityFilter = btn.dataset.complexity || '';
                filterCards();
            });
        });

        document.querySelector('[data-collection-filter-reset]')?.addEventListener('click', () => {
            document.querySelectorAll('[data-collection-filter]').forEach(el => { el.value = ''; });
            complexityFilter = '';
            document.querySelectorAll('[data-complexity-filters] .filter-seg').forEach((b, i) => {
                b.classList.toggle('filter-seg--active', i === 0);
            });
            filterCards();
        });

        filterCards();
    }

    // ── Collection table sorting ───────────────────────────────────────────
    document.querySelectorAll('[data-sort-table]').forEach(table => {
        const headCells = Array.from(table.querySelectorAll('th[data-sort-key]'));
        const tbody = table.querySelector('tbody');

        if (!tbody || !headCells.length) {
            return;
        }

        const normalizeText = value => String(value || '').toLowerCase();

        const sortTable = (key, direction) => {
            const rows = Array.from(tbody.querySelectorAll('tr.collection-table__row'));
            const multiplier = direction === 'asc' ? 1 : -1;

            rows.sort((a, b) => {
                const aValueRaw = a.dataset[key] ?? '';
                const bValueRaw = b.dataset[key] ?? '';

                if (key === 'name') {
                    const aText = normalizeText(aValueRaw);
                    const bText = normalizeText(bValueRaw);
                    if (aText < bText) return -1 * multiplier;
                    if (aText > bText) return 1 * multiplier;
                    return 0;
                }

                const aNum = Number(aValueRaw);
                const bNum = Number(bValueRaw);
                if (aNum < bNum) return -1 * multiplier;
                if (aNum > bNum) return 1 * multiplier;

                const aName = normalizeText(a.dataset.name);
                const bName = normalizeText(b.dataset.name);
                if (aName < bName) return -1;
                if (aName > bName) return 1;
                return 0;
            });

            rows.forEach(row => tbody.append(row));
        };

        headCells.forEach(th => {
            th.dataset.sortDirection = th.dataset.sortDirection || 'desc';
            th.addEventListener('click', () => {
                const key = th.dataset.sortKey;
                if (!key) {
                    return;
                }

                const nextDirection = th.dataset.sortDirection === 'asc' ? 'desc' : 'asc';

                headCells.forEach(other => {
                    other.classList.remove('collection-table__head--asc', 'collection-table__head--desc');
                    if (other !== th) {
                        other.dataset.sortDirection = 'desc';
                    }
                });

                th.dataset.sortDirection = nextDirection;
                th.classList.add(nextDirection === 'asc' ? 'collection-table__head--asc' : 'collection-table__head--desc');
                sortTable(key, nextDirection);
            });
        });
    });

    // ── Admin async import ─────────────────────────────────────────────────
    const importForm = document.querySelector('[data-import-form]');
    if (importForm) {
        const startUrl = importForm.getAttribute('data-import-start-url') || withBase('/admin/import/start');
        const processUrl = importForm.getAttribute('data-import-process-url') || withBase('/admin/import/process');
        const statusUrl = importForm.getAttribute('data-import-status-url') || withBase('/admin/import/status');
        const progressRoot = importForm.querySelector('[data-import-progress]');
        const progressBar = importForm.querySelector('[data-import-progress-bar]');
        const percentLabel = importForm.querySelector('[data-import-percent]');
        const statusLabel = importForm.querySelector('[data-import-status-label]');
        const statsLabel = importForm.querySelector('[data-import-stats]');
        const messageLabel = importForm.querySelector('[data-import-message]');
        const submitButton = importForm.querySelector('[data-import-submit]');
        const csrfInput = importForm.querySelector('input[name="_csrf"]');

        const applyProgressState = state => {
            if (!progressRoot) {
                return;
            }

            progressRoot.hidden = false;

            const percent = Number.isFinite(Number(state.percent)) ? Number(state.percent) : 0;
            if (progressBar) {
                progressBar.value = Math.max(0, Math.min(100, percent));
            }
            if (percentLabel) {
                percentLabel.textContent = `${Math.max(0, Math.min(100, percent))}%`;
            }

            const statusTextMap = {
                queued: 'Queued',
                running: 'Importing',
                completed: 'Completed',
                failed: 'Failed',
            };

            if (statusLabel) {
                statusLabel.textContent = statusTextMap[state.status] || 'Importing';
            }

            if (statsLabel) {
                statsLabel.textContent = `Processed ${state.processed || 0} of ${state.total || 0} rows`;
            }

            if (messageLabel) {
                const extra = state.first_error ? ` First skipped row error: ${state.first_error}` : '';
                messageLabel.textContent = state.message ? `${state.message}${extra}` : extra.trim();
            }
        };

        const setImportBusy = busy => {
            if (submitButton) {
                submitButton.disabled = busy;
                submitButton.textContent = busy ? 'Importing...' : 'Import';
            }
        };

        const wait = ms => new Promise(resolve => window.setTimeout(resolve, ms));

        const fetchImportStatus = async jobId => {
            const response = await fetch(`${statusUrl}?job_id=${encodeURIComponent(jobId)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'Could not load import status.');
            }
            return data;
        };

        const pollUntilFinished = async jobId => {
            let state;
            while (true) {
                state = await fetchImportStatus(jobId);
                applyProgressState(state);

                if (state.status === 'completed' || state.status === 'failed') {
                    return state;
                }

                await wait(1000);
            }
        };

        const processOneBatch = async jobId => {
            const body = new FormData();
            body.append('_csrf', csrfInput ? csrfInput.value : '');
            body.append('job_id', jobId);

            const response = await fetch(processUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrfToken,
                },
                body,
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'Import step failed.');
            }

            return data;
        };

        const runPollingProcessorUntilFinished = async jobId => {
            let state;
            while (true) {
                state = await processOneBatch(jobId);
                applyProgressState(state);

                if (state.status === 'completed' || state.status === 'failed') {
                    return state;
                }

                await wait(350);
            }
        };

        importForm.addEventListener('submit', async event => {
            event.preventDefault();

            const fileInput = importForm.querySelector('input[name="bgg_file"]');
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                return;
            }

            const submitData = new FormData(importForm);

            setImportBusy(true);
            if (messageLabel) {
                messageLabel.textContent = '';
            }

            try {
                const startResponse = await fetch(startUrl, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': csrfToken,
                    },
                    body: submitData,
                });

                const startData = await startResponse.json();
                if (!startResponse.ok) {
                    throw new Error(startData.error || 'Failed to start import.');
                }

                applyProgressState(startData);
                const jobId = String(startData.job_id || '');
                if (!jobId) {
                    throw new Error('Import job id missing from server response.');
                }

                sessionStorage.setItem('hutImportJobId', jobId);
                const finalState = startData.runner === 'polling'
                    ? await runPollingProcessorUntilFinished(jobId)
                    : await pollUntilFinished(jobId);

                if (finalState.status === 'completed') {
                    sessionStorage.removeItem('hutImportJobId');
                    window.location.href = withBase('/admin');
                }
            } catch (error) {
                if (progressRoot) {
                    progressRoot.hidden = false;
                }
                if (statusLabel) {
                    statusLabel.textContent = 'Failed';
                }
                if (messageLabel) {
                    messageLabel.textContent = error instanceof Error ? error.message : 'Import failed.';
                }
            } finally {
                setImportBusy(false);
            }
        });

        const resumeJobId = sessionStorage.getItem('hutImportJobId');
        if (resumeJobId) {
            setImportBusy(true);
            fetchImportStatus(resumeJobId)
                .then(async data => {
                    applyProgressState(data);
                    if (data.status === 'completed' || data.status === 'failed') {
                        sessionStorage.removeItem('hutImportJobId');
                        return;
                    }

                    const finalState = data.runner === 'polling'
                        ? await runPollingProcessorUntilFinished(resumeJobId)
                        : await pollUntilFinished(resumeJobId);
                    if (finalState.status === 'completed') {
                        sessionStorage.removeItem('hutImportJobId');
                        window.location.href = withBase('/admin');
                    }
                })
                .catch(error => {
                    sessionStorage.removeItem('hutImportJobId');
                    if (statusLabel) {
                        statusLabel.textContent = 'Failed';
                    }
                    if (messageLabel) {
                        messageLabel.textContent = error instanceof Error ? error.message : 'Import failed.';
                    }
                })
                .finally(() => {
                    setImportBusy(false);
                });
        }
    }
});
