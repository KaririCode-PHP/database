<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Conformance;

use KaririCode\Database\Tests\Support\SourceFiles;
use PHPUnit\Framework\TestCase;

/** Spec V4.3.3 §12, §16, §18 e §31, mais os marcadores de 72 colunas. */
final class SpecRulesTest extends TestCase
{
    public function testTypesFollowTheNamingAndStateRules(): void
    {
        $violations = [];
        foreach ($this->classes() as $class) {
            $ref = new \ReflectionClass($class);
            $short = $ref->getShortName();
            if (preg_match('/(Manager|Helper|Util|Interface)$/', $short)) {
                $violations[] = "nome: {$short}";
            }
            foreach ($ref->getProperties() as $p) {
                if ($p->isStatic() && $p->getDeclaringClass()->getName() === $class) {
                    $violations[] = "estatica: {$short}::\${$p->getName()}";
                }
            }
            if (!$ref->isInterface() && !$ref->isAbstract() && !$ref->isEnum() && !$ref->isTrait() && !$ref->isFinal()) {
                $violations[] = "nao final: {$short}";
            }
            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
                if ($m->getDeclaringClass()->getName() === $class && $m->getNumberOfParameters() === 0 && preg_match('/^(get|set)[A-Z]/', $m->getName())) {
                    $violations[] = "acessor: {$short}::{$m->getName()}()";
                }
            }
        }
        self::assertSame([], $violations);
    }

    public function testNoDuckTypingAndMarkersHave72Columns(): void
    {
        $bad = [];
        foreach ($this->files() as $file) {
            foreach (explode("\n", SourceFiles::read($file)) as $n => $line) {
                if (str_contains($line, 'method_exists(')) {
                    $bad[] = basename($file) . ':' . ($n + 1) . ' method_exists';
                }
                $t = trim($line);
                if (str_starts_with($t, '// ──') && mb_strlen($t) !== 72) {
                    $bad[] = basename($file) . ':' . ($n + 1) . ' marcador ' . mb_strlen($t);
                }
            }
        }
        self::assertSame([], $bad);
    }

    /**
     * §17: each type states its domain role (C1), preconditions and
     * thread-safety (C2), side effects (C3), an example (C4) and references
     * with a link (C5), without authorship tags.
     */
    public function testDocblocksMeetTheFiveCriteria(): void
    {
        $markers = [
            'preconditions (C2)' => 'Preconditions',
            'thread-safety (C2)' => 'Thread-safety:',
            'side effects (C3)' => 'Side effects:',
            'example (C4)' => 'Example',
            'references (C5)' => 'References:',
            'link (C5)' => '@see https://',
        ];
        $missing = [];

        foreach ($this->classes() as $class) {
            $type = new \ReflectionClass($class);
            $docblock = (string) $type->getDocComment();

            foreach ($markers as $criterion => $marker) {
                if (!str_contains($docblock, $marker)) {
                    $missing[] = $type->getShortName() . ': ' . $criterion;
                }
            }

            if (preg_match('/@(?:package|author|license|copyright|version)\b/', $docblock) === 1) {
                $missing[] = $type->getShortName() . ': authorship tag';
            }

            if (preg_match('/\A\/\*\*\s+\*\s+Represents\b/', $docblock) === 1) {
                $missing[] = $type->getShortName() . ': C1 states the domain role, not "Represents"';
            }
        }

        self::assertSame([], $missing);
        self::assertCount(92, $this->classes(), 'every source file declares one loadable type');
    }

    /** @return list<class-string> */
    private function classes(): array
    {
        return array_values(array_filter(array_map(SourceFiles::typeOf(...), $this->files())));
    }

    /** @return list<string> */
    private function files(): array
    {
        return SourceFiles::php('src');
    }
}
