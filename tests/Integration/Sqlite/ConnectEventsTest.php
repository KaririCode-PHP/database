<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Integration\Sqlite;

use KaririCode\Database\Connection\ConnectionFactory;
use KaririCode\Database\Connection\ConnectionRegistry;
use KaririCode\Database\Event\ConnectionError;
use KaririCode\Database\Event\ConnectionEstablished;
use KaririCode\Database\Exception\ConnectionException;
use KaririCode\Database\Tests\Support\RecordingDispatcher;
use KaririCode\Database\ValueObject\ConnectionParams;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** SPEC-001 §2.3: ConnectionFactory reports each connect, named by the registry. */
#[CoversClass(ConnectionFactory::class)]
#[CoversClass(ConnectionRegistry::class)]
final class ConnectEventsTest extends TestCase
{
    public function testAConnectIsReportedWithTheRegistryName(): void
    {
        $dispatcher = new RecordingDispatcher();
        $registry = new ConnectionRegistry(['reporting' => 'sqlite:///:memory:'], new ConnectionFactory(eventDispatcher: $dispatcher));

        $registry->get('reporting');

        self::assertCount(1, $dispatcher->events);
        $event = $dispatcher->events[0];
        self::assertInstanceOf(ConnectionEstablished::class, $event);
        self::assertSame(['reporting', 'pdo_sqlite'], [$event->connectionName, $event->driverType]);
        self::assertGreaterThan(0.0, $event->durationMs);
        self::assertLessThan(60_000.0, $event->durationMs, 'milliseconds, not nanoseconds');
    }

    public function testTheFactoryNamesItsConnectionDefault(): void
    {
        $dispatcher = new RecordingDispatcher();

        (new ConnectionFactory(eventDispatcher: $dispatcher))->createConnection(new ConnectionParams(driver: 'pdo_sqlite'));

        $event = $dispatcher->events[0];
        self::assertInstanceOf(ConnectionEstablished::class, $event);
        self::assertSame('default', $event->connectionName);
    }

    public function testAFailedConnectIsReportedAndRethrown(): void
    {
        $dispatcher = new RecordingDispatcher();

        try {
            (new ConnectionFactory(eventDispatcher: $dispatcher))->createConnection(new ConnectionParams(driver: 'pdo_sqlite', database: '/kc-missing-directory/app.db'));
            self::fail('the database opened');
        } catch (ConnectionException $e) {
            self::assertCount(1, $dispatcher->events);
            $event = $dispatcher->events[0];
            self::assertInstanceOf(ConnectionError::class, $event);
            self::assertSame($e, $event->exception);
            self::assertNull($event->sql);
        }
    }
}
