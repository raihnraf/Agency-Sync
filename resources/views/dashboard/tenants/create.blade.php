@extends('layouts.dashboard')

@section('title', 'Create Client Store - AgencySync Dashboard')

@section('content')
<div x-data="tenantCreate()" class="max-w-2xl mx-auto">
    <!-- Page Header -->
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Create Client Store</h2>
        <p class="mt-1 text-sm text-gray-600">Add a new e-commerce client store to manage</p>
    </div>

    <!-- Form -->
    <form @submit.prevent="submit" class="bg-white shadow rounded-lg p-6 space-y-6">
        <!-- Name Field -->
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700">
                Store Name <span class="text-red-500">*</span>
            </label>
            <input type="text"
                   id="name"
                   x-model="form.name"
                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                   data-testid="tenant-name-input"
                   required>
            <p x-show="errors.name" x-cloak class="mt-2 text-sm text-red-600" x-text="errors.name"></p>
        </div>

        <!-- Platform Type Field -->
        <div>
            <label for="platform_type" class="block text-sm font-medium text-gray-700">
                Platform <span class="text-red-500">*</span>
            </label>
            <select id="platform_type"
                    x-model="form.platform_type"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    data-testid="tenant-platform-select"
                    required>
                <option value="">Select a platform</option>
                <option value="shopify">Shopify</option>
                <option value="shopware">Shopware</option>
            </select>
            <p x-show="errors.platform_type" x-cloak class="mt-2 text-sm text-red-600" x-text="errors.platform_type"></p>
        </div>

        <!-- Platform URL Field -->
        <div>
            <label for="platform_url" class="block text-sm font-medium text-gray-700">
                Platform URL <span class="text-red-500">*</span>
            </label>
            <input type="url"
                   id="platform_url"
                   x-model="form.platform_url"
                   placeholder="https://your-store.myshopify.com"
                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                   data-testid="tenant-platform-url-input"
                   required>
            <p x-show="errors.platform_url" x-cloak class="mt-2 text-sm text-red-600" x-text="errors.platform_url"></p>
            <p class="mt-1 text-xs text-gray-500">The full URL to your client's e-commerce store</p>
        </div>

        <!-- API Credentials Field -->
        <div>
            <label for="api_credentials" class="block text-sm font-medium text-gray-700">
                API Credentials <span class="text-red-500">*</span>
            </label>
            <textarea id="api_credentials"
                      x-model="form.api_credentials"
                      rows="4"
                      placeholder='{"api_key": "your-key", "password": "your-password"}'
                      class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm font-mono text-sm"
                      data-testid="tenant-api-credentials-input"
                      required></textarea>
            <p x-show="errors.api_credentials" x-cloak class="mt-2 text-sm text-red-600" x-text="errors.api_credentials"></p>
            <p class="mt-1 text-xs text-gray-500">JSON-formatted API credentials (will be encrypted)</p>
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
                        Client store created successfully!
                    </p>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="flex justify-end space-x-3">
            <a href="{{ url('/dashboard/tenants') }}"
               class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Cancel
            </a>
            <button type="submit"
                    :disabled="submitting"
                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50"
                    data-testid="tenant-create-submit">
                <span x-show="!submitting">Create Client Store</span>
                <span x-show="submitting">Creating...</span>
            </button>
        </div>
    </form>
</div>
@endsection
