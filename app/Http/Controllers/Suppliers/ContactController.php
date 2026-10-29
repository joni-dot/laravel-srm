<?php

namespace App\Http\Controllers\Suppliers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\ContactRequest;
use App\Models\Contact;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ContactController extends Controller
{
    public function store(ContactRequest $request, Supplier $supplier): RedirectResponse
    {
        DB::transaction(function () use ($request, $supplier) {
            if ($request->validated('is_primary')) {
                $this->clearPrimaryContact($supplier);
            }

            $supplier->contacts()->create($request->validated());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact added.')]);

        return back();
    }

    public function update(ContactRequest $request, Supplier $supplier, Contact $contact): RedirectResponse
    {
        DB::transaction(function () use ($request, $supplier, $contact) {
            if ($request->validated('is_primary')) {
                $this->clearPrimaryContact($supplier, except: $contact);
            }

            $contact->update($request->validated());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return back();
    }

    /**
     * Contacts are people, so they are truly deleted (not archived) to respect their privacy.
     */
    public function destroy(Supplier $supplier, Contact $contact): RedirectResponse
    {
        $contact->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact deleted.')]);

        return back();
    }

    /**
     * A supplier has at most one primary contact.
     */
    private function clearPrimaryContact(Supplier $supplier, ?Contact $except = null): void
    {
        $supplier->contacts()
            ->where('is_primary', true)
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->update(['is_primary' => false]);
    }
}
