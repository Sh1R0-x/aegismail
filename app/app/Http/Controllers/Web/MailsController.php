<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Mailing\Composer\ComposerPageDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MailsController extends Controller
{
    public function __invoke(Request $request, ComposerPageDataService $pageDataService): Response
    {
        return Inertia::render('Mails/Index', $pageDataService->mails(
            $request->only(['status'])
        ));
    }
}
