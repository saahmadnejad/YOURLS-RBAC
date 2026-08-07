<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use YOURLS\RBAC\Rbac;
use InvalidArgumentException;

class QuoteIdentifierTest extends TestCase
{
    public function testQuoteIdentifier_WithValidName_ReturnsBacktickQuoted(): void
    {
        $identifier = 'rbac_users';
        $result = Rbac::quote_identifier($identifier);
        $this->assertSame('`rbac_users`', $result);
    }

    public function testQuoteIdentifier_WithAlphanumericAndUnderscore_ReturnsBacktickQuoted(): void
    {
        $identifier = 'yourls_rbac_roles';
        $result = Rbac::quote_identifier($identifier);
        $this->assertSame('`yourls_rbac_roles`', $result);
    }

    public function testQuoteIdentifier_WithNumbersOnly_ReturnsBacktickQuoted(): void
    {
        $identifier = 'table123';
        $result = Rbac::quote_identifier($identifier);
        $this->assertSame('`table123`', $result);
    }

    public function testQuoteIdentifier_WithSqlInjectionAttempt_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid database identifier');
        Rbac::quote_identifier("users; DROP TABLE users");
    }

    public function testQuoteIdentifier_WithSemicolon_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::quote_identifier('users; DROP TABLE users');
    }

    public function testQuoteIdentifier_WithBacktick_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::quote_identifier('users`');
    }

    public function testQuoteIdentifier_WithSpace_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::quote_identifier('users roles');
    }

    public function testQuoteIdentifier_WithHyphen_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::quote_identifier('users-roles');
    }

    public function testQuoteIdentifier_WithSpecialCharacters_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::quote_identifier('users!@#');
    }

    public function testQuoteIdentifier_WithEmptyString_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid database identifier');
        Rbac::quote_identifier('');
    }

    public function testQuoteIdentifier_WithSingleCharacter_ReturnsBacktickQuoted(): void
    {
        $identifier = 't';
        $result = Rbac::quote_identifier($identifier);
        $this->assertSame('`t`', $result);
    }

    public function testQuoteIdentifier_WithSqlCommentSequence_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::quote_identifier('table --');
    }

    public function testQuoteIdentifier_WithSqlQuoteSequence_ThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rbac::quote_identifier("table' OR '1'='1");
    }
}
