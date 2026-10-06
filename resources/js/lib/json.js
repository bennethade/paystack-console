/**
 * JSON helpers. Everything that ends up in innerHTML is escaped here first,
 * so API responses can never inject markup.
 */
export function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

/**
 * Syntax-highlight a JSON (or JSON-ish) string. Input is escaped before any
 * markup is added. Invalid JSON is still highlighted token by token.
 */
export function highlightJson(text) {
    if (text === null || text === undefined) return '';
    const escaped = escapeHtml(text);

    return escaped.replace(
        /(&quot;(?:\\u[a-fA-F0-9]{4}|\\[^u]|(?!&quot;).)*?&quot;)(\s*:)?|\b(true|false)\b|\bnull\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/g,
        (match, str, colon, bool) => {
            if (str) {
                return colon
                    ? `<span class="json-key">${str}</span><span class="json-punct">${colon}</span>`
                    : `<span class="json-string">${str}</span>`;
            }
            if (bool) return `<span class="json-boolean">${match}</span>`;
            if (match === 'null') return `<span class="json-null">${match}</span>`;
            return `<span class="json-number">${match}</span>`;
        },
    );
}

export function pretty(value) {
    return JSON.stringify(value, null, 2);
}

function scalarHtml(value) {
    if (value === null) return '<span class="json-null">null</span>';
    if (typeof value === 'string') return `<span class="json-string">"${escapeHtml(value)}"</span>`;
    if (typeof value === 'number') return `<span class="json-number">${value}</span>`;
    if (typeof value === 'boolean') return `<span class="json-boolean">${value}</span>`;
    return escapeHtml(String(value));
}

/**
 * Collapsible tree using <details>, which is keyboard accessible natively.
 */
export function renderJsonTree(value, key = null, depth = 0) {
    const label = key !== null ? `<span class="json-key">"${escapeHtml(key)}"</span><span class="json-punct">: </span>` : '';

    if (value === null || typeof value !== 'object') {
        return `<div class="json-row">${label}${scalarHtml(value)}</div>`;
    }

    const isArray = Array.isArray(value);
    const entries = isArray ? value.map((v, i) => [i, v]) : Object.entries(value);
    const [open, close] = isArray ? ['[', ']'] : ['{', '}'];

    if (entries.length === 0) {
        return `<div class="json-row">${label}<span class="json-punct">${open}${close}</span></div>`;
    }

    const preview = isArray ? `${entries.length} items` : `${entries.length} keys`;
    const children = entries.map(([k, v]) => renderJsonTree(v, isArray ? null : String(k), depth + 1)).join('');
    const openAttr = depth < 3 ? ' open' : '';

    return `<details${openAttr}><summary>${label}<span class="json-punct">${open}</span><span class="json-preview"> … ${preview} ${close}</span></summary>${children}<div class="json-punct" style="margin-left:-1rem">${close}</div></details>`;
}

/**
 * Validate JSON text; returns { ok, value, error }.
 */
export function parseJson(text) {
    if (text === null || text === undefined || String(text).trim() === '') {
        return { ok: true, value: undefined, error: null };
    }
    try {
        return { ok: true, value: JSON.parse(text), error: null };
    } catch (e) {
        return { ok: false, value: undefined, error: e.message };
    }
}

/**
 * Build a starter JSON value from documented nested properties.
 */
export function templateFor(parameter) {
    const sample = (prop) => {
        const type = prop.type ?? 'string';
        if (prop.enum?.length) return prop.enum[0];
        if (type === 'object') return Object.fromEntries((prop.properties ?? []).map((p) => [p.name, sample(p)]));
        if (type === 'array') return prop.items ? [sample(prop.items)] : [];
        if (type === 'integer' || type === 'number') return 0;
        if (type === 'boolean') return false;
        return '';
    };

    if (parameter.type === 'array') {
        return [sample(parameter.items ?? { type: 'string' })];
    }
    if (parameter.properties?.length) {
        return sample({ type: 'object', properties: parameter.properties });
    }
    return {};
}
