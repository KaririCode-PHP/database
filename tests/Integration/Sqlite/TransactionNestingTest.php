<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Sqlite;

use KaririCode\Database\Tests\Support\Sqlite;
use KaririCode\Database\Type\ScalarValue;
use PHPUnit\Framework\TestCase;

/** BUG-006: begin e commit/rollback precisam usar o mesmo nome de savepoint. */
final class TransactionNestingTest extends TestCase
{
    public function testThreeLevelsCommit(): void
    {
        $c = Sqlite::connection();
        $c->executeStatement('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $c->beginTransaction();
        $c->beginTransaction();
        $c->beginTransaction();
        $c->executeStatement('INSERT INTO t (id) VALUES (1)');
        $c->commit();
        $c->commit();
        $c->commit();
        self::assertSame(1, ScalarValue::asInt($c->executeQuery('SELECT COUNT(*) FROM t')->fetchOne(), 'integer'));
        self::assertFalse($c->isTransactionActive());
    }

    public function testInnerRollbackKeepsOuterWork(): void
    {
        $c = Sqlite::connection();
        $c->executeStatement('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $c->beginTransaction();
        $c->executeStatement('INSERT INTO t (id) VALUES (1)');
        $c->beginTransaction();
        $c->executeStatement('INSERT INTO t (id) VALUES (2)');
        $c->rollBack();
        $c->commit();
        self::assertSame('1', ScalarValue::asString($c->executeQuery('SELECT GROUP_CONCAT(id) FROM t')->fetchOne(), 'string'));
    }

    public function testNestedTransactional(): void
    {
        $c = Sqlite::connection();
        $c->executeStatement('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $c->transactional(fn() => $c->transactional(fn() => $c->executeStatement('INSERT INTO t (id) VALUES (7)')));
        self::assertSame(1, ScalarValue::asInt($c->executeQuery('SELECT COUNT(*) FROM t')->fetchOne(), 'integer'));
    }
}
