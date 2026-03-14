<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AgencySync - Multi-tenant E-commerce Agency Management System">

    <title>AgencySync - Multi-tenant E-commerce Agency Management</title>

    <!-- TailwindCSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                            800: '#3730a3',
                            900: '#312e81',
                        },
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                    },
                },
            },
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center">
                <!-- Logo/Title -->
                <h1 class="text-5xl font-bold text-primary-600 mb-6">
                    AgencySync
                </h1>

                <!-- Tagline -->
                <p class="text-2xl text-gray-600 mb-8">
                    Multi-tenant E-commerce Agency Management System
                </p>

                <!-- Value Proposition -->
                <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
                    <p class="text-lg text-gray-700 leading-relaxed">
                        Manage product catalogs across multiple client stores with
                        <span class="font-semibold text-primary-600">sub-second search performance</span>
                        and <span class="font-semibold text-primary-600">non-blocking background processing</span>.
                    </p>
                </div>

                <!-- Features -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-primary-600 mb-3">
                            <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Multi-tenant Architecture</h3>
                        <p class="text-sm text-gray-600">Isolate client data with tenant_id separation</p>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-primary-600 mb-3">
                            <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Lightning Search</h3>
                        <p class="text-sm text-gray-600">Sub-second Elasticsearch-powered product search</p>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-primary-600 mb-3">
                            <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Background Sync</h3>
                        <p class="text-sm text-gray-600">Non-blocking catalog synchronization</p>
                    </div>
                </div>

                <!-- Login CTA -->
                @if (Route::has('login'))
                    <div class="space-y-4">
                        @auth
                            <a href="{{ url('/dashboard') }}"
                               class="inline-block bg-primary-600 hover:bg-primary-700 text-white font-bold py-4 px-8 rounded-lg transition duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                                Go to Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                               class="inline-block bg-primary-600 hover:bg-primary-700 text-white font-bold py-4 px-8 rounded-lg transition duration-300 shadow-lg hover:shadow-xl transform hover:-translate-y-1">
                                Log in to AgencySync
                            </a>
                        @endauth

                        <p class="text-sm text-gray-500 mt-4">
                            @auth
                                Already logged in? Access your dashboard
                            @else
                                Enter your credentials to access your agency dashboard
                            @endauth
                        </p>
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <footer class="mt-16 text-center text-sm text-gray-500">
                <p>&copy; {{ date('Y') }} AgencySync. All rights reserved.</p>
                <p class="mt-2">Built with Laravel 11, Elasticsearch, and Redis</p>
            </footer>
        </div>
    </div>
</body>
</html>
