<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Mailing\Inbound\MailboxActivityService;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function __construct(
        private readonly MailboxActivityService $activityService,
    ) {
    }

    public function __invoke(): Response
    {
        return Inertia::render('Activity/Index', $this->activityService->activity());
    }
}
