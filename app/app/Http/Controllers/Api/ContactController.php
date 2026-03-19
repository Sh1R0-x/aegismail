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
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(
        private readonly CrmManagementService $crmManagementService,
    ) {
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json(['contacts' => []]);
        }

        $contacts = Contact::query()
            ->with(['organization:id,name', 'contactEmails' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('id')])
            ->where(function ($query) use ($q) {
                $query->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('full_name', 'like', "%{$q}%")
                    ->orWhereHas('organization', fn ($oq) => $oq->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('contactEmails', fn ($eq) => $eq->where('email', 'like', "%{$q}%"));
            })
            ->limit(20)
            ->get()
            ->map(function (Contact $contact) {
                $primaryEmail = $contact->contactEmails->first();

                return [
                    'contactId' => $contact->id,
                    'name' => trim(($contact->first_name ?? '').' '.($contact->last_name ?? '')) ?: $contact->full_name,
                    'email' => $primaryEmail?->email,
                    'contactEmailId' => $primaryEmail?->id,
                    'organizationId' => $contact->organization_id,
                    'organizationName' => $contact->organization?->name,
                ];
            })
            ->filter(fn ($c) => $c['email'] !== null)
            ->values()
            ->all();

        return response()->json(['contacts' => $contacts]);
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
