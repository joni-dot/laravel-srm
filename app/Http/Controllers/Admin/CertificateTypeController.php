<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CertificateTypeRequest;
use App\Models\CertificateType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CertificateTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/certificate-types/Index', [
            'certificateTypes' => CertificateType::query()
                ->select(['id', 'name', 'is_required'])
                ->withCount('certificates')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(CertificateTypeRequest $request): RedirectResponse
    {
        CertificateType::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Certificate type added.')]);

        return back();
    }

    public function update(CertificateTypeRequest $request, CertificateType $certificateType): RedirectResponse
    {
        $certificateType->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Certificate type updated.')]);

        return back();
    }

    /**
     * Types that certificates still use cannot be deleted, so no certificate loses its type.
     */
    public function destroy(CertificateType $certificateType): RedirectResponse
    {
        if ($certificateType->certificates()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This type is still used by certificates, so it cannot be deleted.')]);

            return back();
        }

        $certificateType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Certificate type deleted.')]);

        return back();
    }
}
