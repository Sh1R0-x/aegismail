<?php

use App\Http\Controllers\Web\ActivityController;
use App\Http\Controllers\Web\ContactsController;
use App\Http\Controllers\Web\ContactShowController;
use App\Http\Controllers\Web\CampaignsController;
use App\Http\Controllers\Web\CampaignCreateController;
use App\Http\Controllers\Web\CampaignShowController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DraftsController;
use App\Http\Controllers\Web\MailsController;
use App\Http\Controllers\Web\OrganizationShowController;
use App\Http\Controllers\Web\OrganizationsController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\TemplatesController;
use App\Http\Controllers\Web\ThreadShowController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::get('/dashboard', DashboardController::class);

Route::get('/contacts', ContactsController::class);
Route::get('/contacts/imports', fn () => Inertia::render('Contacts/Import'));
Route::get('/contacts/{contact}', ContactShowController::class);

Route::get('/organizations', OrganizationsController::class);
Route::get('/organizations/{organization}', OrganizationShowController::class);

Route::get('/mails', MailsController::class);

Route::get('/drafts', DraftsController::class);

Route::get('/templates', TemplatesController::class);

Route::get('/campaigns', CampaignsController::class);
Route::get('/campaigns/create', CampaignCreateController::class);
Route::get('/campaigns/{campaign}', CampaignShowController::class);

Route::get('/activity', ActivityController::class);

Route::get('/threads/{thread}', ThreadShowController::class);

Route::get('/settings', SettingsController::class);

Route::get('/users', function () {
    return Inertia::render('Users/Index');
});
