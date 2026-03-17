<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MailCampaign;
use App\Services\Mailing\Composer\CampaignService;
use App\Services\Mailing\Composer\DraftService;
use App\Services\Mailing\Composer\ComposerPageDataService;
use Inertia\Inertia;
use Inertia\Response;

class CampaignShowController extends Controller
{
    public function __invoke(
        MailCampaign $campaign,
        CampaignService $campaignService,
        DraftService $draftService,
        ComposerPageDataService $pageDataService,
    ): Response {
        $draft = $campaign->draft;

        return Inertia::render('Campaigns/Show', [
            'campaign' => $campaignService->serializeDetail(
                $campaign,
                $draft ? $draftService->serialize($draft->loadMissing(['campaigns.recipients', 'attachments'])) : []
            ),
            'templates' => $pageDataService->drafts()['templates'],
        ]);
    }
}
