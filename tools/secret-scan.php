<?php

declare(strict_types=1);

// Varredura de segredos (gate de seguranca, Spec V4.3.3 §29.1). Linhas com
// "secret-scan:allow" sao fixtures de teste revisadas.

$root = dirname(__DIR__);
$patterns = [
    'private key' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
    'AWS access key' => '/\bAKIA[0-9A-Z]{16}\b/',
    'GitHub token' => '/\bgh[pousr]_[A-Za-z0-9]{36,}\b/',
    'credential in URL' => '#[a-z][a-z0-9+.-]*://[^\s:/@\'"]+:([^\s@/\'"]+)@#i',
    'hard-coded password' => '/\b(?:password|passwd|pwd)\b\s*(?:=>|=|:)\s*[\'"]([^\'"\s]{6,})[\'"]/i',
];
$placeholders = ['pass', 'password', 'secret', 'changeme', '****', '********', 'pw', 'p', '...'];
/** @var list<string> $files */
$files = [];
foreach (scandir($root) ?: [] as $entry) {
    $path = "{$root}/{$entry}";
    if (is_file($path) && preg_match('/\.(md|json|php|dist|neon|xml|yml)$/', $entry) === 1 && $entry !== 'composer.lock') {
        $files[] = $path;
    }
}
foreach (['src', 'tests', 'tools', 'docs', '.github'] as $dir) {
    if (!is_dir("{$root}/{$dir}")) {
        continue;
    }
    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$dir}", FilesystemIterator::SKIP_DOTS)) as $file) {
        $files[] = $file->getPathname();
    }
}
$findings = [];
foreach (array_unique($files) as $file) {
    if (str_ends_with($file, 'secret-scan.php')) {
        continue;
    }
    foreach (file($file) ?: [] as $n => $line) {
        if (str_contains($line, 'secret-scan:allow')) {
            continue;
        }
        foreach ($patterns as $label => $regex) {
            if (preg_match($regex, $line, $m) === 1 && !in_array(strtolower($m[1] ?? ''), $placeholders, true)) {
                $findings[] = sprintf('%s:%d %s', substr($file, strlen($root) + 1), $n + 1, $label);
            }
        }
    }
}
foreach ($findings as $f) {
    echo $f, PHP_EOL;
}
fwrite(STDERR, count($findings) . " achado(s)\n");
exit($findings === [] ? 0 : 1);
