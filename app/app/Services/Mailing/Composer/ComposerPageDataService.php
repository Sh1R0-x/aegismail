<?php

namespace App\Services\Mailing\Composer;

class ComposerPageDataService
{
    public function __construct(
        private readonly TemplateService $templateService,
        private readonly DraftService $draftService,
        private readonly CampaignService $campaignService,
    ) {
    }

    public function templates(): array
    {
        return [
            'templates' => $this->templateService->list(),
        ];
    }

    public function drafts(): array
    {
        return [
            'drafts' => $this->draftService->list(),
        ];
    }

    public function campaigns(): array
    {
        return [
            'campaigns' => $this->campaignService->list(),
        ];
    }
}
