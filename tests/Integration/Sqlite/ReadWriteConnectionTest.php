<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Sqlite;

use KaririCode\Database\Connection\ReadWriteConnection;
use KaririCode\Database\Tests\Support\Sqlite;
use PHPUnit\Framework\Attributes\DataProvider;
use KaririCode\Database\Type\ScalarValue;
use PHPUnit\Framework\TestCase;

/** ADR-006: sticky=true segue o Doctrine DBAL 4.2; sticky=false segue a opcao sticky do Laravel 12. */
final class ReadWriteConnectionTest extends TestCase
{
    /** @return iterable<string, array{bool, string, string}> */
    public static function cases(): iterable
    {
        foreach ([true, false] as $sticky) {
            $s = $sticky ? 'sticky' : 'nao-sticky';
            yield "{$s}: leitura inicial" => [$sticky, 'none', 'replica'];
            yield "{$s}: dentro de transacao" => [$sticky, 'inside', 'primary'];
            yield "{$s}: apos commit" => [$sticky, 'commit', $sticky ? 'primary' : 'replica'];
            yield "{$s}: apos escrita" => [$sticky, 'write', $sticky ? 'primary' : 'replica'];
        }
    }

    #[DataProvider('cases')]
    public function testRouting(bool $sticky, string $step, string $expected): void
    {
        $p = Sqlite::connection();
        $r = Sqlite::connection();
        foreach ([[$p, 'primary'], [$r, 'replica']] as [$conn, $who]) {
            $conn->executeStatement('CREATE TABLE who (n TEXT)');
            $conn->insert('who', ['n' => $who]);
        }
        $rw = new ReadWriteConnection($p, [$r], $sticky);
        $seen = match ($step) {
            'inside' => (function () use ($rw): string {
                $rw->beginTransaction();
                $v = ScalarValue::asString($rw->executeQuery('SELECT n FROM who')->fetchOne(), 'string');
                $rw->commit();
                return $v;
            })(),
            'commit' => (function () use ($rw): string {
                $rw->beginTransaction();
                $rw->commit();
                return ScalarValue::asString($rw->executeQuery('SELECT n FROM who')->fetchOne(), 'string');
            })(),
            'write' => (function () use ($rw): string {
                $rw->executeStatement('UPDATE who SET n = n');
                return ScalarValue::asString($rw->executeQuery('SELECT n FROM who')->fetchOne(), 'string');
            })(),
            default => ScalarValue::asString($rw->executeQuery('SELECT n FROM who')->fetchOne(), 'string'),
        };
        self::assertSame($expected, $seen);
    }
}
