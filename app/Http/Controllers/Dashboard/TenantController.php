<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TenantController extends Controller
{
    /**
     * Display list of client stores.
     */
    public function index(): View
    {
        return view('dashboard.tenants.index');
    }

    /**
     * Display form to create new client store.
     */
    public function create(): View
    {
        return view('dashboard.tenants.create');
    }
}
