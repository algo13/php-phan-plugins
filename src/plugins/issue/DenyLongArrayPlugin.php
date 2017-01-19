<?php declare(strict_types=1);

use ast\Node;
use Phan\{CodeBase, Plugin};
use Phan\Plugin\PluginImplementation;
use Phan\AST\AnalysisVisitor;
use Phan\Language\Context;

// PhanPluginNonShortArray
return new class extends PluginImplementation
{
    public function analyzeNode(CodeBase $code_base, Context $context, Node $node, Node $parent_node = null)
    {
        (new class ($code_base, $context, $this) extends AnalysisVisitor
        {
            public function __construct(CodeBase $code_base, Context $context, Plugin $plugin)
            {
                parent::__construct($code_base, $context);
                $this->plugin = $plugin;
            }
            public function visit(Node $node)
            {
            }
            public function visitArray(Node $node)
            {
                // [] = BINARY_MUL
                // array() = EXEC_INCLUDE
                if ($node->flags === \ast\flags\EXEC_INCLUDE) {
                    $this->plugin->emitIssue($this->code_base, $this->context, 'PhanPluginNonShortArray', 'array() to []');
                }
            }
            /** @var Plugin */
            private $plugin;
        })($node);
    }
};
