<?php

declare(strict_types=1);

// SBOM CycloneDX 1.5 a partir do composer.lock (Spec V4.3.3 §32).

/** @return array<string, mixed> */
function readJson(string $path): array
{
    $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException("JSON invalido: {$path}");
    }
    $out = [];
    foreach ($data as $key => $value) {
        $out[(string) $key] = $value;
    }

    return $out;
}

function text(mixed $value): string
{
    return is_string($value) ? $value : '';
}

/** @return list<array{license: array{id?: string, name?: string}}> */
function licenses(mixed $ids): array
{
    $out = [];
    foreach (is_array($ids) ? $ids : [] as $id) {
        $id = text($id);
        $out[] = ['license' => preg_match('/^[A-Za-z0-9.+-]+$/', $id) === 1 ? ['id' => $id] : ['name' => $id]];
    }

    return $out;
}

/** @return list<array<string, mixed>> */
function components(mixed $packages, string $scope): array
{
    $out = [];
    foreach (is_array($packages) ? $packages : [] as $p) {
        if (!is_array($p)) {
            continue;
        }
        $purl = 'pkg:composer/' . text($p['name'] ?? null) . '@' . text($p['version'] ?? null);
        $out[] = ['type' => 'library', 'bom-ref' => $purl, 'name' => text($p['name'] ?? null), 'version' => text($p['version'] ?? null),
            'scope' => $scope, 'purl' => $purl, 'licenses' => licenses($p['license'] ?? [])];
    }

    return $out;
}

$root = dirname(__DIR__);
$lock = readJson($root . '/composer.lock');
$manifest = readJson($root . '/composer.json');
$hex = bin2hex(random_bytes(16));
$hex[12] = '4';
$hex[16] = dechex(8 + random_int(0, 3));
$uuid = sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
$name = text($manifest['name'] ?? null);
$bom = [
    'bomFormat' => 'CycloneDX',
    'specVersion' => '1.5',
    'serialNumber' => 'urn:uuid:' . $uuid,
    'version' => 1,
    'metadata' => [
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'component' => ['type' => 'library', 'name' => $name, 'purl' => "pkg:composer/{$name}", 'licenses' => licenses([$manifest['license'] ?? ''])],
    ],
    'components' => [...components($lock['packages'] ?? [], 'required'), ...components($lock['packages-dev'] ?? [], 'optional')],
];
echo json_encode($bom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
