<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use YOURLS\RBAC\Rbac;

/**
 * Covers the standard denial payload produced by the URL write guards
 * (shunt_add_new_link & co). YOURLS core, AJAX and API all merge this
 * array into their normal response, so its shape is a public contract.
 */
class UrlWriteDeniedTest extends TestCase
{
    public function testUrlWriteDenied_ReturnsErrorStatus(): void
    {
        // Given: a denied URL write
        // When: building the payload
        $payload = Rbac::url_write_denied();

        // Then: it signals an error with a 403 code
        $this->assertSame('error', $payload['status']);
        $this->assertSame('403', $payload['errorCode']);
    }

    public function testUrlWriteDenied_ReturnsMachineReadableCode(): void
    {
        // Given: a denied URL write
        // When: building the payload
        $payload = Rbac::url_write_denied();

        // Then: API clients can branch on the stable code
        $this->assertSame('error:permission', $payload['code']);
    }

    public function testUrlWriteDenied_ReturnsHumanReadableMessage(): void
    {
        // Given: a denied URL write
        // When: building the payload
        $payload = Rbac::url_write_denied();

        // Then: a non-empty message is present (untranslated outside YOURLS)
        $this->assertSame('You do not have permission to manage URLs', $payload['message']);
    }

    public function testUrlWriteDenied_ErrorCodeIsStringLikeCoreApiPayloads(): void
    {
        // Given: YOURLS core emits errorCode as a string ('403' in
        // includes/auth.php, '404' in functions-api.php)
        // When: building the RBAC denial payload
        $payload = Rbac::url_write_denied();

        // Then: the errorCode is a string so type-sensitive clients see
        // the same shape as core API errors
        $this->assertIsString($payload['errorCode']);
    }

    public function testUrlWriteDenied_ContainsNoSuperglobalLeakage(): void
    {
        // Given: a denied URL write
        // When: building the payload
        $payload = Rbac::url_write_denied();

        // Then: the payload only carries the four contract keys
        $this->assertSame(['status', 'code', 'message', 'errorCode'], array_keys($payload));
    }
}
