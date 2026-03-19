<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Mailing\Composer\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaignService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'campaigns' => $this->campaignService->list($request->boolean('includeDeleted')),
        ]);
    }
}
