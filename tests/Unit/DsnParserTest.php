<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Unit;

use KaririCode\Database\DsnParser;
use KaririCode\Database\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Every part of a DATABASE_URL lands in its field of ConnectionParams. */
#[CoversClass(DsnParser::class)]
final class DsnParserTest extends TestCase
{
    public function testAFullServerDsn(): void
    {
        $params = (new DsnParser())->parse('pgsql://app%40ops:p%3Ass%2Fw@db.internal:6432/shop?charset=LATIN1&sslmode=require&application_name=api'); // secret-scan:allow: fixture

        self::assertSame(
            ['pdo_pgsql', 'db.internal', 6432, 'shop', 'app@ops', 'p:ss/w', 'LATIN1'],
            [$params->driver, $params->host, $params->port, $params->database, $params->username, $params->password, $params->charset],
        );
        self::assertSame(['charset' => 'LATIN1', 'sslmode' => 'require', 'application_name' => 'api'], $params->options);
    }

    /** @return iterable<string, array{string, string, int}> */
    public static function defaults(): iterable
    {
        yield 'mysql' => ['mysql:///shop', 'pdo_mysql', 3306];
        yield 'mariadb alias' => ['mariadb:///shop', 'pdo_mysql', 3306];
        yield 'postgres alias' => ['postgres:///shop', 'pdo_pgsql', 5432];
        yield 'custom alias' => ['warehouse:///shop', 'pdo_pgsql', 5432];
    }

    #[DataProvider('defaults')]
    public function testHostPortAndCredentialsDefault(string $dsn, string $driver, int $port): void
    {
        $params = (new DsnParser(['warehouse' => 'pdo_pgsql']))->parse($dsn);

        self::assertSame([$driver, 'localhost', $port, '', '', 'utf8mb4', []], [$params->driver, $params->host, $params->port, $params->username, $params->password, $params->charset, $params->options]);
    }

    public function testAnUnknownDriverAliasHasNoDefaultPort(): void
    {
        self::assertSame(0, (new DsnParser(['oracle' => 'pdo_oci']))->parse('oracle://u@db/x')->port);
    }

    public function testSqliteReadsItsQueryString(): void
    {
        $params = (new DsnParser())->parse('sqlite:////var/lib/app/app.db?charset=utf8&mode=ro&1=numeric-key');

        self::assertSame(['pdo_sqlite', '/var/lib/app/app.db', 0, 'utf8'], [$params->driver, $params->database, $params->port, $params->charset]);
        self::assertSame(['charset' => 'utf8', 'mode' => 'ro'], $params->options, 'only named options are kept');
    }

    public function testAPercentEncodedSqlitePathIsDecoded(): void
    {
        self::assertSame('/srv/my data/app.db', (new DsnParser())->parse('sqlite:////srv/my%20data/app.db')->database);
    }

    public function testACharsetThatIsNotTextFallsBackToTheDefault(): void
    {
        self::assertSame('utf8mb4', (new DsnParser())->parse('mysql://db/shop?charset[]=x')->charset);
    }

    /** @return iterable<string, array{string, int}> */
    public static function invalid(): iterable
    {
        yield 'no scheme' => ['//db/shop', 6016];
        yield 'unknown scheme' => ['oracle://db/x', 6017];
        yield 'not a uri' => ['mysql://:80', 6016];
    }

    #[DataProvider('invalid')]
    public function testInvalidDsns(string $dsn, int $code): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionCode($code);

        (new DsnParser())->parse($dsn);
    }
}
