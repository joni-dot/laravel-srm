<?php

namespace App\Actions\Documents;

use App\Models\Contract;
use App\Models\Document;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoreDocument
{
    /**
     * Files live on the private disk, which is never served directly by the web server.
     */
    public const string DISK = 'local';

    public function handle(Supplier $supplier, ?Contract $contract, UploadedFile $file, User $uploadedBy): Document
    {
        $filePath = $file->store("documents/{$supplier->id}", self::DISK);

        if ($filePath === false) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        $document = new Document;
        $document->supplier_id = $supplier->id;
        $document->contract_id = $contract?->id;
        $document->uploaded_by_user_id = $uploadedBy->id;
        $document->file_path = $filePath;
        $document->file_name = mb_substr($file->getClientOriginalName(), 0, 255);
        $document->file_mime_type = (string) $file->getMimeType();
        $document->file_size = (int) $file->getSize();

        try {
            $document->save();
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($filePath);

            throw $exception;
        }

        return $document;
    }
}
