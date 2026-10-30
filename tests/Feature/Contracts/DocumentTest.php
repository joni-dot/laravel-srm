<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use App\Models\Document;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->supplier = Supplier::factory()->create();
        $this->actingAs(User::factory()->buyer()->create());
    }

    public function test_a_buyer_can_upload_a_general_document()
    {
        $this->post(route('suppliers.documents.store', $this->supplier), [
            'file' => UploadedFile::fake()->create('price list.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $document = Document::sole();

        $this->assertNull($document->contract_id);
        $this->assertSame('price list.pdf', $document->file_name);
        $this->assertStringStartsWith("documents/{$this->supplier->id}/", $document->file_path);
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertSame(auth()->id(), $document->uploaded_by_user_id);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function officeFiles(): array
    {
        return [
            'word' => ['agreement.docx', 'word/document.xml'],
            'excel' => ['prices.xlsx', 'xl/workbook.xml'],
        ];
    }

    #[DataProvider('officeFiles')]
    public function test_real_word_and_excel_files_are_accepted(string $name, string $entry)
    {
        $this->post(route('suppliers.documents.store', $this->supplier), ['file' => $this->officeFile($name, $entry)])
            ->assertSessionHasNoErrors();

        $this->assertSame($name, Document::sole()->file_name);
    }

    public function test_macro_enabled_office_files_are_rejected()
    {
        $this->post(route('suppliers.documents.store', $this->supplier), ['file' => $this->officeFile('prices.xlsm', 'xl/workbook.xml')])
            ->assertSessionHasErrors('file');
    }

    public function test_a_file_that_is_not_really_a_pdf_is_rejected()
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, '<html><script>alert(1)</script></html>');

        $this->post(route('suppliers.documents.store', $this->supplier), [
            'file' => new UploadedFile($path, 'harmless.pdf', 'application/pdf', null, true),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Document::count());
    }

    public function test_files_that_are_too_big_or_of_other_types_are_rejected()
    {
        foreach ([
            UploadedFile::fake()->create('huge.pdf', 20 * 1024 + 1, 'application/pdf'),
            UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
            UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload'),
        ] as $file) {
            $this->post(route('suppliers.documents.store', $this->supplier), ['file' => $file])->assertSessionHasErrors('file');
        }
    }

    public function test_viewers_can_download_general_documents_but_not_contract_documents()
    {
        $contract = Contract::factory()->for($this->supplier)->create();
        $general = $this->storedDocument();
        $contractDocument = $this->storedDocument($contract);

        $this->actingAs(User::factory()->create());

        $this->get(route('suppliers.documents.download', [$this->supplier, $general]))
            ->assertOk()
            ->assertDownload($general->file_name);

        $this->get(route('suppliers.documents.download', [$this->supplier, $contractDocument]))
            ->assertForbidden();
    }

    public function test_viewers_see_only_general_documents_on_the_supplier_page()
    {
        $contract = Contract::factory()->for($this->supplier)->create();
        $this->storedDocument(null, 'general.pdf');
        $this->storedDocument($contract, 'secret-prices.pdf');

        $this->actingAs(User::factory()->create())
            ->get(route('suppliers.show', $this->supplier))
            ->assertDontSee('secret-prices.pdf')
            ->assertInertia(fn ($page) => $page
                ->has('supplier.documents', 1)
                ->where('supplier.documents.0.file_name', 'general.pdf')
                ->missing('supplier.documents.0.file_path')
            );
    }

    public function test_viewers_cannot_upload_or_delete()
    {
        $document = $this->storedDocument();
        $this->actingAs(User::factory()->create());

        $this->post(route('suppliers.documents.store', $this->supplier), [
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        $this->delete(route('suppliers.documents.destroy', [$this->supplier, $document]))->assertForbidden();
        $this->assertModelExists($document);
    }

    public function test_guests_cannot_download()
    {
        $document = $this->storedDocument();
        auth()->logout();

        $this->get(route('suppliers.documents.download', [$this->supplier, $document]))->assertRedirect(route('login'));
    }

    public function test_a_document_cannot_be_reached_through_another_supplier()
    {
        $document = $this->storedDocument();
        $other = Supplier::factory()->create();

        $this->get(route('suppliers.documents.download', [$other, $document]))->assertNotFound();
        $this->delete(route('suppliers.documents.destroy', [$other, $document]))->assertNotFound();
        $this->assertModelExists($document);
    }

    public function test_documents_of_an_archived_supplier_cannot_be_deleted()
    {
        $document = $this->storedDocument();
        $this->supplier->archived_at = now();
        $this->supplier->save();

        $this->delete(route('suppliers.documents.destroy', [$this->supplier, $document]))->assertForbidden();
        $this->assertModelExists($document);
    }

    public function test_deleting_a_document_removes_the_file()
    {
        $document = $this->storedDocument();

        $this->delete(route('suppliers.documents.destroy', [$this->supplier, $document]));

        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing($document->file_path);
    }

    public function test_deleting_a_contract_removes_its_documents_and_files_but_not_general_ones()
    {
        $contract = Contract::factory()->for($this->supplier)->create();
        $contract->reminders()->create(['days_before' => 14, 'remind_on' => today()]);
        $contractDocument = $this->storedDocument($contract);
        $general = $this->storedDocument();

        $this->delete(route('suppliers.contracts.destroy', [$this->supplier, $contract]))
            ->assertRedirect(route('suppliers.show', $this->supplier));

        $this->assertModelMissing($contract);
        $this->assertModelMissing($contractDocument);
        Storage::disk('local')->assertMissing($contractDocument->file_path);
        $this->assertModelExists($general);
        Storage::disk('local')->assertExists($general->file_path);
        $this->assertDatabaseCount('contract_reminders', 0);
    }

    public function test_a_contract_document_is_attached_to_the_contract()
    {
        $contract = Contract::factory()->for($this->supplier)->create();

        $this->post(route('suppliers.contracts.documents.store', [$this->supplier, $contract]), [
            'file' => UploadedFile::fake()->create('signed.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->get(route('suppliers.contracts.show', [$this->supplier, $contract]))
            ->assertInertia(fn ($page) => $page->where('contract.documents.0.file_name', 'signed.pdf'));

        $this->get(route('suppliers.show', $this->supplier))
            ->assertInertia(fn ($page) => $page->has('supplier.documents', 0));
    }

    private function storedDocument(?Contract $contract = null, ?string $fileName = null): Document
    {
        $document = Document::factory()->create([
            'supplier_id' => $this->supplier->id,
            'contract_id' => $contract?->id,
            'file_name' => $fileName ?? fake()->word().'.pdf',
        ]);

        Storage::disk('local')->put($document->file_path, '%PDF-1.4');

        return $document;
    }

    /**
     * Word and Excel files are zip archives, so this builds a real one rather than faking its type.
     */
    private function officeFile(string $name, string $entry): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'office');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types/>');
        $zip->addFromString($entry, '<?xml version="1.0"?><root/>');
        $zip->close();

        return new UploadedFile($path, $name, null, null, true);
    }
}
