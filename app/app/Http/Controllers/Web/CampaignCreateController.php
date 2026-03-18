<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Crm\ContactImportService;
use App\Services\Crm\CrmManagementService;
use App\Services\Mailing\Composer\ComposerPageDataService;
use Inertia\Inertia;
use Inertia\Response;

class CampaignCreateController extends Controller
{
    public function __invoke(
        ComposerPageDataService $pageDataService,
        CrmManagementService $crmManagementService,
        ContactImportService $contactImportService,
    ): Response {
        return Inertia::render('Campaigns/Create', [
            'templates' => $pageDataService->drafts()['templates'],
            'audiences' => [
                'contacts' => $crmManagementService->campaignAudienceContacts(),
                'organizations' => $crmManagementService->campaignAudienceOrganizations(),
                'recentImports' => $contactImportService->recentImportAudienceOptions(),
            ],
            'autosave' => [
                'endpoint' => '/api/campaigns/autosave',
                'conflictMode' => 'reject_on_stale_updated_at',
            ],
        ]);
    }
}
