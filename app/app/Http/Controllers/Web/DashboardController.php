<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmPageDataService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(CrmPageDataService $crmPageDataService): Response
    {
        return Inertia::render('Dashboard', $crmPageDataService->dashboard());
    }
}
