<?php

namespace App\Http\Controllers\Suppliers;

use App\Actions\Documents\StoreDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\DocumentRequest;
use App\Models\Contract;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UploadContractDocumentController extends Controller
{
    public function __invoke(DocumentRequest $request, Supplier $supplier, Contract $contract, StoreDocument $storeDocument): RedirectResponse
    {
        $storeDocument->handle($supplier, $contract, $request->uploadedFile(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document uploaded.')]);

        return back();
    }
}
