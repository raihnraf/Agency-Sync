@extends('layouts.dashboard')

@section('title', 'Client Stores - AgencySync Dashboard')

@section('content')
<div x-data="tenantList()" x-init="await fetchTenants(); await fetchAllSyncStatus()" class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Client Stores</h2>
            <p class="mt-1 text-sm text-gray-600">Manage your e-commerce client stores</p>
        </div>
        <a href="{{ url('/dashboard/tenants/create') }}"
           class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 min-h-[44px]">
            <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Add Client Store
        </a>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
        <p class="mt-4 text-gray-600">Loading client stores...</p>
    </div>

    <!-- Error State -->
    <div x-show="error" x-cloak
         class="rounded-md bg-red-50 p-4 border border-red-200">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium text-red-800" x-text="error"></p>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div x-show="!loading && !error && tenants.length === 0" x-cloak
         class="text-center py-12 bg-white rounded-lg shadow">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No client stores</h3>
        <p class="mt-1 text-sm text-gray-500">Get started by adding your first client store.</p>
        <div class="mt-6">
            <a href="{{ url('/dashboard/tenants/create') }}"
               class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                Add Client Store
            </a>
        </div>
    </div>

    <!-- Tenant List -->
    <div x-show="!loading && !error && tenants.length > 0" x-cloak
         class="bg-white shadow rounded-lg overflow-hidden">
        <ul class="divide-y divide-gray-200" data-testid="tenant-list">
            <template x-for="tenant in tenants" :key="tenant.id">
                <li class="px-4 py-4 sm:px-6 hover:bg-gray-50">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium text-indigo-600 truncate" x-text="tenant.name"></p>
                                <!-- Status Badge -->
                                <span x-show="tenant.status === 'active'"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800"
                                      data-testid="tenant-status">
                                    Active
                                </span>
                                <span x-show="tenant.status === 'pending'"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800"
                                      data-testid="tenant-status">
                                    Pending
                                </span>
                                <span x-show="tenant.status === 'error'"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800"
                                      data-testid="tenant-status">
                                    Error
                                </span>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-4">
                                <div class="flex items-center text-sm text-gray-500">
                                    <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                                    </svg>
                                    <span x-text="tenant.platform_type"></span>
                                </div>
                                <div class="flex items-center text-sm text-gray-500">
                                    <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                    </svg>
                                    <span x-text="tenant.platform_url" class="truncate"></span>
                                </div>
                                <!-- Sync Status -->
                                <div class="flex items-center text-sm text-gray-500">
                                    <svg class="flex-shrink-0 mr-1.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <div x-show="tenant.syncStatus">
                                        <span class="text-xs text-gray-500" x-text="formatSyncTime(tenant.syncStatus.started_at)"></span>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="px-2 py-1 text-xs rounded-full"
                                                  :class="{
                                                    'bg-green-100 text-green-800': tenant.syncStatus.status === 'completed',
                                                    'bg-blue-100 text-blue-800': tenant.syncStatus.status === 'running',
                                                    'bg-red-100 text-red-800': tenant.syncStatus.status === 'failed',
                                                    'bg-yellow-100 text-yellow-800': tenant.syncStatus.status === 'pending'
                                                  }"
                                                  x-text="tenant.syncStatus.status"></span>
                                            <span class="text-xs text-gray-600" x-text="tenant.syncStatus.products_processed + ' products'"></span>
                                        </div>
                                    </div>
                                    <div x-show="!tenant.syncStatus" class="text-xs text-gray-400">
                                        No sync history
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                            <a :href="`/dashboard/tenants/${tenant.id}`"
                               class="inline-flex items-center justify-center px-3 py-2 text-indigo-600 hover:text-indigo-900 text-sm font-medium rounded-md hover:bg-gray-100 min-h-[44px]">
                                View
                            </a>
                            <a :href="`/dashboard/tenants/${tenant.id}/edit`"
                               class="inline-flex items-center justify-center px-3 py-2 text-indigo-600 hover:text-indigo-900 text-sm font-medium rounded-md hover:bg-gray-100 min-h-[44px]">
                                Edit
                            </a>
                        </div>
                    </div>
                </li>
            </template>
        </ul>
    </div>
</div>
@endsection
