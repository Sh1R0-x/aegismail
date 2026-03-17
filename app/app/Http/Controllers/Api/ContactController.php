<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreContactRequest;
use App\Http\Requests\Crm\StoreContactEmailRequest;
use App\Http\Requests\Crm\UpdateContactRequest;
use App\Models\Contact;
use App\Models\ContactEmail;
use App\Services\Crm\CrmManagementService;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function __construct(
        private readonly CrmManagementService $crmManagementService,
    ) {
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $this->crmManagementService->createContact($request->validated());

        return response()->json([
            'message' => 'Contact créé.',
            'contact' => $this->crmManagementService->serializeContact($contact),
        ], 201);
    }

    public function show(Contact $contact): JsonResponse
    {
        return response()->json([
            'contact' => $this->crmManagementService->serializeContactDetail($contact),
        ]);
    }

    public function update(UpdateContactRequest $request, Contact $contact): JsonResponse
    {
        $contact = $this->crmManagementService->updateContact($contact, $request->validated());

        return response()->json([
            'message' => 'Contact mis à jour.',
            'contact' => $this->crmManagementService->serializeContactDetail($contact),
        ]);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $this->crmManagementService->deleteContact($contact);

        return response()->json([
            'message' => 'Contact supprimé.',
        ]);
    }

    public function storeEmail(StoreContactEmailRequest $request, Contact $contact): JsonResponse
    {
        $contact = $this->crmManagementService->addContactEmail($contact, $request->validated());

        return response()->json([
            'message' => 'Adresse e-mail ajoutée.',
            'contact' => $this->crmManagementService->serializeContactDetail($contact),
        ], 201);
    }

    public function destroyEmail(Contact $contact, ContactEmail $contactEmail): JsonResponse
    {
        $contact = $this->crmManagementService->deleteContactEmail($contact, $contactEmail);

        return response()->json([
            'message' => 'Adresse e-mail supprimée.',
            'contact' => $this->crmManagementService->serializeContactDetail($contact),
        ]);
    }
}
