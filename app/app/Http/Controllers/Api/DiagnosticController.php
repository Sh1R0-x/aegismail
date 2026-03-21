<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MailEvent;
use App\Models\MailRecipient;
use App\Services\Mailing\MailboxSettingsService;
use App\Services\Mailing\SmtpProviderService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiagnosticController extends Controller
{
    private const SENSITIVE_KEYS = ['password', 'password_encrypted', 'token', 'secret', 'api_key'];

    public function __construct(
        private readonly MailboxSettingsService $mailboxSettingsService,
        private readonly SmtpProviderService $smtpProviderService,
    ) {}

    public function events(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => ['nullable', 'string', 'max:100'],
            'campaign_id' => ['nullable', 'integer'],
            'recipient_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:200'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = MailEvent::query()->latest('occurred_at')->latest('id');

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', (int) $request->input('campaign_id'));
        }

        if ($request->filled('recipient_id')) {
            $query->where('recipient_id', (int) $request->input('recipient_id'));
        }

        if ($request->filled('from')) {
            $query->where('occurred_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('occurred_at', '<=', $request->input('to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('event_type', 'like', "%{$search}%")
                  ->orWhere('event_payload', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 30);
        $paginated = $query->paginate($perPage);

        $tz = config('app.timezone');

        $paginated->getCollection()->transform(function (MailEvent $event) use ($tz) {
            $event->event_payload = $this->scrubSecrets($event->event_payload ?? []);
            $event->occurred_at = $event->occurred_at?->timezone($tz);

            return $event;
        });

        return response()->json($paginated);
    }

    public function eventTypes(): JsonResponse
    {
        $types = MailEvent::query()
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->pluck('count', 'event_type');

        return response()->json($types);
    }

    public function health(): JsonResponse
    {
        $mailbox = $this->mailboxSettingsService->mailbox();
        $providers = $this->smtpProviderService->providersPayload($mailbox);
        $driver = config('mailing.gateway.driver');

        $stuckThreshold = CarbonImmutable::now()->subMinutes(30);
        $stuckRecipients = MailRecipient::query()
            ->whereIn('status', ['queued', 'sending'])
            ->where(function ($q) use ($stuckThreshold) {
                $q->where('scheduled_for', '<', $stuckThreshold)
                  ->orWhere(function ($inner) use ($stuckThreshold) {
                      $inner->whereNull('scheduled_for')
                            ->where('created_at', '<', $stuckThreshold);
                  });
            })
            ->count();

        $queuedCount = MailRecipient::query()
            ->where('status', 'queued')
            ->count();

        $sendingCount = MailRecipient::query()
            ->where('status', 'sending')
            ->count();

        $recentErrors = MailEvent::query()
            ->where('event_type', 'like', '%failed%')
            ->where('occurred_at', '>=', CarbonImmutable::now()->subHours(24))
            ->count();

        $lastEvent = MailEvent::query()
            ->latest('occurred_at')
            ->value('occurred_at');

        return response()->json([
            'gateway_driver' => $driver,
            'mailbox_configured' => $mailbox !== null && filled($mailbox->email),
            'mailbox_health_status' => $mailbox?->health_status ?? 'unknown',
            'mailbox_health_message' => $mailbox?->health_message,
            'providers' => collect($providers)->map(fn (array $p) => [
                'provider' => $p['provider'],
                'label' => $p['label'],
                'configured' => $p['configured'],
                'activatable' => $p['activatable'],
                'ready' => $p['ready'],
                'health_status' => $p['health_status'],
                'health_message' => $p['health_message'],
            ])->values(),
            'queue' => [
                'queued' => $queuedCount,
                'sending' => $sendingCount,
                'stuck' => $stuckRecipients,
            ],
            'errors_last_24h' => $recentErrors,
            'last_event_at' => $lastEvent?->timezone(config('app.timezone')),
        ]);
    }

    public function stuckRecipients(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $stuckThreshold = CarbonImmutable::now()->subMinutes(30);

        $stuck = MailRecipient::query()
            ->with(['campaign:id,name,status', 'contact:id,first_name,last_name'])
            ->whereIn('status', ['queued', 'sending'])
            ->where(function ($q) use ($stuckThreshold) {
                $q->where('scheduled_for', '<', $stuckThreshold)
                  ->orWhere(function ($inner) use ($stuckThreshold) {
                      $inner->whereNull('scheduled_for')
                            ->where('created_at', '<', $stuckThreshold);
                  });
            })
            ->latest('scheduled_for')
            ->paginate($request->integer('per_page', 30));

        return response()->json($stuck);
    }

    private function scrubSecrets(array $payload): array
    {
        $scrubbed = [];

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $scrubbed[$key] = $this->scrubSecrets($value);
            } elseif ($this->isSensitiveKey($key)) {
                $scrubbed[$key] = '[REDACTED]';
            } else {
                $scrubbed[$key] = $value;
            }
        }

        return $scrubbed;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if (str_contains($lower, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
