<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use YOURLS\RBAC\Rbac;
use InvalidArgumentException;

class ValidateSlugTest extends TestCase
{
    public function testValidateSlug_WithValidSlug_ReturnsUnchanged(): void
    {
        $slug = 'admin_role';
        $result = Rbac::validate_slug($slug);
        $this->assertSame('admin_role', $result);
    }

    public function testValidateSlug_WithHyphens_ReturnsUnchanged(): void
    {
        $slug = 'content-editor';
        $result = Rbac::validate_slug($slug);
        $this->assertSame('content-editor', $result);
    }

    public function testValidateSlug_WithNumbers_ReturnsUnchanged(): void
    {
        $slug = 'role_123';
        $result = Rbac::validate_slug($slug);
        $this->assertSame('role_123', $result);
    }

    public function testValidateSlug_WithSurroundingSpaces_TrimsAndReturns(): void
    {
        $slug = '  admin  ';
        $result = Rbac::validate_slug($slug);
        $this->assertSame('admin', $result);
    }

    public function testValidateSlug_WithEmptyString_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slug cannot be empty');
        Rbac::validate_slug('');
    }

    public function testValidateSlug_WithWhitespaceOnly_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slug cannot be empty');
        Rbac::validate_slug('   ');
    }

    public function testValidateSlug_WithUpperCase_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_slug('Admin');
    }

    public function testValidateSlug_WithMixedCase_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_slug('AdminRole');
    }

    public function testValidateSlug_WithSpaces_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_slug('admin role');
    }

    public function testValidateSlug_WithSpecialCharacters_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_slug('admin@role');
    }

    public function testValidateSlug_WithSqlInjectionAttempt_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_slug("admin' OR '1'='1");
    }

    public function testValidateSlug_WithSqlInjectionDropTable_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_slug('admin; DROP TABLE roles');
    }

    public function testValidateSlug_WithDot_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_slug('admin.role');
    }

    public function testValidateSlug_WithExactlyMaxLength100_ReturnsSlug(): void
    {
        $slug = str_repeat('a', 100);
        $result = Rbac::validate_slug($slug);
        $this->assertSame($slug, $result);
    }

    public function testValidateSlug_WithExceedingMaxLength_ThrowsInvalidArgumentException(): void
    {
        $slug = str_repeat('a', 101);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Slug must be 100 characters or less');
        Rbac::validate_slug($slug);
    }

    public function testValidateSlug_WithSingleCharacter_ReturnsUnchanged(): void
    {
        $slug = 'a';
        $result = Rbac::validate_slug($slug);
        $this->assertSame('a', $result);
    }

    public function testValidateSlug_WithNumbersOnly_ReturnsUnchanged(): void
    {
        $slug = '12345';
        $result = Rbac::validate_slug($slug);
        $this->assertSame('12345', $result);
    }
}
