<?php declare(strict_types=1);

const IS_SINGLE_CLASS_OUTPUT = false; //< Output of a single class group with implementations
const IS_FOLLOW_INTERNAL     = true;
const IS_GROUP_NAMESPACE     = true;
const PATH_OUTPUT_ROOT       = '.DotInheritGraphPlugin/';
const PATH_OUTPUT_DOT        = PATH_OUTPUT_ROOT.'dot/';
const PATH_OUTPUT_IMG_DIR    = 'img/';
const PATH_OUTPUT_IMG        = PATH_OUTPUT_ROOT.PATH_OUTPUT_IMG_DIR;
const PREFIX_UNUNKNOWN       = '';
const PREFIX_CLASS           = '';
const PREFIX_FINAL           = "\\<\\<final\\>\\>\\n";
const PREFIX_ABSTRACT        = "\\<\\<abstract\\>\\>\\n";
const PREFIX_INTERFACE       = "\\<\\<interface\\>\\>\\n";
const NODE_UNUNKNOWN         = ',style="solid",color="grey75"';
const NODE_CLASS             = '';
const NODE_FINAL             = ',style="bold"';
const NODE_ABSTRACT          = ',style="rounded"';//, fontname="Italic"
const NODE_INTERFACE         = ',style="dashed"';
const EDGE_UNUNKNOWN         = '';
const EDGE_CLASS             = '';
const EDGE_FINAL             = '';
const EDGE_ABSTRACT          = '';
const EDGE_INTERFACE         = ' [style="dashed",color="firebrick4"]';
const TEMPLATE_DOT           = <<<EOD
digraph "DotInheritGraphPlugin"
{
  graph[rankdir="LR", labeljust=l, ranksep=0.2, nodesep=0.2];
  edge [fontname="Helvetica",fontsize="10",labelfontname="Helvetica",labelfontsize="10",color="midnightblue",arrowsize="0.7",dir="back"];
  node [fontname="Helvetica",fontsize="10",shape=record,height=0.2,width=0.4,color="black",fillcolor="white"];
  /**@ body */
}

EOD;

use ast\Node;
use Phan\Library\Set;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\{TypedElement, Clazz, Func, Method};
use Phan\Plugin;
use Phan\Plugin\PluginImplementation;

