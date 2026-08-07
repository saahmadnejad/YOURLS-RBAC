<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use YOURLS\RBAC\Rbac;
use InvalidArgumentException;

class ValidateEmailTest extends TestCase
{
    public function testValidateEmail_WithValidEmail_ReturnsUnchanged(): void
    {
        $email = 'user@example.com';
        $result = Rbac::validate_email($email);
        $this->assertSame('user@example.com', $result);
    }

    public function testValidateEmail_WithComplexValidEmail_ReturnsUnchanged(): void
    {
        $email = 'john.doe+tag@sub.example.co.uk';
        $result = Rbac::validate_email($email);
        $this->assertSame('john.doe+tag@sub.example.co.uk', $result);
    }

    public function testValidateEmail_WithSurroundingSpaces_TrimsAndReturns(): void
    {
        $email = '  user@example.com  ';
        $result = Rbac::validate_email($email);
        $this->assertSame('user@example.com', $result);
    }

    public function testValidateEmail_WithEmptyString_ReturnsEmptyString(): void
    {
        $result = Rbac::validate_email('');
        $this->assertSame('', $result);
    }

    public function testValidateEmail_WithWhitespaceOnly_ReturnsEmptyString(): void
    {
        $result = Rbac::validate_email('   ');
        $this->assertSame('', $result);
    }

    public function testValidateEmail_WithMissingAtSign_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address');
        Rbac::validate_email('userexample.com');
    }

    public function testValidateEmail_WithMissingDomain_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address');
        Rbac::validate_email('user@');
    }

    public function testValidateEmail_WithSqlInjectionAttempt_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_email("user@example.com' OR '1'='1");
    }

    public function testValidateEmail_WithSqlInjectionDropTable_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_email("user@example.com'; DROP TABLE users; --");
    }

    public function testValidateEmail_WithExceedingMaxLength_ThrowsInvalidArgumentException(): void
    {
        $email = str_repeat('a', 245) . '@example.com';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Email must be 255 characters or less');
        Rbac::validate_email($email);
    }

    public function testValidateEmail_WithLongButValidEmail_ReturnsEmail(): void
    {
        $email = str_repeat('a', 60) . '@example.com';
        $result = Rbac::validate_email($email);
        $this->assertSame($email, $result);
    }

    public function testValidateEmail_WithHtmlInjection_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_email('user@example.com<script>alert(1)</script>');
    }

    public function testValidateEmail_WithAngleBracketInjection_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_email('user@example.com<>malicious');
    }
}
