/**
 * Sync Status Component
 * Reusable component for displaying sync operation status
 */
export function syncStatus(tenantId) {
    return {
        tenantId,
        syncStatus: null,
        syncing: false,
        syncSuccess: false,
        syncPollingInterval: null,

        async fetchSyncStatus() {
            try {
                const response = await fetch(`/api/v1/tenants/${this.tenantId}/sync-logs?per_page=1`, {
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
                    body: JSON.stringify({
                        tenant_id: this.tenantId,
                        data: {}
                    })
                });

                if (!response.ok) {
                    throw new Error('Failed to trigger sync');
                }

                this.syncSuccess = true;
                this.startPolling();

                setTimeout(() => {
                    this.syncSuccess = false;
                }, 3000);

            } catch (error) {
                console.error('Error triggering sync:', error);
            } finally {
                this.syncing = false;
            }
        },

        startPolling() {
            this.stopPolling();
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
            const duration = Math.round((completed - started) / 1000);

            if (duration < 60) {
                return `${duration}s`;
            } else {
                const minutes = Math.floor(duration / 60);
                const seconds = duration % 60;
                return `${minutes}m ${seconds}s`;
            }
        },

        init() {
            this.fetchSyncStatus();
            this.$watch('syncStatus', (status) => {
                if (status && (status.status === 'completed' || status.status === 'failed')) {
                    this.stopPolling();
                }
            });
        },

        destroy() {
            this.stopPolling();
        }
    };
}
