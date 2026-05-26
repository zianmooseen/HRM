<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Document;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_upload_list_download_and_delete_employee_document(): void
    {
        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company);
        Sanctum::actingAs($user);

        $this->post('/api/documents', [
            'employee_id' => $employee->id,
            'document_type' => 'medical_certificate',
            'title' => 'Clinic note',
            'expiry_date' => '2026-12-31',
            'file' => UploadedFile::fake()->create('clinic-note.pdf', 120, 'application/pdf'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.document.document_type', 'medical_certificate')
            ->assertJsonPath('data.document.title', 'Clinic note');

        $document = Document::query()->firstOrFail();
        Storage::disk('local')->assertExists($document->path);

        $this->getJson("/api/documents?employee_id={$employee->id}&document_type=medical_certificate")
            ->assertOk()
            ->assertJsonCount(1, 'data.documents')
            ->assertJsonPath('data.documents.0.id', $document->id);

        $this->get("/api/documents/{$document->id}/download")
            ->assertOk();

        $this->deleteJson("/api/documents/{$document->id}")
            ->assertOk();

        Storage::disk('local')->assertMissing($document->path);
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $company->id,
            'action' => 'document.deleted',
        ]);
    }

    public function test_image_document_returns_preview_metadata_and_response(): void
    {
        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);

        [$company, $user] = $this->companyAdmin();
        $employee = $this->employee($company);
        Sanctum::actingAs($user);

        $this->post('/api/documents', [
            'employee_id' => $employee->id,
            'document_type' => 'passport',
            'title' => 'Passport scan',
            'expiry_date' => now()->addDays(10)->toDateString(),
            'file' => UploadedFile::fake()->image('passport.png'),
        ])->assertCreated();

        $document = Document::query()->firstOrFail();

        $this->getJson("/api/documents?employee_id={$employee->id}")
            ->assertOk()
            ->assertJsonPath('data.documents.0.is_previewable', true)
            ->assertJsonPath('data.documents.0.expiry_status', 'expiring_soon')
            ->assertJsonPath('data.documents.0.preview_url', "/api/documents/{$document->id}/preview");

        $this->get("/api/documents/{$document->id}/preview")
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_document_upload_rejects_foreign_company_employee(): void
    {
        Storage::fake('local');
        $this->seed(RoleAndPermissionSeeder::class);

        [, $user] = $this->companyAdmin();
        $foreignCompany = Company::query()->create(['name' => 'Foreign Company']);
        $foreignEmployee = $this->employee($foreignCompany);
        Sanctum::actingAs($user);

        $this->post('/api/documents', [
            'employee_id' => $foreignEmployee->id,
            'document_type' => 'medical_certificate',
            'file' => UploadedFile::fake()->create('foreign.pdf', 20, 'application/pdf'),
        ])->assertNotFound();

        $this->assertDatabaseCount('documents', 0);
    }

    private function companyAdmin(): array
    {
        $company = Company::query()->create(['name' => 'Demo Company']);
        $user = User::factory()->create();
        $role = Role::query()->where('slug', 'company_admin')->firstOrFail();

        $user->roles()->attach($role->id, [
            'company_id' => $company->id,
            'scope' => 'company',
        ]);

        return [$company, $user];
    }

    private function employee(Company $company): Employee
    {
        return Employee::query()->create([
            'company_id' => $company->id,
            'employee_code' => 'DOC-'.$company->id,
            'first_name' => 'Maya',
            'last_name' => 'Saleh',
            'display_name' => 'Maya Saleh',
            'status' => 'active',
            'hire_date' => '2025-01-01',
        ]);
    }
}
