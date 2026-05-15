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
    areaSummary: null,
    summaryLoading: false,
    summaryError: false,
    crimeComparison: null,
    comparisonLoading: false,
    comparisonError: false,

    typeLabels: { D: 'Detached', S: 'Semi-detached', T: 'Terraced', F: 'Flat', O: 'Other' },

    phaseDescriptions: {
        'Nursery': 'Ages 3–4',
        'Primary': 'Ages 4–11',
        'Middle deemed primary': 'Ages 8–12, classified as primary',
        'Middle deemed secondary': 'Ages 9–13, classified as secondary',
        'Secondary': 'Ages 11–16/18',
        'All-through': 'Ages 4–18',
        '16 plus': 'Ages 16–18',
    },

    async search(page = 1) {
        this.error = null;
        this.validationErrors = {};
        this.currentPage = page;
        this.loading = true;

        const isNewSearch = page === 1;
        if (isNewSearch) {
            this.summaryLoading = true;
            this.summaryError = false;
            this.areaSummary = null;
            this.comparisonLoading = true;
            this.comparisonError = false;
            this.crimeComparison = null;
        }

        const params = new URLSearchParams({ postcode: this.postcode, radius: this.radius, page });
        this.propertyTypes.forEach(t => params.append('property_type[]', t));
        if (this.dateFrom) params.set('date_from', this.dateFrom);
        if (this.dateTo) params.set('date_to', this.dateTo);

        const searchPromise = fetch('/api/search?' + params, { headers: { Accept: 'application/json' } });
        const summaryPromise = isNewSearch
            ? fetch('/api/area-summary?postcode=' + encodeURIComponent(this.postcode), { headers: { Accept: 'application/json' } })
            : null;

        const comparisonPromise = isNewSearch
            ? fetch('/api/crime-comparison?postcode=' + encodeURIComponent(this.postcode), { headers: { Accept: 'application/json' } })
            : null;

        try {
            const res = await searchPromise;
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

        if (summaryPromise) {
            try {
                const res = await summaryPromise;
                if (res.ok) {
                    this.areaSummary = await res.json();
                } else {
                    this.summaryError = true;
                }
            } catch {
                this.summaryError = true;
            } finally {
                this.summaryLoading = false;
            }
        }

        if (comparisonPromise) {
            try {
                const res = await comparisonPromise;
                if (res.ok) {
                    this.crimeComparison = await res.json();
                } else {
                    this.comparisonError = true;
                }
            } catch {
                this.comparisonError = true;
            } finally {
                this.comparisonLoading = false;
            }
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

    formatCrimeCategory(cat) {
        return cat.replace(/-/g, ' ').replace(/^\w/, c => c.toUpperCase());
    },

    mergedCrimeRows() {
        const area = Object.fromEntries((this.areaSummary?.crime ?? []).map(r => [r.category, r.count]));
        const hood = Object.fromEntries((this.crimeComparison?.counts ?? []).map(r => [r.category, r.neighbourhood_count]));
        const cats = [...new Set([...Object.keys(area), ...Object.keys(hood)])];
        return cats
            .map(cat => ({ category: cat, area: area[cat] ?? 0, neighbourhood: hood[cat] ?? 0 }))
            .sort((a, b) => b.area - a.area);
    },
}));

Alpine.start();
