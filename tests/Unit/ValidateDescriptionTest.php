<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use YOURLS\RBAC\Rbac;
use InvalidArgumentException;

class ValidateDescriptionTest extends TestCase
{
    public function testValidateDescription_WithValidDescription_ReturnsUnchanged(): void
    {
        $description = 'Administrator has full access to all features.';
        $result = Rbac::validate_description($description);
        $this->assertSame('Administrator has full access to all features.', $result);
    }

    public function testValidateDescription_WithSurroundingSpaces_TrimsAndReturns(): void
    {
        $description = '  Administrator has full access.  ';
        $result = Rbac::validate_description($description);
        $this->assertSame('Administrator has full access.', $result);
    }

    public function testValidateDescription_WithEmptyString_ReturnsEmptyString(): void
    {
        $result = Rbac::validate_description('');
        $this->assertSame('', $result);
    }

    public function testValidateDescription_WithWhitespaceOnly_ReturnsEmptyString(): void
    {
        $result = Rbac::validate_description('   ');
        $this->assertSame('', $result);
    }

    public function testValidateDescription_WithExactlyMaxLength65535_ReturnsDescription(): void
    {
        $description = str_repeat('a', 65535);
        $result = Rbac::validate_description($description);
        $this->assertSame($description, $result);
    }

    public function testValidateDescription_WithExceedingMaxLength_ThrowsInvalidArgumentException(): void
    {
        $description = str_repeat('a', 65536);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Description must be 65535 characters or less');
        Rbac::validate_description($description);
    }

    public function testValidateDescription_WithSpecialCharacters_ReturnsUnchanged(): void
    {
        $description = 'Can manage <b>users</b> & roles';
        $result = Rbac::validate_description($description);
        $this->assertSame('Can manage <b>users</b> & roles', $result);
    }
}
