<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\MailboxAccount;
use App\Models\MailCampaign;
use App\Models\MailRecipient;
use App\Models\MailThread;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrmApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_organization_in_v1_backend_flow(): void
    {
        $response = $this->postJson('/api/organizations', [
            'name' => 'Acme Industries',
            'domain' => 'acme.test',
            'website' => 'https://acme.test',
            'notes' => 'Compte prioritaire',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Organisation créée.')
            ->assertJsonPath('organization.name', 'Acme Industries')
            ->assertJsonPath('organization.domain', 'acme.test')
            ->assertJsonPath('organization.contactCount', 0)
            ->assertJsonPath('organization.sentCount', 0)
            ->assertJsonPath('organization.lastActivityAt', null);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Acme Industries',
            'domain' => 'acme.test',
            'website' => 'https://acme.test',
            'notes' => 'Compte prioritaire',
        ]);
    }

    public function test_admin_can_create_a_contact_with_primary_email(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Acme Industries',
            'domain' => 'acme.test',
        ]);

        $response = $this->postJson('/api/contacts', [
            'organizationId' => $organization->id,
            'firstName' => 'Alice',
            'lastName' => 'Martin',
            'title' => 'Head of Sales',
            'email' => 'ALICE@ACME.TEST',
            'phone' => '+33102030405',
            'notes' => 'Premier contact',
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Contact créé.')
            ->assertJsonPath('contact.firstName', 'Alice')
            ->assertJsonPath('contact.lastName', 'Martin')
            ->assertJsonPath('contact.organization', 'Acme Industries')
            ->assertJsonPath('contact.email', 'alice@acme.test')
            ->assertJsonPath('contact.score', 0)
            ->assertJsonPath('contact.scoreLevel', 'cold')
            ->assertJsonPath('contact.excluded', false)
            ->assertJsonPath('contact.unsubscribed', false)
            ->assertJsonPath('contact.lastActivityAt', null);

        $this->assertDatabaseHas('contacts', [
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'job_title' => 'Head of Sales',
            'phone' => '+33102030405',
            'notes' => 'Premier contact',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('contact_emails', [
            'email' => 'alice@acme.test',
            'is_primary' => 1,
        ]);
    }

    public function test_contact_creation_returns_precise_validation_errors(): void
    {
        $this->postJson('/api/contacts', [
            'email' => 'bad-email',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['organizationId', 'email'])
            ->assertJsonPath('errors.organizationId.0', 'Le champ l’organisation est obligatoire.')
            ->assertJsonPath('errors.email.0', 'Le champ l’adresse e-mail doit être une adresse e-mail valide.');
    }

    public function test_contact_creation_rejects_duplicate_email_with_french_message(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Acme Industries',
        ]);

        $this->postJson('/api/contacts', [
            'organizationId' => $organization->id,
            'firstName' => 'Alice',
            'lastName' => 'Martin',
            'email' => 'alice@acme.test',
        ])->assertCreated();

        $response = $this->postJson('/api/contacts', [
            'organizationId' => $organization->id,
            'firstName' => 'Alice 2',
            'email' => 'alice@acme.test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertStringContainsString('La valeur de l’adresse e-mail est déjà utilisée.', $response->json('message'));
    }

    public function test_admin_can_show_and_update_an_organization_with_real_detail_payload(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Acme Industries',
            'domain' => 'acme.test',
            'website' => 'https://acme.test',
            'notes' => 'Compte prioritaire',
        ]);

        $contact = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'job_title' => 'Sales Lead',
        ]);

        ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        $this->getJson('/api/organizations/'.$organization->id)
            ->assertOk()
            ->assertJsonPath('organization.name', 'Acme Industries')
            ->assertJsonPath('organization.contactCount', 1)
            ->assertJsonPath('organization.contacts.0.id', $contact->id)
            ->assertJsonPath('organization.contacts.0.email', 'alice@acme.test');

        $this->putJson('/api/organizations/'.$organization->id, [
            'name' => 'Acme Europe',
            'domain' => 'eu.acme.test',
            'website' => 'https://eu.acme.test',
            'notes' => 'Compte mis à jour',
        ])->assertOk()
            ->assertJsonPath('message', 'Organisation mise à jour.')
            ->assertJsonPath('organization.name', 'Acme Europe')
            ->assertJsonPath('organization.domain', 'eu.acme.test');
    }

    public function test_organization_delete_is_rejected_when_contacts_are_still_attached(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Acme Industries',
        ]);

        $contact = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
        ]);

        ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        $this->deleteJson('/api/organizations/'.$organization->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['organization'])
            ->assertJsonPath('errors.organization.0', 'Cette organisation ne peut pas être supprimée tant que des contacts y sont rattachés.');

        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
    }

    public function test_admin_can_show_update_and_delete_a_contact_with_organization_reassignment(): void
    {
        $organization = Organization::query()->create(['name' => 'Acme Industries']);
        $newOrganization = Organization::query()->create(['name' => 'Globex']);

        $contact = Contact::query()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'full_name' => 'Alice Martin',
            'job_title' => 'Sales Lead',
            'status' => 'active',
        ]);

        $primaryEmail = ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        $mailbox = MailboxAccount::query()->create([
            'provider' => 'ovh_mx_plan',
            'email' => 'ops@aegis.test',
            'display_name' => 'AEGIS Ops',
            'username' => 'ops@aegis.test',
            'password_encrypted' => 'secret',
            'imap_host' => 'imap.mail.ovh.net',
            'imap_port' => 993,
            'imap_secure' => true,
            'smtp_host' => 'smtp.mail.ovh.net',
            'smtp_port' => 465,
            'smtp_secure' => true,
            'sync_enabled' => true,
            'send_enabled' => true,
            'health_status' => 'healthy',
        ]);

        $thread = MailThread::query()->create([
            'public_uuid' => (string) Str::uuid(),
            'mailbox_account_id' => $mailbox->id,
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'subject_canonical' => 'offre aegis',
            'first_message_at' => now()->subHour(),
            'last_message_at' => now(),
            'last_direction' => 'in',
            'reply_received' => true,
            'auto_reply_received' => false,
            'status' => 'active',
        ]);

        $campaign = MailCampaign::query()->create([
            'mailbox_account_id' => $mailbox->id,
            'name' => 'CRM flow',
            'mode' => 'single',
            'status' => 'draft',
        ]);

        $recipient = MailRecipient::query()->create([
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'contact_email_id' => $primaryEmail->id,
            'email' => 'alice@acme.test',
            'status' => 'draft',
        ]);

        $this->getJson('/api/contacts/'.$contact->id)
            ->assertOk()
            ->assertJsonPath('contact.organizationName', 'Acme Industries')
            ->assertJsonPath('contact.emails.0.email', 'alice@acme.test')
            ->assertJsonPath('contact.recentThreads.0.id', $thread->id);

        $this->putJson('/api/contacts/'.$contact->id, [
            'organizationId' => $newOrganization->id,
            'firstName' => 'Alicia',
            'lastName' => 'Martin',
            'title' => 'VP Sales',
            'email' => 'alicia@globex.test',
            'phone' => '+33102030405',
            'notes' => 'Contact réassigné',
            'status' => 'active',
        ])->assertOk()
            ->assertJsonPath('message', 'Contact mis à jour.')
            ->assertJsonPath('contact.firstName', 'Alicia')
            ->assertJsonPath('contact.organizationName', 'Globex')
            ->assertJsonPath('contact.emails.0.email', 'alicia@globex.test');

        $this->deleteJson('/api/contacts/'.$contact->id)
            ->assertOk()
            ->assertJsonPath('message', 'Contact supprimé.');

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('mail_threads', [
            'id' => $thread->id,
            'contact_id' => null,
        ]);
        $this->assertDatabaseHas('mail_recipients', [
            'id' => $recipient->id,
            'contact_id' => null,
            'contact_email_id' => null,
        ]);
    }

    public function test_admin_can_add_and_remove_secondary_contact_emails(): void
    {
        $contact = Contact::query()->create([
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'full_name' => 'Alice Martin',
        ]);

        ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        $created = $this->postJson('/api/contacts/'.$contact->id.'/emails', [
            'email' => 'alice.secondary@acme.test',
        ]);

        $emailId = $created->assertCreated()
            ->assertJsonPath('message', 'Adresse e-mail ajoutée.')
            ->assertJsonPath('contact.emails.1.email', 'alice.secondary@acme.test')
            ->json('contact.emails.1.id');

        $this->deleteJson('/api/contacts/'.$contact->id.'/emails/'.$emailId)
            ->assertOk()
            ->assertJsonPath('message', 'Adresse e-mail supprimée.')
            ->assertJsonCount(1, 'contact.emails');

        $this->assertDatabaseMissing('contact_emails', ['id' => $emailId]);
    }

    public function test_contact_email_cannot_be_deleted_when_it_is_the_last_remaining_address(): void
    {
        $contact = Contact::query()->create([
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'full_name' => 'Alice Martin',
        ]);

        $email = ContactEmail::query()->create([
            'contact_id' => $contact->id,
            'email' => 'alice@acme.test',
            'is_primary' => true,
        ]);

        $this->deleteJson('/api/contacts/'.$contact->id.'/emails/'.$email->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Le contact doit conserver au moins une adresse e-mail.');
    }
}
