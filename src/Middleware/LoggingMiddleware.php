<?php

declare(strict_types=1);

namespace KaririCode\Database\Middleware;

use KaririCode\Database\Contract\Driver;
use KaririCode\Database\Contract\DriverConnection;
use KaririCode\Database\Contract\Middleware;
use KaririCode\Database\Contract\Result;
use KaririCode\Database\Contract\Statement;
use KaririCode\Database\ValueObject\ConnectionParams;
use Psr\Log\LoggerInterface;

// ── LoggingMiddleware ────────────────────────────────────────────────
//
// Logs all SQL queries with parameters and execution time.
// Duck-typed callable — zero hard dependency on kariricode/logging.
// ─────────────────────────────────────────────────────────────────────

final class LoggingMiddleware implements Middleware
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    #[\Override]
    public function wrap(Driver $driver): Driver
    {
        return new class ($driver, $this->logger) implements Driver {
            public function __construct(
                private readonly Driver $inner,
                private readonly LoggerInterface $logger,
            ) {}

            #[\Override]
            public function connect(ConnectionParams $params): DriverConnection
            {
                $start = hrtime(true);
                $conn = $this->inner->connect($params);
                $ms = (hrtime(true) - $start) / 1_000_000;
                $this->logger->info('Database connection established.', ['driver' => $params->driver, 'host' => $params->host, 'port' => $params->port, 'database' => $params->database, 'duration_ms' => $ms]);

                return new class ($conn, $this->logger) implements DriverConnection {
                    public function __construct(
                        private readonly DriverConnection $inner,
                        private readonly LoggerInterface $logger,
                    ) {}

                    #[\Override]
                    public function prepare(string $sql): Statement
                    {
                        return new class (inner: $this->inner->prepare($sql), sql: $sql, logger: $this->logger) implements Statement {
                            /** @var array<int|string, true> */
                            private array $boundParams = [];

                            public function __construct(
                                private readonly Statement $inner,
                                private readonly string $sql,
                                private readonly LoggerInterface $logger,
                            ) {}

                            #[\Override]
                            public function bindValue(int|string $param, mixed $value, int $type = 2): void
                            {
                                $this->boundParams[$param] = true; // so a contagem: valores nao vao para o log
                                $this->inner->bindValue(param: $param, value: $value, type: $type);
                            }

                            /**
                             * @param array<int|string, mixed> $params
                             */
                            #[\Override]
                            public function execute(?array $params = null): Result
                            {
                                $start = hrtime(true);
                                $result = $this->inner->execute($params);
                                $ms = (hrtime(true) - $start) / 1_000_000;
                                $this->logger->debug('Statement executed.', ['sql' => $this->sql, 'parameter_count' => count($params ?? $this->boundParams), 'duration_ms' => $ms]);

                                return $result;
                            }
                        };
                    }

                    #[\Override]
                    public function exec(string $sql): int
                    {
                        $start = hrtime(true);
                        $result = $this->inner->exec($sql);
                        $ms = (hrtime(true) - $start) / 1_000_000;
                        $this->logger->debug('Statement executed.', ['sql' => $sql, 'parameter_count' => 0, 'duration_ms' => $ms]);

                        return $result;
                    }

                    #[\Override]
                    public function lastInsertId(?string $name = null): string
                    {
                        return $this->inner->lastInsertId($name);
                    }

                    #[\Override]
                    public function beginTransaction(): void
                    {
                        $this->inner->beginTransaction();
                    }
                    #[\Override]
                    public function commit(): void
                    {
                        $this->inner->commit();
                    }
                    #[\Override]
                    public function rollBack(): void
                    {
                        $this->inner->rollBack();
                    }
                    #[\Override]
                    public function nativeConnection(): object
                    {
                        return $this->inner->nativeConnection();
                    }
                };
            }
        };
    }
}
