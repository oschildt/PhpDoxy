<?php

namespace PhpDoxy;

class AuxNodeVisitor extends \PhpParser\NodeVisitorAbstract
{
    protected $value_type = "";
    protected $params = [];

    function __construct($value_type, $params)
    {
        $this->value_type = $value_type;
        $this->params = $params;
    }

    public function enterNode(\PhpParser\Node $node)
    {
        if ($node->getType() == "Stmt_Function" || $node->getType() == "Stmt_ClassMethod") {
            $node->stmts = [];

            if (!empty($this->value_type)) {
                $node->returnType = new \PhpParser\Node\Name($this->value_type);
            }

            if (!empty($node->params)) {
                foreach ($node->params as &$param) {
                    if (empty($this->params["$" . $param->var->name])) {
                        continue;
                    }

                    $param->type = new \PhpParser\Node\Name($this->params["$" . $param->var->name]);
                }
            }
        }

        if ($node->getType() == "Stmt_Property") {
            if (!empty($this->value_type)) {
                $node->type = new \PhpParser\Node\Name($this->value_type);
            }
        }

        $node->setAttribute('comments', []);
    }
} // AuxNodeVisitor

class PhpDocParser
{
    protected int $foundGlobalVars = 0;
    protected int $foundConstants = 0;
    protected int $foundFunction = 0;
    protected int $foundClasses = 0;
    protected int $foundInterfaces = 0;
    protected int $foundTraits = 0;

    protected int $foundSourceFilesTotal = 0;
    protected int $foundNamespacesTotal = 0;
    protected int $foundGlobalVarsTotal = 0;
    protected int $foundConstantsTotal = 0;
    protected int $foundFunctionTotal = 0;
    protected int $foundClassesTotal = 0;
    protected int $foundInterfacesTotal = 0;
    protected int $foundTraitsTotal = 0;

    protected string $current_source_file;
    protected string $current_namespace;
    protected string $current_package;

    // Do not dup this, it is recursive by ref
    protected array $dictionary;

    protected \phpDocumentor\Reflection\DocBlockFactory $docblock_factory;

    protected function getPhpStatement(&$stmt, $value_type, $params)
    {
        $prettyPrinter = new \PhpParser\PrettyPrinter\Standard();
        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor(new AuxNodeVisitor($value_type, $params));

        return $prettyPrinter->prettyPrint($traverser->traverse([$stmt]));
    } // getPhpStatement

    protected function injectChild(&$object_descriptor, $object_type)
    {
        if (!empty($this->dictionary[$object_type][$object_descriptor["full_name"]])) {
            $read_name = $this->getNameByType($object_descriptor["type"]);

            PhpDocWatcher::trackError("The $read_name '" . $object_descriptor["name"] . "' defined in:\n" .
                "\nFile: " . $object_descriptor["source_file"]["full_name"] . ", line: " . $object_descriptor["start_line"] .
                "\nhas another definition in\n" .
                "\nFile: " . $this->dictionary[$object_type][$object_descriptor["full_name"]]["source_file"]["full_name"] . ", line: " . $this->dictionary[$object_type][$object_descriptor["full_name"]]["start_line"]
            );

            return;
        }

        switch ($object_type) {
            case "global_variables":
                $this->foundGlobalVars++;
                $this->foundGlobalVarsTotal++;
                break;

            case "constants":
                $this->foundConstants++;
                $this->foundConstantsTotal++;
                break;

            case "functions":
                $this->foundFunction++;
                $this->foundFunctionTotal++;
                break;

            case "classes":
                $this->foundClasses++;
                $this->foundClassesTotal++;
                break;

            case "interfaces":
                $this->foundInterfaces++;
                $this->foundInterfacesTotal++;
                break;

            case "traits":
                $this->foundTraits++;
                $this->foundTraitsTotal++;
                break;
        }

        $this->dictionary[$object_type][$object_descriptor["full_name"]] = &$object_descriptor;

        if ($object_type != "global_variables") {
            $object_descriptor["namespace"] = &$this->dictionary["namespaces"][$this->current_namespace];
            $this->dictionary["namespaces"][$this->current_namespace]["children"][$object_type][$object_descriptor["full_name"]] = &$object_descriptor;
        }

        $object_descriptor["package"] = &$this->dictionary["packages"][$this->current_package];
        $this->dictionary["packages"][$this->current_package]["children"][$object_type][$object_descriptor["full_name"]] = &$object_descriptor;

        $this->dictionary["lookup"][$object_descriptor["full_name"]] = &$object_descriptor;
    } // injectChild

    protected function analyze(&$stmts): void
    {
        foreach ($stmts as &$stmt) {
            switch ($stmt->getType()) {
                case "Stmt_Namespace":
                    $this->analyzeNamespase($stmt);
                    break;

                case "Stmt_Use":
                    $this->analyzeUseExpression($stmt);
                    break;

                case "Stmt_GroupUse":
                    $this->analyzeGroupUseExpression($stmt);
                    break;

                case "Stmt_Const":
                    $this->analyzeConstExpression($stmt);
                    break;

                case "Stmt_Expression":
                    $this->analyzeStmtExpression($stmt);
                    break;

                case "Stmt_Function":
                    $this->analyzeFunctionExpression($stmt);
                    break;

                case "Stmt_Interface":
                    $this->analyzeInterfaceExpression($stmt);
                    break;

                case "Stmt_Class":
                    $this->analyzeClassExpression($stmt);
                    break;

                case "Stmt_Trait":
                    $this->analyzeTraitExpression($stmt);
                    break;

                default:
                    break;
            }
        }
    } // analyze

    protected function analyzeStmtExpression(&$stmt)
    {
        if ($stmt->expr->getType() != "Expr_Assign") {
            return;
        }

        if ($stmt->expr->var->getType() != "Expr_Variable") {
            return;
        }

        $object_name = "$" . $stmt->expr->var->name;

        $object_descriptor = [];
        $object_descriptor["type"] = "global_variable";

        $object_descriptor["name"] = $object_name;
        $object_descriptor["short_name"] = $object_name;
        $object_descriptor["full_name"] = $object_name;

        $object_descriptor["source_file"] = &$this->dictionary["source_files"][$this->current_source_file];
        $object_descriptor["start_line"] = $stmt->getLine();

        $object_descriptor["docblock"] = $stmt->getDocComment() ? $stmt->getDocComment()->getText() : "";
        $object_descriptor["stmt"] = &$stmt;

        $this->injectChild($object_descriptor, "global_variables");
    } // analyzeStmtExpression

    protected function analyzeClassExpression(&$stmt)
    {
        $object_name = (string)$stmt->name->name;

        $object_descriptor = [];

        $object_descriptor["type"] = "class";
        $object_descriptor["flags"] = $stmt->flags;

        $object_descriptor["name"] = $object_name;
        $object_descriptor["short_name"] = $object_name;
        $object_descriptor["full_name"] = rtrim($this->current_namespace, "\\") . "\\" . $object_name;

        $object_descriptor["source_file"] = &$this->dictionary["source_files"][$this->current_source_file];
        $object_descriptor["start_line"] = $stmt->getLine();

        if (!empty($stmt->extends)) {
            $object_descriptor["extends"][] = $stmt->extends->toCodeString();
        }

        if (!empty($stmt->implements)) {
            foreach ($stmt->implements as $implement) {
                $object_descriptor["implements"][] = $implement->toCodeString();
            }
        }

        $object_descriptor["docblock"] = $stmt->getDocComment() ? $stmt->getDocComment()->getText() : "";

        $object_descriptor["properties"] = [];
        $object_descriptor["constants"] = [];
        $object_descriptor["methods"] = [];

        $this->injectChild($object_descriptor, "classes");

        if (empty($stmt->stmts)) {
            return;
        }

        // const and methods

        foreach ($stmt->stmts as &$substmt) {
            switch ($substmt->getType()) {
                case "Stmt_ClassConst":
                    $this->analyzeClassConstExpression($substmt, $object_descriptor);
                    break;

                case "Stmt_ClassMethod":
                    $this->analyzeClassMethodExpression($substmt, $object_descriptor);
                    break;

                case "Stmt_Property":
                    $this->analyzeClassPropertyExpression($substmt, $object_descriptor);
                    break;

                case "Stmt_TraitUse":
                    $this->analyzeClassUseTraitExpression($substmt, $object_descriptor);
                    break;
            }
        }
    } // analyzeClassExpression

