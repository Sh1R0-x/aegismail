<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\PreviewContactImportRequest;
use App\Http\Requests\Crm\StoreContactImportRequest;
use App\Services\Crm\ContactImportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactImportController extends Controller
{
    public function __construct(
        private readonly ContactImportService $contactImportService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'module' => $this->contactImportService->modulePayload(),
        ]);
    }

    public function template(): StreamedResponse
    {
        $content = $this->contactImportService->templateDownload();

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, 'aegis-contacts-organizations-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function export(): StreamedResponse
    {
        $content = $this->contactImportService->exportDownload();

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, 'aegis-contacts-organizations-export.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function preview(PreviewContactImportRequest $request): JsonResponse
    {
        return response()->json([
            'preview' => $this->contactImportService->preview($request->file('file')),
        ]);
    }

    public function store(StoreContactImportRequest $request): JsonResponse
    {
        return response()->json(
            $this->contactImportService->importFromPreviewToken(
                $request->validated('previewToken'),
                $request->user()?->id,
            ),
            201,
        );
    }
}
