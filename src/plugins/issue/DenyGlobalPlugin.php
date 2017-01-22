<?php declare(strict_types=1);

use ast\Node;
use Phan\{CodeBase, Plugin, Issue};
use Phan\Plugin\PluginImplementation;
use Phan\AST\AnalysisVisitor;
use Phan\Language\Context;

// PhanPluginDenyGlobalVariable
// PhanPluginDenyGlobalKeyword
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
            public function visitVar(Node $node)
            {
                if ($node->children['name'] === 'GLOBAL') {
                    $this->emitIssueByPlugin('Variable', '$GLOBAL Variable is not allowed.');
                }
            }
            public function visitGlobal(Node $node)
            {
                $this->emitIssueByPlugin('Keyword', 'global keyword are not allowed.');
            }
            private function emitIssueByPlugin(string $issue_type_suffix, string $issue_message = '', int $severity = Issue::SEVERITY_NORMAL)
            {
                $this->plugin->emitIssue($this->code_base, $this->context, 'PhanPluginDenyGlobal'.$issue_type_suffix, $issue_message, $severity);
            }
            /** @var Plugin */
            private $plugin;
        })($node);
    }
};
