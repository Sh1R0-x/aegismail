<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreOrganizationRequest;
use App\Http\Requests\Crm\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Services\Crm\CrmManagementService;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly CrmManagementService $crmManagementService,
    ) {
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = $this->crmManagementService->createOrganization($request->validated());

        return response()->json([
            'message' => 'Organisation créée.',
            'organization' => $this->crmManagementService->serializeOrganization($organization),
        ], 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        return response()->json([
            'organization' => $this->crmManagementService->serializeOrganizationDetail($organization),
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResponse
    {
        $organization = $this->crmManagementService->updateOrganization($organization, $request->validated());

        return response()->json([
            'message' => 'Organisation mise à jour.',
            'organization' => $this->crmManagementService->serializeOrganizationDetail($organization),
        ]);
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $this->crmManagementService->deleteOrganization($organization);

        return response()->json([
            'message' => 'Organisation supprimée.',
        ]);
    }
}
