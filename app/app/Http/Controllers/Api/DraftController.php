<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mailing\CreateCampaignFromDraftRequest;
use App\Http\Requests\Mailing\ScheduleDraftRequest;
use App\Http\Requests\Mailing\UpsertDraftRequest;
use App\Models\MailDraft;
use App\Services\Mailing\Composer\CampaignService;
use App\Services\Mailing\Composer\DraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DraftController extends Controller
{
    public function __construct(
        private readonly DraftService $draftService,
        private readonly CampaignService $campaignService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'drafts' => $this->draftService->list(),
        ]);
    }

    public function show(MailDraft $draft): JsonResponse
    {
        return response()->json([
            'draft' => $this->draftService->serialize($draft),
        ]);
    }

    public function store(UpsertDraftRequest $request): JsonResponse
    {
        $draft = $this->draftService->create($request->validated(), $request->user()?->id);

        return response()->json([
            'draft' => $this->draftService->serialize($draft),
        ], 201);
    }

    public function update(UpsertDraftRequest $request, MailDraft $draft): JsonResponse
    {
        $draft = $this->draftService->update($draft, $request->validated());

        return response()->json([
            'draft' => $this->draftService->serialize($draft),
        ]);
    }

    public function duplicate(MailDraft $draft): JsonResponse
    {
        $draft = $this->draftService->duplicate($draft->loadMissing('attachments'));

        return response()->json([
            'draft' => $this->draftService->serialize($draft),
        ], 201);
    }

    public function preflight(MailDraft $draft): JsonResponse
    {
        return response()->json([
            'preflight' => $this->draftService->preflight($draft),
        ]);
    }

    public function schedule(ScheduleDraftRequest $request, MailDraft $draft): JsonResponse
    {
        [$draft, $campaign, $preflight] = $this->draftService->schedule(
            $draft,
            Carbon::parse($request->validated('scheduledAt')),
            $request->validated('name'),
        );

        return response()->json([
            'draft' => $this->draftService->serialize($draft),
            'campaign' => $this->campaignService->serialize($campaign),
            'preflight' => $preflight,
        ]);
    }

    public function unschedule(MailDraft $draft): JsonResponse
    {
        [$draft, $campaign] = $this->draftService->unschedule($draft);

        return response()->json([
            'draft' => $this->draftService->serialize($draft),
            'campaign' => $campaign ? $this->campaignService->serialize($campaign) : null,
        ]);
    }

    public function createCampaign(CreateCampaignFromDraftRequest $request, MailDraft $draft): JsonResponse
    {
        $mailbox = $this->draftService->mailbox();

        if ($mailbox === null) {
            return response()->json([
                'message' => 'Mailbox is not configured.',
            ], 422);
        }

        [$campaign, $preflight] = $this->campaignService->createFromDraft(
            $draft->fresh(['attachments']),
            $mailbox,
            $request->validated('name'),
            $request->filled('scheduledAt') ? Carbon::parse($request->validated('scheduledAt')) : null,
        );

        if ($campaign === null) {
            return response()->json([
                'message' => 'Preflight failed.',
                'preflight' => $preflight,
            ], 422);
        }

        return response()->json([
            'campaign' => $this->campaignService->serialize($campaign),
            'preflight' => $preflight,
        ], 201);
    }
}
