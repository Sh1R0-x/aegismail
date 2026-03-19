<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MailCampaign;
use App\Services\Mailing\Composer\CampaignService;
use Illuminate\Http\JsonResponse;

class CampaignManagementController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaignService,
    ) {}

    public function clone(MailCampaign $campaign): JsonResponse
    {
        $newCampaign = $this->campaignService->clone($campaign);

        return response()->json([
            'campaign' => $this->campaignService->serialize($newCampaign),
            'message' => 'Campagne clonée avec succès.',
        ], 201);
    }

    public function destroy(MailCampaign $campaign): JsonResponse
    {
        $deletionMode = $this->campaignService->destroy($campaign);

        return response()->json([
            'message' => 'Campagne supprimée.',
            'deletionMode' => $deletionMode,
        ]);
    }
}
