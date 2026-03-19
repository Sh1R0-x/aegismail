<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ContactImportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_supports_french_aliases_and_dry_run_does_not_persist_contacts(): void
    {
        $response = $this->post('/api/contacts/imports/preview', [
            'file' => UploadedFile::fake()->createWithContent('contacts.csv', implode("\n", [
                'Société,Prénom,Nom,E-mail,Profil LinkedIn,Téléphone fixe,Portable',
                'Acme Industries,Alice,Martin,alice@acme.test,https://linkedin.test/alice,+33 1 02 03 04 05,+33 6 12 34 56 78',
            ])),
        ]);

        $response->assertOk()
            ->assertJsonPath('preview.moduleKey', 'contacts_organizations')
            ->assertJsonPath('preview.mapping.organizationName', 'Société')
            ->assertJsonPath('preview.mapping.primaryEmail', 'E-mail')
            ->assertJsonPath('preview.summary.createRows', 1)
            ->assertJsonPath('preview.summary.updateRows', 0)
            ->assertJsonPath('preview.summary.unchangedRows', 0)
            ->assertJsonPath('preview.summary.errorRows', 0)
            ->assertJsonPath('preview.rows.0.action', 'create')
            ->assertJsonPath('preview.rows.0.contact.action', 'create')
            ->assertJsonPath('preview.rows.0.primaryEmail', 'alice@acme.test')
            ->assertJsonPath('preview.rows.0.organization.action', 'create')
            ->assertJsonPath('preview.rows.0.organization.name', 'Acme Industries')
            ->assertJsonPath('preview.rows.0.linkedinUrl', 'https://linkedin.test/alice')
            ->assertJsonPath('preview.rows.0.phoneLandline', '+33 1 02 03 04 05')
            ->assertJsonPath('preview.rows.0.phoneMobile', '+33 6 12 34 56 78');

        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('contact_emails', 0);
    }

    public function test_confirm_import_creates_contact_and_organization_with_split_phones(): void
    {
        $previewToken = $this->post('/api/contacts/imports/preview', [
            'file' => UploadedFile::fake()->createWithContent('contacts.csv', implode("\n", [
                'societe,prenom,nom,email,linkedin,telephone_fixe,telephone_portable',
                'Acme Industries,Alice,Martin,alice@acme.test,https://linkedin.test/alice,+33 1 02 03 04 05,+33 6 12 34 56 78',
            ])),
        ])->assertOk()->json('preview.previewToken');

        $this->postJson('/api/contacts/imports', [
            'previewToken' => $previewToken,
        ])->assertCreated()
            ->assertJsonPath('summary.importedRows', 1)
            ->assertJsonPath('summary.createdRows', 1)
            ->assertJsonPath('summary.updatedRows', 0)
            ->assertJsonPath('rows.0.resultAction', 'create');

        $organization = Organization::query()->firstOrFail();
        $contact = Contact::query()->firstOrFail();

        $this->assertSame('Acme Industries', $organization->name);
        $this->assertSame($organization->id, $contact->organization_id);
        $this->assertSame('Alice', $contact->first_name);
        $this->assertSame('Martin', $contact->last_name);
        $this->assertSame('https://linkedin.test/alice', $contact->linkedin_url);
        $this->assertSame('+33 1 02 03 04 05', $contact->phone_landline);
        $this->assertSame('+33 6 12 34 56 78', $contact->phone_mobile);
        $this->assertSame('+33 1 02 03 04 05', $contact->phone);
        $this->assertDatabaseHas('contact_emails', [
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => 1,
        ]);
    }

    public function test_exact_email_is_previewed_and_imported_as_prudent_update(): void
    {
        $organization = Organization::query()->create(['name' => 'Acme Industries']);
        $contact = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'phone' => '+33 1 00 00 00 00',
            'phone_landline' => '+33 1 00 00 00 00',
        ]);

        ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        $previewToken = $this->post('/api/contacts/imports/preview', [
            'file' => UploadedFile::fake()->createWithContent('contacts.csv', implode("\n", [
                'email,prenom,nom,linkedin,portable',
                'alice@acme.test,Alicia,Martin,https://linkedin.test/alicia,+33 6 99 88 77 66',
            ])),
        ])->assertOk()
            ->assertJsonPath('preview.summary.createRows', 0)
            ->assertJsonPath('preview.summary.updateRows', 1)
            ->assertJsonPath('preview.summary.unchangedRows', 0)
            ->assertJsonPath('preview.rows.0.action', 'update')
            ->assertJsonPath('preview.rows.0.organization.action', 'keep_existing')
            ->assertJsonPath('preview.rows.0.contact.action', 'update')
            ->json('preview.previewToken');

        $this->postJson('/api/contacts/imports', [
            'previewToken' => $previewToken,
        ])->assertCreated()
            ->assertJsonPath('summary.createdRows', 0)
            ->assertJsonPath('summary.updatedRows', 1)
            ->assertJsonPath('rows.0.resultAction', 'update');

        $contact->refresh();

        $this->assertSame('Alicia', $contact->first_name);
        $this->assertSame('Martin', $contact->last_name);
        $this->assertSame($organization->id, $contact->organization_id);
        $this->assertSame('https://linkedin.test/alicia', $contact->linkedin_url);
        $this->assertSame('+33 6 99 88 77 66', $contact->phone_mobile);
        $this->assertSame('+33 1 00 00 00 00', $contact->phone_landline);
    }

    public function test_invalid_rows_and_double_confirmation_are_protected(): void
    {
        $previewToken = $this->post('/api/contacts/imports/preview', [
            'file' => UploadedFile::fake()->createWithContent('contacts.csv', implode("\n", [
                'societe,prenom,nom,email',
                'Acme Industries,Alice,Martin,alice@acme.test',
                'Acme Industries,Alicia,Martin,alice@acme.test',
                'Globex,Bob,Durand,',
            ])),
        ])->assertOk()
            ->assertJsonPath('preview.summary.createRows', 1)
            ->assertJsonPath('preview.summary.skipRows', 1)
            ->assertJsonPath('preview.summary.errorRows', 1)
            ->assertJsonPath('preview.summary.duplicateFileRows', 1)
            ->json('preview.previewToken');

        $this->postJson('/api/contacts/imports', [
            'previewToken' => $previewToken,
        ])->assertCreated()
            ->assertJsonPath('summary.importedRows', 1)
            ->assertJsonPath('summary.skippedRows', 1)
            ->assertJsonPath('summary.errorRows', 1);

        $this->postJson('/api/contacts/imports', [
            'previewToken' => $previewToken,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['previewToken']);

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('contact_emails', 1);
    }

    public function test_import_template_endpoint_downloads_french_headers(): void
    {
        $response = $this->get('/api/contacts/imports/template');

        $response->assertOk();
        $this->assertStringContainsString('societe,prenom,nom,email,linkedin,telephone_fixe,telephone_portable', $response->streamedContent());
    }

    public function test_module_export_round_trip_preview_marks_unchanged_rows_and_confirm_skips_writes(): void
    {
        $organization = Organization::query()->create(['name' => 'Acme Industries']);
        $contact = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'phone' => '+33 1 02 03 04 05',
            'phone_landline' => '+33 1 02 03 04 05',
            'phone_mobile' => '+33 6 12 34 56 78',
            'linkedin_url' => 'https://linkedin.test/alice',
        ]);

        ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        $export = $this->get('/api/import-export/export')
            ->assertOk()
            ->streamedContent();

        $previewToken = $this->post('/api/import-export/preview', [
            'file' => UploadedFile::fake()->createWithContent('roundtrip.csv', $export),
        ])->assertOk()
            ->assertJsonPath('preview.summary.createRows', 0)
            ->assertJsonPath('preview.summary.updateRows', 0)
            ->assertJsonPath('preview.summary.unchangedRows', 1)
            ->assertJsonPath('preview.rows.0.action', 'unchanged')
            ->assertJsonPath('preview.rows.0.contact.action', 'unchanged')
            ->assertJsonPath('preview.rows.0.organization.action', 'reuse')
            ->json('preview.previewToken');

        $this->postJson('/api/import-export/confirm', [
            'previewToken' => $previewToken,
        ])->assertCreated()
            ->assertJsonPath('summary.importedRows', 0)
            ->assertJsonPath('summary.updatedRows', 0)
            ->assertJsonPath('summary.unchangedRows', 1)
            ->assertJsonPath('rows.0.resultAction', 'unchanged');

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('contact_emails', 1);
    }

    public function test_module_export_can_be_modified_and_reimported_without_creating_duplicates(): void
    {
        $organization = Organization::query()->create(['name' => 'Acme Industries']);
        $contact = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'phone' => '+33 1 02 03 04 05',
            'phone_landline' => '+33 1 02 03 04 05',
        ]);

        ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        $export = $this->get('/api/import-export/export')
            ->assertOk()
            ->streamedContent();

        $lines = preg_split("/\r\n|\n|\r/", trim($export)) ?: [];
        $modified = implode("\n", [
            $lines[0] ?? 'societe,prenom,nom,email,linkedin,telephone_fixe,telephone_portable',
            'Acme Industries,Alice,Martin,alice@acme.test,https://linkedin.test/alice,+33 1 02 03 04 05,+33 6 12 34 56 78',
        ]);

        $previewToken = $this->post('/api/import-export/preview', [
            'file' => UploadedFile::fake()->createWithContent('roundtrip-modified.csv', $modified),
        ])->assertOk()
            ->assertJsonPath('preview.summary.createRows', 0)
            ->assertJsonPath('preview.summary.updateRows', 1)
            ->assertJsonPath('preview.summary.unchangedRows', 0)
            ->assertJsonPath('preview.rows.0.action', 'update')
            ->assertJsonPath('preview.rows.0.organization.action', 'reuse')
            ->assertJsonPath('preview.rows.0.contact.action', 'update')
            ->assertJsonPath('preview.rows.0.contact.changes.0.field', 'linkedinUrl')
            ->json('preview.previewToken');

        $this->postJson('/api/import-export/confirm', [
            'previewToken' => $previewToken,
        ])->assertCreated()
            ->assertJsonPath('summary.createdRows', 0)
            ->assertJsonPath('summary.updatedRows', 1)
            ->assertJsonPath('rows.0.resultAction', 'update');

        $contact->refresh();

        $this->assertSame('https://linkedin.test/alice', $contact->linkedin_url);
        $this->assertSame('+33 6 12 34 56 78', $contact->phone_mobile);
        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_preview_reuses_existing_organization_and_creates_only_the_missing_contact(): void
    {
        $organization = Organization::query()->create(['name' => 'Acme Industries']);

        $previewToken = $this->post('/api/import-export/preview', [
            'file' => UploadedFile::fake()->createWithContent('contacts.csv', implode("\n", [
                'societe,prenom,nom,email',
                'Acme Industries,Bob,Durand,bob@acme.test',
            ])),
        ])->assertOk()
            ->assertJsonPath('preview.summary.createRows', 1)
            ->assertJsonPath('preview.rows.0.organization.action', 'reuse')
            ->assertJsonPath('preview.rows.0.contact.action', 'create')
            ->json('preview.previewToken');

        $this->postJson('/api/import-export/confirm', [
            'previewToken' => $previewToken,
        ])->assertCreated();

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseHas('contacts', [
            'organization_id' => $organization->id,
            'first_name' => 'Bob',
            'last_name' => 'Durand',
        ]);
    }
}
