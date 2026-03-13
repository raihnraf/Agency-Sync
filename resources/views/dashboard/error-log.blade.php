@extends('layouts.dashboard')

@section('title', 'Error Log - AgencySync Dashboard')

@section('content')
<div x-data="errorLog()" x-init="fetchLogs()" class="space-y-6">
    <!-- Page Header -->
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Error Log</h2>
        <p class="mt-1 text-sm text-gray-600">View sync errors and troubleshoot issues</p>
    </div>

    <!-- Filters -->
    <div class="bg-white shadow rounded-lg p-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Tenant Filter -->
            <div>
                <label for="tenant-filter" class="block text-sm font-medium text-gray-700">
                    Client Store
                </label>
                <select id="tenant-filter"
                        x-model="filters.tenant_id"
                        @change="fetchLogs()"
                        class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                        data-testid="error-log-tenant-filter">
                    <option value="">All Client Stores</option>
                    <template x-for="tenant in tenants" :key="tenant.id">
                        <option :value="tenant.id" x-text="tenant.name"></option>
                    </template>
                </select>
            </div>

            <!-- Date From Filter -->
            <div>
                <label for="date-from" class="block text-sm font-medium text-gray-700">
                    Date From
                </label>
                <input type="date"
                       id="date-from"
                       x-model="filters.date_from"
                       @change="fetchLogs()"
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                       data-testid="error-log-date-filter">
            </div>

            <!-- Date To Filter -->
            <div>
                <label for="date-to" class="block text-sm font-medium text-gray-700">
                    Date To
                </label>
                <input type="date"
                       id="date-to"
                       x-model="filters.date_to"
                       @change="fetchLogs()"
                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>
        </div>

        <div class="mt-4">
            <button @click="clearFilters"
                    class="text-sm text-indigo-600 hover:text-indigo-900">
                Clear Filters
            </button>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
        <p class="mt-4 text-gray-600">Loading error logs...</p>
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

    <!-- Empty State -->
    <div x-show="!loading && !error && logs.length === 0" x-cloak
         class="text-center py-12 bg-white rounded-lg shadow">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No error logs</h3>
        <p class="mt-1 text-sm text-gray-500">No errors found matching your filters</p>
    </div>

    <!-- Error Log List -->
    <div x-show="!loading && !error && logs.length > 0" x-cloak class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
                Sync Errors
            </h3>
        </div>

        <ul class="divide-y divide-gray-200" data-testid="error-log-list">
            <template x-for="log in logs" :key="log.id">
                <li class="px-4 py-4 sm:px-6" data-testid="error-log-item">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <!-- Tenant Name -->
                            <p class="text-sm font-medium text-gray-900" x-text="log.tenant_name"></p>

                            <!-- Error Message -->
                            <div class="mt-2">
                                <p class="text-sm text-gray-600" x-text="log.error_message"></p>
                            </div>

                            <!-- Metadata -->
                            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                                <div>
                                    <span class="font-medium">Started:</span>
                                    <span x-text="formatDateTime(log.started_at)"></span>
                                </div>
                                <div x-show="log.completed_at">
                                    <span class="font-medium">Duration:</span>
                                    <span x-text="calculateDuration(log.started_at, log.completed_at)"></span>
                                </div>
                                <div>
                                    <span class="font-medium">Products:</span>
                                    <span x-text="log.indexed_products || 0"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                Failed
                            </span>
                        </div>
                    </div>
                </li>
            </template>
        </ul>

        <!-- Pagination -->
        <div x-show="totalPages > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
                <div class="flex-1 flex justify-between sm:hidden">
                    <button @click="prevPage"
                            :disabled="currentPage === 1"
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Previous
                    </button>
                    <button @click="nextPage"
                            :disabled="currentPage === totalPages"
                            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Next
                    </button>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Page <span class="font-medium" x-text="currentPage"></span> of <span class="font-medium" x-text="totalPages"></span>
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            <button @click="prevPage"
                                    :disabled="currentPage === 1"
                                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                Previous
                            </button>
                            <button @click="nextPage"
                                    :disabled="currentPage === totalPages"
                                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                Next
                            </button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
