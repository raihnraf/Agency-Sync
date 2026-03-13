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

        // Sync-related properties
        syncStatus: null,
        syncing: false,
        syncSuccess: false,
        syncPollingInterval: null,

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

                // Fetch sync status after tenant data
                this.fetchSyncStatus();
            } catch (error) {
                this.error = error.message;
                console.error('Error fetching tenant:', error);
            } finally {
                this.loading = false;
            }
        },

        async fetchSyncStatus() {
            try {
                const response = await fetch(`/api/v1/tenants/${tenantId}/sync-logs?per_page=1`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    }
                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                if (data.data && data.data.length > 0) {
                    this.syncStatus = data.data[0];

                    // Start polling if sync is running
                    if (this.syncStatus.status === 'running' || this.syncStatus.status === 'pending') {
                        this.startPolling();
                    }
                }
            } catch (error) {
                console.error('Error fetching sync status:', error);
            }
        },

        async triggerSync() {
            this.syncing = true;
            this.syncSuccess = false;

            try {
                const response = await fetch(`/api/v1/tenants/${tenantId}/sync`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    }
                });

                if (!response.ok) {
                    throw new Error('Failed to trigger sync');
                }

                const data = await response.json();
                this.syncSuccess = true;

                // Start polling for status updates
                this.startPolling();

                // Clear success message after 3 seconds
                setTimeout(() => {
                    this.syncSuccess = false;
                }, 3000);

            } catch (error) {
                this.error = error.message;
                console.error('Error triggering sync:', error);
            } finally {
                this.syncing = false;
            }
        },

        startPolling() {
            // Clear existing interval if any
            this.stopPolling();

            // Poll every 2 seconds
            this.syncPollingInterval = setInterval(() => {
                this.fetchSyncStatus();
            }, 2000);
        },

        stopPolling() {
            if (this.syncPollingInterval) {
                clearInterval(this.syncPollingInterval);
                this.syncPollingInterval = null;
            }
        },

        // Computed properties
        get syncProgress() {
            if (!this.syncStatus || !this.syncStatus.total_products) {
                return 0;
            }
            return Math.round((this.syncStatus.indexed_products / this.syncStatus.total_products) * 100);
        },

        get syncDuration() {
            if (!this.syncStatus) {
                return '';
            }

            const started = new Date(this.syncStatus.started_at);
            const completed = this.syncStatus.completed_at ? new Date(this.syncStatus.completed_at) : new Date();
            const duration = Math.round((completed - started) / 1000); // seconds

            if (duration < 60) {
                return `${duration}s`;
            } else {
                const minutes = Math.floor(duration / 60);
                const seconds = duration % 60;
                return `${minutes}m ${seconds}s`;
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
        },

        formatDateTime(dateString) {
            return new Date(dateString).toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        // Lifecycle hooks
        init() {
            // Fetch tenant data on init
            this.fetchTenant();

            // Cleanup on component destroy
            this.$watch('syncStatus', (status) => {
                if (status && (status.status === 'completed' || status.status === 'failed')) {
                    // Stop polling when sync completes or fails
                    this.stopPolling();
                }
            });
        },

        destroy() {
            this.stopPolling();
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
