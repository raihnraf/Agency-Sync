function tenantList() {
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
        }
    };
}
