<?php declare(strict_types=1);

use ast\Node;
use Phan\{CodeBase, Plugin, Issue};
use Phan\Plugin\PluginImplementation;
use Phan\AST\AnalysisVisitor;
use Phan\Language\Context;

//PhanPluginDenyExecBackticks
//PhanPluginDenyExecExec
//PhanPluginDenyExecPassthru
//PhanPluginDenyExecProcOpen
//PhanPluginDenyExecShellExec
//PhanPluginDenyExecSystem
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
            public function visitShellExec(Node $node)
            {
                $this->emitIssueByPlugin('Backticks', 'Execution operator: backticks(``) is not allowed.');
            }
            public function visitCall(Node $node)
            {
                static $cache = [
                    'exec' => 'Exec',
                    'passthru' => 'Passthru',
                    //'proc_close' => 'ProcClose',
                    //'proc_get_status' => 'ProcGetStatus',
                    //'proc_nice' => 'ProcNice',
                    'proc_open' => 'ProcOpen',
                    //'proc_terminate' => 'ProcTerminate',
                    'shell_exec' => 'ShellExec',
                    'system' => 'System',
                ];
                $expr = $node->children['expr'];
                if (($expr instanceof Node) && ($expr->kind === \ast\AST_NAME)) {
                    $name = $expr->children['name'];
                    if (isset($cache[$name])) {
                        $this->emitIssueByPlugin($cache[$name], "$name is not allowed.");
                    }
                }
            }
            private function emitIssueByPlugin(string $issue_type_suffix, string $issue_message = '', int $severity = Issue::SEVERITY_NORMAL)
            {
                $this->plugin->emitIssue($this->code_base, $this->context, 'PhanPluginDenyExec'.$issue_type_suffix, $issue_message, $severity);
            }
            /** @var Plugin */
            private $plugin;
        })($node);
    }
};
