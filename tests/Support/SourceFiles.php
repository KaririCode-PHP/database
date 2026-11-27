<?php

declare(strict_types=1);

namespace KaririCode\Database\Tests\Support;

use PHPUnit\Framework\Assert;

/** Lista e le arquivos do pacote com tipos concretos (o iterador SPL devolve mixed). */
final class SourceFiles
{
    public const string ROOT = __DIR__ . '/../..';

    /** @return list<string> caminhos .php sob o diretorio, ordenados */
    public static function php(string $dir): array
    {
        $paths = [];
        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/' . $dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $paths[] = $file->getPathname();
            }
        }
        sort($paths);

        return $paths;
    }

    public static function read(string $path): string
    {
        $content = file_get_contents($path);
        Assert::assertIsString($content, "nao foi possivel ler {$path}");

        return $content;
    }

    /** @return array<string, mixed> */
    public static function json(string $path): array
    {
        $data = json_decode(self::read($path), true, flags: JSON_THROW_ON_ERROR);
        Assert::assertIsArray($data);

        $out = [];
        foreach ($data as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }

    /** @return class-string|null o tipo declarado no arquivo, se carregavel */
    public static function typeOf(string $path): ?string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', self::read($path), $m) !== 1) {
            return null;
        }
        $type = $m[1] . '\\' . basename($path, '.php');

        return class_exists($type) || interface_exists($type) || enum_exists($type) || trait_exists($type) ? $type : null;
    }
}
