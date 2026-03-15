<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Mailing\Composer\ComposerPageDataService;
use Inertia\Inertia;
use Inertia\Response;

class DraftsController extends Controller
{
    public function __invoke(ComposerPageDataService $pageDataService): Response
    {
        return Inertia::render('Drafts/Index', $pageDataService->drafts());
    }
}
