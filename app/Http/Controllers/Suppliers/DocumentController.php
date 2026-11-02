<?php

namespace App\Http\Controllers\Suppliers;

use App\Actions\Documents\StoreDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\DocumentRequest;
use App\Models\Document;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function store(DocumentRequest $request, Supplier $supplier, StoreDocument $storeDocument): RedirectResponse
    {
        $storeDocument->handle($supplier, null, $request->uploadedFile(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document uploaded.')]);

        return back();
    }

    /**
     * Always sent as a download, never shown inside the app, so a crafted file cannot run in the app's context.
     */
    public function show(Supplier $supplier, Document $document): StreamedResponse
    {
        return Storage::disk(StoreDocument::DISK)->download(
            $document->file_path,
            $document->file_name,
            ['Content-Type' => $document->file_mime_type],
        );
    }

    public function destroy(Supplier $supplier, Document $document): RedirectResponse
    {
        $document->delete();

        Storage::disk(StoreDocument::DISK)->delete($document->file_path);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document deleted.')]);

        return back();
    }
}
