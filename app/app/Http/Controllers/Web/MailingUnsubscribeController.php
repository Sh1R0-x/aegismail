<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Mailing\MailUnsubscribeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MailingUnsubscribeController extends Controller
{
    public function __construct(
        private readonly MailUnsubscribeService $mailUnsubscribeService,
    ) {}

    public function handle(Request $request, string $token): Response
    {
        $recipient = $this->mailUnsubscribeService->unsubscribe($token);

        abort_if($recipient === null, 404);

        return response(
            $request->isMethod('post') ? 'OK' : 'Adresse désinscrite avec succès.',
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
