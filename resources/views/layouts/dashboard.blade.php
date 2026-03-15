<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="AgencySync Dashboard - Manage your e-commerce client stores">
    <title>@yield('title', 'AgencySync Dashboard')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Skip to main content link for keyboard users -->
    <style>
        .skip-link {
            position: absolute;
            top: -40px;
            left: 0;
            background: #4f46e5;
            color: white;
            padding: 8px;
            text-decoration: none;
            z-index: 100;
        }

        .skip-link:focus {
            top: 0;
        }
    </style>

    <!-- TailwindCSS CDN with configuration -->
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
                    spacing: {
                        '18': '4.5rem',
                        '88': '22rem',
                        '128': '32rem',
                    },
                    maxWidth: {
                        '8xl': '88rem',
                        '9xl': '128rem',
                    },
                },
            },
        }
    </script>

    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.0/dist/cdn.min.js"></script>

    <!-- Custom dashboard styles -->
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <script src="{{ asset("js/dashboard.js") }}"></script>

    @stack('styles')
</head>
<body class="bg-gray-50 text-gray-900">
    <!-- Skip link -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <div x-data="{ mobileMenuOpen: false }" class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200" role="banner">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <!-- Logo -->
                    <div class="flex items-center">
                        <h1 class="text-xl font-bold text-gray-900">AgencySync</h1>
                    </div>

                    <!-- Desktop Navigation -->
                    <nav class="hidden md:flex space-x-8">
                        <a href="{{ url('/dashboard/tenants') }}"
                           class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                            Client Stores
                        </a>
                        <a href="{{ url('/dashboard/error-log') }}"
                           class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                            Error Log
                        </a>
                    </nav>

                    <!-- User Menu -->
                    <div class="hidden md:flex items-center space-x-4">
                        <span class="text-sm text-gray-700">{{ auth()->user()->email }}</span>
                        <form method="POST" action="{{ url('/logout') }}">
                            @csrf
                            <button type="submit"
                                    class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">
                                Logout
                            </button>
                        </form>
                    </div>

                    <!-- Mobile menu button -->
                    <button @click="mobileMenuOpen = !mobileMenuOpen"
                            :aria-expanded="mobileMenuOpen"
                            aria-label="Toggle navigation menu"
                            class="md:hidden p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 min-h-[44px] min-w-[44px]">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation -->
            <div x-show="mobileMenuOpen"
                 @click.away="mobileMenuOpen = false"
                 :aria-hidden="!mobileMenuOpen"
                 class="md:hidden hidden"
                 role="navigation"
                 aria-label="Mobile navigation">
                <div class="px-2 pt-2 pb-3 space-y-1">
                    <a href="{{ url('/dashboard/tenants') }}"
                       class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50">
                        Client Stores
                    </a>
                    <a href="{{ url('/dashboard/error-log') }}"
                       class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50">
                        Error Log
                    </a>
                    <form method="POST" action="{{ url('/logout') }}">
                        @csrf
                        <button type="submit"
                                class="block w-full text-left px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:text-gray-900 hover:bg-gray-50">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main id="main-content"
              role="main"
              tabindex="-1"
              class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-12" role="contentinfo">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <p class="text-center text-sm text-gray-500">
                    &copy; {{ date('Y') }} AgencySync. All rights reserved.
                </p>
            </div>
        </footer>
    </div>
    </div>

    @stack("scripts")
</body>
</html>