class DotInheritGraphPlugin extends PluginImplementation
{
    public function __destruct()
    {
        try {
            if (self::putDot($this->groups)) {
                self::putImg();
            }
        } finally {
            if (is_callable([get_parent_class($this), __FUNCTION__])) {
                parent::__destruct();
            }
        }
    }
    public function analyzeClass(CodeBase $code_base, Clazz $class)
    {
        $flags = 0;
        $parent = '';
        $inherit = [];
        $interfaces = [];
        $traits = [];
        $canonical = (string)$class->getFQSEN()->getCanonicalFQSEN();
        if (!$class->isTrait()) {
            foreach ($class->getInterfaceFQSENList() as $fqsen) {
                $interfaces[] = (string)$fqsen->getCanonicalFQSEN();
            }
        }
        if (!$class->isInterface()) {
            foreach ($class->getTraitFQSENList() as $fqsen) {
                $traits[] = (string)$fqsen->getCanonicalFQSEN();
            }
        }
        if ($class->isInterface()) {
            $flags = \ast\flags\CLASS_INTERFACE;
        } elseif ($class->isTrait()) {
            $flags = \ast\flags\CLASS_TRAIT;
        } else {
            if ($class->isFinal()) {
                $flags = \ast\flags\CLASS_FINAL;
            } elseif ($class->isAbstract()) {
                $flags = \ast\flags\CLASS_ABSTRACT;
            }
            if ($class->hasParentType()) {
                $parent = (string)$class->getParentClassFQSEN()->getCanonicalFQSEN();
                $inherit[] = $parent;
                do {
                    try {
                        $class = $class->getParentClass($code_base);
                        $inherit[] = (string)$class->getFQSEN()->getCanonicalFQSEN();
                    } catch (UnexpectedValueException $e) {
                        $inherit[] = (string)$class->getParentClassFQSEN()->getCanonicalFQSEN();
                        //fputs(STDERR, 'Class Not Found: '.(string)$class->getParentClassFQSEN()->getCanonicalFQSEN()."\n");
                        break;
                    }
                } while ($class->hasParentType());
            }
        }
        $canonical = self::canonicalNamespace($canonical);
        $parent = self::canonicalNamespace($parent);
        $inherit = self::canonicalNamespace($inherit);
        $interfaces = self::canonicalNamespace($interfaces);
        $traits = self::canonicalNamespace($traits);
        $relations = array_unique(array_merge([$canonical], $inherit, $interfaces));
        $target = null;
        foreach ($this->groups as &$group) {
            foreach ($relations as $relational) {
                if (array_key_exists($relational, $group)) {
                    if ($target === null) {
                        $target =& $group;
                    } else {
                        foreach ($group as $key => $value) {
                            if ($value !== null) {
                                $target[$key] = $value;
                            }
                        }
                        unset($group);
                    }
                    break;
                }
            }
        }
        if ($target === null) {
            $info = [];
            $this->groups[] =& $info;
            $target =& $info;
        }
        if (($parent !== '') && !array_key_exists($parent, $target)) {
            $target[$parent] = null;
        }
        foreach ($interfaces as $interface) {
            if (!array_key_exists($interface, $target)) {
                $target[$interface] = null;
            }
        }
        $target[$canonical] = self::createNodeInfo($flags, $parent, $interfaces, $traits);
    }
    public static function putDot(array $groups) : bool
    {
        if (!self::prepareDir(PATH_OUTPUT_DOT)) {
            return false;
        }
        // Follow internal
        if (IS_FOLLOW_INTERNAL) {
            foreach ($groups as &$group) {
                $internal_group = [];
                foreach ($group as $fqsen => &$class) {
                    if ($class === null) {
                        //echo "Internal: $fqsen\n";
                        $internals = self::createInternalNodeInfoGroup($fqsen);
                        if ($internals !== false) {
                            $internal_group = $internals + $internal_group;
                        }
                    }
                }
                $group = $internal_group + $group;
            }
        }
        $group_count = 0;
        foreach ($groups as $group) {
            if (count($group) <= 1) {
                if (!IS_SINGLE_CLASS_OUTPUT || count($group->$interfaces) < 1) {
                    continue;
                }
            }
            // Namespace Group
            $prefix_namespcase = '';
            if (IS_GROUP_NAMESPACE) {
                foreach ($group as $fqsen => $class) {
                    $rpos = strrpos($fqsen, '\\');
                    if (($rpos !== false) && ($rpos !== 0)) {
                        $ns = substr($fqsen, 0, $rpos + 1);
                        if ($prefix_namespcase === '') {
                            $prefix_namespcase = $ns;
                        } else {
                            $matchpos = self::strmatchpos($prefix_namespcase, $ns);
                            if ($matchpos === false) {
                                $prefix_namespcase = '';
                                break;
                            }
                            while ((1 < $matchpos) && ($ns[$matchpos - 1] !== '\\')) {
                                --$matchpos;
                            }
                            $prefix_namespcase = substr($ns, 0, $matchpos);
                        }
                        if ($prefix_namespcase === '\\') {
                            $prefix_namespcase = '';
                            break;
                        }
                    }
                }
                //echo "NS: $prefix_namespcase\n";
            }
            $result_node_gl = [];
            $result_node_ns = [];
            $result = [];
            $node_count = 0;
            $node_names = [];
            // Node
            foreach ($group as $fqsen => $class) {
                $prefix = '';
                $suffix = '';
                $attr = '';
                $is_ns = false;
                if ($prefix_namespcase !== '' && strpos($fqsen, $prefix_namespcase) === 0) {
                    $is_ns = true;
                }
                if ($class === null) {
                    $prefix = PREFIX_UNUNKNOWN;
                    $attr = NODE_UNUNKNOWN;
                } elseif ($class->flags & \ast\flags\CLASS_INTERFACE) {
                    $prefix = PREFIX_INTERFACE;
                    $attr = NODE_INTERFACE;
                } elseif ($class->flags & \ast\flags\CLASS_TRAIT) {
                    continue;
                } else {
                    $prefix = PREFIX_CLASS;
                    if ($class->flags & \ast\flags\CLASS_ABSTRACT) {
                        $prefix = PREFIX_ABSTRACT;
                        $attr = NODE_ABSTRACT;
                    } elseif ($class->flags & \ast\flags\CLASS_FINAL) {
                        $prefix = PREFIX_FINAL;
                        $attr = NODE_FINAL;
                    }
                    // traits
                    $traits = '';
                    foreach ($class->traits as $trait) {
                        if ($is_ns && (strpos($trait, $prefix_namespcase) === 0)) {
                            $trait = substr($trait, strlen($prefix_namespcase));
                        }
                        // \l -> left-justified <http://www.graphviz.org/content/attrs#kescString>
                        $traits .= '\\<\\<trait\\>\\>'.addslashes((string)$trait).'\\l';
                    }
                    if (!empty($traits)) {
                        $suffix .= '|'.$traits;
                    }
                }
                $node_names[$fqsen] = $node_count;
                $label = $fqsen;
                if ($is_ns && (strpos($fqsen, $prefix_namespcase) === 0)) {
                    $label = substr($fqsen, strlen($prefix_namespcase));
                }
                $node = "Node{$node_count} [label=\"{$prefix}".addslashes($label)."{$suffix}\"{$attr}]";
                if ($is_ns) {
                    $result_node_ns[] = $node;
                } else {
                    $result_node_gl[] = $node;
                }
                ++$node_count;
            }
            foreach ($result_node_gl as $node) {
                $result[] = '  '.$node;
            }
            if (count($result_node_ns)) {
                $result[] = '  subgraph cluster_0 {';
                $result[] = '    label = "'.addslashes($prefix_namespcase).'";';
                foreach ($result_node_ns as $node) {
                    $result[] = '    '.$node;
                }
                $result[] = '  }';
            }
            // Edge
            foreach ($group as $fqsen => $class) {
                if ($class === null) {
                    continue;
                }
                $rhs = $node_names[$fqsen];
                // parent <- this
                if (!($class->flags & (\ast\flags\CLASS_TRAIT | \ast\flags\CLASS_INTERFACE))) {
                    if ($class->extends) {
                        $attr = EDGE_CLASS;
                        if (!array_key_exists($class->extends, $group) || $group[$class->extends] === null) {
                            $attr = EDGE_UNUNKNOWN;
                        } else {
                            assert(!($class->flags & \ast\flags\CLASS_INTERFACE), 'Bad extends(interface)');
                            assert(!($class->flags & \ast\flags\CLASS_TRAIT), 'Bad extends(trait)');
                            if ($group[$class->extends]->flags & \ast\flags\CLASS_ABSTRACT) {
                                $attr = EDGE_ABSTRACT;
                            } elseif ($group[$class->extends]->flags & \ast\flags\CLASS_FINAL) {
                                $attr = EDGE_FINAL;
                            }
                        }
                        $result[] = "  Node{$node_names[$class->extends]} -> Node{$rhs}{$attr}";
                    }
                }
                // interface <- this
                if (!($class->flags & \ast\flags\CLASS_TRAIT)) {
                    foreach ($class->implements as $interface) {
                        $result[] = "  Node{$node_names[$interface]} -> Node{$rhs}".EDGE_INTERFACE;
                    }
                }
            }
            // Create dot file for Graphviz.
            $filename = PATH_OUTPUT_DOT."inherit_graph_{$group_count}.dot";
            if (file_put_contents($filename, str_replace('/**@ body */', implode("\n", $result), TEMPLATE_DOT)) === false) {
                fputs(STDERR, 'Failed write file: '.$filename."\n");
            }
            ++$group_count;
        }
        return true;
    }
    // Create img and html.
    private static function putImg()
    {
        $filename = PATH_OUTPUT_ROOT.'inherit_graph.html';
        if (is_file($filename) && unlink($filename)) {
            return false;
        }
        if (!self::prepareDir(PATH_OUTPUT_IMG)) {
            return false;
        }
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            PATH_OUTPUT_DOT,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS
        )) as $fileInfo) {
            $img = $fileInfo->getBasename('.dot').'.png';
            shell_exec('dot -Tpng '.PATH_OUTPUT_DOT.$fileInfo->getBasename().' -o '.PATH_OUTPUT_IMG.$img);
            $files[] = '    <div><h3>'.$img.'</h3><div><img src="'.PATH_OUTPUT_IMG_DIR.$img.'"></div></div>';
        }
        $template = file_get_contents(__FILE__, false, null, __COMPILER_HALT_OFFSET__);
        if (file_put_contents($filename, str_replace('/**@ body */', implode("\n", $files), $template)) === false) {
            fputs(STDERR, 'Failed write file: '.$filename."\n");
        }
    }
    private static function prepareDir($path)
    {
        if (file_exists($path)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $path,
                \FilesystemIterator::SKIP_DOTS
            )) as $fileinfo) {
                if ($fileinfo->isFile()) {
                    if (unlink($fileinfo->getPathname())) {
                        fputs(STDERR, "Failed unlink: {$fileinfo->getPathname()}\n");
                        return false;
                    }
                }
            }
            //rmdir($path);
        } else {
            if (!mkdir($path, 0777, true)) {
                fputs(STDERR, "Failed mkdir: $path\n");
                return false;
            }
        }
        return true;
    }
    private static function strmatchpos(string $lhs, string $rhs) : int
    {
        $pos = 0;
        $len = min(strlen($lhs), strlen($rhs));
        if (($len === 0) || ($lhs[$pos] !== $rhs[$pos])) {
            return false;
        }
        do {
            ++$pos;
        } while (($pos < $len) && ($lhs[$pos] === $rhs[$pos]));
        return $pos;
    }
    private static function canonicalNamespace($names)
    {
        $canonical = [];
        foreach ((array)$names as $name) {
            if (0 < strlen($name)) {
                if ($name[0] !== '\\') {
                    $canonical[] = '\\'.$name;
                } else {
                    $canonical[] = $name;
                }
            }
        }
        if (is_string($names)) {
            return ((count($canonical) === 1) ? $canonical[0] : '');
        }
        return $canonical;
    }
    private static function createInternalNodeInfoGroup(string $name)
    {
        //echo "Internal: $name\n";
        try {
            $reflection = new \ReflectionClass($name);
        } catch (\ReflectionException $e) {
            return false;
        }
        if ($reflection->isInternal()) {
            $flags = 0;
            $parent = '';
            $inherit = [];
            $interfaces = [];
            $traits = [];
            if (!$reflection->isTrait()) {
                $interfaces = $reflection->getInterfaceNames();
            }
            if (!$reflection->isInterface()) {
                $traits = $reflection->getTraitNames();
            }
            if ($reflection->isInterface()) {
                $flags = \ast\flags\CLASS_INTERFACE;
            } elseif ($reflection->isTrait()) {
                $flags = \ast\flags\CLASS_TRAIT;
            } else {
                if ($reflection->isFinal()) {
                    $flags = \ast\flags\CLASS_FINAL;
                } elseif ($reflection->isAbstract()) {
                    $flags = \ast\flags\CLASS_ABSTRACT;
                }
                $parentClass = $reflection->getParentClass();
                if ($parentClass instanceof \ReflectionClass) {
                    $parent = $parentClass->name;
                }
            }
            $parent = self::canonicalNamespace($parent);
            $interfaces = self::canonicalNamespace($interfaces);
            $traits = self::canonicalNamespace($traits);
            $info = self::createNodeInfo($flags, $parent, $interfaces, $traits);
            $result = [$name => $info];
            $relations = $info->implements;
            if ($info->extends) {
                $relations[] = $info->extends;
            }
            foreach ($relations as $relational) {
                $result += self::createInternalNodeInfoGroup($relational);
            }
            return $result;
        }
        return false;
    }
    private static function createNodeInfo(int $flags, string $extends, array $implements, array $traits)
    {
        return new class($flags, $extends, $implements, $traits)
        {
            public function __construct(int $flags, string $extends, array $implements, array $traits)
            {
                $this->flags = $flags;
                $this->extends = $extends;
                $this->implements = $implements;
                $this->traits = $traits;
            }
            // Used by ast\AST_CLASS (exclusive)
            // \ast\flags\CLASS_ABSTRACT
            // \ast\flags\CLASS_FINAL
            // \ast\flags\CLASS_TRAIT
            // \ast\flags\CLASS_INTERFACE
            // \ast\flags\CLASS_ANONYMOUS
            public $flags; //< int
            public $extends; //< string
            public $implements; //< string[]
            public $traits; //< string[]
        };
    }
    private $groups = [];
}
return new DotInheritGraphPlugin;
__halt_compiler();<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DotInheritGraph</title>
  </head>
  <body>
/**@ body */
  </body>
</html>
