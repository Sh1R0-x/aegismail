<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DeliverabilitySettingsRequest;
use App\Http\Requests\Settings\GeneralSettingsRequest;
use App\Http\Requests\Settings\MailConnectionTestRequest;
use App\Http\Requests\Settings\MailSettingsRequest;
use App\Services\Mailing\MailEventLogger;
use App\Services\Mailing\MailboxConnectionTester;
use App\Services\Mailing\MailboxSettingsService;
use App\Services\SettingsStore;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsStore $settingsStore,
        private readonly MailboxSettingsService $mailboxSettingsService,
        private readonly MailboxConnectionTester $mailboxConnectionTester,
        private readonly MailEventLogger $eventLogger,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'general' => $this->settingsStore->get('general', config('mailing.defaults.general', [])),
            'mail' => $this->mailboxSettingsService->getSettings(),
            'deliverability' => $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', [])),
        ]);
    }

    public function updateGeneral(GeneralSettingsRequest $request): JsonResponse
    {
        $this->settingsStore->put('general', $request->validated(), $request->user()?->id);
        $this->eventLogger->log('settings.general.updated', $request->validated());

        return response()->json([
            'message' => 'General settings updated.',
            'general' => $this->settingsStore->get('general', config('mailing.defaults.general', [])),
        ]);
    }

    public function updateMail(MailSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Mail settings updated.',
            'mail' => $this->mailboxSettingsService->update($request->validated(), $request->user()?->id),
        ]);
    }

    public function updateDeliverability(DeliverabilitySettingsRequest $request): JsonResponse
    {
        $this->settingsStore->put('deliverability', $request->validated(), $request->user()?->id);
        $this->eventLogger->log('settings.deliverability.updated', $request->validated());

        return response()->json([
            'message' => 'Deliverability settings updated.',
            'deliverability' => $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', [])),
        ]);
    }

    public function testImap(MailConnectionTestRequest $request): JsonResponse
    {
        $result = $this->mailboxConnectionTester->testImap($request->validated());
        $statusCode = $result['status_code'] ?? 200;
        unset($result['status_code']);

        return response()->json($result, $statusCode);
    }

    public function testSmtp(MailConnectionTestRequest $request): JsonResponse
    {
        $result = $this->mailboxConnectionTester->testSmtp($request->validated());
        $statusCode = $result['status_code'] ?? 200;
        unset($result['status_code']);

        return response()->json($result, $statusCode);
    }
}
