<?php

namespace App\Services\Mailing\Inbound;

use App\Models\MailEvent;
use App\Models\MailMessage;
use App\Models\MailThread;
use Illuminate\Support\Carbon;

class MailboxActivityService
{
    public function activity(): array
    {
        $messageEvents = MailMessage::query()
            ->with(['thread.contact.organization', 'recipient'])
            ->orderByRaw('coalesce(received_at, sent_at, created_at) desc')
            ->limit(100)
            ->get();

        $loadedMessageIds = $messageEvents->pluck('id')->all();

        $mappedMessages = $messageEvents
            ->map(fn (MailMessage $message): array => [
                'id' => $message->id,
                'threadId' => $message->thread?->id,
                'title' => $message->subject !== '' ? $message->subject : '(Sans objet)',
                'description' => $this->description($message),
                'status' => $this->status($message),
                'direction' => $message->direction === 'out' ? 'outbound' : 'inbound',
                'isAutoReply' => in_array($message->classification, ['auto_reply', 'out_of_office', 'auto_ack'], true),
                'isBounce' => in_array($message->classification, ['soft_bounce', 'hard_bounce'], true),
                'date' => $this->formatDate($message->received_at ?? $message->sent_at ?? $message->created_at),
                'sortDate' => $message->received_at ?? $message->sent_at ?? $message->created_at,
            ]);

        $trackingQuery = MailEvent::query()
            ->with(['mailMessage.thread.contact.organization'])
            ->whereIn('event_type', ['mail_message.opened', 'mail_message.clicked'])
            ->orderByDesc('occurred_at')
            ->limit(100);

        if ($loadedMessageIds !== []) {
            $trackingQuery->whereNotIn('message_id', $loadedMessageIds);
        }

        $trackingEvents = $trackingQuery
            ->get()
            ->map(fn (MailEvent $event): ?array => $this->trackingEvent($event))
            ->filter();

        $events = $mappedMessages
            ->concat($trackingEvents)
            ->sortByDesc(fn (array $event) => $event['sortDate'] ?? null)
            ->take(100)
            ->map(fn (array $event) => collect($event)->except('sortDate')->all())
            ->values()
            ->all();

        return [
            'events' => $events,
        ];
    }

    public function threads(): array
    {
        return MailThread::query()
            ->with(['contact.organization', 'messages' => fn ($query) => $query->latest('created_at')])
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get()
            ->map(fn (MailThread $thread): array => [
                'id' => $thread->id,
                'publicUuid' => $thread->public_uuid,
                'subject' => $thread->messages->first()?->subject ?: $thread->subject_canonical,
                'contactName' => trim((string) ($thread->contact?->first_name.' '.$thread->contact?->last_name)) ?: null,
                'organization' => $thread->contact?->organization?->name,
                'replyReceived' => $thread->reply_received,
                'autoReplyReceived' => $thread->auto_reply_received,
                'lastDirection' => $thread->last_direction,
                'lastActivityAt' => $this->formatDate($thread->last_message_at),
                'messageCount' => $thread->messages->count(),
            ])
            ->all();
    }

    public function thread(MailThread $thread): array
    {
        $thread->load(['contact.organization', 'messages.attachments']);

        return [
            'thread' => [
                'id' => $thread->id,
                'publicUuid' => $thread->public_uuid,
                'subject' => $thread->messages->sortByDesc('created_at')->first()?->subject ?: $thread->subject_canonical,
                'contactName' => trim((string) ($thread->contact?->first_name.' '.$thread->contact?->last_name)) ?: null,
                'organization' => $thread->contact?->organization?->name,
                'replyReceived' => $thread->reply_received,
                'autoReplyReceived' => $thread->auto_reply_received,
                'lastDirection' => $thread->last_direction,
                'lastActivityAt' => $this->formatDate($thread->last_message_at),
                'messages' => $thread->messages
                    ->sortBy(fn (MailMessage $message) => $message->received_at ?? $message->sent_at ?? $message->created_at)
                    ->map(fn (MailMessage $message): array => [
                        'id' => $message->id,
                        'direction' => $message->direction,
                        'fromEmail' => $message->from_email,
                        'toEmails' => $message->to_emails ?? [],
                        'subject' => $message->subject,
                        'htmlBody' => $message->html_body,
                        'textBody' => $message->text_body,
                        'classification' => $message->classification,
                        'messageIdHeader' => $message->message_id_header,
                        'inReplyToHeader' => $message->in_reply_to_header,
                        'referencesHeader' => $message->references_header,
                        'sentAt' => $this->formatDate($message->sent_at),
                        'receivedAt' => $this->formatDate($message->received_at),
                        'hasAttachments' => $message->attachments->isNotEmpty(),
                        'attachmentCount' => $message->attachments->count(),
                    ])->values()->all(),
            ],
        ];
    }

    private function description(MailMessage $message): string
    {
        $thread = $message->thread;
        $contact = trim((string) ($thread?->contact?->first_name.' '.$thread?->contact?->last_name));

        return implode(' · ', array_filter([
            $message->from_email,
            $contact !== '' ? $contact : null,
            $thread?->contact?->organization?->name,
        ]));
    }

    private function status(MailMessage $message): string
    {
        if ($message->direction === 'out') {
            if ($message->recipient !== null) {
                return $message->recipient->status;
            }

            return $message->sent_at !== null ? 'sent' : 'queued';
        }

        return match ($message->classification) {
            'human_reply' => 'replied',
            'auto_reply', 'out_of_office', 'auto_ack' => 'auto_replied',
            'soft_bounce' => 'soft_bounced',
            'hard_bounce' => 'hard_bounced',
            default => 'delivered_if_known',
        };
    }

    private function trackingEvent(MailEvent $event): ?array
    {
        $message = $event->mailMessage;

        if ($message === null) {
            return null;
        }

        $isClick = $event->event_type === 'mail_message.clicked';
        $contact = trim((string) ($message->thread?->contact?->first_name.' '.$message->thread?->contact?->last_name));
        $parts = [
            $message->from_email,
            $contact !== '' ? $contact : null,
            $message->thread?->contact?->organization?->name,
            $isClick ? data_get($event->event_payload, 'url') : null,
        ];

        return [
            'id' => 'tracking-'.$event->id,
            'threadId' => $message->thread?->id,
            'title' => $message->subject !== '' ? $message->subject : '(Sans objet)',
            'description' => implode(' · ', array_filter($parts)),
            'status' => $isClick ? 'clicked' : 'opened',
            'direction' => 'outbound',
            'isAutoReply' => false,
            'isBounce' => false,
            'date' => $this->formatDate($event->occurred_at),
            'sortDate' => $event->occurred_at,
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = $value instanceof Carbon ? $value : Carbon::parse($value);

        return $date->timezone(config('app.timezone'))->toIso8601String();
    }
}
