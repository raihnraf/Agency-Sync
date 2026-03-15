function tenantList() {
    return {
        tenants: [],
        loading: true,
        error: null,

        async fetchTenants() {
            this.loading = true;
            this.error = null;

            try {
                const response = await fetch('/dashboard/tenants/json', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    credentials: 'same-origin'

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

        async fetchAllSyncStatus() {
            for (let tenant of this.tenants) {
                try {
                    const response = await fetch(`/api/v1/sync-logs?tenant_id=${tenant.id}&per_page=1`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                        },
                        credentials: 'same-origin'
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (data.data && data.data.length > 0) {
                            tenant.syncStatus = data.data[0];
                        }
                    }
                } catch (error) {
                    console.error(`Error fetching sync status for tenant ${tenant.id}:`, error);
                }
            }
        },

        formatSyncTime(timestamp) {
            if (!timestamp) return 'Unknown';
            const date = new Date(timestamp);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMins / 60);
            const diffDays = Math.floor(diffHours / 24);

            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            return `${diffDays}d ago`;
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

                const response = await fetch('/dashboard/tenants/json', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    credentials: 'same-origin',
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
                const response = await fetch(`/dashboard/tenants/json/${tenantId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    credentials: 'same-origin'

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
                const response = await fetch(`/api/v1/sync-logs?tenant_id=${tenantId}&per_page=1`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    credentials: 'same-origin'

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
                const response = await fetch('/api/v1/sync/dispatch', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        tenant_id: this.tenantId,
                        data: {}
                    })
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
            return Math.round((this.syncStatus.processed_products / this.syncStatus.total_products) * 100);
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
                const response = await fetch(`/dashboard/tenants/json/${tenantId}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    credentials: 'same-origin'

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
                const response = await fetch(`/dashboard/tenants/json/${tenantId}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    credentials: 'same-origin'

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

                const response = await fetch(`/dashboard/tenants/json/${tenantId}`, {
                    method: 'PATCH',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    credentials: 'same-origin',
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

function productSearch(tenantId, tenantName) {
    return {
        tenantId,
        tenantName,
        searchQuery: '',
        products: [],
        searching: false,
        error: null,
        currentPage: 1,
        totalPages: 1,
        totalProducts: 0,
        searchTimeout: null,

        async performSearch() {
            // Clear existing timeout
            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout);
            }

            // Skip if search query is empty
            if (!this.searchQuery.trim()) {
                this.products = [];
                this.totalProducts = 0;
                this.totalPages = 1;
                return;
            }

            // Set loading state
            this.searching = true;
            this.error = null;

            try {
                const response = await fetch(`/dashboard/tenants/json/${this.tenantId}/search?query=${encodeURIComponent(this.searchQuery)}&page=${this.currentPage}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    credentials: 'same-origin'

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

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.performSearch();
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.performSearch();
            }
        },

        goToPage(page) {
            this.currentPage = page;
            this.performSearch();
        },

        get visiblePages() {
            const pages = [];
            const maxVisible = 5;
            const start = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
            const end = Math.min(this.totalPages, start + maxVisible - 1);

            for (let i = start; i <= end; i++) {
                pages.push(i);
            }

            return pages;
        },

        formatPrice(price) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(price);
        }
    };
}

function errorLog() {
    return {
        logs: [],
        tenants: [],
        loading: true,
        error: null,
        filters: {
            tenant_id: '',
            date_from: '',
            date_to: ''
        },
        currentPage: 1,
        totalPages: 1,

        // Modal state
        selectedLog: null,
        showModal: false,
        loadingDetails: false,

        async fetchLogs() {
            this.loading = true;
            this.error = null;

            try {
                const params = new URLSearchParams({
                    page: this.currentPage,
                    per_page: 20
                });

                if (this.filters.tenant_id) {
                    params.append('tenant_id', this.filters.tenant_id);
                }
                if (this.filters.date_from) {
                    params.append('date_from', this.filters.date_from);
                }
                if (this.filters.date_to) {
                    params.append('date_to', this.filters.date_to);
                }

                const response = await fetch(`/api/v1/sync-logs?${params}`, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    credentials: 'same-origin'

                });

                if (!response.ok) {
                    throw new Error('Failed to fetch error logs');
                }

                const data = await response.json();
                this.logs = data.data.filter(log => log.status === 'failed');
                this.totalPages = data.meta.last_page;

            } catch (error) {
                this.error = error.message;
                console.error('Error fetching logs:', error);
            } finally {
                this.loading = false;
            }
        },

        async fetchTenants() {
            try {
                const response = await fetch('/dashboard/tenants/json', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    credentials: 'same-origin'

                });

                if (!response.ok) {
                    return;
                }

                const data = await response.json();
                this.tenants = data.data;
            } catch (error) {
                console.error('Error fetching tenants:', error);
            }
        },

        async viewDetails(logId) {
            this.loadingDetails = true;

            try {
                const response = await fetch(`/api/v1/sync-logs/${logId}/details`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                    },
                    credentials: 'same-origin'

                });

                if (!response.ok) throw new Error('Failed to fetch error details');

                const result = await response.json();
                this.selectedLog = result.data;
                this.showModal = true;

                // Apply syntax highlighting after modal opens
                this.$nextTick(() => {
                    document.querySelectorAll('#error-modal pre code').forEach((el) => {
                        hljs.highlightElement(el);
                    });
                });
            } catch (error) {
                console.error('Error fetching details:', error);
                alert('Failed to load error details');
            } finally {
                this.loadingDetails = false;
            }
        },

        closeModal() {
            this.showModal = false;
            this.selectedLog = null;
        },

        clearFilters() {
            this.filters = {
                tenant_id: '',
                date_from: '',
                date_to: ''
            };
            this.currentPage = 1;
            this.fetchLogs();
        },

        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.fetchLogs();
            }
        },

        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.fetchLogs();
            }
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

        calculateDuration(started, completed) {
            const start = new Date(started);
            const end = new Date(completed);
            const duration = Math.round((end - start) / 1000);

            if (duration < 60) {
                return `${duration}s`;
            } else {
                const minutes = Math.floor(duration / 60);
                const seconds = duration % 60;
                return `${minutes}m ${seconds}s`;
            }
        },

        init() {
            this.fetchTenants();
            this.fetchLogs();
        }
    };
}


