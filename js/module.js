(() => {
    'use strict';

    const MIN_DELAY = 1200;
    const MAX_DELAY = 25000;
    const HIDDEN_DELAY = 5000;
    const TERMINAL_STATUSES = new Set(['finished', 'failed', 'completed']);

    /**
     * Refresh the owning Livewire surface once after a watched task becomes terminal.
     *
     * @param {HTMLElement} row Active task-backed row
     * @returns {void}
     */
    function refreshSurface(row) {
        const componentRoot = row.closest('[wire\\:id]');
        const componentId = componentRoot?.getAttribute('wire:id');

        if (componentId && window.Livewire?.find) {
            window.Livewire.find(componentId)?.$refresh();
        }
    }

    /**
     * Apply a progress snapshot to the row without re-rendering the dashboard.
     *
     * @param {HTMLElement} row Active task-backed row
     * @param {object} snapshot Progress endpoint response
     * @returns {void}
     */
    function applySnapshot(row, snapshot) {
        const progress = Math.max(0, Math.min(100, Number(snapshot.progress) || 0));
        const progressCell = row.querySelector('[data-stask-progress-cell], .stask-task-progress-cell');

        row.style.setProperty('--stask-task-progress', `${progress}%`);
        if (progressCell) {
            progressCell.textContent = `${progress}%`;
        }
    }

    /**
     * Start the 1.x-style adaptive watcher for one active task row.
     *
     * The watcher reads the filesystem-backed progress endpoint, never overlaps
     * requests, backs off exponentially while unchanged, pauses when hidden, and
     * stops immediately after a terminal status or repeated network failures.
     *
     * @param {HTMLElement} row Active task-backed row
     * @returns {void}
     */
    function watchRow(row) {
        if (row.dataset.staskWatcherStarted === '1') {
            return;
        }

        row.dataset.staskWatcherStarted = '1';

        let delay = MIN_DELAY;
        let failures = 0;
        let lastSignature = '';
        let stopped = false;
        let timer = null;

        const stop = () => {
            stopped = true;
            if (timer !== null) {
                window.clearTimeout(timer);
                timer = null;
            }
        };

        const schedule = (nextDelay) => {
            if (!stopped) {
                timer = window.setTimeout(loop, nextDelay);
            }
        };

        async function loop() {
            if (stopped || !row.isConnected) {
                stop();
                return;
            }

            if (document.hidden || row.offsetParent === null) {
                schedule(HIDDEN_DELAY);
                return;
            }

            try {
                const response = await window.fetch(row.dataset.staskProgressUrl, {
                    cache: 'no-store',
                    headers: {'Accept': 'application/json'},
                });

                if (!response.ok) {
                    if (response.status === 404) {
                        delay = Math.min(MAX_DELAY, Math.max(3000, Math.round(delay * 1.8)));
                        schedule(delay);
                        return;
                    }
                    throw new Error(`Progress request failed with ${response.status}`);
                }

                const snapshot = await response.json();
                const signature = JSON.stringify([
                    snapshot.status,
                    snapshot.progress,
                    snapshot.processed,
                    snapshot.total,
                    snapshot.message,
                ]);
                const changed = lastSignature === '' || signature !== lastSignature;

                applySnapshot(row, snapshot);
                failures = 0;
                lastSignature = signature;

                if (TERMINAL_STATUSES.has(snapshot.status)) {
                    stop();
                    refreshSurface(row);
                    return;
                }

                delay = changed
                    ? MIN_DELAY
                    : Math.min(MAX_DELAY, Math.round(delay * 1.8));
            } catch (error) {
                failures += 1;
                delay = Math.min(MAX_DELAY, Math.max(3000, Math.round(delay * 1.8)));

                if (failures >= 5) {
                    stop();
                    return;
                }
            }

            schedule(delay);
        }

        schedule(0);
    }

    /** Discover active rows added by the initial page or a Livewire morph. */
    function scan(root = document) {
        root.querySelectorAll?.('[data-stask-progress-url]').forEach(watchRow);
    }

    function boot() {
        scan();

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.matches?.('[data-stask-progress-url]')) {
                            watchRow(node);
                        }
                        scan(node);
                    }
                });
            });
        });

        observer.observe(document.body, {childList: true, subtree: true});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, {once: true});
    } else {
        boot();
    }
})();
