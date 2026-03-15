<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmPageDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationsController extends Controller
{
    public function __invoke(Request $request, CrmPageDataService $crmPageDataService): Response
    {
        return Inertia::render('Organizations/Index', $crmPageDataService->organizations(
            $request->only(['search'])
        ));
    }
}
