import Alpine from 'alpinejs';

Alpine.data('search', () => ({
    postcode: '',
    radius: '1',
    propertyTypes: [],
    dateFrom: '',
    dateTo: '',
    results: [],
    pagination: null,
    loading: false,
    error: null,
    validationErrors: {},
    searched: false,
    currentPage: 1,

    typeLabels: { D: 'Detached', S: 'Semi-detached', T: 'Terraced', F: 'Flat', O: 'Other' },

    async search(page = 1) {
        this.error = null;
        this.validationErrors = {};
        this.currentPage = page;
        this.loading = true;

        const params = new URLSearchParams({ postcode: this.postcode, radius: this.radius, page });
        this.propertyTypes.forEach(t => params.append('property_type[]', t));
        if (this.dateFrom) params.set('date_from', this.dateFrom);
        if (this.dateTo) params.set('date_to', this.dateTo);

        try {
            const res = await fetch('/api/search?' + params, {
                headers: { Accept: 'application/json' },
            });
            const json = await res.json();

            if (res.status === 422) {
                this.validationErrors = json.errors ?? {};
                this.results = [];
                this.pagination = null;
            } else if (!res.ok) {
                this.error = json.message ?? 'Something went wrong. Please try again.';
                this.results = [];
                this.pagination = null;
            } else {
                this.results = json.data;
                this.pagination = json.meta;
                this.searched = true;
            }
        } catch {
            this.error = 'Could not connect. Please try again.';
        } finally {
            this.loading = false;
        }
    },

    formatPrice(p) {
        return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP', maximumFractionDigits: 0 }).format(p);
    },

    formatDate(d) {
        return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    },

    formatDistance(m) {
        if (m == null) return null;
        return m < 1000 ? `${m}m` : `${(m / 1609.34).toFixed(1)} mi`;
    },
}));

Alpine.start();
