<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\Crm\CrmPageDataService;
use Inertia\Inertia;
use Inertia\Response;

class ContactShowController extends Controller
{
    public function __invoke(Contact $contact, CrmPageDataService $crmPageDataService): Response
    {
        return Inertia::render('Contacts/Show', $crmPageDataService->contact($contact));
    }
}
