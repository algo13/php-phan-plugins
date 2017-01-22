<?php declare(strict_types=1);

use ast\Node;
use Phan\{CodeBase, Plugin, Issue};
use Phan\AST\{ContextNode, AnalysisVisitor};
use Phan\Language\{Context, UnionType, Type};

class PhanPluginVisitor extends AnalysisVisitor
{
    public function __construct(CodeBase $code_base, Context $context, Plugin $plugin)
    {
        parent::__construct($code_base, $context);
        $this->plugin = $plugin;
    }
    public function visit(Node $node)
    {
    }
    /** @param null|string|Node $node */
    protected function createContextNode($node) : ContextNode
    {
        return (new ContextNode($this->code_base, $this->context, $node));
    }
    /** @param null|string|Node $node */
    protected function createUnionTypeFromNode($node, bool $should_catch_issue_exception = true) : UnionType
    {
        return UnionType::fromNode($this->context, $this->code_base, $node, $should_catch_issue_exception);
    }
    protected function emitIssueByPlugin(
        string $issue_type,
        string $issue_message,
        int $severity = Issue::SEVERITY_NORMAL,
        int $remediation_difficulty = Issue::REMEDIATION_B,
        int $issue_type_id = Issue::TYPE_ID_UNKNOWN
    ) {
        $this->plugin->emitIssue(
            $this->code_base,
            $this->context,
            'PhanPlugin'.$issue_type,
            $issue_message,
            $severity,
            $remediation_difficulty,
            $issue_type_id
        );
    }
    /** @var Plugin */
    protected $plugin;
}
