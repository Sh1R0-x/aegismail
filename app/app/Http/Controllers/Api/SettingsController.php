<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DeliverabilitySettingsRequest;
use App\Http\Requests\Settings\GeneralSettingsRequest;
use App\Http\Requests\Settings\MailConnectionTestRequest;
use App\Http\Requests\Settings\MailSettingsRequest;
use App\Http\Requests\Settings\RefreshDeliverabilityChecksRequest;
use App\Services\Mailing\DeliverabilityDomainCheckService;
use App\Services\Mailing\MailboxConnectionTester;
use App\Services\Mailing\MailboxSettingsService;
use App\Services\Mailing\MailEventLogger;
use App\Services\SettingsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsStore $settingsStore,
        private readonly MailboxSettingsService $mailboxSettingsService,
        private readonly MailboxConnectionTester $mailboxConnectionTester,
        private readonly DeliverabilityDomainCheckService $deliverabilityDomainCheckService,
        private readonly MailEventLogger $eventLogger,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'general' => $this->settingsStore->get('general', config('mailing.defaults.general', [])),
            'mail' => $this->mailboxSettingsService->getSettings(),
            'deliverability' => $this->deliverabilityDomainCheckService->payload(),
        ]);
    }

    public function updateGeneral(GeneralSettingsRequest $request): JsonResponse
    {
        $this->settingsStore->put('general', $request->validated(), $request->user()?->id);
        $this->eventLogger->log('settings.general.updated', $request->validated());

        return response()->json([
            'message' => 'Réglages de cadence et de scoring enregistrés.',
            'general' => $this->settingsStore->get('general', config('mailing.defaults.general', [])),
        ]);
    }

    public function updateMail(MailSettingsRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Réglages mail enregistrés.',
            'mail' => $this->mailboxSettingsService->update($request->validated(), $request->user()?->id),
        ]);
    }

    public function updateDeliverability(DeliverabilitySettingsRequest $request): JsonResponse
    {
        $current = $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', []));
        $payload = array_merge(
            Arr::except($current, [
                'trackOpens',
                'trackClicks',
                'maxLinks',
                'maxImages',
                'maxHtmlSizeKb',
                'maxAttachmentSizeMb',
                'spfValid',
                'dkimValid',
                'dmarcValid',
                'refreshEndpoint',
                'domain',
                'dkimSelectors',
            ]),
            $request->validated(),
            ['checks' => $current['checks'] ?? []],
        );

        $this->settingsStore->put('deliverability', $payload, $request->user()?->id);
        $this->eventLogger->log('settings.deliverability.updated', $request->validated());

        return response()->json([
            'message' => 'Réglages de délivrabilité enregistrés.',
            'deliverability' => $this->deliverabilityDomainCheckService->payload(),
        ]);
    }

    public function refreshDeliverabilityChecks(RefreshDeliverabilityChecksRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Vérifications de délivrabilité relancées.',
            'deliverability' => $this->deliverabilityDomainCheckService->refresh(
                $request->validated('mechanisms'),
                $request->user()?->id,
            ),
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
