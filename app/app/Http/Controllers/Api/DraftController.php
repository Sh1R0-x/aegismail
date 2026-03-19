<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mailing\BulkDeleteDraftsRequest;
use App\Http\Requests\Mailing\CreateCampaignFromDraftRequest;
use App\Http\Requests\Mailing\ScheduleDraftRequest;
use App\Http\Requests\Mailing\UpsertDraftRequest;
use App\Models\MailAttachment;
use App\Models\MailDraft;
use App\Services\Mailing\Composer\CampaignService;
use App\Services\Mailing\Composer\DraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

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
            'driver' => config('mailing.gateway.driver'),
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
            'driver' => config('mailing.gateway.driver'),
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

    public function uploadAttachment(Request $request, MailDraft $draft): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,rtf,odt,ods,jpg,jpeg,png,gif,webp,svg,zip'],
        ], [
            'file.required' => 'Un fichier est requis.',
            'file.file' => 'Le fichier est invalide.',
            'file.max' => 'Le fichier ne peut pas dépasser 10 Mo.',
            'file.mimes' => 'Type de fichier non autorisé. Formats acceptés : PDF, documents, tableurs, images, archives ZIP.',
        ]);

        $file = $request->file('file');
        $path = $file->store('attachments/'.$draft->id, 'local');

        $attachment = MailAttachment::query()->create([
            'draft_id' => $draft->id,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'storage_disk' => 'local',
            'storage_path' => $path,
            'disposition' => 'attachment',
        ]);

        return response()->json([
            'attachment' => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'size' => $attachment->size_bytes,
                'mimeType' => $attachment->mime_type,
            ],
        ], 201);
    }

    public function deleteAttachment(MailDraft $draft, MailAttachment $attachment): JsonResponse
    {
        if ((int) $attachment->draft_id !== (int) $draft->id) {
            return response()->json(['message' => 'Pièce jointe introuvable pour ce brouillon.'], 404);
        }

        if ($attachment->storage_path && $attachment->storage_disk) {
            Storage::disk($attachment->storage_disk)->delete($attachment->storage_path);
        }

        $attachment->delete();

        return response()->json(['message' => 'Pièce jointe supprimée.']);
    }
}
