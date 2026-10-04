<?php

namespace Tests\Feature\People;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentType;
use App\Models\User;
use App\Services\EmployeeDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Leave\LeaveTestCase;

/**
 * Employee documents: private disk only, served through authorized, employee-scoped routes.
 */
class EmployeeDocumentsTest extends LeaveTestCase
{
    private Employee $employee;

    private EmployeeDocumentType $contract;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->employee = Employee::factory()->create();
        $this->contract = EmployeeDocumentType::where('code', 'employment_contract')->firstOrFail();
    }

    private function upload(User $user, ?UploadedFile $file = null, array $data = [])
    {
        return $this->actingAs($user)->post(route('people.employee.documents.store', $this->employee), array_merge([
            'file' => $file ?? UploadedFile::fake()->create('Signed Contract.pdf', 120, 'application/pdf'),
            'employee_document_type_id' => $this->contract->id,
            'description' => 'Signed copy',
            'expires_on' => '2027-12-31',
        ], $data));
    }

    private function document(): EmployeeDocument
    {
        $this->upload($this->userWithRole('hr_director'))->assertSessionHasNoErrors();

        return EmployeeDocument::latest('id')->firstOrFail();
    }

    #[Test]
    public function hr_uploads_to_the_private_disk_under_a_random_name(): void
    {
        $hr = $this->userWithRole('hr_staff');
        $this->upload($hr)->assertSessionHasNoErrors()->assertRedirect();

        $doc = EmployeeDocument::sole();
        $this->assertSame([$this->employee->id, $this->contract->id, 'Signed Contract.pdf', 'local', 'upload', $hr->id, '2027-12-31', 'Signed copy'],
            [$doc->employee_id, $doc->employee_document_type_id, $doc->original_name, $doc->disk, $doc->source, $doc->uploaded_by, $doc->expires_on->toDateString(), $doc->description]);
        $this->assertSame(120 * 1024, $doc->file_size);
        $this->assertSame('application/pdf', $doc->mime_type);
        $this->assertNotNull($doc->uploaded_at);

        $this->assertStringStartsWith("employee-documents/{$this->employee->id}/", $doc->file_path);
        $this->assertStringNotContainsString('Signed Contract', $doc->file_path);
        Storage::disk('local')->assertExists($doc->file_path);
        $this->assertEmpty(Storage::disk('public')->allFiles(), 'nothing is written to public storage');
    }

    #[Test]
    public function the_profile_lists_documents_without_exposing_storage_paths(): void
    {
        $doc = $this->document();

        $this->actingAs($this->userWithRole('hr_staff'))
            ->get(route('people.employee.show', $this->employee))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can.viewDocuments', true)
                ->where('can.uploadDocuments', true)
                ->where('can.deleteDocuments', false)
                ->where('documents.0.id', $doc->id)
                ->where('documents.0.original_name', 'Signed Contract.pdf')
                ->where('documents.0.type.name', 'Employment Contract')
                ->where('documents.0.expires_on', '2027-12-31')
                ->missing('documents.0.file_path')
                ->missing('documents.0.disk'));
    }

    #[Test]
    public function authorized_users_can_download_and_view_inline(): void
    {
        $doc = $this->document();
        $hr = $this->userWithRole('hr_staff');

        $download = $this->actingAs($hr)->get(route('people.employee.documents.download', [$this->employee, $doc]))->assertOk();
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Signed Contract.pdf', $download->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $download->headers->get('X-Content-Type-Options'));

        $view = $this->actingAs($hr)->get(route('people.employee.documents.view', [$this->employee, $doc]))->assertOk();
        $this->assertStringStartsWith('inline', $view->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $view->headers->get('Content-Type'));
    }

    #[Test]
    public function non_previewable_types_are_always_downloaded(): void
    {
        $this->upload($this->userWithRole('hr_director'), UploadedFile::fake()->create('notes.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))->assertSessionHasNoErrors();
        $doc = EmployeeDocument::sole();

        $view = $this->actingAs($this->userWithRole('hr_staff'))->get(route('people.employee.documents.view', [$this->employee, $doc]))->assertOk();
        $this->assertStringContainsString('attachment', $view->headers->get('Content-Disposition'));
    }

    #[Test]
    public function users_without_document_permissions_are_refused_everywhere(): void
    {
        $doc = $this->document();

        foreach (['supervisor', 'employee', 'payroll'] as $role) {
            $user = $this->userWithRole($role);
            $this->upload($user)->assertForbidden();
            $this->actingAs($user)->get(route('people.employee.documents.download', [$this->employee, $doc]))->assertForbidden();
            $this->actingAs($user)->get(route('people.employee.documents.view', [$this->employee, $doc]))->assertForbidden();
            $this->actingAs($user)->put(route('people.employee.documents.update', [$this->employee, $doc]), ['employee_document_type_id' => $this->contract->id])->assertForbidden();
            $this->actingAs($user)->delete(route('people.employee.documents.destroy', [$this->employee, $doc]))->assertForbidden();
        }

        // A supervisor may open the profile (employee.view) but sees no documents.
        $this->actingAs($this->userWithRole('supervisor'))
            ->get(route('people.employee.show', $this->employee))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.viewDocuments', false)->where('documents', []));

        $this->assertSame(1, EmployeeDocument::count());
    }

    #[Test]
    public function an_employee_cannot_download_their_own_documents_without_hr_permission(): void
    {
        $self = User::factory()->create(['employee_id' => $this->employee->id])->assignRole('employee');
        $doc = $this->document();

        $this->actingAs($self)->get(route('people.employee.documents.download', [$this->employee, $doc]))->assertForbidden();
    }

    #[Test]
    public function documents_are_scoped_to_their_employee(): void
    {
        $doc = $this->document();
        $other = Employee::factory()->create();

        $this->actingAs($this->userWithRole('hr_director'))
            ->get(route('people.employee.documents.download', [$other, $doc]))
            ->assertNotFound();
    }

    #[Test]
    public function update_changes_metadata_and_delete_needs_its_own_permission(): void
    {
        $doc = $this->document();
        $certificate = EmployeeDocumentType::where('code', 'certificate')->firstOrFail();

        $this->actingAs($this->userWithRole('hr_staff'))
            ->put(route('people.employee.documents.update', [$this->employee, $doc]), ['employee_document_type_id' => $certificate->id, 'description' => 'Renewed', 'expires_on' => null])
            ->assertSessionHasNoErrors();
        $this->assertSame([$certificate->id, 'Renewed', null], [$doc->fresh()->employee_document_type_id, $doc->fresh()->description, $doc->fresh()->expires_on]);

        $this->actingAs($this->userWithRole('hr_staff'))->delete(route('people.employee.documents.destroy', [$this->employee, $doc]))->assertForbidden();

        $this->actingAs($this->userWithRole('hr_director'))->delete(route('people.employee.documents.destroy', [$this->employee, $doc]))->assertRedirect();
        $this->assertModelMissing($doc);
        Storage::disk('local')->assertMissing($doc->file_path);
    }

    #[Test]
    public function uploads_are_validated(): void
    {
        $hr = $this->userWithRole('hr_director');
        $inactive = EmployeeDocumentType::create(['code' => 'retired', 'name' => 'Retired type', 'is_active' => false]);

        $this->upload($hr, UploadedFile::fake()->create('tool.exe', 10, 'application/x-msdownload'))->assertSessionHasErrors('file');
        $this->upload($hr, UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'))->assertSessionHasErrors('file');
        $this->upload($hr, null, ['employee_document_type_id' => $inactive->id])->assertSessionHasErrors('employee_document_type_id');
        $this->upload($hr, null, ['expires_on' => 'not-a-date'])->assertSessionHasErrors('expires_on');

        $this->assertSame(0, EmployeeDocument::count());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    #[Test]
    public function files_from_other_modules_can_be_copied_onto_the_employee(): void
    {
        Storage::disk('local')->put('recruitment/applications/7/cv.pdf', '%PDF-1.4 resume');
        $resume = EmployeeDocumentType::where('code', 'resume')->firstOrFail();

        $doc = app(EmployeeDocumentService::class)->copyFromStorage(
            $this->employee, 'local', 'recruitment/applications/7/cv.pdf', 'Juan Resume.pdf',
            ['employee_document_type_id' => $resume->id], null, 'recruitment'
        );

        $this->assertSame(['Juan Resume.pdf', 'recruitment', 15], [$doc->original_name, $doc->source, $doc->file_size]);
        $this->assertNotSame('recruitment/applications/7/cv.pdf', $doc->file_path);
        Storage::disk('local')->assertExists([$doc->file_path, 'recruitment/applications/7/cv.pdf']);
        $this->assertSame('%PDF-1.4 resume', Storage::disk('local')->get($doc->file_path));
    }
}
