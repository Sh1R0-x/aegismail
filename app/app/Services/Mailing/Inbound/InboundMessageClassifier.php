<?php

namespace App\Services\Mailing\Inbound;

use Illuminate\Support\Str;

class InboundMessageClassifier
{
    public function classify(array $payload): string
    {
        $headers = collect($payload['headers_json'] ?? [])
            ->mapWithKeys(fn ($value, $key) => [Str::lower((string) $key) => is_array($value) ? implode(' ', $value) : (string) $value]);
        $subject = Str::lower((string) ($payload['subject'] ?? ''));
        $text = Str::lower(trim((string) (($payload['text_body'] ?? '').' '.($payload['html_body'] ?? ''))));
        $fromEmail = Str::lower((string) ($payload['from_email'] ?? ''));

        if ($this->isSoftBounce($fromEmail, $subject, $text, $headers->all())) {
            return 'soft_bounce';
        }

        if ($this->isHardBounce($fromEmail, $subject, $text, $headers->all())) {
            return 'hard_bounce';
        }

        if ($this->isOutOfOffice($subject, $text, $headers->all())) {
            return 'out_of_office';
        }

        if ($this->isAutoAcknowledgement($subject, $text, $headers->all())) {
            return 'auto_ack';
        }

        if ($this->isAutoReply($subject, $text, $headers->all())) {
            return 'auto_reply';
        }

        if ($this->isSystem($fromEmail, $headers->all())) {
            return 'system';
        }

        return $this->looksLikeHumanReply($payload, $subject) ? 'human_reply' : 'unknown';
    }

    private function isAutoReply(string $subject, string $text, array $headers): bool
    {
        $autoSubmitted = Str::lower((string) ($headers['auto-submitted'] ?? ''));

        return ($autoSubmitted !== '' && $autoSubmitted !== 'no')
            || isset($headers['x-autoreply'])
            || isset($headers['x-autorespond'])
            || Str::contains($subject, ['automatic reply', 'réponse automatique', 'auto reply']);
    }

    private function isOutOfOffice(string $subject, string $text, array $headers): bool
    {
        return $this->isAutoReply($subject, $text, $headers)
            && (Str::contains($subject, ['out of office', 'absence', 'congés', 'vacation'])
                || Str::contains($text, ['out of office', 'absent du bureau', 'je suis absent', 'i am away']));
    }

    private function isAutoAcknowledgement(string $subject, string $text, array $headers): bool
    {
        return $this->isAutoReply($subject, $text, $headers)
            && (Str::contains($subject, ['acknowledgement', 'accusé de réception', 'confirmation de réception'])
                || Str::contains($text, ['we received your email', 'nous avons bien reçu', 'accusons réception']));
    }

    private function isHardBounce(string $fromEmail, string $subject, string $text, array $headers): bool
    {
        if (! $this->looksLikeBounceSender($fromEmail, $headers)) {
            return false;
        }

        return Str::contains($subject.' '.$text, [
            'delivery failure',
            'delivery status notification (failure)',
            'undeliverable',
            'mail delivery failed',
            'recipient address rejected',
            'no such user',
            'unknown user',
            'user unknown',
            'permanent error',
            'hard bounce',
        ]);
    }

    private function isSoftBounce(string $fromEmail, string $subject, string $text, array $headers): bool
    {
        if (! $this->looksLikeBounceSender($fromEmail, $headers)) {
            return false;
        }

        return Str::contains($subject.' '.$text, [
            'mailbox full',
            'temporarily deferred',
            'temporary error',
            'try again later',
            'greylisted',
            'soft bounce',
            'quota exceeded',
        ]);
    }

    private function looksLikeBounceSender(string $fromEmail, array $headers): bool
    {
        return Str::contains($fromEmail, ['mailer-daemon', 'postmaster'])
            || Str::contains(Str::lower((string) ($headers['return-path'] ?? '')), ['mailer-daemon', 'postmaster']);
    }

    private function isSystem(string $fromEmail, array $headers): bool
    {
        return Str::contains($fromEmail, ['noreply', 'no-reply', 'system', 'notification'])
            || in_array(Str::lower((string) ($headers['precedence'] ?? '')), ['bulk', 'list', 'junk'], true);
    }

    private function looksLikeHumanReply(array $payload, string $subject): bool
    {
        if (filled($payload['in_reply_to_header'] ?? null) || filled($payload['references_header'] ?? null)) {
            return true;
        }

        return preg_match('/^(re)\s*:/i', trim($subject)) === 1;
    }
}
