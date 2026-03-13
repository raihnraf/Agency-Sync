@extends('layouts.dashboard')

@section('title', "{$tenant['name']} - AgencySync Dashboard")

@section('content')
<div x-data="tenantDetail({{ $tenantId }})" x-init="fetchTenant()" class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <a href="{{ url('/dashboard/tenants') }}"
               class="text-sm text-gray-500 hover:text-gray-700">
                &larr; Back to Client Stores
            </a>
            <h2 class="mt-2 text-2xl font-bold text-gray-900" x-text="tenant.name"></h2>
        </div>
        <div class="flex space-x-3">
            <a :href="`/dashboard/tenants/${tenant.id}/edit`"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Edit
            </a>
            <button @click="confirmDelete"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
                    data-testid="tenant-delete-button">
                Delete
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
        <p class="mt-4 text-gray-600">Loading client store...</p>
    </div>

    <!-- Error State -->
    <div x-show="error" x-cloak
         class="rounded-md bg-red-50 p-4 border border-red-200">
        <div class="flex">
            <div class="ml-3">
                <p class="text-sm font-medium text-red-800" x-text="error"></p>
            </div>
        </div>
    </div>

    <!-- Tenant Details -->
    <div x-show="!loading && !error" x-cloak class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:px-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Client Store Information
            </h3>
        </div>
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
            <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
                <!-- Name -->
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500">Store Name</dt>
                    <dd class="mt-1 text-sm text-gray-900" x-text="tenant.name"></dd>
                </div>

                <!-- Status -->
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                    <dd class="mt-1">
                        <span x-show="tenant.status === 'active'"
                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            Active
                        </span>
                        <span x-show="tenant.status === 'pending'"
                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            Pending
                        </span>
                        <span x-show="tenant.status === 'error'"
                              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            Error
                        </span>
                    </dd>
                </div>

                <!-- Platform Type -->
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500">Platform</dt>
                    <dd class="mt-1 text-sm text-gray-900 capitalize" x-text="tenant.platform_type"></dd>
                </div>

                <!-- Platform URL -->
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500">Platform URL</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        <a :href="tenant.platform_url" target="_blank"
                           class="text-indigo-600 hover:text-indigo-900"
                           x-text="tenant.platform_url"></a>
                    </dd>
                </div>

                <!-- Slug -->
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500">Slug</dt>
                    <dd class="mt-1 text-sm text-gray-900 font-mono text-xs" x-text="tenant.slug"></dd>
                </div>

                <!-- Created At -->
                <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500">Created At</dt>
                    <dd class="mt-1 text-sm text-gray-900" x-text="formatDate(tenant.created_at)"></dd>
                </div>
            </dl>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="showDeleteModal" x-cloak
         class="fixed z-10 inset-0 overflow-y-auto"
         aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div x-show="showDeleteModal"
                 @click="showDeleteModal = false"
                 class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Delete Client Store
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">
                                    Are you sure you want to delete <strong x-text="tenant.name"></strong>?
                                    This action cannot be undone. All associated data will be archived.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button"
                            @click="deleteTenant"
                            :disabled="deleting"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                            data-testid="tenant-delete-confirm">
                        <span x-show="!deleting">Delete</span>
                        <span x-show="deleting">Deleting...</span>
                    </button>
                    <button type="button"
                            @click="showDeleteModal = false"
                            :disabled="deleting"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                            data-testid="tenant-delete-cancel">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    <div x-show="success" x-cloak
         class="rounded-md bg-green-50 p-4 border border-green-200">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-green-800">
                    Client store deleted successfully!
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
