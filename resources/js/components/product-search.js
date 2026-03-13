/**
 * Product Search Component
 * Reusable component for product search with debouncing
 */
export function productSearch(tenantId) {
    return {
        tenantId,
        searchQuery: '',
        products: [],
        searching: false,
        error: null,
        currentPage: 1,
        totalPages: 1,
        totalProducts: 0,
        searchTimeout: null,

        async performSearch() {
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }

            if (!this.searchQuery.trim()) {
                this.products = [];
                this.totalProducts = 0;
                this.totalPages = 1;
                return;
            }

            this.searching = true;
            this.error = null;

            try {
                const response = await fetch(`/api/v1/tenants/${this.tenantId}/products?query=${encodeURIComponent(this.searchQuery)}&page=${this.currentPage}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to search products');
                }

                const data = await response.json();
                this.products = data.data;
                this.totalProducts = data.meta.total;
                this.totalPages = data.meta.last_page;

            } catch (error) {
                this.error = error.message;
                console.error('Error searching products:', error);
            } finally {
                this.searching = false;
            }
        },

        formatPrice(price) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(price);
        }
    };
}
