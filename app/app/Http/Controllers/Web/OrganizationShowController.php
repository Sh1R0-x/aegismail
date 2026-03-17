<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Crm\CrmPageDataService;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationShowController extends Controller
{
    public function __invoke(Organization $organization, CrmPageDataService $crmPageDataService): Response
    {
        return Inertia::render('Organizations/Show', $crmPageDataService->organization($organization));
    }
}
