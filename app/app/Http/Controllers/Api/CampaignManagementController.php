<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MailCampaign;
use App\Models\MailEvent;
use App\Models\MailMessage;
use App\Services\Mailing\Composer\CampaignService;
use App\Services\Mailing\Composer\DraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignManagementController extends Controller
{
    public function __construct(
        private readonly CampaignService $campaignService,
        private readonly DraftService $draftService,
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
        $campaign->loadMissing(['recipients.messages', 'draft.attachments']);

        $hasSentHistory = $campaign->recipients->contains(function ($recipient) {
            return $recipient->messages->contains(fn (MailMessage $message) => $message->sent_at !== null)
                || in_array($recipient->status, [
                    'sent',
                    'delivered_if_known',
                    'opened',
                    'clicked',
                    'replied',
                    'auto_replied',
                    'soft_bounced',
                    'hard_bounced',
                    'unsubscribed',
                ], true);
        });

        if ($hasSentHistory) {
            throw ValidationException::withMessages([
                'campaign' => ['Cette campagne ne peut plus être supprimée car des envois ou des événements existent déjà.'],
            ]);
        }

        DB::transaction(function () use ($campaign): void {
            $recipientIds = $campaign->recipients->pluck('id');
            $messageIds = $campaign->recipients->flatMap(fn ($recipient) => $recipient->messages->pluck('id'))->unique();

            if ($messageIds->isNotEmpty()) {
                MailEvent::query()->whereIn('message_id', $messageIds)->delete();
                MailMessage::query()->whereIn('id', $messageIds)->delete();
            }

            if ($recipientIds->isNotEmpty()) {
                MailEvent::query()->whereIn('recipient_id', $recipientIds)->delete();
                $campaign->recipients()->delete();
            }

            MailEvent::query()->where('campaign_id', $campaign->id)->delete();
            $campaign->draft?->attachments()->delete();
            $campaign->draft?->delete();
            $campaign->delete();
        });

        return response()->json([
            'message' => 'Campagne supprimée.',
        ]);
    }
}