    protected function analyzeTraitExpression(&$stmt)
    {
        $object_name = (string)$stmt->name->name;

        $object_descriptor = [];

        $object_descriptor["type"] = "trait";

        $object_descriptor["name"] = $object_name;
        $object_descriptor["short_name"] = $object_name;
        $object_descriptor["full_name"] = rtrim($this->current_namespace, "\\") . "\\" . $object_name;

        $object_descriptor["source_file"] = &$this->dictionary["source_files"][$this->current_source_file];
        $object_descriptor["start_line"] = $stmt->getLine();

        if (!empty($stmt->extends)) {
            $object_descriptor["extends_names_only"][] = $stmt->extends->toCodeString();
        }

        if (!empty($stmt->implements)) {
            foreach ($stmt->implements as $implement) {
                $object_descriptor["implements_names_only"][] = $implement->toCodeString();
            }
        }

        $object_descriptor["docblock"] = $stmt->getDocComment() ? $stmt->getDocComment()->getText() : "";

        $object_descriptor["constants"] = [];
        $object_descriptor["methods"] = [];

        $this->injectChild($object_descriptor, "traits");

        if (empty($stmt->stmts)) {
            return;
        }

        // const and methods

        foreach ($stmt->stmts as &$substmt) {
            switch ($substmt->getType()) {
                case "Stmt_ClassConst":
                    $this->analyzeClassConstExpression($substmt, $object_descriptor);
                    break;

                case "Stmt_ClassMethod":
                    $this->analyzeClassMethodExpression($substmt, $object_descriptor);
                    break;

                case "Stmt_Property":
                    $this->analyzeClassPropertyExpression($substmt, $object_descriptor);
                    break;
            }
        }
    } // analyzeTraitExpression

    protected function analyzeInterfaceExpression(&$stmt)
    {
        $object_name = (string)$stmt->name->name;

        $object_descriptor = [];

        $object_descriptor["type"] = "interface";

        $object_descriptor["name"] = $object_name;
        $object_descriptor["short_name"] = $object_name;
        $object_descriptor["full_name"] = rtrim($this->current_namespace, "\\") . "\\" . $object_name;

        $object_descriptor["source_file"] = &$this->dictionary["source_files"][$this->current_source_file];
        $object_descriptor["start_line"] = $stmt->getLine();

        if (!empty($stmt->extends)) {
            foreach ($stmt->extends as $extend) {
                $object_descriptor["extends"][] = $extend->toCodeString();
            }
        }

        $object_descriptor["docblock"] = $stmt->getDocComment() ? $stmt->getDocComment()->getText() : "";

        $object_descriptor["constants"] = [];
        $object_descriptor["methods"] = [];

        $this->injectChild($object_descriptor, "interfaces");

        if (empty($stmt->stmts)) {
            return;
        }

        // const and methods

        foreach ($stmt->stmts as &$substmt) {
            switch ($substmt->getType()) {
                case "Stmt_ClassConst":
                    $this->analyzeClassConstExpression($substmt, $object_descriptor);
                    break;

                case "Stmt_ClassMethod":
                    $this->analyzeClassMethodExpression($substmt, $object_descriptor);
                    break;
            }
        }
    } // analyzeInterfaceExpression

    protected function analyzeClassMethodExpression(&$stmt, &$parent_descriptor)
    {
        $object_name = (string)$stmt->name->name;

        $object_descriptor = [];

        $object_descriptor["type"] = "method";
        $object_descriptor["flags"] = $stmt->flags;
        $object_descriptor["by_ref"] = !empty($stmt->byRef);

        $object_descriptor["name"] = $parent_descriptor["name"] . "::" . $object_name . "()";
        $object_descriptor["short_name"] = $object_name . "()";
        $object_descriptor["full_name"] = $parent_descriptor["full_name"] . "::" . $object_name . "()";

        $object_descriptor["stmt"] = &$stmt;

        if ($stmt->returnType) {
            $rtype = $stmt->returnType->getType();
            
            if ($rtype == "Identifier") {
                $object_descriptor["return_type"] = (string)$stmt->returnType->name;
            } elseif ($rtype == "NullableType") {
                $object_descriptor["return_type"] = "null";
            } elseif ($rtype == "Name") {
                $object_descriptor["return_type"] = (string)$stmt->returnType;
            } elseif ($rtype == "UnionType") {
                $object_descriptor["return_type"] = "";
                foreach ($stmt->returnType->types as $type) {
                    $object_descriptor["return_type"] .= $type . "|";
                }

                $object_descriptor["return_type"] = trim($object_descriptor["return_type"] ?? "", "|");
            }

            $object_descriptor["return_type"] = trim($object_descriptor["return_type"] ?? "");
        }

        $object_descriptor["params"] = [];
        if (!empty($stmt->params)) {
            foreach ($stmt->params as &$param) {
                $param_data = [];
                $param_data["name"] = "$" . $param->var->name;
                $param_data["stmt"] = &$param;

                if ($param->type) {
                    $vtype = $param->type->getType();

                    if ($vtype == "Identifier") {
                        $param_data["val_type"] = (string)$param->type->name;
                    } elseif ($vtype == "NullableType") {
                        $param_data["val_type"] = "null";
                    } elseif ($vtype == "Name") {
                        $param_data["val_type"] = (string)$param->type;
                    } elseif ($vtype == "UnionType") {
                        $param_data["val_type"] = "";
                        foreach ($param->type->types as $type) {
                            $param_data["val_type"] .= $type . "|";
                        }

                        $param_data["val_type"] = trim($param_data["val_type"], "|");
                    }
                }

                $param_data["by_ref"] = !empty($param->byRef);
                $param_data["variadic"] = !empty($param->variadic);

                $object_descriptor["params"][$param_data["name"]] = &$param_data;

                unset($param_data);
            }
        }

        $object_descriptor["source_file"] = &$this->dictionary["source_files"][$this->current_source_file];
        $object_descriptor["start_line"] = $stmt->getLine();

        $object_descriptor["docblock"] = $stmt->getDocComment() ? $stmt->getDocComment()->getText() : "";

        $object_descriptor["parent"] = &$parent_descriptor;
        $parent_descriptor["methods"][$object_descriptor["full_name"]] = &$object_descriptor;

        $this->dictionary["lookup"][$object_descriptor["full_name"]] = &$object_descriptor;
    } // analyzeClassMethodExpression

    protected function analyzeClassUseTraitExpression(&$stmt, &$parent_descriptor)
    {
        foreach ($stmt->traits as $trait) {
            $parent_descriptor["uses_traits"][] = $trait->toCodeString();
        }
    } // analyzeClassUseTraitExpression

