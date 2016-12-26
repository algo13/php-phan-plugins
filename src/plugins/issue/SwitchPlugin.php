<?php declare(strict_types=1);

use ast\Node;
use Phan\{CodeBase, Plugin, Issue};
use Phan\Plugin\PluginImplementation;
use Phan\AST\{ContextNode, AnalysisVisitor};
use Phan\Language\{Context, UnionType, Type};

// PhanPluginSwitchContinueBreak
// PhanPluginSwitchFallThrough
// PhanPluginSwitchDefaultNothing
// PhanPluginSwitchDefaultNonTail
// PhanPluginSwitchDefaultMultiple
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
            public function visitSwitchList(Node $node)
            {
                $default_count = 0;
                foreach ($node->children as $case) {
                    if ($case->children['cond'] === null) {
                        ++$default_count;
                    }
                    $stmts_count = count($case->children['stmts']->children);
                    if (0 < $stmts_count) {
                        $last = $case->children['stmts']->children[$stmts_count - 1];
                        if ($last->kind === \ast\AST_CONTINUE) {
                            if ($last->children['depth'] === null || $last->children['depth'] === 1) {
                                $this->emitIssueByPlugin('ContinueBreak');
                            }
                        } elseif (($last->kind !== \ast\AST_BREAK)
                         && ($last->kind !== \ast\AST_RETURN)
                         && ($last->kind !== \ast\AST_THROW)
                        ) {
                            $this->emitIssueByPlugin('FallThrough');
                        }
                    }
                }
                if ($default_count === 0) {
                    $this->emitIssueByPlugin('DefaultNothing');
                } elseif (1 < $default_count) {
                    $this->emitIssueByPlugin('DefaultMultiple', "($default_count)", Issue::SEVERITY_CRITICAL);
                } else {
                    $case_count = count($node->children);
                    if (0 < $case_count) {
                        if ($node->children[$case_count - 1]->children['cond'] !== null) {
                            $this->emitIssueByPlugin('DefaultNonTail');
                        }
                    }
                }
            }
            private function emitIssueByPlugin(string $issue_type_suffix, string $issue_message = '', int $severity = Issue::SEVERITY_NORMAL)
            {
                $this->plugin->emitIssue($this->code_base, $this->context, 'PhanPluginSwitch'.$issue_type_suffix, $issue_message, $severity);
            }
            /** @var Plugin */
            private $plugin;
        })($node);
    }
};