/**
 * Export Sync Logs Component
 * Handles CSV export of sync logs with filters
 */
function exportSyncLogsComponent() {
    return {
        filters: {
            start_date: '',
            end_date: '',
            status: ''
        },
        loading: false,
        downloadUrl: null,
        jobUuid: null,

        async exportSyncLogs() {
            this.loading = true;
            this.downloadUrl = null;

            try {
                const tenantId = document.querySelector('[data-tenant-id]')?.dataset.tenantId;
                const filters = { ...this.filters };
                if (tenantId) filters.tenant_id = tenantId;

                const response = await fetch('/api/v1/exports/sync-logs', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        filters: filters,
                        format: 'csv'
                    })
                });

                if (!response.ok) throw new Error('Export request failed');

                const data = await response.json();
                this.jobUuid = data.data.job_uuid;
                this.pollJobStatus();
            } catch (error) {
                console.error('Export failed:', error);
                showToast('Export failed: ' + error.message, 'error');
                this.loading = false;
            }
        },

        async pollJobStatus() {
            const interval = setInterval(async () => {
                try {
                    const response = await fetch(`/api/v1/exports/${this.jobUuid}`);
                    const data = await response.json();

                    if (response.ok && data.data.download_url) {
                        this.downloadUrl = data.data.download_url;
                        this.loading = false;
                        clearInterval(interval);
                        showToast('Export ready! Click Download to save.', 'success');
                    }
                } catch (error) {
                    console.error('Status check failed:', error);
                    clearInterval(interval);
                    this.loading = false;
                }
            }, 2000);
        }
    };
}

/**
 * Export Products Component
 * Handles Excel/CSV export of product catalog
 */
function exportProductsComponent() {
    return {
        format: 'csv',
        loading: false,
        downloadUrl: null,
        jobUuid: null,
        tenantId: null,

        init() {
            this.tenantId = document.querySelector('[data-tenant-id]')?.dataset.tenantId;
        },

        async exportProducts() {
            this.loading = true;
            this.downloadUrl = null;

            try {
                if (!this.tenantId) throw new Error('Tenant ID not found');

                const response = await fetch('/api/v1/exports/products', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        tenant_id: this.tenantId
                    })
                });

                if (!response.ok) throw new Error('Export request failed');

                const data = await response.json();
                this.jobUuid = data.data.job_uuid;
                this.pollJobStatus();
            } catch (error) {
                console.error('Export failed:', error);
                showToast('Export failed: ' + error.message, 'error');
                this.loading = false;
            }
        },

        async pollJobStatus() {
            const interval = setInterval(async () => {
                try {
                    const response = await fetch(`/api/v1/exports/${this.jobUuid}`);
                    const data = await response.json();

                    if (response.ok && data.data.download_url) {
                        this.downloadUrl = data.data.download_url;
                        this.loading = false;
                        clearInterval(interval);
                        showToast('Export ready! Click Download to save.', 'success');
                    }
                } catch (error) {
                    console.error('Status check failed:', error);
                    clearInterval(interval);
                    this.loading = false;
                }
            }, 2000);
        }
    };
}
