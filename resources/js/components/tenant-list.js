/**
 * Tenant List Component
 * Reusable component for displaying paginated tenant list
 */
export function tenantList() {
    return {
        tenants: [],
        loading: true,
        error: null,

        async fetchTenants() {
            this.loading = true;
            this.error = null;

            try {
                const response = await fetch('/api/v1/tenants', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch client stores');
                }

                const data = await response.json();
                this.tenants = data.data;
            } catch (error) {
                this.error = error.message;
                console.error('Error fetching tenants:', error);
            } finally {
                this.loading = false;
            }
        },

        getStatusBadgeClass(status) {
            const classes = {
                active: 'bg-green-100 text-green-800',
                pending: 'bg-yellow-100 text-yellow-800',
                error: 'bg-red-100 text-red-800'
            };
            return classes[status] || 'bg-gray-100 text-gray-800';
        },

        getStatusLabel(status) {
            const labels = {
                active: 'Active',
                pending: 'Pending',
                error: 'Error'
            };
            return labels[status] || status;
        }
    };
}
