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

function tenantDetail(tenantId) {
    return {
        tenant: {},
        loading: true,
        error: null,
        showDeleteModal: false,
        deleting: false,
        success: false,

        async fetchTenant() {
            this.loading = true;
            this.error = null;

            try {
                const response = await fetch(`/api/v1/tenants/${tenantId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch client store');
                }

                const data = await response.json();
                this.tenant = data.data;
            } catch (error) {
                this.error = error.message;
                console.error('Error fetching tenant:', error);
            } finally {
                this.loading = false;
            }
        },

        confirmDelete() {
            this.showDeleteModal = true;
        },

        async deleteTenant() {
            this.deleting = true;

            try {
                const response = await fetch(`/api/v1/tenants/${tenantId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to delete client store');
                }

                this.showDeleteModal = false;
                this.success = true;

                // Redirect after 1.5 seconds
                setTimeout(() => {
                    window.location.href = '/dashboard/tenants';
                }, 1500);

            } catch (error) {
                this.error = error.message;
                this.showDeleteModal = false;
                console.error('Error deleting tenant:', error);
            } finally {
                this.deleting = false;
            }
        },

        formatDate(dateString) {
            return new Date(dateString).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
    };
}

function tenantEdit(tenantId) {
    return {
        form: {
            name: '',
            status: 'active',
            platform_url: '',
            api_credentials: ''
        },
        errors: {},
        submitting: false,
        success: false,
        loading: true,

        async fetchTenant() {
            try {
                const response = await fetch(`/api/v1/tenants/${tenantId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch client store');
                }

                const data = await response.json();
                this.form = {
                    name: data.data.name,
                    status: data.data.status,
                    platform_url: data.data.platform_url,
                    api_credentials: '' // Don't pre-fill for security
                };
            } catch (error) {
                console.error('Error fetching tenant:', error);
            } finally {
                this.loading = false;
            }
        },

        async submit() {
            this.submitting = true;
            this.errors = {};
            this.success = false;

            try {
                const payload = {
                    name: this.form.name,
                    status: this.form.status,
                    platform_url: this.form.platform_url
                };

                // Only include api_credentials if provided
                if (this.form.api_credentials.trim()) {
                    try {
                        JSON.parse(this.form.api_credentials);
                        payload.api_credentials = this.form.api_credentials;
                    } catch (e) {
                        this.errors.api_credentials = 'API credentials must be valid JSON';
                        return;
                    }
                }

                const response = await fetch(`/api/v1/tenants/${tenantId}`, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (!response.ok) {
                    if (data.errors) {
                        data.errors.forEach(error => {
                            this.errors[error.field] = error.message;
                        });
                    } else {
                        throw new Error(data.message || 'Failed to update client store');
                    }
                    return;
                }

                this.success = true;

                // Redirect after 1.5 seconds
                setTimeout(() => {
                    window.location.href = `/dashboard/tenants/${tenantId}`;
                }, 1500);

            } catch (error) {
                this.errors.form = error.message;
                console.error('Error updating tenant:', error);
            } finally {
                this.submitting = false;
            }
        }
    };
}
