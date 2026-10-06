import { request } from '../lib/http';
import { copyText } from '../lib/clipboard';
import { highlightJson, parseJson, pretty } from '../lib/json';

/**
 * Signature checker: the payload and signature go to the server, which
 * computes HMAC-SHA512 with the configured key and returns valid/invalid.
 */
export function signatureVerifier() {
    return {
        payload: '',
        signature: '',
        country: '',
        environment: 'test',
        result: null,
        loading: false,

        init() {
            this.country = this.$el.dataset.country ?? '';
        },

        async verify() {
            this.loading = true;
            const response = await request('POST', this.$el.dataset.url, {
                payload: this.payload,
                signature: this.signature,
                country: this.country,
                environment: this.environment,
            });
            this.loading = false;
            this.result = response.ok
                ? response.data
                : { valid: false, note: response.data?.message ?? 'Verification failed.' };
        },
    };
}

/**
 * Small helper used on pages that render a stored JSON document.
 */
export function jsonBlock() {
    return {
        html: '',
        text: '',
        copied: false,

        init() {
            const source = this.$el.querySelector('script[type="application/json"]');
            const parsed = parseJson(source?.textContent ?? '');
            this.text = parsed.ok && parsed.value !== undefined ? pretty(parsed.value) : (source?.textContent ?? '');
            this.html = highlightJson(this.text);
        },

        copy() {
            copyText(this.text).then(() => {
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 1500);
            });
        },
    };
}

export function copyButton() {
    return {
        copied: false,
        copy(text) {
            copyText(text).then(() => {
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 1500);
            });
        },
    };
}
