<?php

namespace App\Services\Mailing;

use App\Models\ContactEmail;
use App\Models\MailRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MailUnsubscribeService
{
    public function __construct(
        private readonly PublicEmailUrlService $publicEmailUrlService,
        private readonly MailEventLogger $eventLogger,
    ) {}

    public function unsubscribeUrl(MailRecipient $recipient): ?string
    {
        $baseUrl = $this->publicEmailUrlService->trackingBaseUrl();

        if ($baseUrl === null) {
            return null;
        }

        return rtrim($baseUrl, '/').'/u/'.$this->token($recipient);
    }

    public function unsubscribe(string $token): ?MailRecipient
    {
        [$recipientId, $signature] = $this->parseToken($token);

        if ($recipientId === null || $signature === null) {
            return null;
        }

        $recipient = MailRecipient::query()
            ->with(['contactEmail', 'campaign'])
            ->find($recipientId);

        if ($recipient === null || ! hash_equals($this->signature($recipient), $signature)) {
            return null;
        }

        $contactEmail = $recipient->contactEmail
            ?? ContactEmail::query()->whereRaw('lower(email) = ?', [Str::lower($recipient->email)])->first();

        $occurredAt = now();
        $firstUnsubscribe = $recipient->unsubscribe_at === null && $contactEmail?->opt_out_at === null;

        DB::transaction(function () use ($recipient, $contactEmail, $occurredAt): void {
            if (! in_array($recipient->status, ['hard_bounced', 'replied', 'auto_replied'], true)) {
                $recipient->status = 'unsubscribed';
            }

            $recipient->unsubscribe_at = $recipient->unsubscribe_at ?? $occurredAt;
            $recipient->last_event_at = $occurredAt;
            $recipient->save();

            if ($contactEmail !== null) {
                $contactEmail->forceFill([
                    'opt_out_at' => $contactEmail->opt_out_at ?? $occurredAt,
                    'opt_out_reason' => $contactEmail->opt_out_reason ?? 'one_click_unsubscribe',
                ])->save();
            }
        });

        if ($firstUnsubscribe) {
            $this->eventLogger->log(
                'mail_recipient.unsubscribed',
                [
                    'recipient_id' => $recipient->id,
                    'email' => $recipient->email,
                    'source' => 'list_unsubscribe',
                ],
                [
                    'mailbox_account_id' => $recipient->campaign?->mailbox_account_id,
                    'campaign_id' => $recipient->campaign_id,
                    'recipient_id' => $recipient->id,
                ],
                'mail_recipient.unsubscribed.'.$recipient->id,
            );
        }

        return $recipient->refresh();
    }

    private function token(MailRecipient $recipient): string
    {
        return $recipient->id.'.'.$this->signature($recipient);
    }

    private function signature(MailRecipient $recipient): string
    {
        return substr(hash_hmac('sha256', $recipient->id.'|'.Str::lower($recipient->email), $this->secret()), 0, 32);
    }

    private function parseToken(string $token): array
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            return [null, null];
        }

        return [
            ctype_digit($parts[0]) ? (int) $parts[0] : null,
            ctype_xdigit($parts[1]) ? strtolower($parts[1]) : null,
        ];
    }

    private function secret(): string
    {
        $key = (string) config('app.key', 'aegis-mailing');

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $key;
    }
}
