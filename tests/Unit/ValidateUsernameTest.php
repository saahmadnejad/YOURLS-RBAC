<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use YOURLS\RBAC\Rbac;
use InvalidArgumentException;

class ValidateUsernameTest extends TestCase
{
    public function testValidateUsername_WithValidAlphanumeric_ReturnsUnchanged(): void
    {
        $username = 'john_doe';
        $result = Rbac::validate_username($username);
        $this->assertSame('john_doe', $result);
    }

    public function testValidateUsername_WithDotsAndHyphens_ReturnsUnchanged(): void
    {
        $username = 'john.doe-smith';
        $result = Rbac::validate_username($username);
        $this->assertSame('john.doe-smith', $result);
    }

    public function testValidateUsername_WithSurroundingSpaces_TrimsAndReturns(): void
    {
        $username = '  john_doe  ';
        $result = Rbac::validate_username($username);
        $this->assertSame('john_doe', $result);
    }

    public function testValidateUsername_WithEmptyString_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Username cannot be empty');
        Rbac::validate_username('');
    }

    public function testValidateUsername_WithWhitespaceOnly_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Username cannot be empty');
        Rbac::validate_username('   ');
    }

    public function testValidateUsername_WithSqlInjectionAttempt_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_username("admin' OR '1'='1");
    }

    public function testValidateUsername_WithSqlInjectionComment_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_username('admin; DROP TABLE users');
    }

    public function testValidateUsername_WithSpacesInMiddle_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_username('john doe');
    }

    public function testValidateUsername_WithSqlKeywordsAndSpecialChars_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_username("admin'--");
    }

    public function testValidateUsername_WithExactlyMaxLength100_ThrowsNoException(): void
    {
        $username = str_repeat('a', 100);
        $result = Rbac::validate_username($username);
        $this->assertSame($username, $result);
    }

    public function testValidateUsername_WithExceedingMaxLength_ThrowsInvalidArgumentException(): void
    {
        $username = str_repeat('a', 101);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Username must be 100 characters or less');
        Rbac::validate_username($username);
    }

    public function testValidateUsername_WithUnicodeCharacters_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::validate_username('用户名');
    }

    public function testValidateUsername_WithNumbersOnly_ReturnsUnchanged(): void
    {
        $username = '123456';
        $result = Rbac::validate_username($username);
        $this->assertSame('123456', $result);
    }

    public function testValidateUsername_WithSingleCharacter_ReturnsUnchanged(): void
    {
        $username = 'a';
        $result = Rbac::validate_username($username);
        $this->assertSame('a', $result);
    }
}
