<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/tools']);

return (new PhpCsFixer\Config())
    ->setRules(['@PER-CS3.0' => true])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/build/php-cs-fixer.cache');
