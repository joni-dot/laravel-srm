<?php

namespace App\Actions\Contracts;

use App\Actions\Documents\StoreDocument;
use App\Models\Contract;
use Illuminate\Support\Facades\Storage;

class DeleteContract
{
    public function handle(Contract $contract): void
    {
        $filePaths = $contract->documents()->pluck('file_path')->all();

        $contract->delete();

        Storage::disk(StoreDocument::DISK)->delete($filePaths);
    }
}
