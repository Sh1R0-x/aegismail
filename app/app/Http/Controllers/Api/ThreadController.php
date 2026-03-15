<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MailThread;
use App\Services\Mailing\Inbound\MailboxActivityService;
use Illuminate\Http\JsonResponse;

class ThreadController extends Controller
{
    public function __construct(
        private readonly MailboxActivityService $activityService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'threads' => $this->activityService->threads(),
        ]);
    }

    public function show(MailThread $thread): JsonResponse
    {
        return response()->json($this->activityService->thread($thread));
    }
}
