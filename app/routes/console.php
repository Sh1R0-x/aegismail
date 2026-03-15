<?php

use App\Jobs\Mailing\SyncMailboxFolderJob;
use App\Models\MailboxAccount;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mailbox:poll', function () {
    $mailbox = MailboxAccount::query()
        ->where('provider', config('mailing.provider'))
        ->where('sync_enabled', true)
        ->first();

    if ($mailbox === null) {
        $this->comment('No OVH mailbox configured for IMAP polling.');

        return 0;
    }

    foreach (['INBOX', 'SENT'] as $folder) {
        SyncMailboxFolderJob::dispatch([
            'mailbox_account_id' => $mailbox->id,
            'folder' => $folder,
            'idempotency_key' => 'mailbox.poll.'.$mailbox->id.'.'.strtolower($folder).'.'.now()->format('YmdHi'),
        ]);
    }

    $this->comment('Mailbox polling jobs dispatched for INBOX and SENT.');

    return 0;
})->purpose('Dispatch IMAP polling jobs for the unique OVH mailbox');

Schedule::command('mailbox:poll')
    ->everyFiveMinutes()
    ->withoutOverlapping();