    protected function analyzeClassPropertyExpression(&$stmt, &$parent_descriptor)
    {
        if (empty($stmt->props)) {
            return;
        }

        $common_docblock = $stmt->getDocComment() ? $stmt->getDocComment()->getText() : "";

        $val_type = "";
        if ($stmt->type) {
            $vtype = $stmt->type->getType();

            if ($vtype == "Identifier") {
                $val_type = (string)$stmt->type->name;
            } elseif ($vtype == "NullableType") {
                $val_type = "null";
            } elseif ($vtype == "Name") {
                $val_type = (string)$stmt->type;
            } elseif ($vtype == "UnionType") {
                $val_type = "";
                foreach ($stmt->type->types as $type) {
                    $val_type .= $type . "|";
                }

                $val_type = trim($val_type, "|");
            }
        }

        $flags = $stmt->flags;

        foreach ($stmt->props as &$prop) {
            $object_name = "$" . $prop->name->name;

            $object_descriptor = [];
            $object_descriptor["type"] = "property";
            $object_descriptor["flags"] = $flags;

            $object_descriptor["name"] = $parent_descriptor["name"] . "::" . $object_name;
            $object_descriptor["short_name"] = $object_name;
            $object_descriptor["full_name"] = $parent_descriptor["full_name"] . "::" . $object_name;

            $object_descriptor["val_type"] = $val_type;

            $object_descriptor["source_file"] = &$this->dictionary["source_files"][$this->current_source_file];
            $object_descriptor["start_line"] = $prop->getLine();

            $object_descriptor["docblock"] = $prop->getDocComment() ? $prop->getDocComment()->getText() : "";
            if (empty($object_descriptor["docblock"])) {
                $object_descriptor["docblock"] = $common_docblock;
            }

            $object_descriptor["stmt"] = &$prop;
            $object_descriptor["parent_stmt"] = &$stmt;

            $object_descriptor["parent"] = &$parent_descriptor;
            $parent_descriptor["properties"][$object_descriptor["full_name"]] = &$object_descriptor;

            $this->dictionary["lookup"][$object_descriptor["full_name"]] = &$object_descriptor;

            // this is important to prevent reusing the same reference
            unset($object_descriptor);
        } // foreach prop
    } // analyzeClassPropertyExpression

    protected function analyzeClassConstExpression(&$stmt, &$parent_descriptor)
    {
        if (empty($stmt->consts)) {
            return;
        }

        $common_docblock = $stmt->getDocComment() ? $stmt->getDocComment()->getText() : "";
        $flags = $stmt->flags;

        foreach ($stmt->consts as &$const) {
            $object_name = (string)$const->name->name;

            $object_descriptor = [];
            $object_descriptor["type"] = "class_constant";
            $object_descriptor["flags"] = $flags;

            $object_descriptor["name"] = $parent_descriptor["name"] . "::" . $object_name;
            $object_descriptor["short_name"] = $object_name;
            $object_descriptor["full_name"] = $parent_descriptor["full_name"] . "::" . $object_name;

            $object_descriptor["source_file"] = &$this->dictionary["source_files"][$this->current_source_file];
            $object_descriptor["start_line"] = $const->getLine();

            $object_descriptor["docblock"] = $const->getDocComment() ? $const->getDocComment()->getText() : "";
            if (empty($object_descriptor["docblock"])) {
                $object_descriptor["docblock"] = $common_docblock;
            }

            $object_descriptor["stmt"] = &$const;

            $object_descriptor["parent"] = &$parent_descriptor;
            $parent_descriptor["constants"][$object_descriptor["full_name"]] = &$object_descriptor;

            $this->dictionary["lookup"][$object_descriptor["full_name"]] = &$object_descriptor;

            // this is important to prevent reusing the same reference
            unset($object_descriptor);
        } // foreach const
    } // analyzeClassConstExpression

    protected function analyzeFunctionExpression(&$stmt)
    {
        $object_name = (string)$stmt->name->name;

        $object_descriptor = [];

        $object_descriptor["type"] = "function";
        $object_descriptor["by_ref"] = !empty($stmt->byRef);

        $object_descriptor["name"] = $object_name . "()";
        $object_descriptor["short_name"] = $object_name . "()";
        $object_descriptor["full_name"] = rtrim($this->current_namespace, "\\") . "\\" . $object_name . "()";

        if ($stmt->returnType) {
            if ($stmt->returnType->getType() == "Identifier") {
                $object_descriptor["return_type"] = (string)$stmt->returnType->name;
            } elseif ($stmt->returnType->getType() == "NullableType") {
                $object_descriptor["return_type"] = "null";
            } elseif ($stmt->returnType->getType() == "Name") {
                $object_descriptor["return_type"] = (string)$stmt->returnType;
            } elseif ($stmt->returnType->getType() == "UnionType") {
                $return_type = "";
                foreach ($stmt->returnType->types as $type) {
                    $return_type .= $type . "|";
                }
                $return_type = trim($return_type, "|");

                $object_descriptor["return_type"] = $return_type;
            }

            $object_descriptor["return_type"] = trim($object_descriptor["return_type"] ?? "");
        }

        $object_descriptor["params"] = [];
        if (!empty($stmt->params)) {
            foreach ($stmt->params as &$param) {
                $param_data = [];
                $param_data["name"] = "$" . $param->var->name;

                $param_data["stmt"] = &$param;

                if ($param->type) {
                    $vtype = $param->type->getType();

                    if ($vtype == "Identifier") {
                        $param_data["val_type"] = (string)$param->type->name;
                    } elseif ($vtype == "NullableType") {
                        $param_data["val_type"] = "null";
                    } elseif ($vtype == "Name") {
                        $param_data["val_type"] = (string)$param->type;
                    } elseif ($vtype == "UnionType") {
                        $param_data["val_type"] = "";
                        foreach ($param->type->types as $type) {
                            $param_data["val_type"] .= $type . "|";
                        }

                        $param_data["val_type"] = trim($param_data["val_type"], "|");
                    }
                }

                $param_data["by_ref"] = !empty($param->byRef);
                $param_data["variadic"] = !empty($param->variadic);

                $object_descriptor["params"][$param_data["name"]] = &$param_data;

                unset($param_data);
            }
        }

        $object_descriptor["source_file"] = &$this->dictionary["source_files"][$this->current_source_file];
        $object_descriptor["start_line"] = $stmt->getLine();

        $object_descriptor["docblock"] = $stmt->getDocComment() ? $stmt->getDocComment()->getText() : "";
        $object_descriptor["stmt"] = &$stmt;

        $this->injectChild($object_descriptor, "functions");
    } // analyzeFunctionExpression

    protected function analyzeGroupUseExpression(&$stmt)
    {
        if (empty($stmt->uses)) {
            return;
        }

        $prefix = $stmt->prefix->toCodeString();

        foreach ($stmt->uses as $use) {
            $full_path = "\\" . $prefix . "\\" . $use->name->toCodeString();

            $this->dictionary["source_files"][$this->current_source_file]["uses_map"][$full_path] = $full_path;
        }
    } // analyzeGroupUseExpression

    protected function analyzeUseExpression(&$stmt)
    {
        if (empty($stmt->uses)) {
            return;
        }

        foreach ($stmt->uses as $use) {
            $full_path = "\\" . $use->name->toCodeString();

            if ($use->alias) {
                $alias = $use->alias->name;
            } else {
                $alias = $this->removeNamespacePrefix($full_path);
            }

            $this->dictionary["source_files"][$this->current_source_file]["uses_map"][$alias] = $full_path;
        }
    } // analyzeUseExpression

