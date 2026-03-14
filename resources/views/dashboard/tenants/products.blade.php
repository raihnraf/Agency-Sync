@extends('layouts.dashboard')

@section('title', 'Product Search - AgencySync Dashboard')

@section('content')
<div x-data="productSearch({{ $tenantId }}, '{{ $tenantName }}')" class="space-y-6" data-tenant-id="{{ $tenantId }}">
    <!-- Page Header -->
    <div class="mb-6">
        <a href="{{ url('/dashboard/tenants') }}/{{ $tenantId }}"
           class="text-sm text-gray-500 hover:text-gray-700">
            &larr; Back to Client Store
        </a>
        <h2 class="mt-2 text-2xl font-bold text-gray-900">Product Search</h2>
        <p class="mt-1 text-sm text-gray-600">Search products in <strong>{{ $tenantName }}</strong> catalog</p>
    </div>

    <!-- Product Catalog Export Section -->
    <div class="bg-white shadow rounded-lg p-6 mb-6" x-data="exportProductsComponent()">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-medium text-gray-900">Export Catalog</h3>
                <p class="mt-1 text-sm text-gray-500">Download product catalog as CSV or Excel</p>
            </div>
            <div class="flex gap-4 items-center">
                <div class="flex gap-2">
                    <label class="flex items-center gap-1 text-sm">
                        <input type="radio" x-model="format" value="csv" class="text-blue-600"> CSV
                    </label>
                    <label class="flex items-center gap-1 text-sm">
                        <input type="radio" x-model="format" value="xlsx" class="text-blue-600"> Excel
                    </label>
                </div>
                <button @click="exportProducts()" :disabled="loading"
                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg x-show="!loading" class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <svg x-show="loading" class="animate-spin mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-show="!loading">Export</span>
                    <span x-show="loading">Exporting...</span>
                </button>
                <a :href="downloadUrl" x-show="downloadUrl" target="_blank"
                   class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download
                </a>
            </div>
        </div>
    </div>

    <!-- Search Box -->
    <div class="bg-white shadow rounded-lg p-6">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <input type="text"
                   x-model="searchQuery"
                   @input.debounce.300ms="performSearch"
                   class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                   placeholder="Search by product name, SKU, or description..."
                   data-testid="product-search-input">
            <div x-show="searching" x-cloak class="absolute inset-y-0 right-0 pr-3 flex items-center">
                <svg class="animate-spin h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
        <p class="mt-2 text-sm text-gray-500">
            Type to search products across the catalog. Results appear as you type.
        </p>
    </div>

    <!-- Loading State -->
    <div x-show="searching" class="text-center py-12">
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
        <p class="mt-4 text-gray-600">Searching products...</p>
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

    <!-- No Results State -->
    <div x-show="!searching && !error && searchQuery && products.length === 0" x-cloak
         class="text-center py-12 bg-white rounded-lg shadow">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">No products found</h3>
        <p class="mt-1 text-sm text-gray-500">Try adjusting your search terms</p>
    </div>

    <!-- Initial State -->
    <div x-show="!searching && !error && !searchQuery" x-cloak
         class="text-center py-12 bg-white rounded-lg shadow">
        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <h3 class="mt-2 text-sm font-medium text-gray-900">Search Products</h3>
        <p class="mt-1 text-sm text-gray-500">Enter a search term above to find products</p>
    </div>

    <!-- Results -->
    <div x-show="!searching && !error && products.length > 0" x-cloak class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Search Results
                </h3>
                <span class="text-sm text-gray-500">
                    <span x-text="totalProducts"></span> products found
                </span>
            </div>
        </div>

        <ul class="divide-y divide-gray-200" data-testid="product-search-results">
            <template x-for="product in products" :key="product.id">
                <li class="px-4 py-4 sm:px-6 hover:bg-gray-50" data-testid="product-search-result-item">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-3">
                                <p class="text-sm font-medium text-indigo-600 truncate" x-text="product.name"></p>

                                <!-- Stock Status Badge -->
                                <span x-show="product.stock_status === 'in_stock'"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    In Stock
                                </span>
                                <span x-show="product.stock_status === 'out_of_stock'"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    Out of Stock
                                </span>
                                <span x-show="product.stock_status === 'low_stock'"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    Low Stock
                                </span>
                            </div>

                            <div class="mt-2 flex items-center space-x-4">
                                <div class="flex items-center text-sm text-gray-500">
                                    <span class="font-medium">SKU:</span>
                                    <span class="ml-1 font-mono text-xs" x-text="product.sku || 'N/A'"></span>
                                </div>
                                <div class="flex items-center text-sm text-gray-500">
                                    <span class="font-medium">Price:</span>
                                    <span class="ml-1" x-text="formatPrice(product.price)"></span>
                                </div>
                            </div>
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
                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                        Previous
                    </button>
                    <button @click="nextPage"
                            :disabled="currentPage === totalPages"
                            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50">
                        Next
                    </button>
                </div>
                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-700">
                            Showing
                            <span class="font-medium" x-text="(currentPage - 1) * 20 + 1"></span>
                            to
                            <span class="font-medium" x-text="Math.min(currentPage * 20, totalProducts)"></span>
                            of
                            <span class="font-medium" x-text="totalProducts"></span>
                            results
                        </p>
                    </div>
                    <div>
                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                            <button @click="prevPage"
                                    :disabled="currentPage === 1"
                                    class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50">
                                Previous
                            </button>
                            <template x-for="page in visiblePages" :key="page">
                                <button @click="goToPage(page)"
                                        :class="page === currentPage ? 'bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'"
                                        class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                                        x-text="page">
                                </button>
                            </template>
                            <button @click="nextPage"
                                    :disabled="currentPage === totalPages"
                                    class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50">
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
