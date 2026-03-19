<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class DraftsController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect('/mails?tab=drafts');
    }
}
