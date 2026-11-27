<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Server;

use KaririCode\Database\Connection\ConnectionFactory;
use KaririCode\Database\Contract\SchemaInspector;
use KaririCode\Database\Enum\ReferentialAction;
use KaririCode\Database\Driver\PdoPgSqlDriver;
use KaririCode\Database\Middleware\TenancyMiddleware;
use KaririCode\Database\Platform\PostgreSqlPlatform;
use KaririCode\Database\Schema\PostgreSqlSchemaInspector;
use KaririCode\Database\Type\ScalarValue;
use KaririCode\Database\ValueObject\ColumnMetadata;
use KaririCode\Database\ValueObject\ConnectionParams;

/** Runs when KARIRICODE_DATABASE_PGSQL_URL holds a DSN such as pgsql://user:pass@127.0.0.1:5432/db. */
final class PostgreSqlServerTest extends ServerTestCase
{
    public function testConnectsWithTheDefaultCharset(): void
    {
        self::assertInstanceOf(PostgreSqlPlatform::class, $this->connection->platform);
        self::assertTrue($this->connection->platform->version->isPostgreSql());
        self::assertSame('UTF8', $this->connection->executeQuery('SHOW client_encoding')->fetchOne(), 'utf8mb4 is sent as UTF8');
    }

    public function testTenancySetsTheSearchPathOnConnect(): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS "kc_tenant_a" CASCADE');
        $this->connection->executeStatement('CREATE SCHEMA "kc_tenant_a"');
        $this->connection->executeStatement('CREATE TABLE "kc_tenant_a".kc_marker (tenant VARCHAR(10))');
        $this->connection->executeStatement("INSERT INTO \"kc_tenant_a\".kc_marker VALUES ('a')");

        $tenant = (new ConnectionFactory([new TenancyMiddleware(static fn(): string => 'kc_tenant_a')]))->createConnection(self::params());

        self::assertSame('a', $tenant->executeQuery('SELECT tenant FROM kc_marker')->fetchOne());
        self::assertTrue((new PostgreSqlSchemaInspector($tenant))->tableExists('kc_marker'), 'the inspector reads the search path, not public');
        self::assertFalse((new PostgreSqlSchemaInspector($this->connection))->tableExists('kc_marker'));
        $this->connection->executeStatement('DROP SCHEMA "kc_tenant_a" CASCADE');
    }

    public function testTheCharsetIsSentAsAPostgreSqlEncoding(): void
    {
        $base = self::params();
        foreach (['LATIN1' => 'LATIN1', 'UTF8MB4' => 'UTF8', 'utf8' => 'UTF8'] as $charset => $encoding) {
            $connection = self::connect(new ConnectionParams(driver: $base->driver, host: $base->host, port: $base->port, database: $base->database, username: $base->username, password: $base->password, charset: $charset));

            self::assertSame($encoding, $connection->executeQuery('SHOW client_encoding')->fetchOne(), $charset);
        }
        self::assertInstanceOf(\Pdo\Pgsql::class, $this->connection->nativeConnection());
    }

    #[\Override]
    protected function defaultUpdateAction(): ReferentialAction
    {
        return ReferentialAction::NoAction;
    }

    #[\Override]
    protected function assertExplicitSchemaIsRead(SchemaInspector $inspector): void
    {
        $this->connection->executeStatement('DROP SCHEMA IF EXISTS kc_other CASCADE');
        $this->connection->executeStatement('CREATE SCHEMA kc_other');
        $this->connection->executeStatement('CREATE TABLE kc_other.kc_elsewhere (id INT PRIMARY KEY, parent_id INT REFERENCES kc_other.kc_elsewhere (id))');

        try {
            self::assertFalse($inspector->tableExists('kc_elsewhere'));
            self::assertTrue($inspector->tableExists('kc_elsewhere', 'kc_other'));
            self::assertSame(['kc_elsewhere'], $inspector->listTableNames('kc_other'));
            self::assertSame(['id', 'parent_id'], array_map(static fn(ColumnMetadata $c): string => $c->name, $inspector->listTableColumns('kc_elsewhere', 'kc_other')));
            self::assertCount(1, $inspector->listTableIndexes('kc_elsewhere', 'kc_other'));
            self::assertCount(1, $inspector->listTableForeignKeys('kc_elsewhere', 'kc_other'));
            self::assertSame([], $inspector->listTableColumns('kc_elsewhere'));
        } finally {
            $this->connection->executeStatement('DROP SCHEMA kc_other CASCADE');
        }
    }

    #[\Override]
    protected static function urlVariable(): string
    {
        return 'KARIRICODE_DATABASE_PGSQL_URL';
    }

    #[\Override]
    protected function autoIncrementKey(): string
    {
        return 'id SERIAL PRIMARY KEY';
    }

    #[\Override]
    protected function unknownDatabaseCode(): int
    {
        return 6013;
    }

    #[\Override]
    protected function sessionIsolation(): string
    {
        return ScalarValue::asString($this->connection->executeQuery('SHOW default_transaction_isolation')->fetchOne(), 'string');
    }

    #[\Override]
    protected function sequenceFor(string $table): string
    {
        return "{$table}_id_seq";
    }

    #[\Override]
    protected function inspector(): SchemaInspector
    {
        return new PostgreSqlSchemaInspector($this->connection);
    }
}
