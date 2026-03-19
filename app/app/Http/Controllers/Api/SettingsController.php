<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DeliverabilitySettingsRequest;
use App\Http\Requests\Settings\GeneralSettingsRequest;
use App\Http\Requests\Settings\MailConnectionTestRequest;
use App\Http\Requests\Settings\MailSettingsRequest;
use App\Services\Mailing\MailboxConnectionTester;
use App\Services\Mailing\MailboxSettingsService;
use App\Services\Mailing\MailEventLogger;
use App\Services\Mailing\PublicEmailUrlService;
use App\Services\SettingsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsStore $settingsStore,
        private readonly MailboxSettingsService $mailboxSettingsService,
        private readonly MailboxConnectionTester $mailboxConnectionTester,
        private readonly PublicEmailUrlService $publicEmailUrlService,
        private readonly MailEventLogger $eventLogger,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'general' => $this->settingsStore->get('general', config('mailing.defaults.general', [])),
            'mail' => $this->mailboxSettingsService->getSettings(),
            'deliverability' => $this->deliverabilityPayload(),
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
        $current = Arr::except(
            $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', [])),
            [
                'checks',
                'domain_override',
                'dkim_selectors',
                'trackOpens',
                'trackClicks',
                'maxLinks',
                'maxImages',
                'maxHtmlSizeKb',
                'maxAttachmentSizeMb',
                'publicBaseUrl',
                'trackingBaseUrl',
                'publicBaseUrlStatus',
                'trackingBaseUrlStatus',
                'publicBaseUrlIssue',
                'trackingBaseUrlIssue',
            ],
        );
        $payload = array_merge($current, $request->validated());

        $this->settingsStore->put('deliverability', $payload, $request->user()?->id);
        $this->eventLogger->log('settings.deliverability.updated', $request->validated());

        return response()->json([
            'message' => 'Réglages de délivrabilité enregistrés.',
            'deliverability' => $this->deliverabilityPayload(),
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

    private function deliverabilityPayload(): array
    {
        $settings = Arr::except(
            $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', [])),
            ['checks', 'domain_override', 'dkim_selectors'],
        );
        $publicBase = $this->publicEmailUrlService->publicBaseReport();
        $trackingBase = $this->publicEmailUrlService->trackingBaseReport();

        return array_merge($settings, [
            'trackOpens' => (bool) ($settings['tracking_opens_enabled'] ?? true),
            'trackClicks' => (bool) ($settings['tracking_clicks_enabled'] ?? true),
            'maxLinks' => (int) ($settings['max_links_warning_threshold'] ?? 8),
            'maxImages' => (int) ($settings['max_remote_images_warning_threshold'] ?? 3),
            'maxHtmlSizeKb' => (int) ($settings['html_size_warning_kb'] ?? 100),
            'maxAttachmentSizeMb' => (int) ($settings['attachment_size_warning_mb'] ?? 10),
            'publicBaseUrl' => $publicBase['resolved'],
            'trackingBaseUrl' => $trackingBase['resolved'],
            'publicBaseUrlStatus' => $publicBase['status'],
            'trackingBaseUrlStatus' => $trackingBase['status'],
            'publicBaseUrlIssue' => $publicBase['issue'],
            'trackingBaseUrlIssue' => $trackingBase['issue'],
        ]);
    }
}
