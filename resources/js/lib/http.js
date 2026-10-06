/**
 * Same-origin requests to the Laravel backend only. The browser never talks
 * to api.paystack.co (the CSP connect-src enforces this too).
 */
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export async function request(method, url, body = undefined) {
    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
    };

    let payload;
    if (body instanceof FormData) {
        payload = body;
        if (method !== 'POST') {
            body.append('_method', method);
            method = 'POST';
        }
    } else if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    let response;
    try {
        response = await fetch(url, { method, headers, body: payload, credentials: 'same-origin' });
    } catch (error) {
        return { ok: false, status: 0, data: { message: 'Network error: could not reach the explorer server.' } };
    }

    let data = null;
    const text = await response.text();
    try {
        data = text ? JSON.parse(text) : null;
    } catch {
        data = { message: text.slice(0, 500) };
    }

    if (response.status === 419) {
        data = { message: 'Your session expired. Reload the page and sign in again.', code: 'session_expired' };
    } else if (response.status === 429 && !data?.code) {
        const retry = response.headers.get('Retry-After');
        data = {
            message: `Explorer rate limit reached. ${retry ? `Try again in ${retry} seconds.` : 'Slow down and try again shortly.'}`,
            code: 'explorer_rate_limited',
        };
    }

    return { ok: response.ok, status: response.status, data };
}
