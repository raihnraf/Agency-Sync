<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Illuminate\Http\Request;

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

    /**
     * Display single client store details.
     */
    public function show(Request $request, string $id): View
    {
        return view('dashboard.tenants.show', ['tenantId' => $id]);
    }

    /**
     * Display form to edit client store.
     */
    public function edit(Request $request, string $id): View
    {
        return view('dashboard.tenants.edit', ['tenantId' => $id]);
    }
}
