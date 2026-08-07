<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use YOURLS\RBAC\Rbac;
use InvalidArgumentException;

class ValidateNameTest extends TestCase
{
    public function testValidateName_WithValidName_ReturnsUnchanged(): void
    {
        $name = 'Administrator';
        $result = Rbac::validate_name($name);
        $this->assertSame('Administrator', $result);
    }

    public function testValidateName_WithSurroundingSpaces_TrimsAndReturns(): void
    {
        $name = '  Administrator  ';
        $result = Rbac::validate_name($name);
        $this->assertSame('Administrator', $result);
    }

    public function testValidateName_WithExactlyMaxLength100_ReturnsName(): void
    {
        $name = str_repeat('a', 100);
        $result = Rbac::validate_name($name);
        $this->assertSame($name, $result);
    }

    public function testValidateName_WithExceedingMaxLength_ThrowsInvalidArgumentException(): void
    {
        $name = str_repeat('a', 101);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name must be 100 characters or less');
        Rbac::validate_name($name);
    }

    public function testValidateName_WithEmptyString_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name cannot be empty');
        Rbac::validate_name('');
    }

    public function testValidateName_WithWhitespaceOnly_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Name cannot be empty');
        Rbac::validate_name('   ');
    }

    public function testValidateName_WithSpecialCharacters_ReturnsUnchanged(): void
    {
        $name = 'Content Editor!';
        $result = Rbac::validate_name($name);
        $this->assertSame('Content Editor!', $result);
    }

    public function testValidateName_WithSqlInjectionAttempt_ReturnsAsValidName(): void
    {
        // Name validation only checks non-empty and length, so SQL injection chars pass
        // (they'll be parameterized in the actual SQL query)
        $name = "admin' OR '1'='1";
        $result = Rbac::validate_name($name);
        $this->assertSame($name, $result);
    }
}
