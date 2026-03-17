<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mailing\UpsertTemplateRequest;
use App\Models\MailTemplate;
use App\Services\Mailing\Composer\TemplateService;
use Illuminate\Http\JsonResponse;

class TemplateController extends Controller
{
    public function __construct(
        private readonly TemplateService $templateService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'templates' => $this->templateService->list(),
        ]);
    }

    public function store(UpsertTemplateRequest $request): JsonResponse
    {
        $template = $this->templateService->create($request->validated(), $request->user()?->id);

        return response()->json([
            'template' => $this->templateService->serialize($template),
        ], 201);
    }

    public function update(UpsertTemplateRequest $request, MailTemplate $template): JsonResponse
    {
        $template = $this->templateService->update($template, $request->validated());

        return response()->json([
            'template' => $this->templateService->serialize($template),
        ]);
    }

    public function duplicate(MailTemplate $template): JsonResponse
    {
        $template = $this->templateService->duplicate($template);

        return response()->json([
            'template' => $this->templateService->serialize($template),
        ], 201);
    }

    public function archive(MailTemplate $template): JsonResponse
    {
        $template = $this->templateService->archive($template);

        return response()->json([
            'template' => $this->templateService->serialize($template),
        ]);
    }

    public function activate(MailTemplate $template): JsonResponse
    {
        $template = $this->templateService->activate($template);

        return response()->json([
            'template' => $this->templateService->serialize($template),
        ]);
    }
}