    protected function analyzeConstExpression(&$stmt)
    {
        if (empty($stmt->consts)) {
            return;
        }

        $common_docblock = $stmt->getDocComment() ? $stmt->getDocComment()->getText() : "";

        foreach ($stmt->consts as &$const) {
            $object_name = (string)$const->name->name;

            $object_descriptor = [];
            $object_descriptor["type"] = "constant";

            $object_descriptor["name"] = $object_name;
            $object_descriptor["short_name"] = $object_name;
            $object_descriptor["full_name"] = rtrim($this->current_namespace, "\\") . "\\" . $object_name;

            $object_descriptor["source_file"] = &$this->dictionary["source_files"][$this->current_source_file];
            $object_descriptor["start_line"] = $const->getLine();

            $object_descriptor["docblock"] = $const->getDocComment() ? $const->getDocComment()->getText() : "";
            if (empty($object_descriptor["docblock"])) {
                $object_descriptor["docblock"] = $common_docblock;
            }

            $object_descriptor["stmt"] = &$const;

            $this->injectChild($object_descriptor, "constants");

            // this is important to prevent reusing the same reference
            unset($object_descriptor);
        } // foreach const
    } // analyzeConstExpression

    protected function &addNamespace($full_name)
    {
        $this->foundNamespacesTotal++;

        $object_descriptor = [];

        $object_descriptor["type"] = "namespace";
        $object_descriptor["name"] = trim($full_name, "\\");
        $object_descriptor["short_name"] = trim($full_name, "\\");
        $object_descriptor["full_name"] = $full_name;
        $object_descriptor["children"] = [];
        $object_descriptor["children"]["namespaces"] = [];
        $object_descriptor["children"]["constants"] = [];
        $object_descriptor["children"]["functions"] = [];
        $object_descriptor["children"]["interfaces"] = [];
        $object_descriptor["children"]["classes"] = [];
        $object_descriptor["children"]["traits"] = [];

        $this->dictionary["namespaces"][$object_descriptor["full_name"]] = &$object_descriptor;

        $this->dictionary["lookup"][$object_descriptor["full_name"]] = &$object_descriptor;

        return $object_descriptor;
    } // addNamespace

    protected function analyzeNamespase(&$stmt)
    {
        $object_name = "\\" . $stmt->name->toCodeString();
        $this->current_namespace = $object_name;

        if (empty($this->dictionary["namespaces"][$object_name])) {
            $this->addNamespace($object_name);
        }

        if (!empty($stmt->stmts)) {
            $this->analyze($stmt->stmts);
        }
    } // analyzeNamespase

    protected function parseFile($code, $source_file): array
    {
        $this->foundGlobalVars = 0;
        $this->foundConstants = 0;
        $this->foundFunction = 0;
        $this->foundClasses = 0;
        $this->foundInterfaces = 0;
        $this->foundTraits = 0;

        $this->current_source_file = $source_file;
        $this->current_namespace = "\\";
        $this->current_package = "";

        if (empty($this->dictionary["namespaces"][$this->current_namespace])) {
            $object_descriptor = [];
            $object_descriptor["type"] = "namespace";
            $object_descriptor["name"] = "\\";
            $object_descriptor["short_name"] = "\\";
            $object_descriptor["full_name"] = "\\";
            $object_descriptor["children"] = [];
            $object_descriptor["children"]["namespaces"] = [];
            $object_descriptor["children"]["constants"] = [];
            $object_descriptor["children"]["functions"] = [];
            $object_descriptor["children"]["interfaces"] = [];
            $object_descriptor["children"]["classes"] = [];
            $object_descriptor["children"]["traits"] = [];

            $this->dictionary["namespaces"][$object_descriptor["full_name"]] = $object_descriptor;
        }

        $object_descriptor = [];
        $object_descriptor["type"] = "source_file";
        $object_descriptor["name"] = basename($source_file);
        $object_descriptor["short_name"] = basename($source_file);
        $object_descriptor["full_name"] = $source_file;
        $object_descriptor["relative_path"] = $source_file;
        $object_descriptor["source_file"] = &$object_descriptor; // for unification - each object must have a source file
        $object_descriptor["start_line"] = 1;
        $object_descriptor["uses_map"] = [];

        $dblocks = array_filter(token_get_all($code), function ($item) {
            return $item[0] == T_DOC_COMMENT;
        });
        $dblock = reset($dblocks);
        if (!empty($dblock)) {
            $object_descriptor["docblock"] = $dblock[1];
        }

        if (!empty($object_descriptor["docblock"])) {
            $docblock = $this->docblock_factory->create($object_descriptor["docblock"]);

            if ($docblock->hasTag("package")) {
                $tags = $docblock->getTagsByName("package");
                foreach ($tags as $tag) {
                    $this->current_package = trim($tag->getDescription());
                }
            }
        }

        if (empty($this->dictionary["packages"][$this->current_package])) {
            $package_descriptor = [];
            $package_descriptor["type"] = "package";
            $package_descriptor["name"] = $this->current_package;
            $package_descriptor["short_name"] = $this->current_package;
            $package_descriptor["full_name"] = $this->current_package;
            $package_descriptor["children"] = [];
            $package_descriptor["children"]["constants"] = [];
            $package_descriptor["children"]["global_variables"] = [];
            $package_descriptor["children"]["functions"] = [];
            $package_descriptor["children"]["interfaces"] = [];
            $package_descriptor["children"]["classes"] = [];
            $package_descriptor["children"]["traits"] = [];

            $this->dictionary["packages"][$package_descriptor["full_name"]] = $package_descriptor;

            if (!empty($package_descriptor["full_name"])) {
                $this->dictionary["lookup"][$package_descriptor["full_name"]] = &$package_descriptor;
            }
        }

        $this->dictionary["source_files"][$object_descriptor["full_name"]] = $object_descriptor;

        // parsing

        // We need this approach, otherwise the docblocks by subparts of
        // some compelx statements disappear!
        $lexer = new \PhpParser\Lexer\Emulative();
        $parser = new \PhpParser\Parser\Php8($lexer);

        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor(new \PhpParser\NodeVisitor\CloningVisitor());
        $stmts = $parser->parse($code);
        $stmts = $traverser->traverse($stmts);

        $this->analyze($stmts);

        ksort($this->dictionary);

        return $this->dictionary;
    } // parseFile

    protected function processDir($dir, $relative_path)
    {
        $dir = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR;;

        if (!file_exists($dir)) {
            throw new \Exception("The directory '$dir' does not exists!");
        }

        echo_highlighted("\n\nProcessing path: " . (empty($relative_path) ? "." : $relative_path));

        $files = scandir($dir);
        if ($files) {
            sort($files);

            foreach ($files as $file) {
                if ($file == "." || $file == "..") {
                    continue;
                }

                $sub_relative_path = $relative_path;

                if (!empty($sub_relative_path)) {
                    $sub_relative_path .= "/";
                }

                $sub_relative_path .= $file;

                if (pathinfo($dir . $file, PATHINFO_EXTENSION) == "php") {
                    $this->processFile($dir . $file, $sub_relative_path);
                }
            }

            foreach ($files as $file) {
                if ($file == "." || $file == "..") {
                    continue;
                }

                $sub_relative_path = $relative_path;

                if (!empty($sub_relative_path)) {
                    $sub_relative_path .= "/";
                }

                $sub_relative_path .= $file;

                if (is_dir($dir . $file)) {
                    $this->processDir($dir . $file, $sub_relative_path);
                }
            }
        }
    } // processDir

