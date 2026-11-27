<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Conformance;

use KaririCode\Database\Tests\Support\SourceFiles;
use PHPUnit\Framework\TestCase;

// ── ArfaConformanceTest ──────────────────────────────────────────────

/** Regras estruturais da ARFA 1.3 e metadados da Spec V4.3.3 (E-044). */
final class ArfaConformanceTest extends TestCase
{
    public function testEveryFileDeclaresStrictTypes(): void
    {
        foreach (SourceFiles::php('src') as $file) {
            self::assertStringContainsString('declare(strict_types=1);', SourceFiles::read($file), $file);
        }
    }

    public function testOneTypePerFile(): void
    {
        foreach (SourceFiles::php('src') as $file) {
            $declarations = preg_match_all('/^(final |abstract |readonly )*(class|interface|enum|trait) \w+/m', SourceFiles::read($file));
            self::assertLessThanOrEqual(1, $declarations, $file);
        }
    }

    public function testNoSerializationAntipatterns(): void
    {
        foreach (SourceFiles::php('src') as $file) {
            self::assertDoesNotMatchRegularExpression('/function (toArray|fromArray|jsonSerialize)\s*\(/', SourceFiles::read($file), $file);
        }
    }

    public function testNoInterfaceSuffix(): void
    {
        foreach (SourceFiles::php('src') as $file) {
            self::assertDoesNotMatchRegularExpression('/^interface \w+Interface\b/m', SourceFiles::read($file), $file);
        }
    }

    public function testComposerHasKaririCodeMetadata(): void
    {
        $composer = SourceFiles::json(SourceFiles::ROOT . '/composer.json');
        $extra = $composer['extra'] ?? null;
        self::assertIsArray($extra);
        self::assertArrayNotHasKey('arfa', $extra, 'extra.arfa foi substituido por extra.kariricode (E-044)');
        $meta = $extra['kariricode'] ?? null;
        self::assertIsArray($meta);
        self::assertSame('database', $meta['component'] ?? null);
        self::assertSame('4.3.3', $meta['spec'] ?? null);
        self::assertSame(['version' => '1.3', 'level' => 1], $meta['arfa'] ?? null);
        self::assertSame(['version' => '5.0', 'level' => 1], $meta['asvs'] ?? null);
        self::assertSame('6000–6199', $meta['error-code-range'] ?? null);
    }
}
