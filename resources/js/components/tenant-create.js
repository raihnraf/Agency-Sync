Alpine.data('tenantCreate', () => ({
    form: {
        name: '',
        platform_type: '',
        platform_url: '',
        api_credentials: ''
    },
    errors: {},
    success: false,
    submitting: false,

    async submit() {
        this.submitting = true;
        this.errors = {};
        this.success = false;

        try {
            const response = await fetch('/dashboard/tenants', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(this.form)
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) {
                    data.errors.forEach(error => {
                        this.errors[error.field] = error.message;
                    });
                } else {
                    throw new Error(data.message || 'Failed to create client store');
                }
                return;
            }

            this.success = true;
            setTimeout(() => {
                window.location.href = '/dashboard/tenants';
            }, 1000);

        } catch (error) {
            console.error('Error creating tenant:', error);
            this.errors.name = error.message || 'An error occurred while creating the client store';
        } finally {
            this.submitting = false;
        }
    }
}));