    protected function processFile($file, $relative_path)
    {
        $this->foundSourceFilesTotal++;

        echo_highlighted2("\n\nProcessing file: $relative_path\n");

        $code = file_get_contents($file);
        $this->parseFile($code, $relative_path);

        $somethingFound = false;
        if ($this->foundGlobalVars > 0) {
            $somethingFound = true;
            echo_standard("\nGlobal variables: " . $this->foundGlobalVars);
        }
        if ($this->foundConstants > 0) {
            $somethingFound = true;
            echo_standard("\nConstants: " . $this->foundConstants);
        }
        if ($this->foundFunction > 0) {
            $somethingFound = true;
            echo_standard("\nFunctions: " . $this->foundFunction);
        }
        if ($this->foundClasses > 0) {
            $somethingFound = true;
            echo_standard("\nClasses: " . $this->foundClasses);
        }
        if ($this->foundInterfaces > 0) {
            $somethingFound = true;
            echo_standard("\nInterfaces: " . $this->foundInterfaces);
        }
        if ($this->foundTraits > 0) {
            $somethingFound = true;
            echo_standard("\nTraits: " . $this->foundTraits);
        }

        if (!$somethingFound) {
            echo_standard("\nNothing found to document");
        }
    } // processFile

    function &process(&$config)
    {
        if (empty($config["source"])) {
            throw new \Exception("Source directory is not specified!");
        }

        $source_dir = rtrim($config["source"], "/\\");;

        echo_highlighted("\n\nProcessing source files from: " . $source_dir);

        $source_dir .= DIRECTORY_SEPARATOR;;

        $this->docblock_factory = \phpDocumentor\Reflection\DocBlockFactory::createInstance();

        $this->dictionary["namespaces"] = [];
        $this->dictionary["packages"] = [];
        $this->dictionary["lookup"] = [];
        $this->dictionary["constants"] = [];
        $this->dictionary["global_variables"] = [];
        $this->dictionary["functions"] = [];
        $this->dictionary["classes"] = [];
        $this->dictionary["interfaces"] = [];
        $this->dictionary["traits"] = [];
        $this->dictionary["source_files"] = [];
        $this->dictionary["todos"] = [];
        $this->dictionary["since"] = [];
        $this->dictionary["deprecated"] = [];

        $this->processDir($source_dir, "");

        $this->postProcess();

        echo_highlighted("\n\nProcessed source files: " . $this->foundSourceFilesTotal . "\n");

        $somethingFound = false;

        if ($this->foundNamespacesTotal > 0) {
            $somethingFound = true;
            echo_standard("\nNamespaces: " . $this->foundNamespacesTotal);
        }
        if ($this->foundGlobalVarsTotal > 0) {
            $somethingFound = true;
            echo_standard("\nGlobal variables: " . $this->foundGlobalVarsTotal);
        }
        if ($this->foundConstantsTotal > 0) {
            $somethingFound = true;
            echo_standard("\nConstants: " . $this->foundConstantsTotal);
        }
        if ($this->foundFunctionTotal > 0) {
            $somethingFound = true;
            echo_standard("\nFunctions: " . $this->foundFunctionTotal);
        }
        if ($this->foundClassesTotal > 0) {
            $somethingFound = true;
            echo_standard("\nClasses: " . $this->foundClassesTotal);
        }
        if ($this->foundInterfacesTotal > 0) {
            $somethingFound = true;
            echo_standard("\nInterfaces: " . $this->foundInterfacesTotal);
        }
        if ($this->foundTraitsTotal > 0) {
            $somethingFound = true;
            echo_standard("\nTraits: " . $this->foundTraitsTotal);
        }

        if (!$somethingFound) {
            echo_standard("\nNothing found to document");

            PhpDocWatcher::trackWarning("Nothing found to document!");
        }

        return $this->dictionary;
    } // process

    protected function postProcess()
    {
        $this->buildNamespaceHierarchy();

        $this->processDocBlocks();
    
        $this->sortObjectLists();
    } // postProcess

    protected function sortObjectLists()
    {
        foreach ($this->dictionary as $key => &$block) {
            if ($key == "source_files") {
                continue;
            }

            ksort($block);
        }

        $sort_function = function (&$a, &$b) {
            if ($a["name"] == $b["name"]) {
                return strcmp($a["namespace"]["name"], $b["namespace"]["name"]);
            }

            return strcmp($a["name"], $b["name"]);
        };

        ksort($this->dictionary["lookup"]);
        ksort($this->dictionary["constants"]);
        ksort($this->dictionary["global_variables"]);

        ksort($this->dictionary["namespaces"]);
        foreach ($this->dictionary["namespaces"] as &$namespace) {
            foreach ($namespace["children"] as &$children) {
                uasort($children, $sort_function);
            }
        }

        ksort($this->dictionary["packages"]);
        foreach ($this->dictionary["packages"] as $pk => &$package) {
            foreach ($package["children"] as $tp => &$children) {
                uasort($children, $sort_function);
            }
        }
    
        uasort($this->dictionary["todos"], $sort_function);
        uasort($this->dictionary["deprecated"], $sort_function);
        uasort($this->dictionary["since"], $sort_function);

        uasort($this->dictionary["functions"], $sort_function);

        uasort($this->dictionary["classes"], $sort_function);
        foreach ($this->dictionary["classes"] as &$class) {
            uasort($class["constants"], $sort_function);
            uasort($class["properties"], $sort_function);
            uasort($class["methods"], $sort_function);
        }

        uasort($this->dictionary["interfaces"], $sort_function);
        foreach ($this->dictionary["interfaces"] as &$interface) {
            uasort($interface["constants"], $sort_function);
            uasort($interface["methods"], $sort_function);
        }

        uasort($this->dictionary["traits"], $sort_function);
        foreach ($this->dictionary["traits"] as &$trait) {
            uasort($trait["constants"], $sort_function);
            uasort($trait["methods"], $sort_function);
        }
    } // sortObjectLists

    protected function removeNamespacePrefix($name)
    {
        return preg_replace("/.*\\\\/", "", $name);
    } // removeNamespacePrefix

    protected function getParentNamespace($name)
    {
        $parent_namespace = "";
        if (preg_match("/(.*)\\\\/", $name, $matches)) {
            $parent_namespace = $matches[1];
        }

        if (empty($parent_namespace)) {
            $parent_namespace = "\\";
        }

        return $parent_namespace;
    } // getParentNamespace

    protected function buildNamespaceHierarchy()
    {
        foreach ($this->dictionary["namespaces"] as &$namespace) {
            if ($namespace["full_name"] == "\\") {
                continue;
            }

            $parent_namespace = $this->getParentNamespace($namespace["full_name"]);

            if ($parent_namespace == "\\") {
                continue;
            }

            if (empty($this->dictionary["namespaces"][$parent_namespace])) {
                $parent_namespace_descriptor = &$this->addNamespace($parent_namespace);
            } else {
                $parent_namespace_descriptor = &$this->dictionary["namespaces"][$parent_namespace];
            }

            $parent_namespace_descriptor["children"]["namespaces"][$namespace["full_name"]] = &$namespace;
        }
    } // buildNamespaceHierarchy

