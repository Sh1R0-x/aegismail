<?php

namespace App\Services\Mailing\Composer;

use App\Models\MailTemplate;
use App\Services\Mailing\MailEventLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TemplateService
{
    public function __construct(
        private readonly MailEventLogger $eventLogger,
    ) {
    }

    public function list(): array
    {
        return MailTemplate::query()
            ->withCount('drafts')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (MailTemplate $template) => $this->serializeListItem($template))
            ->all();
    }

    public function create(array $validated, ?int $createdBy = null): MailTemplate
    {
        $template = DB::transaction(function () use ($validated, $createdBy): MailTemplate {
            $template = MailTemplate::query()->create([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name']),
                'subject_template' => $validated['subject'],
                'html_template' => $validated['htmlBody'] ?? '',
                'text_template' => $validated['textBody'] ?? '',
                'is_active' => $validated['active'] ?? true,
                'created_by' => $createdBy,
            ]);

            $this->eventLogger->log(
                'mail_template.created',
                ['template_id' => $template->id, 'name' => $template->name],
                [],
                'mail_template.created.'.$template->id,
            );

            return $template;
        });

        return $template->loadCount('drafts');
    }

    public function update(MailTemplate $template, array $validated): MailTemplate
    {
        $template->fill([
            'name' => $validated['name'],
            'subject_template' => $validated['subject'],
            'html_template' => $validated['htmlBody'] ?? '',
            'text_template' => $validated['textBody'] ?? '',
            'is_active' => $validated['active'] ?? $template->is_active,
        ])->save();

        $this->eventLogger->log(
            'mail_template.updated',
            ['template_id' => $template->id],
            [],
            'mail_template.updated.'.$template->id.'.'.$template->updated_at?->timestamp,
        );

        return $template->loadCount('drafts');
    }

    public function duplicate(MailTemplate $template): MailTemplate
    {
        $copy = MailTemplate::query()->create([
            'name' => $template->name.' (copie)',
            'slug' => $this->uniqueSlug($template->slug.'-copy'),
            'subject_template' => $template->subject_template,
            'html_template' => $template->html_template,
            'text_template' => $template->text_template,
            'is_active' => $template->is_active,
            'created_by' => $template->created_by,
        ]);

        $this->eventLogger->log(
            'mail_template.duplicated',
            ['template_id' => $template->id, 'duplicate_id' => $copy->id],
            [],
            'mail_template.duplicated.'.$template->id.'.'.$copy->id,
        );

        return $copy->loadCount('drafts');
    }

    public function archive(MailTemplate $template): MailTemplate
    {
        $template->forceFill(['is_active' => false])->save();

        $this->eventLogger->log(
            'mail_template.archived',
            ['template_id' => $template->id],
            [],
            'mail_template.archived.'.$template->id,
        );

        return $template->loadCount('drafts');
    }

    public function serialize(MailTemplate $template): array
    {
        $template->loadCount('drafts');

        return [
            'id' => $template->id,
            'name' => $template->name,
            'slug' => $template->slug,
            'subject' => $template->subject_template,
            'htmlBody' => $template->html_template,
            'textBody' => $template->text_template,
            'active' => $template->is_active,
            'usageCount' => $template->drafts_count ?? 0,
            'createdAt' => $template->created_at?->toIso8601String(),
            'updatedAt' => $this->formatDate($template->updated_at),
        ];
    }

    public function serializeListItem(MailTemplate $template): array
    {
        return [
            'id' => $template->id,
            'name' => $template->name,
            'subject' => $template->subject_template,
            'active' => $template->is_active,
            'usageCount' => $template->drafts_count ?? 0,
            'updatedAt' => $this->formatDate($template->updated_at),
        ];
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'template';
        $slug = $base;
        $suffix = 2;

        while (MailTemplate::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function formatDate($value): ?string
    {
        return $value?->timezone(config('app.timezone'))->format('Y-m-d H:i');
    }
}
