import { request } from '../lib/http';
import { copyText, downloadText, readJsonIsland } from '../lib/clipboard';
import { buildCurl, buildRequestParts, buildUrl } from '../lib/curl';
import { highlightJson, parseJson, pretty, renderJsonTree, templateFor } from '../lib/json';

const AMOUNT_FIELDS = ['amount', 'price', 'refund_amount'];
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * The interactive request/response console for one endpoint. The form is
 * generated from the registry definition (endpoint-definition island); the
 * request is sent to Laravel, which validates again, attaches the key and
 * calls Paystack.
 */
export default () => ({
    def: { parameters: [] },
    config: {},
    values: {},
    files: {},
    errors: {},
    maskedFields: [],
    showOptional: false,

    loading: false,
    result: null,
    failure: null,
    responseTab: 'pretty',
    requestTab: 'form',

    liveConfirmOpen: false,
    liveConfirmText: '',

    isFavorite: false,
    saveOpen: false,
    saveName: '',
    saveMessage: null,
    toast: null,

    bankSearch: '',
    bankPage: 1,
    bankPerPage: 20,
    tableSearch: '',
    tablePage: 1,

    init() {
        this.def = readJsonIsland('endpoint-definition', { parameters: [] });
        this.config = readJsonIsland('endpoint-config', {});
        const prefill = readJsonIsland('endpoint-prefill', { params: {}, masked: [] });
        this.isFavorite = Boolean(this.$el.dataset.favorite === '1');
        this.maskedFields = prefill.masked ?? [];

        this.values = this.initialValues(prefill.params ?? {});

        const optionalPrefilled = this.optionalParams.some((p) => (prefill.params ?? {})[p.name] !== undefined);
        this.showOptional = optionalPrefilled || this.optionalParams.length <= 6;
    },

    // ------------------------------------------------------------------ form

    get requiredParams() {
        return this.def.parameters.filter((p) => p.required);
    },

    get optionalParams() {
        return this.def.parameters.filter((p) => !p.required);
    },

    get hasFiles() {
        return this.def.parameters.some((p) => p.type === 'file');
    },

    emptyValue(p) {
        switch (p.ui) {
            case 'multiselect': return [];
            case 'repeatable': return [''];
            default: return '';
        }
    },

    fromStored(p, value) {
        if (value === undefined || value === null) return this.emptyValue(p);
        switch (p.ui) {
            case 'toggle': return value === true || value === 'true' ? 'true' : value === false || value === 'false' ? 'false' : '';
            case 'multiselect': return Array.isArray(value) ? value.map(String) : [String(value)];
            case 'repeatable': return Array.isArray(value) && value.length ? value.map(String) : [''];
            case 'json': {
                if (typeof value === 'string') {
                    const parsed = parseJson(value);
                    return parsed.ok && parsed.value !== undefined ? pretty(parsed.value) : value;
                }
                return pretty(value);
            }
            case 'datetime': return typeof value === 'string' && value.length >= 16 ? value.slice(0, 16) : String(value);
            default: return String(value);
        }
    },

    initialValues(prefill = {}) {
        const values = {};
        for (const p of this.def.parameters) {
            const source = prefill[p.name] !== undefined ? prefill[p.name] : this.config.defaults?.[p.name];
            values[p.name] = this.fromStored(p, source);
        }
        return values;
    },

    allowed(p) {
        return this.config.allowed?.[p.name] ?? (p.type === 'array' ? p.items?.enum : p.enum) ?? [];
    },

    optionLabel(p, value) {
        if (p.name === 'channels' || p.binding === 'channels') {
            return this.config.channel_labels?.[value] ?? value;
        }
        return value;
    },

    inputType(p) {
        return { email: 'email', url: 'url', number: 'number', date: 'date', datetime: 'datetime-local' }[p.ui] ?? 'text';
    },

    toggleMulti(p, value) {
        const list = this.values[p.name];
        const i = list.indexOf(value);
        i === -1 ? list.push(value) : list.splice(i, 1);
    },

    addRepeat(p) {
        this.values[p.name].push('');
    },

    removeRepeat(p, index) {
        this.values[p.name].splice(index, 1);
        if (this.values[p.name].length === 0) this.values[p.name].push('');
    },

    onFile(p, event) {
        const file = event.target.files?.[0];
        if (file) {
            this.files[p.name] = file;
        } else {
            delete this.files[p.name];
        }
    },

    amountHint(p) {
        if (!AMOUNT_FIELDS.includes(p.name)) return null;
        const raw = this.values[p.name];
        if (raw === '' || raw === undefined || Number.isNaN(Number(raw))) {
            const c = this.selectedCurrency();
            return c ? `In the subunit${c.subunit ? ` (${c.subunit})` : ''}: multiply by ${c.subunit_factor}.` : null;
        }
        const c = this.selectedCurrency();
        if (!c) return null;
        const major = Number(raw) / (c.subunit_factor || 100);
        return `= ${c.symbol}${major.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${c.code}`;
    },

    selectedCurrency() {
        const code = this.values.currency || this.config.currency?.code;
        return (this.config.currencies ?? []).find((c) => c.code === code) ?? this.config.currency;
    },

    // JSON editor helpers
    jsonHighlight(name) {
        return highlightJson(this.values[name] ?? '') + '\n';
    },

    jsonError(name) {
        const parsed = parseJson(this.values[name]);
        return parsed.ok ? null : parsed.error;
    },

    jsonFormat(name) {
        const parsed = parseJson(this.values[name]);
        if (parsed.ok && parsed.value !== undefined) this.values[name] = pretty(parsed.value);
    },

    jsonMinify(name) {
        const parsed = parseJson(this.values[name]);
        if (parsed.ok && parsed.value !== undefined) this.values[name] = JSON.stringify(parsed.value);
    },

    jsonTemplate(p) {
        this.values[p.name] = pretty(templateFor(p));
    },

    // A documented sample exists for whole-body parameters (e.g. bulk charge).
    hasExample(p) {
        return Boolean(p.root && this.def.example);
    },

    jsonExample(p) {
        if (this.hasExample(p)) this.values[p.name] = pretty(this.def.example);
    },

    syncScroll(event) {
        const pre = event.target.previousElementSibling;
        if (pre) pre.scrollTop = event.target.scrollTop;
    },

    // --------------------------------------------------------------- payload

    payload() {
        const out = {};
        for (const p of this.def.parameters) {
            const v = this.values[p.name];
            switch (p.ui) {
                case 'file':
                    break;
                case 'toggle':
                    if (v !== '') out[p.name] = v === 'true';
                    break;
                case 'number':
                    if (v !== '' && v !== null) out[p.name] = Number.isNaN(Number(v)) ? v : Number(v);
                    break;
                case 'multiselect':
                    if (v.length) out[p.name] = [...v];
                    break;
                case 'repeatable': {
                    const items = v.map((x) => String(x).trim()).filter((x) => x !== '');
                    if (items.length) {
                        out[p.name] = ['integer', 'number'].includes(p.items?.type) ? items.map((x) => (Number.isNaN(Number(x)) ? x : Number(x))) : items;
                    }
                    break;
                }
                case 'json': {
                    if (String(v).trim() === '') break;
                    const parsed = parseJson(v);
                    if (p.type === 'string') {
                        out[p.name] = parsed.ok ? JSON.stringify(parsed.value) : v;
                    } else {
                        out[p.name] = parsed.ok ? parsed.value : v;
                    }
                    break;
                }
                case 'datetime':
                    if (v !== '') {
                        const date = new Date(v);
                        out[p.name] = Number.isNaN(date.getTime()) ? v : date.toISOString();
                    }
                    break;
                default:
                    if (String(v).trim() !== '') out[p.name] = v;
            }
        }
        return out;
    },

    validateClient() {
        const errors = {};
        const payload = this.payload();

        for (const p of this.def.parameters) {
            const label = p.name;
            const has = p.type === 'file' ? Boolean(this.files[p.name]) : payload[p.name] !== undefined;

            if (p.ui === 'json' && String(this.values[p.name]).trim() !== '' && this.jsonError(p.name)) {
                errors[p.name] = [`${label} must be valid JSON (${this.jsonError(p.name)}).`];
                continue;
            }
            if (p.required && !has) {
                errors[p.name] = [`${label.charAt(0).toUpperCase()}${label.slice(1)} is required.`];
                continue;
            }
            if (!has) continue;

            const value = payload[p.name];

            if (p.type === 'array' && p.items?.type === 'object') {
                const itemErrors = this.validateItems(p, value);
                if (itemErrors.length) errors[p.name] = itemErrors;
                continue;
            }

            if (p.type === 'integer' && !Number.isInteger(value)) {
                errors[p.name] = [`${label} must be an integer.`];
            } else if (p.type === 'number' && typeof value !== 'number') {
                errors[p.name] = [`${label} must be a number.`];
            } else if (AMOUNT_FIELDS.includes(p.name) && !(/^[1-9]\d*$/.test(String(value)))) {
                errors[p.name] = [`${label.charAt(0).toUpperCase()}${label.slice(1)} must be a positive integer.`];
            } else if (p.format === 'email' && !EMAIL_RE.test(String(value))) {
                errors[p.name] = [`${label} must be a valid email address.`];
            } else if (p.format === 'uri') {
                try {
                    const url = new URL(String(value));
                    if (!['http:', 'https:'].includes(url.protocol)) throw new Error();
                } catch {
                    errors[p.name] = [`${label} must be a fully qualified URL.`];
                }
            }

            const allowed = this.config.allowed?.[p.name];
            if (allowed && p.ui === 'select' && !allowed.includes(String(value))) {
                errors[p.name] = [p.binding === 'currency'
                    ? `Currency is invalid for the selected country.`
                    : `The selected ${label} is invalid.`];
            }
        }

        this.errors = errors;
        return Object.keys(errors).length === 0;
    },

    /**
     * Arrays of objects: each item must be an object with the documented
     * required fields (the server checks this again).
     */
    validateItems(p, value) {
        if (!Array.isArray(value)) return [`${p.name} must be a JSON array.`];
        if (p.required && value.length === 0) return [`${p.name} must contain at least one item.`];

        const required = (p.items?.properties ?? []).filter((prop) => prop.required).map((prop) => prop.name);
        const errors = [];
        value.forEach((item, index) => {
            if (item === null || typeof item !== 'object' || Array.isArray(item)) {
                errors.push(`Item ${index + 1} must be a JSON object.`);
                return;
            }
            for (const name of required) {
                if (item[name] === undefined || item[name] === null || item[name] === '') {
                    errors.push(`Item ${index + 1}: ${name} is required.`);
                }
            }
            if ('amount' in item && !/^[1-9]\d*$/.test(String(item.amount))) {
                errors.push(`Item ${index + 1}: amount must be a positive integer.`);
            }
        });

        return errors.slice(0, 10);
    },

    get curlPreview() {
        return buildCurl(this.def, this.payload(), this.config.base_url ?? 'https://api.paystack.co', this.files);
    },

    get urlPreview() {
        const { path, query } = buildRequestParts(this.def, this.payload());
        return buildUrl(this.config.base_url ?? 'https://api.paystack.co', path, query);
    },

    // ------------------------------------------------------------- execution

    get isLive() {
        return this.config.context?.environment === 'live';
    },

    get liveConfirmReady() {
        return !this.def.is_dangerous || this.liveConfirmText.trim().toUpperCase() === 'LIVE';
    },

    execute() {
        if (this.loading) return;
        if (!this.validateClient()) {
            this.failure = { message: 'Fix the highlighted fields before executing.', code: 'client_validation' };
            this.focusFirstError();
            return;
        }
        if (this.isLive) {
            this.liveConfirmText = '';
            this.liveConfirmOpen = true;
            return;
        }
        this.send(false);
    },

    confirmLive() {
        if (!this.liveConfirmReady) return;
        this.liveConfirmOpen = false;
        this.send(true);
    },

    async send(confirmLive) {
        this.loading = true;
        this.failure = null;

        const payload = this.payload();
        const context = this.config.context;
        let body;

        if (Object.keys(this.files).length > 0) {
            body = new FormData();
            for (const [name, value] of Object.entries(payload)) {
                if (Array.isArray(value) && value.every((v) => typeof v !== 'object')) {
                    value.forEach((v) => body.append(`params[${name}][]`, String(v)));
                } else if (typeof value === 'object') {
                    body.append(`params[${name}]`, JSON.stringify(value));
                } else {
                    body.append(`params[${name}]`, typeof value === 'boolean' ? (value ? 'true' : 'false') : String(value));
                }
            }
            for (const [name, file] of Object.entries(this.files)) body.append(`params[${name}]`, file);
            for (const [k, v] of Object.entries(context)) body.append(`context[${k}]`, v);
            body.append('confirm_live', confirmLive ? '1' : '0');
        } else {
            body = { params: payload, context, confirm_live: confirmLive };
        }

        const response = await request('POST', this.config.execute_url, body);
        this.loading = false;

        if (response.ok) {
            this.result = response.data;
            this.errors = {};
            this.bankPage = 1;
            this.tablePage = 1;
            this.responseTab = this.defaultTab();
            this.$nextTick(() => {
                if (window.innerWidth < 1280) this.$refs.response?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            return;
        }

        if (response.status === 422 && response.data?.errors) {
            const errors = {};
            for (const [key, messages] of Object.entries(response.data.errors)) {
                const name = key.replace(/^params\./, '').split('.')[0];
                errors[name] = [...(errors[name] ?? []), ...messages];
            }
            this.errors = errors;
            this.failure = { message: response.data.message ?? 'Validation failed.', code: 'validation' };
            this.focusFirstError();
            return;
        }

        this.failure = {
            status: response.status,
            message: response.data?.message ?? 'Request failed.',
            code: response.data?.code ?? null,
            details: response.data?.details ?? [],
        };
    },

    defaultTab() {
        if (this.def.workflow === 'bank_table' && this.bankRows.length) return 'banks';
        if (this.def.workflow === 'transaction_result' && this.transaction) return 'summary';
        return 'pretty';
    },

    focusFirstError() {
        this.$nextTick(() => {
            const first = Object.keys(this.errors)[0];
            if (!first) return;
            if (this.optionalParams.some((p) => p.name === first)) this.showOptional = true;
            this.$nextTick(() => document.getElementById(`field-${first}`)?.focus());
        });
    },

    reset() {
        this.values = this.initialValues({});
        this.files = {};
        this.errors = {};
        this.result = null;
        this.failure = null;
        this.maskedFields = [];
        this.$refs.form?.querySelectorAll('input[type=file]').forEach((input) => { input.value = ''; });
        this.flash('Request reset.');
    },

    // -------------------------------------------------------------- response

    get response() {
        return this.result?.response ?? null;
    },

    get statusClass() {
        const s = this.response?.status;
        if (!s) return 'badge-danger';
        if (s >= 200 && s < 300) return this.response.ok ? 'badge-success' : 'badge-warning';
        if (s >= 400 && s < 500) return 'badge-warning';
        return 'badge-danger';
    },

    get treeHtml() {
        if (!this.response) return '';
        return this.response.is_json ? renderJsonTree(this.response.body) : '';
    },

    get rawText() {
        if (!this.response) return '';
        return this.response.is_json ? pretty(this.response.body) : (this.response.raw ?? '');
    },

    get rawHtml() {
        return this.response?.is_json ? highlightJson(this.rawText) : highlightJson(this.rawText);
    },

    get requestBodyHtml() {
        const body = this.result?.request?.body;
        return body ? highlightJson(pretty(body)) : '';
    },

    expandAll(open) {
        this.$refs.tree?.querySelectorAll('details').forEach((d) => { d.open = open; });
    },

    copy(text, label = 'Copied') {
        copyText(text).then((ok) => this.flash(ok ? `${label} to clipboard.` : 'Copy failed.'));
    },

    /**
     * The payload that was sent: the JSON body, or for requests without a
     * body (GET), the query parameters. Sensitive values stay masked.
     */
    get payloadValue() {
        const request = this.result?.request;
        if (!request) return null;
        if (request.body !== null && request.body !== undefined) return request.body;
        const query = request.query ?? {};
        return Array.isArray(query) && query.length === 0 ? {} : query;
    },

    get payloadText() {
        return this.payloadValue === null ? '' : pretty(this.payloadValue);
    },

    get payloadHtml() {
        return highlightJson(this.payloadText);
    },

    get payloadIsQuery() {
        return Boolean(this.result?.request && !this.result.request.body);
    },

    // Copy / Download act on whichever of response or payload is showing.
    get showingPayload() {
        return this.responseTab === 'payload';
    },

    copyResponse() {
        if (this.showingPayload) {
            this.copy(this.payloadText, 'Payload copied');
        } else {
            this.copy(this.rawText, 'Response copied');
        }
    },

    downloadResponse() {
        const kind = this.showingPayload ? 'payload' : 'response';
        const name = `${this.def.key}-${kind}-${new Date().toISOString().replace(/[:.]/g, '-')}.json`;
        downloadText(name, this.showingPayload ? this.payloadText : this.rawText);
    },

    flash(message) {
        this.toast = message;
        clearTimeout(this._toastTimer);
        this._toastTimer = setTimeout(() => { this.toast = null; }, 2200);
    },

    // Generic table view for list responses.
    get dataRows() {
        const data = this.response?.body?.data;
        return Array.isArray(data) && data.length && typeof data[0] === 'object' && data[0] !== null ? data : [];
    },

    get tableColumns() {
        const columns = [];
        for (const row of this.dataRows.slice(0, 20)) {
            for (const [key, value] of Object.entries(row)) {
                if (!columns.includes(key) && (value === null || typeof value !== 'object')) columns.push(key);
            }
        }
        return columns.slice(0, 8);
    },

    get filteredTableRows() {
        const term = this.tableSearch.toLowerCase().trim();
        if (!term) return this.dataRows;
        return this.dataRows.filter((row) => JSON.stringify(row).toLowerCase().includes(term));
    },

    get pagedTableRows() {
        const start = (this.tablePage - 1) * 25;
        return this.filteredTableRows.slice(start, start + 25);
    },

    get tablePageCount() {
        return Math.max(1, Math.ceil(this.filteredTableRows.length / 25));
    },

    cell(value) {
        if (value === null || value === undefined) return '—';
        return String(value);
    },

    // Bank table workflow.
    get bankRows() {
        return this.def.workflow === 'bank_table' ? this.dataRows : [];
    },

    get filteredBanks() {
        const term = this.bankSearch.toLowerCase().trim();
        if (!term) return this.bankRows;
        return this.bankRows.filter((b) => [b.name, b.code, b.longcode, b.slug, b.type].some((v) => String(v ?? '').toLowerCase().includes(term)));
    },

    get pagedBanks() {
        const start = (this.bankPage - 1) * this.bankPerPage;
        return this.filteredBanks.slice(start, start + Number(this.bankPerPage));
    },

    get bankPageCount() {
        return Math.max(1, Math.ceil(this.filteredBanks.length / this.bankPerPage));
    },

    // Transaction summary workflow.
    get transaction() {
        const data = this.response?.body?.data;
        return data && typeof data === 'object' && !Array.isArray(data) && 'status' in data ? data : null;
    },

    formatAmount(amount, currencyCode) {
        if (amount === null || amount === undefined) return '—';
        const currency = (this.config.currencies ?? []).find((c) => c.code === currencyCode);
        const factor = currency?.subunit_factor ?? 100;
        const major = Number(amount) / factor;
        return `${currency?.symbol ?? ''}${major.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${currencyCode ?? ''}`.trim();
    },

    get verifyUrl() {
        const reference = this.response?.reference;
        if (!reference || !this.config.verify_url) return null;
        return `${this.config.verify_url}${encodeURIComponent(reference)}`;
    },

    // Server-side pagination (Paystack meta).
    get pagination() {
        return this.response?.pagination ?? null;
    },

    get perPageParam() {
        return this.def.parameters.find((p) => ['perPage', 'per_page'].includes(p.name) && p.in === 'query')?.name ?? null;
    },

    get hasPageParam() {
        return this.def.parameters.some((p) => p.name === 'page' && p.in === 'query');
    },

    goToPage(page) {
        if (!this.hasPageParam || page < 1) return;
        this.values.page = String(page);
        this.execute();
    },

    setPerPage(size) {
        if (!this.perPageParam) return;
        this.values[this.perPageParam] = String(size);
        if (this.hasPageParam) this.values.page = '1';
        this.execute();
    },

    goToCursor(cursor, direction) {
        if (!cursor) return;
        if ('use_cursor' in this.values) this.values.use_cursor = 'true';
        if ('next' in this.values) this.values.next = direction === 'next' ? cursor : '';
        if ('previous' in this.values) this.values.previous = direction === 'previous' ? cursor : '';
        this.execute();
    },

    get pageWindow() {
        const p = this.pagination;
        if (!p || p.type !== 'page') return [];
        const pages = [];
        const start = Math.max(1, p.page - 2);
        const end = Math.min(p.page_count, start + 4);
        for (let i = start; i <= end; i++) pages.push(i);
        return pages;
    },

    // ---------------------------------------------------- favourites & saves

    async toggleFavorite() {
        const response = await request('POST', this.config.favorite_url);
        if (response.ok) {
            this.isFavorite = response.data.favorite;
            this.flash(this.isFavorite ? 'Added to favorites (reload to see it in the sidebar).' : 'Removed from favorites.');
        }
    },

    async saveConfiguration() {
        if (!this.saveName.trim()) return;
        const response = await request('POST', this.config.save_url, { name: this.saveName.trim(), params: this.payload() });
        if (response.ok) {
            this.saveMessage = { name: response.data.name, url: response.data.url };
            this.saveOpen = false;
            this.saveName = '';
            this.flash('Configuration saved.');
        } else {
            this.flash(response.data?.message ?? 'Could not save.');
        }
    },
});
