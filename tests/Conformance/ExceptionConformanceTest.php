<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Conformance;

use KaririCode\Contract\Exception\Configuration;
use KaririCode\Contract\Exception\ContainerFailure;
use KaririCode\Contract\Exception\NotFound;
use KaririCode\Contract\Exception\Protocol;
use KaririCode\Contract\Exception\Runtime;
use KaririCode\Contract\Exception\Validation;
use KaririCode\Database\Exception\DatabaseException;
use KaririCode\Database\Tests\Support\SourceFiles;
use PHPUnit\Framework\TestCase;

/** Spec V4.3.3 §8.4 e E-047; registro do ecossistema: Database 6000–6199. */
final class ExceptionConformanceTest extends TestCase
{
    private const array FAMILIES = [Validation::class, NotFound::class, Configuration::class, Protocol::class, ContainerFailure::class, Runtime::class];

    private const array INTERPOLATION_ALLOWED = ['feature', 'phpType', 'dbType'];

    public function testEveryExceptionHasOneFamilyAPrivateConstructorAndDepthTwo(): void
    {
        foreach ($this->files() as $file) {
            $class = SourceFiles::typeOf($file);
            self::assertNotNull($class, $file);
            $ref = new \ReflectionClass($class);
            if ($ref->isInterface()) {
                continue;
            }
            $families = array_filter(self::FAMILIES, fn(string $f): bool => $ref->implementsInterface($f));
            self::assertTrue($ref->isFinal(), $class);
            self::assertTrue($ref->implementsInterface(DatabaseException::class), $class);
            self::assertCount(1, $families, "{$class}: exatamente uma familia");
            self::assertTrue($ref->getConstructor()?->isPrivate() ?? false, "{$class}: construtor privado");
            $depth = 0;
            for ($p = $ref->getParentClass(); $p !== false; $p = $p->getParentClass()) {
                ++$depth;
            }
            self::assertLessThanOrEqual(2, $depth, $class);
        }
    }

    public function testCodesAreUniqueInsideTheRangeAndPrefixed(): void
    {
        $codes = [];
        foreach ($this->files() as $file) {
            $src = SourceFiles::read($file);
            preg_match_all('/new self\\(\\s*(?:message:\\s*)?([\'"])\\[DB_(\\d{4})\\][^\'"]*\\1,\\s*(?:code:\\s*)?(\\d{4})/', $src, $m, PREG_SET_ORDER);
            self::assertSame(substr_count($src, 'new self('), count($m), basename($file) . ': toda mensagem leva [DB_nnnn]');
            foreach ($m as $hit) {
                self::assertSame($hit[2], $hit[3], "prefixo {$hit[2]} != codigo {$hit[3]}");
                $codes[] = (int) $hit[3];
            }
        }
        self::assertSame(count($codes), count(array_unique($codes)), 'codigos unicos');
        foreach ($codes as $code) {
            self::assertTrue($code >= 6000 && $code <= 6199, "codigo {$code} fora de 6000–6199");
        }
    }

    public function testMessagesInterpolateOnlyPackageIdentifiers(): void
    {
        foreach ($this->files() as $file) {
            preg_match_all('/new self\\(\\s*(?:message:\\s*)?"[^"]*"/', SourceFiles::read($file), $msgs);
            foreach ($msgs[0] as $msg) {
                preg_match_all('/\\{\\$(\\w+)/', $msg, $vars);
                foreach ($vars[1] as $var) {
                    self::assertContains($var, self::INTERPOLATION_ALLOWED, basename($file) . ": {$var}");
                }
            }
        }
    }

    public function testNoThrowNewOutsideFactories(): void
    {
        $offenders = [];
        foreach (SourceFiles::php('src') as $file) {
            if (!str_contains($file, '/src/Exception/') && str_contains(SourceFiles::read($file), 'throw new ')) {
                $offenders[] = basename($file);
            }
        }
        self::assertSame([], $offenders);
    }

    /** @return list<string> */
    private function files(): array
    {
        return array_values(array_filter(SourceFiles::php('src'), static fn(string $f): bool => str_contains($f, '/src/Exception/')));
    }
}
