<?php

declare(strict_types=1);

// Orcamentos da Spec V4.3.3 §13 medidos na arvore sintatica (nikic/php-parser),
// porque o PHPMD 2.15 nao le PHP 8.4 (propriedades em interface, private(set)).

// nikic/php-parser comes with the toolchain kcode installs (ADR-016).
require __DIR__ . '/../.kcode/vendor/autoload.php';

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

const BUDGET = ['cyclomatic' => 10, 'cognitive' => 15, 'nesting' => 3, 'parameters' => 4];

/** @return array{cyclomatic: int, cognitive: int, nesting: int} */
function measure(Stmt\ClassMethod $method): array
{
    $visitor = new class extends NodeVisitorAbstract {
        public int $cyclomatic = 1;
        public int $cognitive = 0;
        public int $nesting = 0;
        private int $depth = 0;
        private int $controlDepth = 0;

        public function enterNode(Node $node): ?int
        {
            if ($node instanceof Stmt\Class_) {
                return NodeVisitor::DONT_TRAVERSE_CHILDREN; // classes anonimas sao medidas a parte
            }
            if (self::branches($node)) {
                ++$this->cyclomatic;
            }
            if (self::structural($node)) {
                $this->cognitive += 1 + $this->depth;
            } elseif ($node instanceof Stmt\ElseIf_ || $node instanceof Stmt\Else_
                || $node instanceof Expr\BinaryOp\BooleanAnd || $node instanceof Expr\BinaryOp\BooleanOr) {
                ++$this->cognitive;
            }
            if (self::nests($node)) {
                ++$this->depth;
            }
            if (self::controlNests($node)) {
                $this->nesting = max($this->nesting, ++$this->controlDepth);
            }

            return null;
        }

        public function leaveNode(Node $node): null
        {
            if (self::nests($node)) {
                --$this->depth;
            }
            if (self::controlNests($node)) {
                --$this->controlDepth;
            }

            return null;
        }

        private static function branches(Node $n): bool
        {
            return $n instanceof Stmt\If_ || $n instanceof Stmt\ElseIf_ || $n instanceof Stmt\For_ || $n instanceof Stmt\Foreach_
                || $n instanceof Stmt\While_ || $n instanceof Stmt\Do_ || $n instanceof Stmt\Catch_ || $n instanceof Expr\Ternary
                || $n instanceof Expr\BinaryOp\Coalesce || $n instanceof Expr\BinaryOp\BooleanAnd || $n instanceof Expr\BinaryOp\BooleanOr
                || $n instanceof Expr\BinaryOp\LogicalAnd || $n instanceof Expr\BinaryOp\LogicalOr
                || ($n instanceof Stmt\Case_ && $n->cond !== null) || ($n instanceof Node\MatchArm && $n->conds !== null);
        }

        private static function structural(Node $n): bool
        {
            return $n instanceof Stmt\If_ || $n instanceof Stmt\Switch_ || $n instanceof Stmt\For_ || $n instanceof Stmt\Foreach_
                || $n instanceof Stmt\While_ || $n instanceof Stmt\Do_ || $n instanceof Stmt\Catch_ || $n instanceof Expr\Ternary
                || $n instanceof Expr\Match_;
        }

        private static function controlNests(Node $n): bool
        {
            return $n instanceof Stmt\If_ || $n instanceof Stmt\For_ || $n instanceof Stmt\Foreach_ || $n instanceof Stmt\While_
                || $n instanceof Stmt\Do_ || $n instanceof Stmt\Switch_ || $n instanceof Stmt\TryCatch;
        }

        private static function nests(Node $n): bool
        {
            return self::controlNests($n) || $n instanceof Expr\Closure || $n instanceof Expr\ArrowFunction || $n instanceof Expr\Match_;
        }
    };
    $traverser = new NodeTraverser($visitor);
    $traverser->traverse($method->stmts ?? []);

    return ['cyclomatic' => $visitor->cyclomatic, 'cognitive' => $visitor->cognitive, 'nesting' => $visitor->nesting];
}

$baseline = json_decode((string) file_get_contents(__DIR__ . '/complexity-baseline.json'), true, flags: JSON_THROW_ON_ERROR);
$accepted = static fn(string $rule, string $id): bool => is_array($baseline) && is_array($baseline[$rule] ?? null) && array_key_exists($id, $baseline[$rule]);
$parser = (new ParserFactory())->createForNewestSupportedVersion();
$finder = new NodeFinder();
$violations = [];
$root = $argv[1] ?? __DIR__ . '/../src';
/** @var SplFileInfo $file */
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $ast = $parser->parse((string) file_get_contents($file->getPathname())) ?? [];
    $namespace = $finder->findFirstInstanceOf($ast, Stmt\Namespace_::class)?->name?->toString() ?? '';
    foreach ($finder->findInstanceOf($ast, Stmt\ClassLike::class) as $type) {
        $typeName = $namespace . '\\' . ($type->name?->toString() ?? 'class@anonymous');
        foreach ($type->getMethods() as $method) {
            $id = $typeName . '::' . $method->name->toString();
            $metrics = measure($method) + ['parameters' => count($method->params)];
            foreach (BUDGET as $rule => $max) {
                if ($metrics[$rule] > $max && !$accepted($rule, $id)) {
                    $violations[] = sprintf('%s %s=%d (max %d)', $id, $rule, $metrics[$rule], $max);
                }
            }
        }
    }
    $calls = [...$finder->findInstanceOf($ast, Expr\New_::class), ...$finder->findInstanceOf($ast, Expr\MethodCall::class),
        ...$finder->findInstanceOf($ast, Expr\StaticCall::class), ...$finder->findInstanceOf($ast, Expr\NullsafeMethodCall::class)];
    foreach ($calls as $call) {
        $named = 0;
        $positional = 0;
        foreach ($call->args as $arg) {
            if (!$arg instanceof Node\Arg || $arg->unpack) {
                continue;
            }
            if ($arg->name === null) {
                ++$positional;
            } else {
                ++$named;
            }
        }
        if ($named + $positional >= 3 && $positional > 0) {
            $violations[] = sprintf('%s:%d named-arguments (%d posicionais)', $file->getFilename(), $call->getStartLine(), $positional);
        }
    }
}
sort($violations);
foreach ($violations as $v) {
    echo $v, PHP_EOL;
}
fwrite(STDERR, count($violations) . " violacao(oes)\n");
exit($violations === [] ? 0 : 1);
