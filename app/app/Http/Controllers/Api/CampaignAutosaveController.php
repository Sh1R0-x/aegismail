<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mailing\AutosaveCampaignRequest;
use App\Services\Crm\ContactImportService;
use App\Services\Crm\CrmManagementService;
use App\Services\Mailing\Composer\CampaignService;
use App\Services\Mailing\Composer\DraftService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CampaignAutosaveController extends Controller
{
    public function __construct(
        private readonly DraftService $draftService,
        private readonly CampaignService $campaignService,
        private readonly CrmManagementService $crmManagementService,
        private readonly ContactImportService $contactImportService,
    ) {}

    public function autosave(AutosaveCampaignRequest $request): JsonResponse
    {
        try {
            [$draft, $campaign, $created] = $this->draftService->autosaveCampaign(
                $request->validated(),
                $request->user()?->id,
            );
        } catch (ConflictHttpException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'draft' => $this->draftService->serialize($draft),
            'campaign' => $this->campaignService->serialize($campaign),
        ], $created ? 201 : 200);
    }

    public function audiences(): JsonResponse
    {
        return response()->json([
            'contacts' => $this->crmManagementService->campaignAudienceContacts(),
            'organizations' => $this->crmManagementService->campaignAudienceOrganizations(),
            'recentImports' => $this->contactImportService->recentImportAudienceOptions(),
        ]);
    }
}
