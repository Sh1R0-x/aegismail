<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MailingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_provider_mail_schema_is_available_on_sqlite(): void
    {
        $this->assertTrue(Schema::hasTable('smtp_provider_accounts'));
        $this->assertTrue(Schema::hasColumns('smtp_provider_accounts', [
            'provider',
            'username',
            'password_encrypted',
            'smtp_host',
            'smtp_port',
            'smtp_secure',
            'send_enabled',
        ]));
        $this->assertTrue(Schema::hasColumn('mail_drafts', 'outbound_provider'));
        $this->assertTrue(Schema::hasColumn('mail_campaigns', 'outbound_provider'));
    }
}
