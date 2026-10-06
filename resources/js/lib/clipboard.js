export async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch {
        // Fallback for non-secure contexts (plain http on a LAN IP).
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand('copy');
        area.remove();
        return ok;
    }
}

export function downloadText(filename, text, type = 'application/json') {
    const blob = new Blob([text], { type });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/**
 * Read a JSON <script type="application/json"> island rendered by Blade.
 */
export function readJsonIsland(id, fallback = null) {
    const el = document.getElementById(id);
    if (!el) return fallback;
    try {
        return JSON.parse(el.textContent);
    } catch {
        return fallback;
    }
}
