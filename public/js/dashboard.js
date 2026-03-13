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

function tenantCreate() {
    return {
        form: {
            name: '',
            platform_type: '',
            platform_url: '',
            api_credentials: ''
        },
        errors: {},
        submitting: false,
        success: false,

        async submit() {
            this.submitting = true;
            this.errors = {};
            this.success = false;

            try {
                // Validate API credentials JSON
                try {
                    JSON.parse(this.form.api_credentials);
                } catch (e) {
                    this.errors.api_credentials = 'API credentials must be valid JSON';
                    return;
                }

                const response = await fetch('/api/v1/tenants', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    body: JSON.stringify(this.form)
                });

                const data = await response.json();

                if (!response.ok) {
                    if (data.errors) {
                        // Laravel validation errors format: {errors: [{field, message}]}
                        data.errors.forEach(error => {
                            this.errors[error.field] = error.message;
                        });
                    } else {
                        throw new Error(data.message || 'Failed to create client store');
                    }
                    return;
                }

                this.success = true;

                // Redirect after 1.5 seconds
                setTimeout(() => {
                    window.location.href = '/dashboard/tenants';
                }, 1500);

            } catch (error) {
                this.errors.form = error.message;
                console.error('Error creating tenant:', error);
            } finally {
                this.submitting = false;
            }
        }
    };
}
