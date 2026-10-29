<?php

namespace Tests\Feature\Certificates;

use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private CertificateType $type;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->supplier = Supplier::factory()->create();
        $this->type = CertificateType::factory()->create(['name' => 'ISO 9001']);
        $this->actingAs(User::factory()->buyer()->create());
    }

    public function test_a_certificate_with_a_file_and_reminders_can_be_added()
    {
        $this->post(route('suppliers.certificates.store', $this->supplier), [
            ...$this->validInput(),
            'file' => UploadedFile::fake()->create('iso-9001.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $certificate = Certificate::sole();

        $this->assertSame('iso-9001.pdf', $certificate->file_name);
        $this->assertStringStartsWith("certificates/{$this->supplier->id}/", (string) $certificate->file_path);
        Storage::disk('local')->assertExists((string) $certificate->file_path);

        $this->assertEqualsCanonicalizing(
            [today()->addDays(40)->toDateString(), today()->addDays(86)->toDateString()],
            $certificate->reminders->map(fn ($reminder) => $reminder->remind_on->toDateString())->all(),
        );
    }

    public function test_the_supplier_page_sends_dates_as_plain_dates()
    {
        Certificate::factory()->for($this->supplier)->for($this->type, 'type')->create([
            'issued_on' => '2025-05-01',
            'expires_on' => '2027-05-01',
        ]);

        $this->get(route('suppliers.show', $this->supplier))
            ->assertInertia(fn ($page) => $page
                ->where('supplier.certificates.0.issued_on', '2025-05-01')
                ->where('supplier.certificates.0.expires_on', '2027-05-01')
            );
    }

    public function test_a_file_that_is_not_really_a_pdf_is_rejected()
    {
        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, '<html><script>alert(1)</script></html>');

        $this->post(route('suppliers.certificates.store', $this->supplier), [
            ...$this->validInput(),
            'file' => new UploadedFile($path, 'harmless.pdf', 'application/pdf', null, true),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, Certificate::count());
    }

    public function test_files_that_are_too_big_or_of_other_types_are_rejected()
    {
        $this->post(route('suppliers.certificates.store', $this->supplier), [
            ...$this->validInput(),
            'file' => UploadedFile::fake()->create('huge.pdf', 10 * 1024 + 1, 'application/pdf'),
        ])->assertSessionHasErrors('file');

        $this->post(route('suppliers.certificates.store', $this->supplier), [
            ...$this->validInput(),
            'file' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('file');
    }

    public function test_input_is_validated()
    {
        $this->post(route('suppliers.certificates.store', $this->supplier), [
            'certificate_type_id' => 999,
            'issued_on' => '2026-05-01',
            'expires_on' => '2026-04-01',
            'reminder_days' => [0, 400, 30, 30, 20, 10],
        ])->assertSessionHasErrors(['certificate_type_id', 'expires_on', 'reminder_days', 'reminder_days.0', 'reminder_days.1', 'reminder_days.2']);
    }

    public function test_replacing_the_file_removes_the_old_one()
    {
        $this->post(route('suppliers.certificates.store', $this->supplier), [
            ...$this->validInput(),
            'file' => UploadedFile::fake()->create('old.pdf', 10, 'application/pdf'),
        ]);
        $certificate = Certificate::sole();
        $oldPath = (string) $certificate->file_path;

        $this->put(route('suppliers.certificates.update', [$this->supplier, $certificate]), [
            ...$this->validInput(),
            'file' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists((string) $certificate->refresh()->file_path);
        $this->assertSame('new.pdf', $certificate->file_name);
    }

    public function test_saving_without_changes_keeps_reminders_that_were_already_sent()
    {
        $this->post(route('suppliers.certificates.store', $this->supplier), $this->validInput());
        $certificate = Certificate::sole();
        $certificate->reminders()->where('days_before', 60)->update(['sent_at' => now()]);

        $this->put(route('suppliers.certificates.update', [$this->supplier, $certificate]), [
            ...$this->validInput(),
            'reference' => 'Changed',
        ]);

        $this->assertNotNull($certificate->reminders()->where('days_before', 60)->value('sent_at'));
    }

    public function test_a_new_expiry_date_reschedules_the_reminders()
    {
        $this->post(route('suppliers.certificates.store', $this->supplier), $this->validInput());
        $certificate = Certificate::sole();
        $certificate->reminders()->update(['sent_at' => now()]);

        $this->put(route('suppliers.certificates.update', [$this->supplier, $certificate]), [
            ...$this->validInput(),
            'expires_on' => today()->addDays(400)->toDateString(),
            'reminder_days' => [30],
        ]);

        $reminder = $certificate->reminders()->sole();
        $this->assertSame(30, $reminder->days_before);
        $this->assertSame(today()->addDays(370)->toDateString(), $reminder->remind_on->toDateString());
        $this->assertNull($reminder->sent_at);
    }

    public function test_certificates_without_an_expiry_date_have_no_reminders()
    {
        $this->post(route('suppliers.certificates.store', $this->supplier), [
            ...$this->validInput(),
            'expires_on' => null,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Certificate::sole()->reminders()->count());
    }

    public function test_deleting_a_certificate_removes_its_file_and_reminders()
    {
        $this->post(route('suppliers.certificates.store', $this->supplier), [
            ...$this->validInput(),
            'file' => UploadedFile::fake()->create('cert.pdf', 10, 'application/pdf'),
        ]);
        $certificate = Certificate::sole();

        $this->delete(route('suppliers.certificates.destroy', [$this->supplier, $certificate]));

        $this->assertModelMissing($certificate);
        Storage::disk('local')->assertMissing((string) $certificate->file_path);
        $this->assertDatabaseCount('certificate_reminders', 0);
    }

    public function test_any_signed_in_user_can_download_the_file_as_an_attachment()
    {
        $this->post(route('suppliers.certificates.store', $this->supplier), [
            ...$this->validInput(),
            'file' => UploadedFile::fake()->create('iso 9001.pdf', 10, 'application/pdf'),
        ]);
        $certificate = Certificate::sole();

        $this->actingAs(User::factory()->create())
            ->get(route('suppliers.certificates.download', [$this->supplier, $certificate]))
            ->assertOk()
            ->assertDownload('iso 9001.pdf')
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_files_cannot_be_downloaded_by_guests_or_through_another_supplier()
    {
        $certificate = Certificate::factory()->for($this->supplier)->create(['file_path' => 'certificates/x.pdf', 'file_name' => 'x.pdf']);

        $this->get(route('suppliers.certificates.download', [Supplier::factory()->create(), $certificate]))->assertNotFound();

        auth()->logout();
        $this->get(route('suppliers.certificates.download', [$this->supplier, $certificate]))->assertRedirect(route('login'));
    }

    public function test_viewers_cannot_add_certificates()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('suppliers.certificates.store', $this->supplier), $this->validInput())
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function validInput(): array
    {
        return [
            'certificate_type_id' => $this->type->id,
            'reference' => 'QMS-123',
            'issued_on' => today()->subYear()->toDateString(),
            'expires_on' => today()->addDays(100)->toDateString(),
            'reminder_days' => [60, 14],
        ];
    }
}
