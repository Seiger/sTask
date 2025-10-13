window.sTask = window.sTask || {};

/**
 * Auto-initialize lucide after load
 */
window.addEventListener('DOMContentLoaded', () => {
    if (window.lucide?.createIcons) {
        lucide.createIcons();
    } else {
        const lucideScript = document.querySelector('script[src*="lucide"]');
        if (lucideScript) {
            lucideScript.addEventListener('load', () => {
                lucide.createIcons();
                document.dispatchEvent(new Event('lucide:ready'));
            });
        }
    }
});

/**
 * Handle pinning and hover behavior.
 */
window.sTask.sPinner = function sPinner(key) {
    const saved = localStorage.getItem(key) === 'true';
    return {
        pinned: saved,
        open: saved,
        skipLeave: false,
        togglePin() {
            this.pinned = !this.pinned;
            this.open = this.pinned;
            this.skipLeave = true;
            setTimeout(() => this.skipLeave = false, 50);
            localStorage.setItem(key, this.pinned);
            window.sTask.queueLucide();
        },
        handleEnter() {
            if (!this.pinned) {
                this.open = true;
                window.sTask.queueLucide();
            }
        },
        handleLeave() {
            if (this.skipLeave) return;
            if (!this.pinned) {
                this.open = false;
                window.sTask.queueLucide();
            }
        },
    }
}

/**
 * Queue Lucide icon rendering.
 */
window.sTask.queueLucide = function queueLucide() {
    if (window.lucide?.createIcons) {
        lucide.createIcons();
    } else {
        document.addEventListener('lucide:ready', () => {
            lucide.createIcons();
        }, {once: true});
    }
}

/**
 * Makes a Fetch API call with robust error handling.
 *
 * @param {string} url - The endpoint URL.
 * @param {FormData|object|null} form - The form data or null.
 * @param {string} [method='POST'] - HTTP method.
 * @param {string} [type='json'] - Response type: json, text, blob, formData, arrayBuffer.
 * @returns {Promise<any|null>} - Parsed response or null on failure.
 */
window.sTask.callApi = async function callApi(url, form = null, method = 'POST', type = 'json', headers = {}) {
    try {
        const finalHeaders = {
            'X-Requested-With': 'XMLHttpRequest',
            ...headers
        };

        let body = form;

        if (form instanceof FormData && ['DELETE', 'PUT'].includes(method.toUpperCase())) {
            const jsonObject = {};
            for (const [key, value] of form.entries()) {
                jsonObject[key] = value;
            }
            body = JSON.stringify(jsonObject);
            finalHeaders['Content-Type'] = 'application/json';
        }

        const response = await fetch(url, {
            method,
            cache: 'no-store',
            headers: finalHeaders,
            body
        });

        if (!response.ok) {
            if (response.status === 404) throw new Error('404, Not Found');
            if (response.status === 500) throw new Error('500, Internal Server Error');
            throw new Error(`HTTP error: ${response.status}`);
        }

        switch (type) {
            case 'text': return await response.text();
            case 'json': return await response.json();
            case 'blob': return await response.blob();
            case 'formData': return await response.formData();
            case 'arrayBuffer': return await response.arrayBuffer();
            default: throw new Error('Unsupported response type');
        }
    } catch (error) {
        console.error('Request failed:', error);
        return null;
    }
}