    protected function processDocBlocks()
    {
        $types_to_process = ["source_files", "constants", "global_variables", "functions", "classes", "interfaces", "traits"];

        foreach ($types_to_process as $type_to_process) {
            if (empty($this->dictionary[$type_to_process])) {
                continue;
            }

            foreach ($this->dictionary[$type_to_process] as &$object_descriptor) {
                $this->processObjectDocBlock($object_descriptor);

                switch ($object_descriptor["type"]) {
                    case "class":
                        foreach ($object_descriptor["properties"] as &$property_descriptor) {
                            $this->processObjectDocBlock($property_descriptor);
                        }

                    case "interface":
                        foreach ($object_descriptor["constants"] as &$constant_descriptor) {
                            $this->processObjectDocBlock($constant_descriptor);
                        }

                    case "trait":
                        foreach ($object_descriptor["methods"] as &$method_descriptor) {
                            $this->processObjectDocBlock($method_descriptor);
                        }
                        break;
                }
            } // foreach object
        } // foreach type

        $types_to_process = ["classes", "interfaces"];

        foreach ($types_to_process as $type_to_process) {
            foreach ($this->dictionary[$type_to_process] as &$object_descriptor) {

                if (!empty($object_descriptor["properties"])) {
                    foreach ($object_descriptor["properties"] as &$property) {
                        $property["overrides"] = [];

                        $this->checkOverrides($object_descriptor, $property["short_name"], $property["overrides"], "properties");
                    }
                }

                if (!empty($object_descriptor["constants"])) {
                    foreach ($object_descriptor["constants"] as &$constant) {
                        $constant["overrides"] = [];

                        $this->checkOverrides($object_descriptor, $constant["short_name"], $constant["overrides"], "constants");
                    }
                }

                if (!empty($object_descriptor["methods"])) {
                    foreach ($object_descriptor["methods"] as &$method) {
                        $method["overrides"] = [];

                        $this->checkOverrides($object_descriptor, $method["short_name"], $method["overrides"], "methods");
                    }
                }
            } // foreach object
        } // foreach type
    } // processDocBlocks

    protected function getNameByType($type)
    {
        switch ($type) {
            case "source_file":
                $type = "source file";
                break;

            case "constant":
                $type = "constant";
                break;

            case "global_variable":
                $type = "global variable";
                break;

            case "class":
                $type = "class";
                break;

            case "interface":
                $type = "interface";
                break;

            case "trait":
                $type = "trait";
                break;

            case "method":
                $type = "method";
                break;

            case "property":
                $type = "property";
                break;

            case "class_constant":
                $type = "class constant";
                break;
        }

        return $type;
    } // getNameByType

    protected function processObjectDocBlock(&$object_descriptor)
    {
        $read_name = $this->getNameByType($object_descriptor["type"]);
        $appendix = "\nFile: " . $object_descriptor["source_file"]["full_name"] . ", line: " . $object_descriptor["start_line"];

        if (empty($object_descriptor["docblock"])) {
            PhpDocWatcher::trackWarning("The $read_name '" . $object_descriptor["name"] . "' has no doc block!" . $appendix);

            $this->resolveObjectTypeNames($object_descriptor);

            return;
        }

        $docblock = $this->docblock_factory->create($object_descriptor["docblock"]);

        $object_descriptor["summary"] = trim($docblock->getSummary());
        $object_descriptor["description"] = trim($docblock->getDescription());

        $tags = $docblock->getTags();

        foreach ($tags as $tag) {
            switch ($tag->getName()) {
                case "author":
                    $object_descriptor["authors"][] = [
                        "name" => $tag->getAuthorName(),
                        "email" => $tag->getEmail()
                    ];
                    break;

                case "copyright":
                    $object_descriptor["copyright"] = trim($tag->getDescription() ?: "");
                    break;

                case "deprecated":
                    $object_descriptor["deprecated_description"] = trim($tag->getDescription() ?: "");
                    $object_descriptor["deprecated_version"] = $tag->getVersion();

                    if (!empty($object_descriptor["deprecated_version"])) {
                        $this->dictionary["deprecated"][$object_descriptor["full_name"]] = &$object_descriptor;
                    }
                    break;

                case "ignore":
                    $object_descriptor["ignore"] = 1;
                    break;

                case "internal":
                    $object_descriptor["internal"] = trim($tag->getDescription() ?: "");
                    break;

                case "license":
                    $object_descriptor["license"] = trim($tag->getDescription() ?: "");
                    break;

                case "link":
                    $object_descriptor["links"][] = [
                        "url" => $tag->getLink(),
                        "description" => $tag->getDescription()
                    ];
                    break;

                case "version":
                    $object_descriptor["version_description"] = trim($tag->getDescription() ?: "");
                    $object_descriptor["version"] = $tag->getVersion();
                    break;

                case "return":
                    if (!in_array($object_descriptor["type"], ["function", "method"])) {
                        PhpDocWatcher::trackWarning("The tag @return is inappropriate for the $read_name '" . $object_descriptor["name"] . "'!" . $appendix);
                        return;
                    }

                    $object_descriptor["return_description"] = trim($tag->getDescription() ?: "");
                    $object_descriptor["return_type"] = trim((string)$tag->getType());
                    break;

                case "since":
                    $object_descriptor["since"][] = [
                        "version" => $tag->getVersion(),
                        "description" => $tag->getDescription()
                    ];

                    $this->dictionary["since"][$object_descriptor["full_name"]] = &$object_descriptor;
                    break;

                case "see":
                    $object_descriptor["see"][] = [
                        "reference" => $tag->getReference(),
                        "description" => $tag->getDescription()
                    ];
                    break;

                case "throws":
                    if (!in_array($object_descriptor["type"], ["function", "method"])) {
                        PhpDocWatcher::trackWarning("The tag @throws is inappropriate for the $read_name '" . $object_descriptor["name"] . "'!" . $appendix);
                        continue 2;
                    }

                    $object_descriptor["throws"][] = [
                        "type" => trim((string)$tag->getType()),
                        "description" => $tag->getDescription()
                    ];
                    break;

                case "todo":
                    $object_descriptor["todos"][] = [
                        "description" => $tag->getDescription()
                    ];

                    $this->dictionary["todos"][$object_descriptor["full_name"]] = &$object_descriptor;
                    break;

                case "var":
                    if (!in_array($object_descriptor["type"], ["global_variable", "constant", "class_constant", "property"])) {
                        PhpDocWatcher::trackWarning("The tag @var is inappropriate for the $read_name '" . $object_descriptor["name"] . "'!" . $appendix);
                        continue 2;
                    }

                    $object_descriptor["val_type"] = trim((string)$tag->getType());

                    $description = trim($tag->getDescription());
                    if (empty($object_descriptor["description"])) {
                        $object_descriptor["description"] = $description;
                    } else {
                        if (!empty($description)) {
                            PhpDocWatcher::trackWarning("The description at the tag @var is redundant for the $read_name '" . $object_descriptor["name"] . "', because it has already a description at the docblock!" . $appendix);
                        }
                    }
                    break;

                case "uses":
                    if (in_array($object_descriptor["type"], ["source_file"])) {
                        PhpDocWatcher::trackWarning("The tag @uses is inappropriate for the $read_name '" . $object_descriptor["name"] . "'!" . $appendix);
                        continue 2;
                    }

                    $object_descriptor["uses"][] = [
                        "reference" => $tag->getReference(),
                        "description" => $tag->getDescription()
                    ];
                    break;

                case "param":
                    if (!in_array($object_descriptor["type"], ["function", "method"])) {
                        PhpDocWatcher::trackWarning("The tag @param is inappropriate for the $read_name '" . $object_descriptor["name"] . "'!" . $appendix);
                        return;
                    }

                    $name = $tag->getVariableName();
                    if (empty($name)) {
                        PhpDocWatcher::trackWarning("The $read_name '" . $object_descriptor["name"] . "' has a parameter tag without a name!" . $appendix);
                        continue 2;
                    }

                    $name = "$" . $tag->getVariableName();

                    if (empty($object_descriptor["params"][$name])) {
                        PhpDocWatcher::trackWarning("The $read_name '" . $object_descriptor["name"] . "' has no parameter with the name '$name'!" . $appendix);
                        continue 2;
                    }

                    if ($object_descriptor["params"][$name]["variadic"] && !$tag->isVariadic()) {
                        PhpDocWatcher::trackWarning("The parameter '" . $name . "' of the $read_name '" . $object_descriptor["name"] . "' is declared as variadic but documented as non variadic!" . $appendix);
                    }

                    if (!$object_descriptor["params"][$name]["variadic"] && $tag->isVariadic()) {
                        PhpDocWatcher::trackWarning("The parameter '" . $name . "' of the $read_name '" . $object_descriptor["name"] . "' is declared as not variadic but documented as variadic!" . $appendix);
                    }

                    if ($object_descriptor["params"][$name]["by_ref"] && !$tag->isReference()) {
                        PhpDocWatcher::trackWarning("The parameter '" . $name . "' of the $read_name '" . $object_descriptor["name"] . "' is declared as passed by reference but documented as passed by value!" . $appendix);
                    }

                    if (!$object_descriptor["params"][$name]["by_ref"] && $tag->isReference()) {
                        PhpDocWatcher::trackWarning("The parameter '" . $name . "' of the $read_name '" . $object_descriptor["name"] . "' is declared as passed by value but documented as passed by reference!" . $appendix);
                    }

                    $object_descriptor["params"][$name]["name"] = $name;
                    $object_descriptor["params"][$name]["val_type"] = trim((string)$tag->getType());
                    $object_descriptor["params"][$name]["description"] = trim($tag->getDescription());
                    $object_descriptor["params"][$name]["variadic"] = $tag->isVariadic();
                    $object_descriptor["params"][$name]["by_ref"] = $tag->isReference();

                    break;
            } // switch
        } // foreach

        $this->checkObjectDocCompleteness($object_descriptor);

        $this->resolveObjectTypeNames($object_descriptor);

        $this->checkDeadSeeReferences($object_descriptor);
    } // processObjectDocBlock

