<?php
namespace App\Services\Engine;

class FormulaEvaluator
{ private ?\Symfony\Component\ExpressionLanguage\ExpressionLanguage $lang = null;

    /**
     * Evaluate all formula nodes for one test point.
     * Nodes must be pre-sorted topologically.
     */
    public function evaluate(array $nodes, array $context): array
    {
        $resolved = $context;

        foreach ($nodes as $node) {
            $resolved[$node->key] = $this->evaluateNode($node, $resolved);
        }

        return $resolved;
    }

    /**
     * Topologically sort formula nodes using DFS.
     */
    public function topologicalSort($nodes): array
    {
        $nodeMap = [];
        foreach ($nodes as $n) {
            $nodeMap[$n->key] = $n;
        }

        $sorted  = [];
        $visited = [];

        $visit = function (string $key) use (&$visit, &$nodeMap, &$sorted, &$visited) {
            if (isset($visited[$key])) return;
            $visited[$key] = true;
            if (isset($nodeMap[$key])) {
                foreach ($nodeMap[$key]->depends_on ?? [] as $dep) {
                    $visit($dep);
                }
                $sorted[] = $nodeMap[$key];
            }
        };

        foreach ($nodeMap as $key => $node) {
            $visit($key);
        }

        return $sorted;
    }

    private function evaluateNode($node, array $context): float
    {
        $expression = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function ($m) use ($context, $node) {
            if (!array_key_exists($m[1], $context)) {
                throw new \RuntimeException("Formula [{$node->key}] references undefined variable [{$m[1]}]");
            }
            return (string) $context[$m[1]];
        }, $node->formula);

        return $this->safeEval($expression);
    }

    private function safeEval(string $expression): float
    {
        if ($this->lang === null) {
            $this->lang = new \Symfony\Component\ExpressionLanguage\ExpressionLanguage();

            $fns = ['sqrt', 'abs', 'pow', 'max', 'min', 'log', 'exp', 'round', 'floor', 'ceil'];
            foreach ($fns as $fn) {
                $this->lang->register($fn, fn() => '', fn($args, ...$p) => $fn(...$p));
            }
        }

        return (float) $this->lang->evaluate($expression);
    }
}
