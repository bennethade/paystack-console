/**
 * Builds a cURL command from the endpoint definition and current form values.
 * The key is always a placeholder: the real key never exists in the browser.
 */
function quote(value) {
    return `'${String(value).replaceAll("'", "'\\''")}'`;
}

export function buildRequestParts(definition, payload) {
    let path = definition.path;
    const query = {};
    let body = {};

    for (const parameter of definition.parameters) {
        if (!(parameter.name in payload)) continue;
        const value = payload[parameter.name];
        if (parameter.root) {
            // The value is the entire JSON body (e.g. the array for POST /bulkcharge).
            body = value;
        } else if (parameter.in === 'path') {
            path = path.replace(`{${parameter.name}}`, encodeURIComponent(String(value)));
        } else if (parameter.in === 'query') {
            query[parameter.name] = value;
        } else if (!Array.isArray(body)) {
            body[parameter.name] = value;
        }
    }

    return { path, query, body };
}

export function buildUrl(baseUrl, path, query) {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(query)) {
        params.append(key, typeof value === 'boolean' ? (value ? 'true' : 'false') : String(value));
    }
    const qs = params.toString();
    return `${baseUrl.replace(/\/$/, '')}${path}${qs ? `?${qs}` : ''}`;
}

export function buildCurl(definition, payload, baseUrl, files = {}) {
    const { path, query, body } = buildRequestParts(definition, payload);
    const placeholder = definition.auth === 'public' ? 'YOUR_PUBLIC_KEY' : 'YOUR_SECRET_KEY';
    const lines = [
        `curl -X ${definition.method} ${quote(buildUrl(baseUrl, path, query))}`,
        `  -H ${quote(`Authorization: Bearer ${placeholder}`)}`,
    ];

    const fileNames = Object.keys(files);
    const hasBody = definition.method !== 'GET' && (Object.keys(body).length > 0 || fileNames.length > 0);

    if (hasBody && fileNames.length > 0) {
        for (const [name, value] of Object.entries(body)) {
            const content = typeof value === 'object' ? JSON.stringify(value) : String(value);
            lines.push(`  -F ${quote(`${name}=${content}`)}`);
        }
        for (const name of fileNames) {
            lines.push(`  -F ${quote(`${name}=@/path/to/${files[name].name}`)}`);
        }
    } else if (hasBody) {
        lines.push(`  -H ${quote('Content-Type: application/json')}`);
        lines.push(`  -d ${quote(JSON.stringify(body, null, 2))}`);
    }

    return lines.join(' \\\n');
}
