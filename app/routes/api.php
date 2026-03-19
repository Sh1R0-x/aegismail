<?php

use App\Http\Controllers\Api\CampaignAutosaveController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\CampaignManagementController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactImportController;
use App\Http\Controllers\Api\DraftController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\ThreadController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')->group(function () {
    Route::get('/', [SettingsController::class, 'index']);
    Route::put('/general', [SettingsController::class, 'updateGeneral']);
    Route::put('/mail', [SettingsController::class, 'updateMail']);
    Route::put('/deliverability', [SettingsController::class, 'updateDeliverability']);
    Route::post('/mail/test-imap', [SettingsController::class, 'testImap'])->middleware('throttle:5,1');
    Route::post('/mail/test-smtp', [SettingsController::class, 'testSmtp'])->middleware('throttle:5,1');
});

Route::get('/templates', [TemplateController::class, 'index']);
Route::post('/templates', [TemplateController::class, 'store']);
Route::put('/templates/{template}', [TemplateController::class, 'update']);
Route::delete('/templates/{template}', [TemplateController::class, 'destroy']);
Route::post('/templates/{template}/duplicate', [TemplateController::class, 'duplicate']);

Route::get('/drafts', [DraftController::class, 'index']);
Route::post('/drafts', [DraftController::class, 'store']);
Route::get('/drafts/{draft}', [DraftController::class, 'show']);
Route::put('/drafts/{draft}', [DraftController::class, 'update']);
Route::delete('/drafts/{draft}', [DraftController::class, 'destroy']);
Route::post('/drafts/{draft}/duplicate', [DraftController::class, 'duplicate']);
Route::post('/drafts/{draft}/preflight', [DraftController::class, 'preflight']);
Route::post('/drafts/{draft}/schedule', [DraftController::class, 'schedule']);
Route::post('/drafts/{draft}/unschedule', [DraftController::class, 'unschedule']);
Route::post('/drafts/{draft}/send-now', [DraftController::class, 'sendNow']);
Route::post('/drafts/{draft}/test-send', [DraftController::class, 'testSend'])->middleware('throttle:5,1');
Route::post('/drafts/{draft}/campaign', [DraftController::class, 'createCampaign']);
Route::post('/drafts/{draft}/attachments', [DraftController::class, 'uploadAttachment']);
Route::delete('/drafts/{draft}/attachments/{attachment}', [DraftController::class, 'deleteAttachment']);
Route::post('/drafts/bulk-delete', [DraftController::class, 'bulkDestroy']);

Route::get('/campaigns', [CampaignController::class, 'index']);
Route::get('/campaigns/audiences', [CampaignAutosaveController::class, 'audiences']);
Route::post('/campaigns/autosave', [CampaignAutosaveController::class, 'autosave']);
Route::post('/campaigns/{campaign}/clone', [CampaignManagementController::class, 'clone']);
Route::delete('/campaigns/{campaign}', [CampaignManagementController::class, 'destroy']);
Route::get('/threads', [ThreadController::class, 'index']);
Route::get('/threads/{thread}', [ThreadController::class, 'show']);
Route::get('/import-export', [ContactImportController::class, 'index']);
Route::get('/import-export/template', [ContactImportController::class, 'template']);
Route::get('/import-export/export', [ContactImportController::class, 'export']);
Route::post('/import-export/preview', [ContactImportController::class, 'preview']);
Route::post('/import-export/confirm', [ContactImportController::class, 'store']);
Route::post('/contacts', [ContactController::class, 'store']);
Route::get('/contacts/search', [ContactController::class, 'search']);
Route::get('/contacts/imports/template', [ContactImportController::class, 'template']);
Route::get('/contacts/imports/export', [ContactImportController::class, 'export']);
Route::post('/contacts/imports/preview', [ContactImportController::class, 'preview']);
Route::post('/contacts/imports', [ContactImportController::class, 'store']);
Route::get('/contacts/{contact}', [ContactController::class, 'show']);
Route::put('/contacts/{contact}', [ContactController::class, 'update']);
Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);
Route::post('/contacts/{contact}/emails', [ContactController::class, 'storeEmail']);
Route::delete('/contacts/{contact}/emails/{contactEmail}', [ContactController::class, 'destroyEmail']);
Route::post('/organizations', [OrganizationController::class, 'store']);
Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);
Route::put('/organizations/{organization}', [OrganizationController::class, 'update']);
Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy']);