    protected function checkObjectDocCompleteness(&$object_descriptor)
    {
        $read_name = $this->getNameByType($object_descriptor["type"]);
        $appendix = "\nFile: " . $object_descriptor["source_file"]["full_name"] . ", line: " . $object_descriptor["start_line"];

        if (empty($object_descriptor["summary"]) && empty($object_descriptor["description"])) {
            PhpDocWatcher::trackWarning("The $read_name '" . $object_descriptor["name"] . "' has no description!" . $appendix);
        }

        switch ($object_descriptor["type"]) {
            case "constant":
            case "class_constant":
            case "property":
            case "global_variable":
                if (empty($object_descriptor["val_type"])) {
                    PhpDocWatcher::trackWarning("The type of the $read_name '" . $object_descriptor["name"] . "' is not specified!" . $appendix);
                }
                break;

            case "method":
            case "function":
                if (!($object_descriptor["type"] == "method" && ($object_descriptor["short_name"] == "__construct()" || $object_descriptor["short_name"] == "__destruct()")) &&
                    empty($object_descriptor["return_type"])) {
                    PhpDocWatcher::trackWarning("The return type of the $read_name '" . $object_descriptor["name"] . "' is not specified!" . $appendix);
                }

                foreach ($object_descriptor["params"] as $param) {
                    if (empty($param["val_type"])) {
                        PhpDocWatcher::trackWarning("The type of the parameter '" . $param["name"] . "' of the $read_name '" . $object_descriptor["name"] . "' is not specified!" . $appendix);
                    }

                    if (empty($param["description"])) {
                        PhpDocWatcher::trackWarning("The description of the parameter '" . $param["name"] . "' of the $read_name '" . $object_descriptor["name"] . "' is not specified!" . $appendix);
                    }
                }
                break;
        } // switch
    } // checkObjectDocCompleteness

