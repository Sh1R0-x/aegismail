<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mailing\BulkDeleteDraftsRequest;
use App\Http\Requests\Mailing\CreateCampaignFromDraftRequest;
use App\Http\Requests\Mailing\ScheduleDraftRequest;
use App\Http\Requests\Mailing\UpsertDraftRequest;
use App\Models\MailDraft;
use App\Services\Mailing\Composer\CampaignService;
use App\Services\Mailing\Composer\DraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function destroy(MailDraft $draft): JsonResponse
    {
        $this->draftService->delete($draft);

        return response()->json([
            'message' => 'Brouillon supprimé.',
        ]);
    }

    public function bulkDestroy(BulkDeleteDraftsRequest $request): JsonResponse
    {
        $drafts = MailDraft::query()->whereIn('id', $request->validated('ids'))->get();
        $deleted = $this->draftService->deleteMany($drafts);

        return response()->json([
            'message' => $deleted > 1 ? "{$deleted} brouillons supprimés." : 'Brouillon supprimé.',
            'deletedCount' => $deleted,
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

    public function sendNow(MailDraft $draft): JsonResponse
    {
        [$draft, $campaign, $preflight] = $this->draftService->sendNow($draft);

        return response()->json([
            'draft' => $this->draftService->serialize($draft),
            'campaign' => $this->campaignService->serialize($campaign),
            'preflight' => $preflight,
        ]);
    }

    public function testSend(Request $request, MailDraft $draft): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email:rfc,dns'],
        ], [
            'email.required' => 'L\'adresse e-mail de test est requise.',
            'email.email' => 'L\'adresse e-mail de test n\'est pas valide.',
        ]);

        $result = $this->draftService->testSend($draft, $request->input('email'));

        return response()->json($result);
    }

    public function createCampaign(CreateCampaignFromDraftRequest $request, MailDraft $draft): JsonResponse
    {
        $mailbox = $this->draftService->mailbox();

        if ($mailbox === null) {
            return response()->json([
                'message' => 'La boîte OVH MX Plan doit être configurée avant de créer une campagne.',
            ], 422);
        }

        [$campaign, $preflight] = $this->campaignService->createFromDraft(
            $draft->fresh(['attachments']),
            $mailbox,
            $request->validated('name'),
            $request->filled('scheduledAt') ? Carbon::parse($request->validated('scheduledAt')) : null,
        );

        if ($campaign === null) {
            $messages = array_column($preflight['errors'] ?? [], 'message');

            return response()->json([
                'message' => $messages !== [] ? implode(' ', $messages) : 'Le preflight contient des erreurs bloquantes.',
                'errors' => [
                    'preflight' => $messages !== [] ? $messages : ['Le preflight contient des erreurs bloquantes.'],
                ],
                'preflight' => $preflight,
            ], 422);
        }

        return response()->json([
            'campaign' => $this->campaignService->serialize($campaign),
            'preflight' => $preflight,
        ], 201);
    }
}
