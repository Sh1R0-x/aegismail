<?php

use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\DraftController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\ThreadController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')->group(function () {
    Route::get('/', [SettingsController::class, 'index']);
    Route::put('/general', [SettingsController::class, 'updateGeneral']);
    Route::put('/mail', [SettingsController::class, 'updateMail']);
    Route::put('/deliverability', [SettingsController::class, 'updateDeliverability']);
    Route::post('/mail/test-imap', [SettingsController::class, 'testImap']);
    Route::post('/mail/test-smtp', [SettingsController::class, 'testSmtp']);
});

Route::get('/templates', [TemplateController::class, 'index']);
Route::post('/templates', [TemplateController::class, 'store']);
Route::put('/templates/{template}', [TemplateController::class, 'update']);
Route::post('/templates/{template}/duplicate', [TemplateController::class, 'duplicate']);
Route::post('/templates/{template}/archive', [TemplateController::class, 'archive']);
Route::post('/templates/{template}/activate', [TemplateController::class, 'activate']);

Route::get('/drafts', [DraftController::class, 'index']);
Route::post('/drafts', [DraftController::class, 'store']);
Route::get('/drafts/{draft}', [DraftController::class, 'show']);
Route::put('/drafts/{draft}', [DraftController::class, 'update']);
Route::post('/drafts/{draft}/duplicate', [DraftController::class, 'duplicate']);
Route::post('/drafts/{draft}/preflight', [DraftController::class, 'preflight']);
Route::post('/drafts/{draft}/schedule', [DraftController::class, 'schedule']);
Route::post('/drafts/{draft}/unschedule', [DraftController::class, 'unschedule']);
Route::post('/drafts/{draft}/campaign', [DraftController::class, 'createCampaign']);

Route::get('/campaigns', [CampaignController::class, 'index']);
Route::get('/threads', [ThreadController::class, 'index']);
Route::get('/threads/{thread}', [ThreadController::class, 'show']);
