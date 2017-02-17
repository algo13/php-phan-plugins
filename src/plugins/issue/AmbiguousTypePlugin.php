<?php declare(strict_types=1);

use ast\Node;
use Phan\{CodeBase, Plugin, Issue};
use Phan\Plugin\PluginImplementation;
use Phan\AST\{ContextNode, AnalysisVisitor};
use Phan\Language\{Context, UnionType, Type};
use Phan\Language\Type\{BoolType, IntType, FloatType, StringType};

// PhanPluginAmbiguousTypeEqual
// PhanPluginAmbiguousTypeUnaryBoolNot
// PhanPluginAmbiguousTypeConditional
// PhanPluginAmbiguousTypeDoWhile
// PhanPluginAmbiguousTypeFor
// PhanPluginAmbiguousTypeIfElem
// PhanPluginAmbiguousTypeWhile
// PhanPluginAmbiguousTypeSwitch
// PhanPluginAmbiguousTypeSwitchCase
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
            public function visitBinaryOp(Node $node)
            {
                // BINARY_IS_EQUAL(==), BINARY_IS_NOT_EQUAL(!=)
                if (($node->flags === \ast\flags\BINARY_IS_EQUAL)
                 || ($node->flags === \ast\flags\BINARY_IS_NOT_EQUAL)
                ) {
                    $this->requireIdentical($node->children['left'], 'Equal', '`==`, `!=` are used for AmbiguousType');
                    $this->requireIdentical($node->children['right'], 'Equal', '`==`, `!=` are used for AmbiguousType');
                }
            }
            public function visitUnaryOp(Node $node)
            {
                if ($node->flags & ast\flags\UNARY_BOOL_NOT) {
                    $this->requireIdentical($node->children['expr'], 'UnaryBoolNot', '`!` are used for AmbiguousType');
                }
            }
            public function visitConditional(Node $node)
            {
                $this->requireIdentical($node->children['cond'], 'Conditional');
            }
            public function visitDoWhile(Node $node)
            {
                $this->requireIdentical($node->children['cond'], 'DoWhile');
            }
            public function visitFor(Node $node)
            {
                $this->requireIdentical($node->children['cond'], 'For');
            }
            public function visitIfElem(Node $node)
            {
                $this->requireIdentical($node->children['cond'], 'IfElem');
            }
            public function visitWhile(Node $node)
            {
                $this->requireIdentical($node->children['cond'], 'While');
            }
            public function visitSwitch(Node $node)
            {
                $this->requireIdentical($node->children['cond'], 'Switch');
            }
            public function visitSwitchCase(Node $node)
            {
                $this->requireIdentical($node->children['cond'], 'SwitchCase');
            }
            /** @param null|string|Node $node */
            private function requireIdentical($node, string $issue_type_suffix, string $issue_message = 'AmbiguousType in condition')
            {
                if ($node instanceof Node) {
                    $utype = UnionType::fromNode($this->context, $this->code_base, $node);
                    if (($utype->hasAnyType([BoolType::instance(true), IntType::instance(true), FloatType::instance(true)]))
                     || ($utype->hasType(BoolType::instance(false)) && $utype->hasAnyType([IntType::instance(false), FloatType::instance(false), StringType::instance(false)]))
                    ) {
                        $this->emitIssueByPlugin($issue_type_suffix, $issue_message."($utype)", Issue::SEVERITY_NORMAL);
                    } elseif ($utype->hasType(StringType::instance(true))) {
                        $this->emitIssueByPlugin($issue_type_suffix, $issue_message."($utype)", Issue::SEVERITY_LOW);
                    }
                }
            }
            private function emitIssueByPlugin(string $issue_type_suffix, string $issue_message = '', int $severity = Issue::SEVERITY_NORMAL)
            {
                $this->plugin->emitIssue($this->code_base, $this->context, 'PhanPluginAmbiguousType'.$issue_type_suffix, $issue_message, $severity);
            }
            /** @var Plugin */
            private $plugin;
        })($node);
    }
};
