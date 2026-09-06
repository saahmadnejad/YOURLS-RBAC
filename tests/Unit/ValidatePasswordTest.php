<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use YOURLS\RBAC\Rbac;
use InvalidArgumentException;

class ValidatePasswordTest extends TestCase
{
    public function testValidatePassword_WithValidPassword_ReturnsUnchanged(): void
    {
        $password = 'secureP@ss123';
        $result = Rbac::validate_password($password);
        $this->assertSame('secureP@ss123', $result);
    }

    public function testValidatePassword_WithExactlyMinLength8_ReturnsPassword(): void
    {
        // Given: a password at the minimum allowed length
        $password = 'abcdefgh';

        // When: validated
        $result = Rbac::validate_password($password);

        // Then: it is returned unchanged
        $this->assertSame('abcdefgh', $result);
    }

    public function testValidatePassword_WithExactlyMaxLength255_ReturnsPassword(): void
    {
        $password = str_repeat('a', 255);
        $result = Rbac::validate_password($password);
        $this->assertSame($password, $result);
    }

    public function testValidatePassword_WithEmptyString_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password cannot be empty');
        Rbac::validate_password('');
    }

    public function testValidatePassword_WithLength7_ThrowsInvalidArgumentException(): void
    {
        // Given: a password one character below the minimum length
        // When: validated
        // Then: an InvalidArgumentException with a helpful message is thrown
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters');
        Rbac::validate_password('abcdefg');
    }

    public function testValidatePassword_WithLength256_ThrowsInvalidArgumentException(): void
    {
        $password = str_repeat('a', 256);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be 255 characters or less');
        Rbac::validate_password($password);
    }

    public function testValidatePassword_WithSqlInjectionAttempt_PassesAsValidPassword(): void
    {
        // Password validation only checks length, so SQL injection chars are allowed
        // (they will be hashed before reaching the database)
        $password = "pass' OR '1'='1";
        $result = Rbac::validate_password($password);
        $this->assertSame($password, $result);
    }

    public function testValidatePassword_WithSingleCharacterTooShort_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at least 8 characters');
        Rbac::validate_password('a');
    }

    public function testValidatePassword_WithElevenCharacterPassword_AcceptsAsValid(): void
    {
        // Given: an 11-character password (policy is length-only by design;
        // complexity rules are YAGNI until a config knob asks for them)
        // ponytail: no complexity/blocklist checks — add when a real deployment asks
        $password = 'password123';

        // When: validated
        $result = Rbac::validate_password($password);

        // Then: it passes the length policy
        $this->assertSame($password, $result);
    }
}
