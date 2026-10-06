import { request } from '../lib/http';
import { readJsonIsland } from '../lib/clipboard';

/**
 * Country / currency / environment switcher. Changes are saved server-side
 * (session + user preference) and the page reloads so every server-rendered
 * piece (enum narrowing, availability, credentials status) is consistent.
 */
export default () => ({
    countries: [],
    country: '',
    currency: '',
    environment: 'test',
    original: {},
    saving: false,
    error: null,
    confirmLive: false,

    init() {
        const data = readJsonIsland('explorer-context', {});
        this.countries = data.countries ?? [];
        this.country = data.context?.country ?? '';
        this.currency = data.context?.currency ?? '';
        this.environment = data.context?.environment ?? 'test';
        this.original = { country: this.country, currency: this.currency, environment: this.environment };
    },

    get currencies() {
        return this.countries.find((c) => c.code === this.country)?.currencies ?? [];
    },

    onCountryChange() {
        const country = this.countries.find((c) => c.code === this.country);
        if (!this.currencies.includes(this.currency)) {
            this.currency = country?.default_currency ?? this.currencies[0] ?? '';
        }
        this.save();
    },

    onEnvironmentChange() {
        if (this.environment === 'live' && this.original.environment !== 'live') {
            this.confirmLive = true;
            return;
        }
        this.save();
    },

    cancelLive() {
        this.confirmLive = false;
        this.environment = this.original.environment;
    },

    async save() {
        this.confirmLive = false;
        this.saving = true;
        this.error = null;

        const result = await request('PUT', this.$el.dataset.url, {
            country: this.country,
            currency: this.currency,
            environment: this.environment,
        });

        if (result.ok) {
            window.location.reload();
            return;
        }

        this.saving = false;
        this.error = result.data?.errors
            ? Object.values(result.data.errors).flat()[0]
            : (result.data?.message ?? 'Could not change the context.');
        Object.assign(this, this.original);
    },
});
