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

    public function testValidatePassword_WithExactlyMinLength4_ReturnsPassword(): void
    {
        $password = 'abcd';
        $result = Rbac::validate_password($password);
        $this->assertSame('abcd', $result);
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

    public function testValidatePassword_WithLength3_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must be at least 4 characters');
        Rbac::validate_password('abc');
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
        $this->expectExceptionMessage('Password must be at least 4 characters');
        Rbac::validate_password('a');
    }
}
