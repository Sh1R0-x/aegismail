<?php

namespace App\Services\Mailing\Inbound;

use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\MailboxAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ThreadResolver
{
    public function resolve(MailboxAccount $mailbox, array $payload): array
    {
        if ($message = $this->findByMessageId($payload['in_reply_to_header'] ?? null)) {
            return $this->resolution($message->thread, $message, 'in_reply_to', 1.0);
        }

        if ($message = $this->findFromReferences($payload['references_header'] ?? null)) {
            return $this->resolution($message->thread, $message, 'references', 0.95);
        }

        if ($message = $this->findByMessageId($payload['message_id_header'] ?? null)) {
            return $this->resolution($message->thread, $message, 'known_message_id', 0.9);
        }

        if ($thread = $this->heuristicThread($mailbox, $payload)) {
            return $this->resolution($thread, null, 'heuristic', 0.6);
        }

        $thread = MailThread::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'mailbox_account_id' => $mailbox->id,
            'organization_id' => $payload['organization_id'] ?? null,
            'contact_id' => $payload['contact_id'] ?? null,
            'subject_canonical' => $this->normalizeSubject($payload['subject'] ?? ''),
            'first_message_at' => $this->messageTimestamp($payload),
            'last_message_at' => $this->messageTimestamp($payload),
            'last_direction' => $payload['direction'] ?? 'in',
            'reply_received' => false,
            'auto_reply_received' => false,
            'confidence_score' => 0.0,
        ]);

        return $this->resolution($thread, null, 'new_thread', 0.0);
    }

    public function normalizeSubject(string $subject): string
    {
        $normalized = Str::lower(trim($subject));

        do {
            $previous = $normalized;
            $normalized = preg_replace('/^(re|fw|fwd)\s*:\s*/i', '', $normalized) ?? $normalized;
        } while ($normalized !== $previous);

        return trim($normalized);
    }

    private function heuristicThread(MailboxAccount $mailbox, array $payload): ?MailThread
    {
        $subject = $this->normalizeSubject($payload['subject'] ?? '');

        if ($subject === '') {
            return null;
        }

        $mailboxEmail = Str::lower($mailbox->email);
        $participants = collect([
            $payload['from_email'] ?? null,
            ...($payload['to_emails'] ?? []),
            ...($payload['cc_emails'] ?? []),
            ...($payload['bcc_emails'] ?? []),
        ])
            ->filter()
            ->map(fn ($email) => Str::lower((string) $email))
            ->reject(fn ($email) => $email === $mailboxEmail)
            ->unique()
            ->values();

        if ($participants->isEmpty()) {
            return null;
        }

        return MailThread::query()
            ->where('mailbox_account_id', $mailbox->id)
            ->where('subject_canonical', $subject)
            ->where('last_message_at', '>=', $this->messageTimestamp($payload)->copy()->subDays(30))
            ->with(['messages' => fn ($query) => $query->latest('created_at')->limit(5)])
            ->get()
            ->first(function (MailThread $thread) use ($participants, $mailboxEmail): bool {
                $threadParticipants = $thread->messages
                    ->flatMap(fn (MailMessage $message) => [
                        $message->from_email,
                        ...($message->to_emails ?? []),
                        ...($message->cc_emails ?? []),
                        ...($message->bcc_emails ?? []),
                    ])
                    ->filter()
                    ->map(fn ($email) => Str::lower((string) $email))
                    ->reject(fn ($email) => $email === $mailboxEmail)
                    ->unique();

                return $threadParticipants->intersect($participants)->isNotEmpty();
            });
    }

    private function findFromReferences(?string $references): ?MailMessage
    {
        if ($references === null || trim($references) === '') {
            return null;
        }

        $messageIds = collect(preg_split('/\s+/', trim($references)) ?: [])
            ->map(fn ($messageId) => trim($messageId))
            ->filter()
            ->reverse();

        foreach ($messageIds as $messageId) {
            if ($message = $this->findByMessageId($messageId)) {
                return $message;
            }
        }

        return null;
    }

    private function findByMessageId(?string $messageId): ?MailMessage
    {
        if ($messageId === null || trim($messageId) === '') {
            return null;
        }

        return MailMessage::query()
            ->with('thread')
            ->where('message_id_header', trim($messageId))
            ->first();
    }

    private function resolution(MailThread $thread, ?MailMessage $matchedMessage, string $strategy, float $confidence): array
    {
        return [
            'thread' => $thread,
            'matched_message' => $matchedMessage,
            'strategy' => $strategy,
            'confidence' => $confidence,
        ];
    }

    private function messageTimestamp(array $payload): Carbon
    {
        return Carbon::parse($payload['received_at'] ?? $payload['sent_at'] ?? now());
    }
}
