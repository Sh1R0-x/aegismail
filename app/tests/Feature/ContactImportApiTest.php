<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class ContactImportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_preview_and_confirm_import_create_contacts_with_required_organization(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Acme Industries',
            'domain' => 'acme.test',
        ]);

        Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Existing',
            'last_name' => 'Person',
        ])->contactEmails()->create([
            'email' => 'existing@acme.test',
            'is_primary' => true,
        ]);

        $csv = implode("\n", [
            'organization_name,organization_domain,organization_website,contact_first_name,contact_last_name,contact_full_name,job_title,primary_email,secondary_email,phone,linkedin_url,country,city,tags,notes',
            'Acme Industries,acme.test,https://acme.test,Alice,Martin,Alice Martin,Head of Sales,alice@acme.test,alice.secondary@acme.test,+33102030405,https://linkedin.test/alice,France,Paris,priority;demo,Premier import',
            ',globex.test,https://globex.test,Bob,Durand,Bob Durand,CTO,bob@globex.test,,+33102030406,,,Paris,,Sans organisation',
            'Acme Industries,acme.test,https://acme.test,Existing,Again,Existing Again,VP Sales,existing@acme.test,,+33102030407,,,Paris,,Doublon existant',
        ]);

        $previewResponse = $this->post('/api/contacts/imports/preview', [
            'file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv),
        ]);

        $previewToken = $previewResponse->assertOk()
            ->assertJsonPath('preview.summary.totalRows', 3)
            ->assertJsonPath('preview.summary.validRows', 1)
            ->assertJsonPath('preview.summary.invalidRows', 1)
            ->assertJsonPath('preview.summary.duplicateExistingRows', 1)
            ->assertJsonPath('preview.summary.organizationMatches', 2)
            ->assertJsonPath('preview.rows.0.organization.action', 'match_domain')
            ->assertJsonPath('preview.rows.1.reasonCode', 'organization_required')
            ->assertJsonPath('preview.rows.2.status', 'duplicate_existing')
            ->json('preview.previewToken');

        $this->postJson('/api/contacts/imports', [
            'previewToken' => $previewToken,
        ])->assertCreated()
            ->assertJsonPath('message', 'Import des contacts terminé.')
            ->assertJsonPath('batch.importedContactsCount', 1)
            ->assertJsonPath('summary.importedRows', 1)
            ->assertJsonPath('summary.skippedRows', 2);

        $this->assertDatabaseHas('contacts', [
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'job_title' => 'Head of Sales',
            'linkedin_url' => 'https://linkedin.test/alice',
            'country' => 'France',
            'city' => 'Paris',
            'notes' => 'Premier import',
        ]);
        $this->assertDatabaseHas('contact_emails', [
            'email' => 'alice@acme.test',
            'is_primary' => 1,
        ]);
        $this->assertDatabaseHas('contact_emails', [
            'email' => 'alice.secondary@acme.test',
            'is_primary' => 0,
        ]);
        $this->assertDatabaseCount('contact_import_batches', 1);
    }

    public function test_xlsx_imports_are_supported_and_recent_imports_feed_campaign_audiences(): void
    {
        $xlsxFile = $this->createXlsxUpload([
            ['organization_name', 'organization_domain', 'organization_website', 'contact_first_name', 'contact_last_name', 'contact_full_name', 'job_title', 'primary_email', 'secondary_email', 'phone', 'linkedin_url', 'country', 'city', 'tags', 'notes'],
            ['Globex', 'globex.test', 'https://globex.test', 'Nora', 'Perez', 'Nora Perez', 'CEO', 'nora@globex.test', '', '+33102030408', '', 'France', 'Lyon', 'vip;founder', 'Import xlsx'],
        ]);

        $previewToken = $this->post('/api/contacts/imports/preview', [
            'file' => $xlsxFile,
        ])->assertOk()
            ->assertJsonPath('preview.sourceType', 'xlsx')
            ->assertJsonPath('preview.summary.validRows', 1)
            ->json('preview.previewToken');

        $this->postJson('/api/contacts/imports', [
            'previewToken' => $previewToken,
        ])->assertCreated();

        $this->getJson('/api/campaigns/audiences')
            ->assertOk()
            ->assertJsonPath('recentImports.0.contactCount', 1)
            ->assertJsonPath('recentImports.0.contacts.0.email', 'nora@globex.test')
            ->assertJsonPath('organizations.0.organizationName', 'Globex')
            ->assertJsonPath('contacts.0.organizationName', 'Globex');

        $this->get('/contacts')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contacts/Index')
                ->where('capabilities.imports.templateEndpoint', '/api/contacts/imports/template')
            );

        $this->assertDatabaseHas('contact_import_batches', [
            'source_type' => 'xlsx',
            'imported_contacts_count' => 1,
        ]);
    }

    public function test_import_template_endpoint_downloads_expected_headers(): void
    {
        $response = $this->get('/api/contacts/imports/template');

        $response->assertOk();
        $this->assertStringContainsString('organization_name,organization_domain,organization_website', $response->streamedContent());
        $this->assertStringContainsString('primary_email,secondary_email,phone,linkedin_url,country,city,tags,notes', $response->streamedContent());
    }

    private function createXlsxUpload(array $rows): UploadedFile
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'aegis-xlsx-');
        $xlsxPath = $temporaryPath.'.xlsx';
        rename($temporaryPath, $xlsxPath);

        $sharedStrings = [];
        $sharedStringIndexes = [];
        $sheetRows = [];

        foreach ($rows as $rowIndex => $row) {
            $cells = [];

            foreach ($row as $columnIndex => $value) {
                $string = (string) $value;

                if (! array_key_exists($string, $sharedStringIndexes)) {
                    $sharedStringIndexes[$string] = count($sharedStrings);
                    $sharedStrings[] = $string;
                }

                $cellReference = $this->columnLetter($columnIndex).($rowIndex + 1);
                $cells[] = sprintf(
                    '<c r="%s" t="s"><v>%d</v></c>',
                    $cellReference,
                    $sharedStringIndexes[$string],
                );
            }

            $sheetRows[] = sprintf('<row r="%d">%s</row>', $rowIndex + 1, implode('', $cells));
        }

        $zip = new ZipArchive;
        $zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $sheetRows).'</sheetData></worksheet>');
        $zip->addFromString('xl/sharedStrings.xml', sprintf(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="%d" uniqueCount="%d">%s</sst>',
            count($sharedStrings),
            count($sharedStrings),
            implode('', array_map(fn (string $string): string => '<si><t>'.htmlspecialchars($string, ENT_XML1).'</t></si>', $sharedStrings)),
        ));
        $zip->close();

        return new UploadedFile(
            $xlsxPath,
            'contacts.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }

    private function columnLetter(int $index): string
    {
        $index += 1;
        $letters = '';

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letters = chr(65 + $remainder).$letters;
            $index = (int) floor(($index - 1) / 26);
        }

        return $letters;
    }
}
