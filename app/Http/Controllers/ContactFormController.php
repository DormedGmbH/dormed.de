<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactFormRequest;
use App\Jobs\CreateInquiryInCas;
use Illuminate\Http\RedirectResponse;

class ContactFormController extends Controller
{
    public function store(ContactFormRequest $request): RedirectResponse
    {
        CreateInquiryInCas::dispatch(
            name: $request->validated('name'),
            email: $request->validated('email'),
            telefon: $request->validated('telefon'),
            plz: $request->validated('plz'),
            nachricht: $request->validated('nachricht'),
            praxis: $request->validated('praxis'),
            fachgebiet: $request->validated('fachgebiet'),
            wantsCallback: $request->wantsCallback(),
            rueckrufDatum: $request->validated('rueckruf_datum'),
        );

        return redirect()
            ->route('kontakt')
            ->with('contactFormSubmitted', true);
    }
}