    protected function checkDeadSeeReferences(&$object_descriptor)
    {
        $read_name = $this->getNameByType($object_descriptor["type"]);
        $appendix = "\nFile: " . $object_descriptor["source_file"]["full_name"] . ", line: " . $object_descriptor["start_line"];

        if (!empty($object_descriptor["summary"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["summary"]));
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The summary in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["description"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["description"]));
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The description in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["return_description"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["return_description"]));
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The description of the return value in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["copyright"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["copyright"]));
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The copyright text in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["license"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["license"]));
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The license text in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["deprecated_description"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["deprecated_description"]));
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The deprectation notice in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["version_description"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor["source_file"]["uses_map"], checkempty($object_descriptor["version_description"]));
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The version description in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["see"])) {
            foreach ($object_descriptor["see"] as $object_data) {
                if (empty($object_data["description"])) {
                    continue;
                }

                $dead_refs = $this->hasDeadSeeReferences($object_descriptor["source_file"]["uses_map"], checkempty($object_data["description"]));
                if (!empty($dead_refs)) {
                    PhpDocWatcher::trackWarning("The description of the tag @see in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
                }
            }
        }

        if (!empty($object_descriptor["uses"])) {
            foreach ($object_descriptor["uses"] as $object_data) {
                if (empty($object_data["description"])) {
                    continue;
                }

                $dead_refs = $this->hasDeadSeeReferences($object_descriptor["source_file"]["uses_map"], checkempty($object_data["description"]));
                if (!empty($dead_refs)) {
                    PhpDocWatcher::trackWarning("The description of the tag @uses in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
                }
            }
        }

        if (!empty($object_descriptor["since"])) {
            foreach ($object_descriptor["since"] as $object_data) {
                if (empty($object_data["description"])) {
                    continue;
                }

                $dead_refs = $this->hasDeadSeeReferences($object_descriptor["source_file"]["uses_map"], checkempty($object_data["description"]));
                if (!empty($dead_refs)) {
                    PhpDocWatcher::trackWarning("The description of the tag @since in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
                }
            }
        }

        if (!empty($object_descriptor["todos"])) {
            foreach ($object_descriptor["todos"] as $object_data) {
                if (empty($object_data["description"])) {
                    continue;
                }

                $dead_refs = $this->hasDeadSeeReferences($object_descriptor["source_file"]["uses_map"], checkempty($object_data["description"]));
                if (!empty($dead_refs)) {
                    PhpDocWatcher::trackWarning("The description of the tag @todo in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
                }
            }
        }

        if (!empty($object_descriptor["params"])) {
            foreach ($object_descriptor["params"] as $object_data) {
                if (empty($object_data["description"])) {
                    continue;
                }

                $dead_refs = $this->hasDeadSeeReferences($object_descriptor["source_file"]["uses_map"], checkempty($object_data["description"]));
                if (!empty($dead_refs)) {
                    PhpDocWatcher::trackWarning("The description of the parameter '$object_data[name]' in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
                }
            }
        }
    } // checkDeadSeeReferences

    protected function hasDeadSeeReferences(&$uses, $contents)
    {
        $dead_refs = [];

        $contents = preg_replace_callback("/\{@(see)\s+([^\s\{\}]+)(\s+.*?)?\}/sm", function ($matches) use (&$uses, &$dead_refs) {
            $full_name = $matches[2];
            if (!$this->resolveTypeName($uses, $full_name)) {
                $full_name = trim($full_name, "\\");
                $dead_refs[$full_name] = $full_name;
            }

            return $matches[0];
        }, $contents);

        return $dead_refs;
    } // hasDeadSeeReferences

    protected function resolveTypeName(&$uses, &$name)
    {
        $found = false;

        if (empty($name)) {
            $name = "";
            return $found;
        }

        $names = explode("|", $name);

        foreach ($names as &$nm) {
            foreach ($uses as $key => $full_name) {
                if (trim($key, "\\") == trim($nm, "\\")) {
                    $nm = $full_name;
                    $found = true;
                    break;
                }
            }

            foreach ($this->dictionary["lookup"] as $full_name => &$object_descriptor) {
                if (preg_match("/.*" . preg_p_escape("\\" . ltrim($nm, "\\")) . "$/", $full_name)) {
                    $nm = $full_name;
                    $found = true;
                    break;
                }
            }
        }

        $name = implode("|", $names);

        return $found;
    } // resolveTypeName

    protected function resolveObjectTypeNames(&$object_descriptor)
    {
        $read_name = $this->getNameByType($object_descriptor["type"]);
        $appendix = "\nFile: " . $object_descriptor["source_file"]["full_name"] . ", line: " . $object_descriptor["start_line"];

        if (!empty($object_descriptor["see"])) {
            foreach ($object_descriptor["see"] as &$see) {
                if (!$this->resolveTypeName($object_descriptor["source_file"]["uses_map"], $see["reference"])) {
                    PhpDocWatcher::trackWarning("The @see tag in the docblock of the $read_name '" . $object_descriptor["name"] . "' refers to non-existing object '$see[reference]'!" . $appendix);
                }
            }
        }

        if (!empty($object_descriptor["uses"])) {
            foreach ($object_descriptor["uses"] as &$use) {
                if (!$this->resolveTypeName($object_descriptor["source_file"]["uses_map"], $use["reference"])) {
                    PhpDocWatcher::trackWarning("The @uses tag in the docblock of the $read_name '" . $object_descriptor["name"] . "' refers to non-existing object '$use[reference]'!" . $appendix);
                }

                if (!empty($this->dictionary["lookup"][$use["reference"]])) {
                    $this->dictionary["lookup"][$use["reference"]]["used-by"][$object_descriptor["full_name"]] = [
                        "reference" => $object_descriptor["full_name"],
                        "description" => $use["description"]
                    ];
                }
            }
        }

        if (!empty($object_descriptor["throws"])) {
            foreach ($object_descriptor["throws"] as &$throw) {
                $this->resolveTypeName($object_descriptor["source_file"]["uses_map"], $throw["type"]);
            }
        }

        switch ($object_descriptor["type"]) {
            case "constant":
            case "class_constant":
                $this->resolveTypeName($object_descriptor["source_file"]["uses_map"], $object_descriptor["val_type"]);

                $object_descriptor["php_statement"] = "const " . trim($this->getPhpStatement($object_descriptor["stmt"], "", [])) . ";";
                break;

            case "property":
                $this->resolveTypeName($object_descriptor["source_file"]["uses_map"], $object_descriptor["val_type"]);

                $php_statement = trim($this->getPhpStatement($object_descriptor["parent_stmt"], $object_descriptor["val_type"], []));
                $prefix = "";
                if (preg_match("/^([^\$]*).+/", $php_statement, $matches)) {
                    $prefix = $matches[1];
                }

                $object_descriptor["php_statement"] = $prefix . trim($this->getPhpStatement($object_descriptor["stmt"], "", [])) . ";";
                break;

            case "global_variable":
                $this->resolveTypeName($object_descriptor["source_file"]["uses_map"], $object_descriptor["val_type"]);

                $object_descriptor["php_statement"] = trim($this->getPhpStatement($object_descriptor["stmt"], "", []));
                break;

            case "method":
            case "function":
                $this->resolveTypeName($object_descriptor["source_file"]["uses_map"], $object_descriptor["return_type"]);

                $param_map = [];

                foreach ($object_descriptor["params"] as &$param) {
                    $this->resolveTypeName($object_descriptor["source_file"]["uses_map"], $param["val_type"]);

                    if (!empty($param["stmt"]) && !empty($param["stmt"]->default)) {
                        $orig = $param["stmt"]->default->getAttribute("origNode");
                        if ($orig) {
                            $param["default_value"] = $this->getPhpStatement($orig, "", []);
                        }
                    }

                    $param_map[$param["name"]] = $param["val_type"];
                }

                $object_descriptor["php_statement"] = trim($this->getPhpStatement($object_descriptor["stmt"], $object_descriptor["return_type"], $param_map), "{} \n\r;") . ";";
                break;

            case "class":
            case "interface":
                if (!empty($object_descriptor["extends"])) {
                    foreach ($object_descriptor["extends"] as &$extend) {
                        $this->resolveTypeName($object_descriptor["source_file"]["uses_map"], $extend);

                        if (!empty($this->dictionary["lookup"][$extend])) {
                            $this->dictionary["lookup"][$extend]["known_inheritances"][$object_descriptor["full_name"]] = $object_descriptor["full_name"];
                        }
                    }
                }

                if (!empty($object_descriptor["implements"])) {
                    foreach ($object_descriptor["implements"] as &$implement) {
                        $this->resolveTypeName($object_descriptor["source_file"]["uses_map"], $implement);

                        if (!empty($this->dictionary["lookup"][$implement])) {
                            $this->dictionary["lookup"][$implement]["known_implementations"][$object_descriptor["full_name"]] = $object_descriptor["full_name"];
                        }
                    }
                }

                if (!empty($object_descriptor["uses_traits"])) {
                    foreach ($object_descriptor["uses_traits"] as &$uses_trait) {
                        $this->resolveTypeName($object_descriptor["source_file"]["uses_map"], $uses_trait);

                        if (!empty($this->dictionary["lookup"][$uses_trait])) {
                            $this->dictionary["lookup"][$uses_trait]["known_usages"][$object_descriptor["full_name"]] = $object_descriptor["full_name"];
                        }
                    }
                }

                break;
        }
    } // resolveObjectTypeNames

    protected function checkOverrides(&$object_descriptor, $short_name, &$target, $type)
    {
        if (!empty($object_descriptor["extends"])) {
            foreach ($object_descriptor["extends"] as $extend) {
                if (empty($this->dictionary["lookup"][$extend])) {
                    continue;
                }

                $full_name = $this->dictionary["lookup"][$extend]["full_name"] . "::" . $short_name;

                if (!empty($this->dictionary["lookup"][$extend][$type][$full_name])) {
                    $target = $full_name;
                } else {
                    $this->checkOverrides($this->dictionary["lookup"][$extend], $short_name, $target, $type);
                }
            }
        } else {
        }

        if (!empty($object_descriptor["implements"])) {
            foreach ($object_descriptor["implements"] as $implement) {
                if (empty($this->dictionary["lookup"][$implement])) {
                    continue;
                }

                $full_name = $this->dictionary["lookup"][$implement]["full_name"] . "::" . $short_name;

                if (!empty($this->dictionary["lookup"][$implement][$type][$full_name])) {
                    $target = $full_name;
                } else {

                    $this->checkOverrides($this->dictionary["lookup"][$implement], $short_name, $target, $type);
                }
            }
        } else {
        }

        if (!empty($object_descriptor["uses_traits"])) {
            foreach ($object_descriptor["uses_traits"] as $uses_trait) {
                if (empty($this->dictionary["lookup"][$uses_trait])) {
                    continue;
                }

                $full_name = $this->dictionary["lookup"][$uses_trait]["full_name"] . "::" . $short_name;

                if (!empty($this->dictionary["lookup"][$uses_trait][$type][$full_name])) {
                    $target = $full_name;
                } else {
                    $this->checkOverrides($this->dictionary["lookup"][$uses_trait], $short_name, $target, $type);
                }
            }
        } else {
        }
    } // checkOverrides
} // PhpDocParser



