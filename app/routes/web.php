<?php

use App\Http\Controllers\Web\ActivityController;
use App\Http\Controllers\Web\ContactsController;
use App\Http\Controllers\Web\CampaignsController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DraftsController;
use App\Http\Controllers\Web\MailsController;
use App\Http\Controllers\Web\OrganizationsController;
use App\Http\Controllers\Web\TemplatesController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/dashboard', DashboardController::class);

Route::get('/contacts', ContactsController::class);

Route::get('/organizations', OrganizationsController::class);

Route::get('/mails', MailsController::class);

Route::get('/drafts', DraftsController::class);

Route::get('/templates', TemplatesController::class);

Route::get('/campaigns', CampaignsController::class);

Route::get('/activity', ActivityController::class);

Route::get('/settings', function () {
    return Inertia::render('Settings/Index');
});

Route::get('/users', function () {
    return Inertia::render('Users/Index');
});
