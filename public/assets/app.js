/* Hut — client-side interactivity */

document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const basePath = String(window.HUT_BASE_PATH || '').replace(/\/$/, '');
    const withBase = path => {
        const normalizedPath = String(path || '').startsWith('/') ? String(path) : `/${path}`;
        return `${basePath}${normalizedPath}`;
    };

    // ── Two-level nav interactions ───────────────────────────────────────────
    const navBurger = document.querySelector('[data-nav-burger]');
    const mainNav   = document.getElementById('mainNav');
    if (navBurger && mainNav) {
        const navGroups = Array.from(mainNav.querySelectorAll('[data-nav-group]'));
        const navGroupButtons = Array.from(mainNav.querySelectorAll('[data-nav-group-toggle]'));
        const isMobileViewport = () => window.matchMedia('(max-width: 640px)').matches;

        const closeGroups = (excludeGroup = null) => {
            navGroups.forEach(group => {
                if (excludeGroup && group === excludeGroup) {
                    return;
                }

                group.classList.remove('nav__group--open');
                const toggle = group.querySelector('[data-nav-group-toggle]');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
            });
        };

        navGroupButtons.forEach(button => {
            button.addEventListener('click', event => {
                event.preventDefault();
                event.stopPropagation();

                const group = button.closest('[data-nav-group]');
                if (!group) {
                    return;
                }

                const willOpen = !group.classList.contains('nav__group--open');
                closeGroups(group);

                if (willOpen) {
                    group.classList.add('nav__group--open');
                } else {
                    group.classList.remove('nav__group--open');
                }

                button.setAttribute('aria-expanded', String(willOpen));
            });
        });

        navBurger.addEventListener('click', () => {
            const isOpen = mainNav.classList.toggle('nav--open');
            navBurger.setAttribute('aria-expanded', String(isOpen));

            if (isOpen && isMobileViewport()) {
                const hasOpenGroup = navGroups.some(group => group.classList.contains('nav__group--open'));
                if (!hasOpenGroup) {
                    const activeGroup = mainNav.querySelector('.nav__group--active');
                    if (activeGroup) {
                        activeGroup.classList.add('nav__group--open');
                        const toggle = activeGroup.querySelector('[data-nav-group-toggle]');
                        if (toggle) {
                            toggle.setAttribute('aria-expanded', 'true');
                        }
                    }
                }
            }

            if (!isOpen) {
                closeGroups();
            }
        });

        mainNav.querySelectorAll('.nav__dropdown-link, .nav__guest-link').forEach(link => {
            link.addEventListener('click', () => {
                mainNav.classList.remove('nav--open');
                navBurger.setAttribute('aria-expanded', 'false');
                closeGroups();
            });
        });

        document.addEventListener('click', event => {
            const target = event.target;
            if (!(target instanceof Node)) {
                return;
            }

            if (!mainNav.contains(target)) {
                closeGroups();
            }
        });
    }

    // ── Browse filter: auto-submit on pill checkbox change ─────────────────
    document.querySelector('[data-browse-filters]')?.querySelectorAll('.filters__pill input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', () => cb.closest('form').submit());
    });

    document.querySelector('[data-browse-filters]')?.querySelectorAll('[data-browse-complexity] .filter-seg').forEach(btn => {
        btn.addEventListener('click', () => {
            const form = btn.closest('form');
            const input = form?.querySelector('input[name="complexity"]');

            if (!form || !input) {
                return;
            }

            form.querySelectorAll('[data-browse-complexity] .filter-seg').forEach(seg => {
                seg.classList.remove('filter-seg--active');
            });

            btn.classList.add('filter-seg--active');
            input.value = btn.dataset.complexity || '';
            form.submit();
        });
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
            if (btn.disabled) {
                return;
            }

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

                if (data.selectedByMe) {
                    btn.textContent = '− Remove from hut';
                    btn.classList.add('btn--select--active');
                    btn.disabled = false;
                    btn.title = 'Remove your hut selection';
                } else if (data.inHut) {
                    btn.textContent = '🏠 In hut';
                    btn.classList.add('btn--select--active');
                    btn.disabled = true;
                    btn.title = 'In hut (added by another user)';
                } else {
                    btn.textContent = '+ Add to hut';
                    btn.classList.remove('btn--select--active');
                    btn.disabled = false;
                    btn.title = 'Add to hut';
                }
            } catch (e) {
                console.error('Select toggle failed', e);
            }
        });
    });

    // ── Heart burst animation ─────────────────────────────────────────────
    function spawnHearts(origin) {
        const rect = origin.getBoundingClientRect();
        const cx = rect.left + rect.width / 2;
        const cy = rect.top + rect.height / 2;
        const count = 6;
        for (let i = 0; i < count; i++) {
            const el = document.createElement('span');
            el.textContent = '♥';
            el.className = 'heart-particle';
            const angle = (Math.PI * 2 / count) * i - Math.PI / 2 + (Math.random() - 0.5) * 1.0;
            const dist = 50 + Math.random() * 70;
            const flyX = Math.cos(angle) * dist;
            const flyY = Math.sin(angle) * dist;
            const delay = Math.random() * 120;
            const size = 0.75 + Math.random() * 0.9;
            el.style.cssText = `left:${cx}px;top:${cy}px;--fly-x:${flyX}px;--fly-y:${flyY}px;font-size:${size}rem;animation-delay:${delay}ms;`;
            document.body.appendChild(el);
            el.addEventListener('animationend', () => el.remove());
        }
    }

    // ── Resident name linkifier (used by heart toggle) ───────────────────────
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function linkifyHeartedBy(text) {
        const map = window.HUT_RESIDENT_MAP || {};
        if (!text || text === 'No hearts yet') {
            return escapeHtml(text || '');
        }
        return text.split(', ').map(name => {
            const trimmed = name.trim();
            const id = map[trimmed];
            if (id) {
                return `<a href="${basePath}/residents/${id}">${escapeHtml(trimmed)}</a>`;
            }
            return escapeHtml(trimmed);
        }).join(', ');
    }

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
                if (data.hearted === true) spawnHearts(btn);

                document.querySelectorAll(`.heart-controls[data-game-id="${gameId}"] .heart-tally`).forEach(node => {
                    node.textContent = String(data.hearts);
                });

                document.querySelectorAll(`[data-hearted-by="${gameId}"]`).forEach(node => {
                    if (node.dataset.heartedMode === 'names') {
                        node.innerHTML = linkifyHeartedBy(data.heartedBy);
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

    // ── Collection statistics charts ───────────────────────────────────────
    const statisticsRoot = document.querySelector('[data-statistics-page]');
    if (statisticsRoot) {
        const rawStats = statisticsRoot.getAttribute('data-stats') || '{}';
        let stats = null;

        try {
            stats = JSON.parse(rawStats);
        } catch (error) {
            console.error('Could not parse statistics data', error);
        }

        if (stats && typeof stats === 'object') {
            const palettes = {
                complexity: ['#6ab187', '#e3b04b', '#d36a46', '#a0a9be'],
                bestWith:   ['#5470c6', '#6fa8dc', '#4caf99', '#8bc34a', '#f9a825', '#ef6c00', '#9c6ec7'],
                duration:   ['#5fa8d3', '#3d90b6', '#4f7ebd', '#6a55bf', '#b8507a', '#8c93a8'],
                hearts:     ['#bfc8d8', '#f0a45a', '#e05c5c', '#c7254e'],
            };

            const rankColors = ['#7b9ed9', '#5b8fcf', '#4c7bbf', '#3d6aae', '#a0a9be'];

            const ns = 'http://www.w3.org/2000/svg';
            const svgEl = tag => document.createElementNS(ns, tag);
            const toNumber = value => Number.isFinite(Number(value)) ? Number(value) : 0;

            const polarToCartesian = (cx, cy, r, rad) => ({
                x: cx + Math.cos(rad) * r,
                y: cy + Math.sin(rad) * r,
            });

            const arcPath = (cx, cy, r, startAngle, endAngle) => {
                const s = polarToCartesian(cx, cy, r, startAngle);
                const e = polarToCartesian(cx, cy, r, endAngle);
                const large = endAngle - startAngle > Math.PI ? 1 : 0;
                return [
                    `M ${cx} ${cy}`,
                    `L ${s.x} ${s.y}`,
                    `A ${r} ${r} 0 ${large} 1 ${e.x} ${e.y}`,
                    'Z',
                ].join(' ');
            };

            const renderPieChart = (metricKey, data, urlMapper = null) => {
                const svg    = statisticsRoot.querySelector(`[data-pie-chart="${metricKey}"]`);
                const legend = statisticsRoot.querySelector(`[data-pie-legend="${metricKey}"]`);
                if (!svg || !legend || !Array.isArray(data)) {
                    return;
                }

                svg.innerHTML = '';
                legend.innerHTML = '';

                const normalized = data.map(item => ({
                    label: String(item.label || ''),
                    count: toNumber(item.count),
                }));
                const total = normalized.reduce((sum, item) => sum + item.count, 0);

                if (total <= 0) {
                    const empty = svgEl('text');
                    empty.setAttribute('x', '110');
                    empty.setAttribute('y', '114');
                    empty.setAttribute('text-anchor', 'middle');
                    empty.setAttribute('class', 'stats-pie__empty');
                    empty.textContent = 'No data';
                    svg.append(empty);

                    const emptyLegend = document.createElement('li');
                    emptyLegend.className = 'stats-legend__item stats-legend__item--empty';
                    emptyLegend.textContent = 'No data available';
                    legend.append(emptyLegend);
                    return;
                }

                const colors = palettes[metricKey] || palettes.complexity;
                let startAngle = -Math.PI / 2;

                normalized.forEach((slice, index) => {
                    const color = colors[index % colors.length];
                    const pct   = total > 0 ? Math.round((slice.count / total) * 100) : 0;

                    // ── Legend entry ──────────────────────────────────────
                    const li = document.createElement('li');
                    const sliceUrlParam = urlMapper ? urlMapper(slice.label) : null;
                    li.className = sliceUrlParam ? 'stats-legend__item stats-legend__item--link' : 'stats-legend__item';
                    li.innerHTML = [
                        `<span class="stats-legend__swatch" style="background:${color}"></span>`,
                        `<span class="stats-legend__label">${slice.label}</span>`,
                        `<span class="stats-legend__count">${slice.count} <span class="stats-legend__pct">(${pct}%)</span></span>`,
                    ].join('');
                    if (sliceUrlParam) {
                        li.addEventListener('click', () => { window.location.href = withBase(`/collection?${sliceUrlParam}`); });
                    }
                    legend.append(li);

                    if (slice.count <= 0) {
                        return;
                    }

                    const angle    = (slice.count / total) * Math.PI * 2;
                    const endAngle = startAngle + angle;

                    // ── Arc slice ─────────────────────────────────────────
                    const path = svgEl('path');
                    path.setAttribute('d', arcPath(110, 110, 98, startAngle, endAngle));
                    path.setAttribute('fill', color);
                    path.setAttribute('class', sliceUrlParam ? 'stats-pie__slice stats-pie__slice--link' : 'stats-pie__slice');
                    path.setAttribute('aria-label', `${slice.label}: ${slice.count} (${pct}%)`);
                    if (sliceUrlParam) {
                        path.addEventListener('click', () => { window.location.href = withBase(`/collection?${sliceUrlParam}`); });
                    }
                    svg.append(path);

                    // ── Count + % label inside slice ──────────────────────
                    const midAngle  = startAngle + angle / 2;
                    const labelR    = 73; // midpoint of ring (hole r=52, outer r=98)
                    const lp        = polarToCartesian(110, 110, labelR, midAngle);
                    const showPct   = angle >= 0.42; // ≥ ~24° — enough room for two lines
                    const showLabel = angle >= 0.22; // ≥ ~13° — show at least count

                    if (showLabel) {
                        const g = svgEl('g');
                        g.setAttribute('class', 'stats-pie__label-group');
                        g.setAttribute('text-anchor', 'middle');

                        const tCount = svgEl('text');
                        tCount.setAttribute('x', String(lp.x));
                        tCount.setAttribute('y', showPct ? String(lp.y - 7) : String(lp.y));
                        tCount.setAttribute('dominant-baseline', 'middle');
                        tCount.setAttribute('class', 'stats-pie__count');
                        tCount.textContent = String(slice.count);
                        g.append(tCount);

                        if (showPct) {
                            const tPct = svgEl('text');
                            tPct.setAttribute('x', String(lp.x));
                            tPct.setAttribute('y', String(lp.y + 8));
                            tPct.setAttribute('dominant-baseline', 'middle');
                            tPct.setAttribute('class', 'stats-pie__pct');
                            tPct.textContent = `${pct}%`;
                            g.append(tPct);
                        }

                        svg.append(g);
                    }

                    startAngle = endAngle;
                });

                // ── Donut hole with centre total ──────────────────────────
                const hole = svgEl('circle');
                hole.setAttribute('cx', '110');
                hole.setAttribute('cy', '110');
                hole.setAttribute('r', '52');
                hole.setAttribute('class', 'stats-donut-hole');
                svg.append(hole);

                const cVal = svgEl('text');
                cVal.setAttribute('x', '110');
                cVal.setAttribute('y', '106');
                cVal.setAttribute('text-anchor', 'middle');
                cVal.setAttribute('dominant-baseline', 'middle');
                cVal.setAttribute('class', 'stats-donut__center-value');
                cVal.textContent = String(total);
                svg.append(cVal);

                const cLbl = svgEl('text');
                cLbl.setAttribute('x', '110');
                cLbl.setAttribute('y', '122');
                cLbl.setAttribute('text-anchor', 'middle');
                cLbl.setAttribute('dominant-baseline', 'middle');
                cLbl.setAttribute('class', 'stats-donut__center-label');
                cLbl.textContent = 'games';
                svg.append(cLbl);
            };

            const renderRankDistribution = (data, urlMapper = null) => {
                const root = statisticsRoot.querySelector('[data-rank-chart]');
                if (!root || !Array.isArray(data)) {
                    return;
                }

                root.innerHTML = '';
                const normalized = data.map(item => ({
                    label: String(item.label || ''),
                    count: toNumber(item.count),
                }));
                const maxCount = normalized.reduce((max, item) => Math.max(max, item.count), 0);
                const totalRank = normalized.reduce((sum, item) => sum + item.count, 0);

                if (maxCount <= 0) {
                    const empty = document.createElement('p');
                    empty.className = 'empty-state';
                    empty.textContent = 'No rank data available yet.';
                    root.append(empty);
                    return;
                }

                normalized.forEach((item, index) => {
                    const pct = totalRank > 0 ? Math.round((item.count / totalRank) * 100) : 0;
                    const color = rankColors[index % rankColors.length];
                    const rowUrlParam = urlMapper ? urlMapper(item.label) : null;

                    const row = document.createElement('div');
                    row.className = rowUrlParam ? 'stats-rank-row stats-rank-row--link' : 'stats-rank-row';

                    const label = document.createElement('span');
                    label.className = 'stats-rank-row__label';
                    label.textContent = item.label;

                    const barTrack = document.createElement('div');
                    barTrack.className = 'stats-rank-row__track';

                    const barFill = document.createElement('span');
                    barFill.className = 'stats-rank-row__fill';
                    barFill.style.setProperty('--bar-target', `${Math.max(4, (item.count / maxCount) * 100)}%`);
                    barFill.style.background = `linear-gradient(90deg, ${color}cc, ${color})`;
                    barTrack.append(barFill);

                    const value = document.createElement('span');
                    value.className = 'stats-rank-row__value';
                    value.innerHTML = `${item.count} <span class="stats-rank-row__pct">(${pct}%)</span>`;

                    if (rowUrlParam) {
                        row.addEventListener('click', () => { window.location.href = withBase(`/collection?${rowUrlParam}`); });
                    }

                    row.append(label, barTrack, value);
                    root.append(row);
                });
            };

            const chartUrlMappers = {
                complexity: label => ({ 'Light': 'complexity=light', 'Medium': 'complexity=medium', 'Complex': 'complexity=complex' })[label] || null,
                bestWith: label => {
                    if (label === '6+') { return 'bestplayers=6'; }
                    if (/^\d$/.test(label)) { return `bestplayers=${label}`; }
                    return null;
                },
                duration: label => ({
                    '≤ 30 min': 'maxplaytime=0-30', '31-60 min': 'maxplaytime=31-60',
                    '61-90 min': 'maxplaytime=61-90', '91-120 min': 'maxplaytime=91-120', '> 120 min': 'maxplaytime=121%2B',
                })[label] || null,
                hearts: label => ({
                    '0 hearts': 'hearts=0', '1-2 hearts': 'hearts=1-2', '3-5 hearts': 'hearts=3-5', '6+ hearts': 'hearts=6%2B',
                })[label] || null,
            };

            const rankUrlMapper = label => ({
                'Top 100': 'rank=top100', '101-500': 'rank=101-500', '501-2000': 'rank=501-2000', '2001+': 'rank=2001%2B', 'Unranked': 'rank=unranked',
            })[label] || null;

            renderPieChart('complexity', stats.complexity || [], chartUrlMappers.complexity);
            renderPieChart('bestWith', stats.bestWith || [], chartUrlMappers.bestWith);
            renderPieChart('duration', stats.duration || [], chartUrlMappers.duration);
            renderPieChart('hearts', stats.hearts || [], chartUrlMappers.hearts);
            renderRankDistribution(stats.rankDistribution || [], rankUrlMapper);
        }
    }

    // ── Landing page: complexity donut ─────────────────────────────────────
    const landingRoot = document.querySelector('[data-landing-page]');
    if (landingRoot) {
        const complexityCard = landingRoot.querySelector('[data-landing-complexity]');
        if (complexityCard) {
            let complexityData = null;
            try {
                complexityData = JSON.parse(complexityCard.getAttribute('data-landing-complexity') || '[]');
            } catch (e) {
                console.error('Could not parse landing complexity data', e);
            }

            if (Array.isArray(complexityData) && complexityData.length > 0) {
                const ns      = 'http://www.w3.org/2000/svg';
                const svgEl   = tag => document.createElementNS(ns, tag);
                const toNum   = v => Number.isFinite(Number(v)) ? Number(v) : 0;
                const colors  = ['#6ab187', '#e3b04b', '#d36a46', '#a0a9be'];

                const polarToCartesian = (cx, cy, r, rad) => ({
                    x: cx + Math.cos(rad) * r,
                    y: cy + Math.sin(rad) * r,
                });

                const arcPath = (cx, cy, r, startAngle, endAngle) => {
                    const s = polarToCartesian(cx, cy, r, startAngle);
                    const e = polarToCartesian(cx, cy, r, endAngle);
                    const large = endAngle - startAngle > Math.PI ? 1 : 0;
                    return [`M ${cx} ${cy}`, `L ${s.x} ${s.y}`, `A ${r} ${r} 0 ${large} 1 ${e.x} ${e.y}`, 'Z'].join(' ');
                };

                const svg    = complexityCard.querySelector('[data-landing-pie-chart="complexity"]');
                const legend = complexityCard.querySelector('[data-landing-pie-legend="complexity"]');
                if (svg && legend) {
                    const normalized = complexityData.map(item => ({ label: String(item.label || ''), count: toNum(item.count) }));
                    const total = normalized.reduce((sum, item) => sum + item.count, 0);
                    let startAngle = -Math.PI / 2;

                    normalized.forEach((slice, index) => {
                        const color = colors[index % colors.length];
                        const pct   = total > 0 ? Math.round((slice.count / total) * 100) : 0;

                        const li = document.createElement('li');
                        li.className = 'stats-legend__item';
                        li.innerHTML = [
                            `<span class="stats-legend__swatch" style="background:${color}"></span>`,
                            `<span class="stats-legend__label">${slice.label}</span>`,
                            `<span class="stats-legend__count">${slice.count} <span class="stats-legend__pct">(${pct}%)</span></span>`,
                        ].join('');
                        legend.append(li);

                        if (slice.count <= 0) return;

                        const angle    = (slice.count / total) * Math.PI * 2;
                        const endAngle = startAngle + angle;

                        const path = svgEl('path');
                        path.setAttribute('d', arcPath(110, 110, 98, startAngle, endAngle));
                        path.setAttribute('fill', color);
                        path.setAttribute('class', 'stats-pie__slice');
                        path.setAttribute('aria-label', `${slice.label}: ${slice.count} (${pct}%)`);
                        svg.append(path);

                        const midAngle = startAngle + angle / 2;
                        const lp = polarToCartesian(110, 110, 73, midAngle);
                        const showLabel = angle >= 0.22;
                        const showPct   = angle >= 0.42;
                        if (showLabel) {
                            const g = svgEl('g');
                            g.setAttribute('class', 'stats-pie__label-group');
                            g.setAttribute('text-anchor', 'middle');
                            const tCount = svgEl('text');
                            tCount.setAttribute('x', String(lp.x));
                            tCount.setAttribute('y', showPct ? String(lp.y - 7) : String(lp.y));
                            tCount.setAttribute('dominant-baseline', 'middle');
                            tCount.setAttribute('class', 'stats-pie__count');
                            tCount.textContent = String(slice.count);
                            g.append(tCount);
                            if (showPct) {
                                const tPct = svgEl('text');
                                tPct.setAttribute('x', String(lp.x));
                                tPct.setAttribute('y', String(lp.y + 8));
                                tPct.setAttribute('dominant-baseline', 'middle');
                                tPct.setAttribute('class', 'stats-pie__pct');
                                tPct.textContent = `${pct}%`;
                                g.append(tPct);
                            }
                            svg.append(g);
                        }
                        startAngle = endAngle;
                    });

                    // Donut hole
                    const hole = svgEl('circle');
                    hole.setAttribute('cx', '110'); hole.setAttribute('cy', '110'); hole.setAttribute('r', '52');
                    hole.setAttribute('class', 'stats-donut-hole');
                    svg.append(hole);

                    const cVal = svgEl('text');
                    cVal.setAttribute('x', '110'); cVal.setAttribute('y', '106');
                    cVal.setAttribute('text-anchor', 'middle'); cVal.setAttribute('dominant-baseline', 'middle');
                    cVal.setAttribute('class', 'stats-donut__center-value');
                    cVal.textContent = String(total);
                    svg.append(cVal);

                    const cLbl = svgEl('text');
                    cLbl.setAttribute('x', '110'); cLbl.setAttribute('y', '122');
                    cLbl.setAttribute('text-anchor', 'middle'); cLbl.setAttribute('dominant-baseline', 'middle');
                    cLbl.setAttribute('class', 'stats-donut__center-label');
                    cLbl.textContent = 'games';
                    svg.append(cLbl);
                }
            }
        }
    }

    // ── 14-day forecast chart ─────────────────────────────────────────────
    document.querySelectorAll('[data-forecast-chart-data]').forEach(forecastRoot => {
        const chartSvg = forecastRoot.querySelector('[data-forecast-weather-chart]');
        const rawData = forecastRoot.getAttribute('data-forecast-chart-data') || '[]';
        let forecastData = [];

        try {
            const parsed = JSON.parse(rawData);
            if (Array.isArray(parsed)) {
                forecastData = parsed;
            }
        } catch (error) {
            console.error('Could not parse forecast chart data', error);
        }

        if (!(chartSvg && forecastData.length > 0)) {
            return;
        }

        const normalized = forecastData.filter(day => (
            typeof day === 'object' &&
            day !== null &&
            typeof day.date === 'string'
        ));

        if (normalized.length === 0) {
            return;
        }

        const ns = 'http://www.w3.org/2000/svg';
        const svgEl = tag => document.createElementNS(ns, tag);
        const toNumber = value => Number.isFinite(Number(value)) ? Number(value) : null;
        const isMobile = window.matchMedia('(max-width: 640px)').matches;
        const iconSize = isMobile ? 16 : 20;

        const width = 920;
        const height = 360;
        const padding = { top: 34, right: 26, bottom: 62, left: 54 };
        const plotWidth = width - padding.left - padding.right;
        const plotHeight = height - padding.top - padding.bottom;
        const bottomY = height - padding.bottom;

        const temps = normalized.flatMap(day => [toNumber(day.temp_min), toNumber(day.temp_max)]).filter(value => value !== null);
        const precipValues = normalized.map(day => toNumber(day.precip_sum)).filter(value => value !== null);
        const sunValues = normalized.map(day => toNumber(day.sunshine_hours)).filter(value => value !== null);
        if (temps.length === 0) {
            return;
        }

        const minTemp = Math.floor(Math.min(...temps) - 1);
        const maxTemp = Math.ceil(Math.max(...temps) + 1);
        const tempSpan = Math.max(1, maxTemp - minTemp);
        const maxPrecip = Math.max(1, ...precipValues, 0);
        const maxSun = Math.max(1, ...sunValues, 0);
        const barGroupWidth = Math.max(10, Math.min(28, plotWidth / (normalized.length * 1.5)));
        const barWidth = Math.floor(barGroupWidth / 2) - 1;
        const xStep = normalized.length > 1 ? plotWidth / (normalized.length - 1) : 0;
        const xPos = index => padding.left + (xStep * index);
        const yTemp = value => padding.top + ((maxTemp - value) / tempSpan) * plotHeight;
        const yPrecip = value => bottomY - ((value / maxPrecip) * (plotHeight * 0.3));
        const ySun = value => bottomY - ((value / maxSun) * (plotHeight * 0.3));

        chartSvg.innerHTML = '';

        for (let i = 0; i <= 4; i++) {
            const y = padding.top + ((plotHeight / 4) * i);

            const line = svgEl('line');
            line.setAttribute('x1', String(padding.left));
            line.setAttribute('x2', String(width - padding.right));
            line.setAttribute('y1', String(y));
            line.setAttribute('y2', String(y));
            line.setAttribute('class', 'weather-forecast__grid-line');
            chartSvg.append(line);

            const tempTickValue = maxTemp - ((tempSpan / 4) * i);
            const tick = svgEl('text');
            tick.setAttribute('x', String(padding.left - 8));
            tick.setAttribute('y', String(y + 4));
            tick.setAttribute('text-anchor', 'end');
            tick.setAttribute('class', 'weather-forecast__axis-label');
            tick.textContent = `${Math.round(tempTickValue)}°`;
            chartSvg.append(tick);
        }

        // Right-side axis for sun hours
        for (let i = 0; i <= 2; i++) {
            const sunTickVal = Math.round((maxSun / 2) * i);
            const yTick = ySun(sunTickVal);
            const rtick = svgEl('text');
            rtick.setAttribute('x', String(width - padding.right + 6));
            rtick.setAttribute('y', String(yTick + 4));
            rtick.setAttribute('text-anchor', 'start');
            rtick.setAttribute('class', 'weather-forecast__axis-label weather-forecast__axis-label--sun');
            rtick.textContent = `${sunTickVal}h`;
            chartSvg.append(rtick);
        }

        normalized.forEach((day, index) => {
            const precip = toNumber(day.precip_sum) ?? 0;
            const sun = toNumber(day.sunshine_hours) ?? 0;
            const x = xPos(index);

            // Precipitation bar (left of center)
            const py = yPrecip(precip);
            const precipBar = svgEl('rect');
            precipBar.setAttribute('x', String(x - barWidth - 1));
            precipBar.setAttribute('y', String(py));
            precipBar.setAttribute('width', String(barWidth));
            precipBar.setAttribute('height', String(Math.max(0, bottomY - py)));
            precipBar.setAttribute('rx', '2');
            precipBar.setAttribute('class', 'weather-forecast__precip-bar');
            chartSvg.append(precipBar);

            // Sunshine bar (right of center)
            const sy = ySun(sun);
            const sunBar = svgEl('rect');
            sunBar.setAttribute('x', String(x + 1));
            sunBar.setAttribute('y', String(sy));
            sunBar.setAttribute('width', String(barWidth));
            sunBar.setAttribute('height', String(Math.max(0, bottomY - sy)));
            sunBar.setAttribute('rx', '2');
            sunBar.setAttribute('class', 'weather-forecast__sun-bar');
            chartSvg.append(sunBar);
        });

        const linePath = key => {
            let d = '';
            normalized.forEach((day, index) => {
                const value = toNumber(day[key]);
                if (value === null) {
                    return;
                }
                const x = xPos(index);
                const y = yTemp(value);
                d += d === '' ? `M ${x} ${y}` : ` L ${x} ${y}`;
            });
            return d;
        };

        const maxLine = svgEl('path');
        maxLine.setAttribute('d', linePath('temp_max'));
        maxLine.setAttribute('class', 'weather-forecast__line weather-forecast__line--max');
        chartSvg.append(maxLine);

        const minLine = svgEl('path');
        minLine.setAttribute('d', linePath('temp_min'));
        minLine.setAttribute('class', 'weather-forecast__line weather-forecast__line--min');
        chartSvg.append(minLine);

        normalized.forEach((day, index) => {
            const x = xPos(index);
            const maxTempValue = toNumber(day.temp_max);
            const minTempValue = toNumber(day.temp_min);

            if (maxTempValue !== null) {
                const point = svgEl('circle');
                point.setAttribute('cx', String(x));
                point.setAttribute('cy', String(yTemp(maxTempValue)));
                point.setAttribute('r', '3.2');
                point.setAttribute('class', 'weather-forecast__point weather-forecast__point--max');
                chartSvg.append(point);
            }

            if (minTempValue !== null) {
                const point = svgEl('circle');
                point.setAttribute('cx', String(x));
                point.setAttribute('cy', String(yTemp(minTempValue)));
                point.setAttribute('r', '3.2');
                point.setAttribute('class', 'weather-forecast__point weather-forecast__point--min');
                chartSvg.append(point);
            }

            const showIcon = !isMobile || index % 2 === 0;
            if (showIcon && typeof day.icon_url === 'string' && day.icon_url !== '') {
                const icon = svgEl('image');
                const iconYBase = maxTempValue !== null ? yTemp(maxTempValue) - iconSize - 8 : padding.top;
                icon.setAttribute('x', String(x - (iconSize / 2)));
                icon.setAttribute('y', String(Math.max(2, iconYBase)));
                icon.setAttribute('width', String(iconSize));
                icon.setAttribute('height', String(iconSize));
                icon.setAttribute('href', day.icon_url);
                icon.setAttribute('class', 'weather-forecast__icon-mark');
                icon.setAttribute('preserveAspectRatio', 'xMidYMid meet');
                chartSvg.append(icon);
            }

            const label = svgEl('text');
            label.setAttribute('x', String(x));
            label.setAttribute('y', String(height - 24));
            label.setAttribute('text-anchor', 'middle');
            label.setAttribute('class', 'weather-forecast__date-label');
            label.textContent = day.date.slice(8, 10) + '.' + day.date.slice(5, 7) + '.';
            chartSvg.append(label);
        });
    });

    // ── Trip comparison metric bars (5-year) ─────────────────────────────
    document.querySelectorAll('[data-trip-compare-metrics]').forEach(compareRoot => {
        const rawData = compareRoot.getAttribute('data-trip-compare-metrics') || '{}';
        const barsRoot = compareRoot.querySelector('[data-trip-compare-bars]');
        const metaRoot = compareRoot.querySelector('[data-trip-compare-metric-meta]');
        const buttons = Array.from(compareRoot.querySelectorAll('[data-trip-compare-metric-btn]'));
        let metrics = {};

        try {
            const parsed = JSON.parse(rawData);
            if (parsed && typeof parsed === 'object') {
                metrics = parsed;
            }
        } catch (error) {
            console.error('Could not parse trip comparison metric data', error);
        }

        if (!barsRoot || !metaRoot || buttons.length === 0 || Object.keys(metrics).length === 0) {
            return;
        }

        const formatValue = (value, unit, decimals) => {
            if (!Number.isFinite(Number(value))) {
                return '—';
            }
            return `${Number(value).toFixed(decimals)} ${unit}`;
        };

        const renderMetric = metricKey => {
            const metric = metrics[metricKey];
            if (!metric || !Array.isArray(metric.rows)) {
                return;
            }

            const validValues = metric.rows
                .map(row => Number(row.value))
                .filter(value => Number.isFinite(value));
            const maxValue = validValues.length > 0 ? Math.max(...validValues) : 1;

            const sortedRows = [...metric.rows].sort((a, b) => {
                const aVal = Number(a.value);
                const bVal = Number(b.value);
                const aOk = Number.isFinite(aVal);
                const bOk = Number.isFinite(bVal);
                if (!aOk && !bOk) return 0;
                if (!aOk) return 1;
                if (!bOk) return -1;
                return metric.higherIsBetter ? (bVal - aVal) : (aVal - bVal);
            });

            barsRoot.innerHTML = '';
            sortedRows.forEach(row => {
                const value = Number(row.value);
                const hasValue = Number.isFinite(value);
                const width = hasValue && maxValue > 0 ? Math.max(4, Math.round((value / maxValue) * 100)) : 0;
                const isWinner = String(row.year) === String(metric.winner);

                const rowEl = document.createElement('div');
                rowEl.className = `weather-trip-compare__bar-row${isWinner ? ' is-winner' : ''}`;

                const yearEl = document.createElement('span');
                yearEl.className = 'weather-trip-compare__bar-year';
                yearEl.textContent = String(row.year);

                const trackEl = document.createElement('div');
                trackEl.className = 'weather-trip-compare__bar-track';

                const fillEl = document.createElement('span');
                fillEl.className = 'weather-trip-compare__bar-fill';
                fillEl.style.width = `${width}%`;
                trackEl.append(fillEl);

                const valueEl = document.createElement('span');
                valueEl.className = 'weather-trip-compare__bar-value';
                valueEl.textContent = formatValue(value, metric.unit, Number(metric.decimals ?? 1));

                rowEl.append(yearEl, trackEl, valueEl);
                barsRoot.append(rowEl);
            });

            const winnerText = metric.winner ? `Winner: ${metric.winner}` : 'Winner: —';
            const directionText = metric.higherIsBetter ? 'higher is better' : 'lower is better';
            metaRoot.textContent = `${metric.label} (${directionText}) · ${winnerText}`;

            buttons.forEach(button => {
                const active = button.getAttribute('data-trip-compare-metric-btn') === metricKey;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        };

        const initialButton = buttons.find(button => button.classList.contains('is-active')) || buttons[0];
        const initialMetric = initialButton.getAttribute('data-trip-compare-metric-btn') || 'avgTemp';
        renderMetric(initialMetric);

        buttons.forEach(button => {
            button.addEventListener('click', () => {
                const metricKey = button.getAttribute('data-trip-compare-metric-btn');
                if (!metricKey) {
                    return;
                }
                renderMetric(metricKey);
            });
        });
    });

    // ── Trip overlay chart (5-year comparison) ───────────────────────────
    (function renderTripOverlayChart() {
        const chartSvg = document.querySelector('[data-trip-overlay-chart]');
        const overlayData = window.tripOverlayData || {};
        if (!chartSvg || !overlayData || Object.keys(overlayData).length === 0) return;

        const width = 860;
        const height = 360;
        chartSvg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        const ns = 'http://www.w3.org/2000/svg';
        const svgEl = tag => document.createElementNS(ns, tag);
        const toNumber = v => Number.isFinite(Number(v)) ? Number(v) : null;

        // Same padding as individual trip charts
        const padding = { top: 34, right: 26, bottom: 62, left: 54 };
        const plotWidth = width - padding.left - padding.right;
        const plotHeight = height - padding.top - padding.bottom;
        const bottomY = height - padding.bottom;

        const yearColors = {
            '2025': '#e74c3c',
            '2024': '#f39c12',
            '2023': '#27ae60',
            '2022': '#2980b9',
            '2021': '#8e44ad',
        };

        // Find global min/max for y axis
        let allTemps = [];
        Object.values(overlayData).forEach(arr => {
            arr.forEach(v => { if (v !== null) allTemps.push(Number(v)); });
        });
        if (allTemps.length === 0) return;
        const minTemp = Math.floor(Math.min(...allTemps) - 1);
        const maxTemp = Math.ceil(Math.max(...allTemps) + 1);
        const tempSpan = Math.max(1, maxTemp - minTemp);

        const maxDays = Math.max(...Object.values(overlayData).map(arr => arr.length));
        const xStep = maxDays > 1 ? plotWidth / (maxDays - 1) : 0;
        const xPos = i => padding.left + (xStep * i);
        const yTemp = v => padding.top + ((maxTemp - v) / tempSpan) * plotHeight;

        chartSvg.innerHTML = '';

        // Grid lines and y axis labels
        for (let i = 0; i <= 4; i++) {
            const y = padding.top + ((plotHeight / 4) * i);
            const line = svgEl('line');
            line.setAttribute('x1', String(padding.left));
            line.setAttribute('x2', String(width - padding.right));
            line.setAttribute('y1', String(y));
            line.setAttribute('y2', String(y));
            line.setAttribute('class', 'weather-trip__grid-line');
            chartSvg.append(line);

            const tempTickValue = maxTemp - ((tempSpan / 4) * i);
            const tick = svgEl('text');
            tick.setAttribute('x', String(padding.left - 8));
            tick.setAttribute('y', String(y + 4));
            tick.setAttribute('text-anchor', 'end');
            tick.setAttribute('class', 'weather-trip__axis-label');
            tick.textContent = `${Math.round(tempTickValue)}°`;
            chartSvg.append(tick);
        }

        // X axis day labels
        for (let i = 0; i < maxDays; i++) {
            const label = svgEl('text');
            label.setAttribute('x', String(xPos(i)));
            label.setAttribute('y', String(height - 24));
            label.setAttribute('text-anchor', 'middle');
            label.setAttribute('class', 'weather-trip__date-label');
            label.textContent = `Day ${i + 1}`;
            chartSvg.append(label);
        }

        // Draw each year's temperature line with points
        Object.entries(overlayData).forEach(([year, arr]) => {
            const color = yearColors[year] || '#888';
            let d = '';
            const points = [];
            arr.forEach((v, i) => {
                if (v === null) return;
                const x = xPos(i);
                const y = yTemp(v);
                d += d === '' ? `M ${x} ${y}` : ` L ${x} ${y}`;
                points.push({ x, y });
            });
            if (!d) return;

            const path = svgEl('path');
            path.setAttribute('d', d);
            path.setAttribute('stroke', color);
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke-width', '2.5');
            path.setAttribute('stroke-linejoin', 'round');
            path.setAttribute('stroke-linecap', 'round');
            path.setAttribute('class', 'weather-trip__line');
            path.setAttribute('data-year', year);
            chartSvg.append(path);

            points.forEach(pt => {
                const circle = svgEl('circle');
                circle.setAttribute('cx', String(pt.x));
                circle.setAttribute('cy', String(pt.y));
                circle.setAttribute('r', '3.6');
                circle.setAttribute('fill', color);
                circle.setAttribute('stroke', '#fff');
                circle.setAttribute('stroke-width', '1.5');
                circle.setAttribute('class', 'weather-trip__point');
                chartSvg.append(circle);
            });
        });
    })();

    // ── Trip weather chart (historical) ───────────────────────────────────
    document.querySelectorAll('[data-trip-weather-chart-data]').forEach(tripWeatherRoot => {
        const chartSvg = tripWeatherRoot.querySelector('[data-trip-weather-chart]');
        const rawData = tripWeatherRoot.getAttribute('data-trip-weather-chart-data') || '[]';
        let tripData = [];

        try {
            const parsed = JSON.parse(rawData);
            if (Array.isArray(parsed)) {
                tripData = parsed;
            }
        } catch (error) {
            console.error('Could not parse trip weather chart data', error);
        }

        if (!(chartSvg && tripData.length > 0)) {
            return;
        }

        const normalized = tripData.filter(day => (
            typeof day === 'object' &&
            day !== null &&
            typeof day.date === 'string'
        ));

        if (normalized.length === 0) {
            return;
        }

        const ns = 'http://www.w3.org/2000/svg';
        const svgEl = tag => document.createElementNS(ns, tag);
        const toNumber = value => Number.isFinite(Number(value)) ? Number(value) : null;
        const iconSize = window.matchMedia('(max-width: 640px)').matches ? 18 : 24;

        const width = 860;
        const height = 360;
        const padding = { top: 34, right: 26, bottom: 62, left: 54 };
        const plotWidth = width - padding.left - padding.right;
        const plotHeight = height - padding.top - padding.bottom;
        const bottomY = height - padding.bottom;

        const temps = normalized.flatMap(day => [toNumber(day.temp_min), toNumber(day.temp_max)]).filter(value => value !== null);
        const precipValues = normalized.map(day => toNumber(day.precip_sum)).filter(value => value !== null);
        const sunValues = normalized.map(day => toNumber(day.sunshine_hours)).filter(value => value !== null);

        if (temps.length === 0) {
            return;
        }

        const minTemp = Math.floor(Math.min(...temps) - 1);
        const maxTemp = Math.ceil(Math.max(...temps) + 1);
        const tempSpan = Math.max(1, maxTemp - minTemp);
        const maxPrecip = Math.max(1, ...precipValues, 0);
        const maxSun = Math.max(1, ...sunValues, 0);
        const barGroupWidth = Math.max(16, Math.min(44, plotWidth / (normalized.length * 1.5)));
        const barWidth = Math.floor(barGroupWidth / 2) - 1;
        const xStep = normalized.length > 1 ? plotWidth / (normalized.length - 1) : 0;
        const xPos = index => padding.left + (xStep * index);
        const yTemp = value => padding.top + ((maxTemp - value) / tempSpan) * plotHeight;
        const yPrecip = value => bottomY - ((value / maxPrecip) * (plotHeight * 0.34));
        const ySun = value => bottomY - ((value / maxSun) * (plotHeight * 0.34));

        chartSvg.innerHTML = '';

        for (let i = 0; i <= 4; i++) {
            const y = padding.top + ((plotHeight / 4) * i);

            const line = svgEl('line');
            line.setAttribute('x1', String(padding.left));
            line.setAttribute('x2', String(width - padding.right));
            line.setAttribute('y1', String(y));
            line.setAttribute('y2', String(y));
            line.setAttribute('class', 'weather-trip__grid-line');
            chartSvg.append(line);

            const tempTickValue = maxTemp - ((tempSpan / 4) * i);
            const tick = svgEl('text');
            tick.setAttribute('x', String(padding.left - 8));
            tick.setAttribute('y', String(y + 4));
            tick.setAttribute('text-anchor', 'end');
            tick.setAttribute('class', 'weather-trip__axis-label');
            tick.textContent = `${Math.round(tempTickValue)}°`;
            chartSvg.append(tick);
        }

        // Right-side axis for sun hours
        for (let i = 0; i <= 2; i++) {
            const sunTickVal = Math.round((maxSun / 2) * i);
            const yTick = ySun(sunTickVal);
            const rtick = svgEl('text');
            rtick.setAttribute('x', String(width - padding.right + 6));
            rtick.setAttribute('y', String(yTick + 4));
            rtick.setAttribute('text-anchor', 'start');
            rtick.setAttribute('class', 'weather-trip__axis-label weather-trip__axis-label--sun');
            rtick.textContent = `${sunTickVal}h`;
            chartSvg.append(rtick);
        }

        normalized.forEach((day, index) => {
            const precip = toNumber(day.precip_sum) ?? 0;
            const sun = toNumber(day.sunshine_hours) ?? 0;
            const x = xPos(index);

            // Precipitation bar (left of center)
            const py = yPrecip(precip);
            const precipBar = svgEl('rect');
            precipBar.setAttribute('x', String(x - barWidth - 1));
            precipBar.setAttribute('y', String(py));
            precipBar.setAttribute('width', String(barWidth));
            precipBar.setAttribute('height', String(Math.max(0, bottomY - py)));
            precipBar.setAttribute('rx', '2');
            precipBar.setAttribute('class', 'weather-trip__precip-bar');
            chartSvg.append(precipBar);

            // Sunshine bar (right of center)
            const sy = ySun(sun);
            const sunBar = svgEl('rect');
            sunBar.setAttribute('x', String(x + 1));
            sunBar.setAttribute('y', String(sy));
            sunBar.setAttribute('width', String(barWidth));
            sunBar.setAttribute('height', String(Math.max(0, bottomY - sy)));
            sunBar.setAttribute('rx', '2');
            sunBar.setAttribute('class', 'weather-trip__sun-bar');
            chartSvg.append(sunBar);
        });

        const linePath = key => {
            let d = '';
            normalized.forEach((day, index) => {
                const value = toNumber(day[key]);
                if (value === null) {
                    return;
                }
                const x = xPos(index);
                const y = yTemp(value);
                d += d === '' ? `M ${x} ${y}` : ` L ${x} ${y}`;
            });
            return d;
        };

        const maxLine = svgEl('path');
        maxLine.setAttribute('d', linePath('temp_max'));
        maxLine.setAttribute('class', 'weather-trip__line weather-trip__line--max');
        chartSvg.append(maxLine);

        const minLine = svgEl('path');
        minLine.setAttribute('d', linePath('temp_min'));
        minLine.setAttribute('class', 'weather-trip__line weather-trip__line--min');
        chartSvg.append(minLine);

        normalized.forEach((day, index) => {
            const x = xPos(index);
            const maxTempValue = toNumber(day.temp_max);
            const minTempValue = toNumber(day.temp_min);

            if (maxTempValue !== null) {
                const point = svgEl('circle');
                point.setAttribute('cx', String(x));
                point.setAttribute('cy', String(yTemp(maxTempValue)));
                point.setAttribute('r', '3.6');
                point.setAttribute('class', 'weather-trip__point weather-trip__point--max');
                chartSvg.append(point);
            }

            if (minTempValue !== null) {
                const point = svgEl('circle');
                point.setAttribute('cx', String(x));
                point.setAttribute('cy', String(yTemp(minTempValue)));
                point.setAttribute('r', '3.6');
                point.setAttribute('class', 'weather-trip__point weather-trip__point--min');
                chartSvg.append(point);
            }

            if (typeof day.icon_url === 'string' && day.icon_url !== '') {
                const icon = svgEl('image');
                const iconYBase = maxTempValue !== null ? yTemp(maxTempValue) - iconSize - 8 : padding.top;
                icon.setAttribute('x', String(x - (iconSize / 2)));
                icon.setAttribute('y', String(Math.max(2, iconYBase)));
                icon.setAttribute('width', String(iconSize));
                icon.setAttribute('height', String(iconSize));
                icon.setAttribute('href', day.icon_url);
                icon.setAttribute('class', 'weather-trip__icon-mark');
                icon.setAttribute('preserveAspectRatio', 'xMidYMid meet');
                chartSvg.append(icon);
            }

            const label = svgEl('text');
            label.setAttribute('x', String(x));
            label.setAttribute('y', String(height - 24));
            label.setAttribute('text-anchor', 'middle');
            label.setAttribute('class', 'weather-trip__date-label');
            label.textContent = day.date.slice(8, 10) + '.' + day.date.slice(5, 7) + '.';
            chartSvg.append(label);
        });
    });

    // ── Collection filters ─────────────────────────────────────────────────
    const collectionGrid = document.querySelector('[data-collection-grid]');
    if (collectionGrid) {
        const countEl = document.querySelector('[data-collection-count]');
        let complexityFilter = '';
        let rankFilter = '';
        let categoryFilter = '';
        let mechanicFilter = '';
        let designerFilter = '';
        let heartsFilter = '';

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
                const rank = Number(card.dataset.rank);
                const cats = String(card.dataset.categories || '');
                const mechs = String(card.dataset.mechanics || '');
                const designers = String(card.dataset.designers || '');
                const hearts = Number(card.dataset.hearts);

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
                        const [mptMin, mptMax] = mptSel.split('-').map(Number);
                        show = show && (mpt >= mptMin && mpt <= mptMax);
                    }
                }
                if (complexityFilter === 'light')   { show = show && (cx > 0 && cx <= 1.8); }
                if (complexityFilter === 'medium')  { show = show && (cx > 1.8 && cx <= 3.0); }
                if (complexityFilter === 'complex') { show = show && (cx > 3.0); }

                if (rankFilter === 'top100')   { show = show && (rank > 0 && rank <= 100); }
                if (rankFilter === '101-500')  { show = show && (rank > 100 && rank <= 500); }
                if (rankFilter === '501-2000') { show = show && (rank > 500 && rank <= 2000); }
                if (rankFilter === '2001+')    { show = show && (rank > 2000); }
                if (rankFilter === 'unranked') { show = show && (rank <= 0); }

                if (categoryFilter !== '') {
                    show = show && cats.split(',').map(s => s.trim()).includes(categoryFilter);
                }
                if (mechanicFilter !== '') {
                    show = show && mechs.split(',').map(s => s.trim()).includes(mechanicFilter);
                }
                if (designerFilter !== '') {
                    show = show && designers.split(',').map(s => s.trim()).includes(designerFilter);
                }
                if (heartsFilter === '0')   { show = show && (hearts <= 0); }
                if (heartsFilter === '1-2') { show = show && (hearts >= 1 && hearts <= 2); }
                if (heartsFilter === '3-5') { show = show && (hearts >= 3 && hearts <= 5); }
                if (heartsFilter === '6+')  { show = show && (hearts >= 6); }

                card.hidden = !show;
                if (show) visible++;
            });

            if (countEl) {
                countEl.textContent = `${visible} game${visible !== 1 ? 's' : ''}`;
            }
        };

        document.querySelectorAll('[data-collection-filter-group]').forEach(group => {
            group.querySelectorAll('.filter-seg').forEach(btn => {
                btn.addEventListener('click', () => {
                    const filterName = group.dataset.collectionFilterGroup;
                    const input = document.querySelector(`[data-collection-filter="${filterName}"]`);

                    if (!filterName || !input) {
                        return;
                    }

                    group.querySelectorAll('.filter-seg').forEach(seg => {
                        seg.classList.remove('filter-seg--active');
                    });

                    btn.classList.add('filter-seg--active');
                    input.value = btn.dataset.value || '';
                    filterCards();
                });
            });
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
            document.querySelectorAll('[data-collection-filter-group]').forEach(group => {
                group.querySelectorAll('.filter-seg').forEach((seg, i) => {
                    seg.classList.toggle('filter-seg--active', i === 0);
                });
            });
            complexityFilter = '';
            rankFilter = '';
            categoryFilter = '';
            mechanicFilter = '';
            designerFilter = '';
            heartsFilter = '';
            document.querySelectorAll('[data-complexity-filters] .filter-seg').forEach((b, i) => {
                b.classList.toggle('filter-seg--active', i === 0);
            });
            filterCards();
        });

        // ── Apply URL params on page load ─────────────────────────────────
        const collectionParams = new URLSearchParams(window.location.search);

        const urlComplexity = collectionParams.get('complexity') || '';
        if (['light', 'medium', 'complex'].includes(urlComplexity)) {
            complexityFilter = urlComplexity;
            document.querySelectorAll('[data-complexity-filters] .filter-seg').forEach(b => {
                b.classList.toggle('filter-seg--active', (b.dataset.complexity || '') === urlComplexity);
            });
        }

        const urlBestPlayers = collectionParams.get('bestplayers') || '';
        if (urlBestPlayers !== '') {
            const bpInput = document.querySelector('[data-collection-filter="bestplayers"]');
            if (bpInput) {
                bpInput.value = urlBestPlayers;
                document.querySelector('[data-collection-filter-group="bestplayers"]')?.querySelectorAll('.filter-seg').forEach(seg => {
                    seg.classList.toggle('filter-seg--active', (seg.dataset.value || '') === urlBestPlayers);
                });
            }
        }

        const urlMaxPlaytime = collectionParams.get('maxplaytime') || '';
        if (urlMaxPlaytime !== '') {
            const mptInput = document.querySelector('[data-collection-filter="maxplaytime"]');
            if (mptInput) {
                mptInput.value = urlMaxPlaytime;
                document.querySelector('[data-collection-filter-group="maxplaytime"]')?.querySelectorAll('.filter-seg').forEach(seg => {
                    seg.classList.toggle('filter-seg--active', (seg.dataset.value || '') === urlMaxPlaytime);
                });
            }
        }

        rankFilter     = collectionParams.get('rank')     || '';
        categoryFilter = collectionParams.get('category') || '';
        mechanicFilter = collectionParams.get('mechanic') || '';
        designerFilter = collectionParams.get('designer') || '';
        heartsFilter   = collectionParams.get('hearts')   || '';

        filterCards();
    }

    // ── Rankings complexity filter ─────────────────────────────────────────
    const rankingsGrid = document.querySelector('[data-rankings-grid]');
    if (rankingsGrid) {
        const rankingsCountEl = document.querySelector('[data-rankings-count]');
        let rankComplexityFilter = '';

        const filterRankings = () => {
            let position = 0;
            rankingsGrid.querySelectorAll('.game-card').forEach(card => {
                const cx = Number(card.dataset.complexity);
                let show = true;
                if (rankComplexityFilter === 'light')   { show = cx > 0 && cx <= 1.8; }
                if (rankComplexityFilter === 'medium')  { show = cx > 1.8 && cx <= 3.0; }
                if (rankComplexityFilter === 'complex') { show = cx > 3.0; }
                card.hidden = !show;
                if (show) {
                    position++;
                    const pos = card.querySelector('.rankings__position');
                    if (pos) { pos.textContent = `🏆 #${position}`; }
                }
            });
            if (rankingsCountEl) {
                rankingsCountEl.textContent = `${position} game${position !== 1 ? 's' : ''}`;
            }
        };

        document.querySelectorAll('[data-rankings-complexity] .filter-seg').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('[data-rankings-complexity] .filter-seg').forEach(b => {
                    b.classList.remove('filter-seg--active');
                });
                btn.classList.add('filter-seg--active');
                rankComplexityFilter = btn.dataset.complexity || '';
                filterRankings();
            });
        });

        filterRankings();
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
