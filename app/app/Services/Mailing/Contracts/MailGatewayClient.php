<?php

namespace App\Services\Mailing\Contracts;

interface MailGatewayClient
{
    public function testImap(array $configuration): array;

    public function testSmtp(array $configuration): array;

    public function dispatchMessage(array $payload): array;

    public function syncMailbox(array $payload): array;
}
