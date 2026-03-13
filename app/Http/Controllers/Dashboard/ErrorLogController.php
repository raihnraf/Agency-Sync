<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ErrorLogController extends Controller
{
    /**
     * Display error log page with filtering.
     */
    public function index(): View
    {
        return view('dashboard.error-log');
    }
}
