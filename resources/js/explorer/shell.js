import { readJsonIsland } from '../lib/clipboard';

const ALIASES = { dva: 'dedicated', nuban: 'dedicated', momo: 'mobile', otp: 'otp' };

/**
 * Layout chrome: mobile drawer, sidebar filter and the global endpoint search
 * (press "/" or Ctrl/Cmd+K).
 */
export default () => ({
    drawerOpen: false,
    sidebarFilter: '',
    searchOpen: false,
    query: '',
    highlighted: 0,
    index: [],

    init() {
        this.index = readJsonIsland('endpoint-index', []);

        window.addEventListener('keydown', (event) => {
            const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
            if ((event.key === '/' && !typing) || ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k')) {
                event.preventDefault();
                this.openSearch();
            }
            if (event.key === 'Escape') {
                this.searchOpen = false;
                this.drawerOpen = false;
            }
        });
    },

    openSearch() {
        this.searchOpen = true;
        this.highlighted = 0;
        this.$nextTick(() => this.$refs.searchInput?.focus());
    },

    get results() {
        const terms = this.query.toLowerCase().trim().split(/\s+/).filter(Boolean).map((t) => ALIASES[t] ?? t);
        if (terms.length === 0) return [];

        return this.index
            .map((endpoint) => {
                const haystack = `${endpoint.name} ${endpoint.key} ${endpoint.path} ${endpoint.method} ${endpoint.category}`.toLowerCase();
                let score = 0;
                for (const term of terms) {
                    if (!haystack.includes(term)) return null;
                    score += endpoint.name.toLowerCase().includes(term) ? 3 : 1;
                }
                return { ...endpoint, score };
            })
            .filter(Boolean)
            .sort((a, b) => b.score - a.score)
            .slice(0, 12);
    },

    move(delta) {
        const count = this.results.length;
        if (count === 0) return;
        this.highlighted = (this.highlighted + delta + count) % count;
    },

    go(result = null) {
        const target = result ?? this.results[this.highlighted];
        if (target) window.location.href = target.url;
    },

    matchesSidebar(text) {
        const filter = this.sidebarFilter.toLowerCase().trim();
        return filter === '' || text.toLowerCase().includes(filter);
    },
});
