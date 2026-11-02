<?php

namespace App\Http\Controllers\Suppliers;

use App\Actions\Certificates\SaveCertificate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\CertificateRequest;
use App\Models\Certificate;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public function store(CertificateRequest $request, Supplier $supplier, SaveCertificate $saveCertificate): RedirectResponse
    {
        $saveCertificate->handle(
            $supplier->certificates()->make(),
            $request->certificateAttributes(),
            $request->reminderDays(),
            $request->uploadedFile(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Certificate added.')]);

        return back();
    }

    public function update(CertificateRequest $request, Supplier $supplier, Certificate $certificate, SaveCertificate $saveCertificate): RedirectResponse
    {
        $saveCertificate->handle(
            $certificate,
            $request->certificateAttributes(),
            $request->reminderDays(),
            $request->uploadedFile(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Certificate updated.')]);

        return back();
    }

    public function destroy(Supplier $supplier, Certificate $certificate): RedirectResponse
    {
        $certificate->delete();

        if ($certificate->file_path) {
            Storage::disk(SaveCertificate::DISK)->delete($certificate->file_path);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Certificate deleted.')]);

        return back();
    }

    /**
     * Always sent as a download, never shown inside the app, so a crafted file cannot run in the app's context.
     */
    public function show(Supplier $supplier, Certificate $certificate): StreamedResponse
    {
        abort_unless($certificate->file_path && $certificate->file_name, 404);

        return Storage::disk(SaveCertificate::DISK)->download(
            $certificate->file_path,
            $certificate->file_name,
            ['Content-Type' => $certificate->file_mime_type ?? 'application/octet-stream'],
        );
    }
}
