<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SettingsPageDataService;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __invoke(SettingsPageDataService $settingsPageDataService): Response
    {
        return Inertia::render('Settings/Index', $settingsPageDataService->page());
    }
}
