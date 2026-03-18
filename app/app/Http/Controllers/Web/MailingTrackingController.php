<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Mailing\Tracking\MailTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class MailingTrackingController extends Controller
{
    private const TRANSPARENT_GIF = 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

    public function __construct(
        private readonly MailTrackingService $trackingService,
    ) {
    }

    public function open(string $token): Response
    {
        $this->trackingService->registerOpen($token);

        return response(base64_decode(self::TRANSPARENT_GIF), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Mon, 01 Jan 1990 00:00:00 GMT',
        ]);
    }

    public function click(string $token): RedirectResponse
    {
        $url = $this->trackingService->registerClickAndResolveRedirect($token);

        abort_if($url === null, 404);

        return redirect()->away($url);
    }
}
