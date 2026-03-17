<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MailThread;
use App\Services\Mailing\Inbound\MailboxActivityService;
use Inertia\Inertia;
use Inertia\Response;

class ThreadShowController extends Controller
{
    public function __invoke(MailThread $thread, MailboxActivityService $activityService): Response
    {
        return Inertia::render('Threads/Show', $activityService->thread($thread));
    }
}
