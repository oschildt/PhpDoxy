<?php

namespace PhpDoxy;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\PhpDocParser\Ast\Attribute;
use PHPStan\PhpDocParser\Ast\PhpDoc\InvalidTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypelessParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Parser\ParserException;
use phpDocumentor\Reflection\DocBlock\Tags;

/**
 * Builds the documentation model from PHP declarations and their doc comments.
 *
 * @author Oleg Schildt
 */
class PhpDocParser
{
    /**
     * Number of global variables registered from the current PHP source file.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundGlobalVars = 0;

    /**
     * Number of global constants registered from the current PHP source file.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundConstants = 0;

    /**
     * Number of functions registered from the current PHP source file.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundFunction = 0;

    /**
     * Number of classes and enums registered from the current PHP source file.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundClasses = 0;

    /**
     * Number of interfaces registered from the current PHP source file.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundInterfaces = 0;

    /**
     * Number of traits registered from the current PHP source file.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundTraits = 0;

    /**
     * Number of PHP source files encountered in the current run, including files that could not be read or parsed.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundSourceFilesTotal = 0;

    /**
     * Number of namespace descriptors registered in the current run, including the global and inferred parent namespaces.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundNamespacesTotal = 0;

    /**
     * Number of global variables registered across all source files in the current run.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundGlobalVarsTotal = 0;

    /**
     * Number of global constants registered across all source files in the current run.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundConstantsTotal = 0;

    /**
     * Number of functions registered across all source files in the current run.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundFunctionTotal = 0;

    /**
     * Number of classes and enums registered across all source files in the current run.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundClassesTotal = 0;

    /**
     * Number of interfaces registered across all source files in the current run.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundInterfacesTotal = 0;

    /**
     * Number of traits registered across all source files in the current run.
     *
     * @var int
     *
     * @author Oleg Schildt
     */
    protected int $foundTraitsTotal = 0;

    /**
     * Relative path identifying the current PHP file in the model, including the source root label when present.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $current_source_file;

    /**
     * Resolved absolute path to the source root currently being scanned.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $current_source_root;

    /**
     * Unique source root label for a multi-root scan, or an empty string for a single source root.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $current_source_label;

    /**
     * Fully qualified name of the current namespace, represented by a single backslash at global scope.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $current_namespace;

    /**
     * Name from the current file's package tag, or an empty string when the file has no package.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    protected string $current_package;

    /**
     * Set of scanned absolute directory paths, with lowercase keys on Windows.
     *
     * @var array<string, true>
     *
     * @author Oleg Schildt
     */
    protected array $scanned_directories = [];

    /**
     * Documentation model keyed by collection name. Descriptors are shared by reference and may form cycles.
     * Use references when sharing descriptors to preserve their identity.
     *
     * @var array<string, array<array-key, mixed>>
     *
     * @author Oleg Schildt
     */
    protected array $dictionary;

    /**
     * Reader for the prose, metadata, and typed tags in source doc comments.
     *
     * @var DocBlockReader
     *
     * @author Oleg Schildt
     */
    protected DocBlockReader $docblock_reader;

    /**
     * Pretty-printer used to reconstruct declaration snippets and native types.
     *
     * @var Standard
     *
     * @author Oleg Schildt
     */
    protected Standard $printer;

    /**
     * PHP source parser used to build the syntax trees for declaration analysis.
     *
     * @var \PhpParser\Parser
     *
     * @author Oleg Schildt
     */
    protected \PhpParser\Parser $parser;

    /**
     * Symbol resolver built from the completed declaration index for documentation links.
     *
     * @var ReferenceResolver
     *
     * @author Oleg Schildt
     */
    protected ReferenceResolver $references;

    /**
     * Prints a native PHP declaration, optionally restricted to one declared item.
     *
     * @param Node\Stmt $statement
     * The declaration to print without its leading doc comment.
     *
     * @param Node\Const_|Node\PropertyItem|null $item
     * A single constant or property from a grouped declaration.
     *
     * @return string
     * Returns the declaration with attributes and native types. Named function and method bodies are omitted.
     *
     * @author Oleg Schildt
     */
    protected function getPhpStatement(Node\Stmt $statement, Node\Const_|Node\PropertyItem|null $item = null): string
    {
        $statement = clone $statement;

        $statement->setAttribute('comments', []);

        if (($statement instanceof Stmt\Const_ || $statement instanceof Stmt\ClassConst) && $item instanceof Node\Const_) {
            $statement->consts = [clone $item];

            $statement->consts[0]->setAttribute('comments', []);
        } elseif ($statement instanceof Stmt\Property && $item instanceof Node\PropertyItem) {
            $statement->props = [clone $item];

            $statement->props[0]->setAttribute('comments', []);
        }

        if ($statement instanceof Stmt\ClassLike) {
            $statement->stmts = [];
        }

        if ($statement instanceof Stmt\Function_ || $statement instanceof Stmt\ClassMethod) {
            $statement->stmts = $statement->stmts === null ? null : [];
            $code = $this->printer->prettyPrint([$statement]);

            return preg_replace('/\s*\{\s*\}$/D', ';', $code);
        }

        return $this->printer->prettyPrint([$statement]);
    } // getPhpStatement

    /**
     * Converts a native type to its display form, preserving parentheses in DNF types.
     *
     * @param Node\Identifier|Node\Name|Node\ComplexType|null $type
     * The original type from the PHP declaration, if specified.
     *
     * @return string
     * Returns a native type expression or an empty string.
     *
     * @author Oleg Schildt
     */
    protected function nativeType(Node\Identifier|Node\Name|Node\ComplexType|null $type): string
    {
        return $type === null ? '' : $this->printer->prettyPrint([$type]);
    } // nativeType

    /**
     * Extracts native parameter types, defaults, and calling conventions.
     *
     * @param list<Node\Param> $params
     * The parameters in declaration order.
     *
     * @return array<string, array<string, mixed>>
     * Returns descriptors indexed by the parameter name, including its dollar sign.
     *
     * @author Oleg Schildt
     */
    protected function describeParameters(array $params): array
    {
        $result = [];

        foreach ($params as $param) {
            $name = '$' . $param->var->name;
            $result[$name] = [
                'name' => $name,
                'val_type' => $this->nativeType($param->type),
                'by_ref' => $param->byRef,
                'variadic' => $param->variadic,
                'default_value' => $param->default === null ? '' : $this->printer->prettyPrintExpr($param->default),
            ];
        }

        return $result;
    } // describeParameters

    /**
     * Registers a top-level declaration and associates it with its file, namespace, and package.
     *
     * @param array<string, mixed> $object_descriptor
     * The declaration to register by reference.
     *
     * @param string $object_type
     * The name of the top-level collection that owns the declaration.
     *
     * @return bool
     * Returns true on registration. Keeps the first descriptor and reports duplicate declarations.
     *
     * @author Oleg Schildt
     */
    protected function injectChild(array &$object_descriptor, string $object_type): bool
    {
        $name = $object_descriptor['full_name'];
        $existing = $this->dictionary[$object_type][$name] ?? null;

        if ($existing === null && in_array($object_type, ['classes', 'interfaces', 'traits'], true)) {
            $symbol = $this->dictionary['lookup'][$name] ?? null;

            if (in_array($symbol['type'] ?? null, ['class', 'interface', 'trait', 'enum'], true)) {
                $existing = $symbol;
            }
        }

        if ($existing !== null) {
            $read_name = $this->getNameByType($object_descriptor['type']);
            $source = $object_descriptor['source_file'];
            $original = $existing['source_file'];
            $message = "The $read_name '$name' at {$source['full_name']}:{$object_descriptor['start_line']}"
                . " is also declared at {$original['full_name']}:{$existing['start_line']}.";

            if ($source['source_root'] === $original['source_root']) {
                PhpDocWatcher::trackError($message);
            } else {
                PhpDocWatcher::trackWarning($message . ' Using the declaration from the first source directory.');
            }

            return false;
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

        $statement = $object_descriptor['parent_stmt'] ?? $object_descriptor['stmt'];
        $object_descriptor['name_context'] = $statement->getAttribute('docNameContext');
        $this->dictionary[$object_type][$object_descriptor["full_name"]] = &$object_descriptor;

        if ($object_type != "global_variables") {
            $object_descriptor["namespace"] = &$this->dictionary["namespaces"][$this->current_namespace];
            $this->dictionary["namespaces"][$this->current_namespace]["children"][$object_type][$object_descriptor["full_name"]] = &$object_descriptor;
        }

        $object_descriptor["package"] = &$this->dictionary["packages"][$this->current_package];
        $this->dictionary["packages"][$this->current_package]["children"][$object_type][$object_descriptor["full_name"]] = &$object_descriptor;

        $this->dictionary["lookup"][$object_descriptor["full_name"]] = &$object_descriptor;

        return true;
    } // injectChild

    /**
     * Indexes supported declarations at file or namespace scope.
     *
     * @param list<Node\Stmt> $stmts
     * The statements in a file or a namespace block.
     *
     * @return void
     * Registers declarations in the documentation model.
     *
     * @author Oleg Schildt
     */
    protected function analyze(&$stmts): void
    {
        foreach ($stmts as &$stmt) {
            switch ($stmt->getType()) {
                case "Stmt_Namespace":
                    $this->analyzeNamespace($stmt);
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
                case "Stmt_Class":
                case "Stmt_Enum":
                case "Stmt_Trait":
                    $this->analyzeClassExpression($stmt);
                    break;

                default:
                    break;
            }
        }
    } // analyze

    /**
     * Indexes file-level assignments to statically named global variables.
     *
     * @param Stmt\Expression $stmt
     * The expression statement to examine.
     *
     * @return void
     * Registers directly assigned variables with a known name.
     *
     * @author Oleg Schildt
     */
    protected function analyzeStmtExpression(Stmt\Expression $stmt): void
    {
        $assignment = $stmt->expr;

        if (!$assignment instanceof Node\Expr\Assign) {
            return;
        }

        $variable = $assignment->var;

        if (!$variable instanceof Node\Expr\Variable || !is_string($variable->name)) {
            return;
        }

        $object_name = "$" . $variable->name;
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

    /**
     * Indexes a named class, interface, trait, or enum and its supported members.
     *
     * @param Stmt\ClassLike $stmt
     * A named class-like declaration.
     *
     * @return void
     * Registers the declaration and its members in the documentation model.
     *
     * @author Oleg Schildt
     */
    protected function analyzeClassExpression(Stmt\ClassLike $stmt): void
    {
        $type = match (true) {
            $stmt instanceof Stmt\Enum_ => 'enum',
            $stmt instanceof Stmt\Interface_ => 'interface',
            $stmt instanceof Stmt\Trait_ => 'trait',
            default => 'class',
        };
        $name = $stmt->name->toString();
        $object_descriptor = [
            'type' => $type,
            'flags' => $stmt->flags ?? 0,
            'name' => $name,
            'short_name' => $name,
            'full_name' => '\\' . $stmt->namespacedName->toString(),
            'start_line' => $stmt->getStartLine(),
            'docblock' => $stmt->getDocComment()?->getText() ?? '',
            'stmt' => $stmt,
            'properties' => [],
            'constants' => [],
            'methods' => [],
        ];
        $object_descriptor['source_file'] = &$this->dictionary['source_files'][$this->current_source_file];
        $extends = $stmt instanceof Stmt\Interface_ ? $stmt->extends : array_filter([$stmt->extends ?? null]);

        foreach ($extends as $parent) {
            $object_descriptor['extends'][] = $parent->getAttribute('resolvedName', $parent)->toCodeString();
        }

        foreach ($stmt->implements ?? [] as $interface) {
            $object_descriptor['implements'][] = $interface->getAttribute('resolvedName', $interface)->toCodeString();
        }

        $collection = $type === 'enum' ? 'classes' : $type . ($type === 'class' ? 'es' : 's');

        if (!$this->injectChild($object_descriptor, $collection)) {
            return;
        }

        foreach ($stmt->stmts as $member) {
            if ($member instanceof Stmt\ClassConst) {
                $this->analyzeClassConstExpression($member, $object_descriptor);
            } elseif ($member instanceof Stmt\ClassMethod) {
                $this->analyzeClassMethodExpression($member, $object_descriptor);
            } elseif ($member instanceof Stmt\Property) {
                $this->analyzeClassPropertyExpression($member, $object_descriptor);
            } elseif ($member instanceof Stmt\TraitUse) {
                $this->analyzeClassUseTraitExpression($member, $object_descriptor);
            } elseif ($member instanceof Stmt\EnumCase) {
                $this->analyzeEnumCase($member, $object_descriptor);
            }
        }
    } // analyzeClassExpression

    /**
     * Indexes a method and any properties declared by constructor promotion.
     *
     * @param Stmt\ClassMethod $stmt
     * The method declaration.
     *
     * @param array<string, mixed> $parent_descriptor
     * The class-like descriptor that owns the method.
     *
     * @return void
     * Registers the method and its promoted properties.
     *
     * @author Oleg Schildt
     */
    protected function analyzeClassMethodExpression(Stmt\ClassMethod $stmt, array &$parent_descriptor): void
    {
        $name = $stmt->name->toString() . '()';
        $object_descriptor = [
            'type' => 'method',
            'flags' => $stmt->flags,
            'by_ref' => $stmt->byRef,
            'name' => $parent_descriptor['name'] . '::' . $name,
            'short_name' => $name,
            'full_name' => $parent_descriptor['full_name'] . '::' . $name,
            'stmt' => $stmt,
            'start_line' => $stmt->getStartLine(),
            'docblock' => $stmt->getDocComment()?->getText() ?? '',
            'params' => $this->describeParameters($stmt->params),
            'return_type' => $this->nativeType($stmt->returnType),
            'name_context' => $parent_descriptor['name_context'],
        ];
        $object_descriptor['source_file'] = &$parent_descriptor['source_file'];
        $object_descriptor['parent'] = &$parent_descriptor;
        $parent_descriptor['methods'][$object_descriptor['full_name']] = &$object_descriptor;
        $this->dictionary['lookup'][$object_descriptor['full_name']] = &$object_descriptor;

        foreach ($stmt->params as $param) {
            if ($param->isPromoted()) {
                $property = new Stmt\Property(
                    $param->flags,
                    [new Node\PropertyItem($param->var->name, null, $param->getAttributes())],
                    $param->getAttributes(),
                    $param->type,
                    $param->attrGroups,
                    $param->hooks
                );

                $property->setAttribute('docPromoted', true);

                $this->analyzeClassPropertyExpression($property, $parent_descriptor);
            }
        }
    } // analyzeClassMethodExpression

    /**
     * Indexes an enum case as a linkable member of its enum.
     *
     * @param Stmt\EnumCase $stmt
     * The case declaration, with its value for backed enums.
     *
     * @param array<string, mixed> $parent_descriptor
     * The owning enum descriptor.
     *
     * @return void
     * Adds the case to the enum's member list and the symbol index.
     *
     * @author Oleg Schildt
     */
    protected function analyzeEnumCase(Stmt\EnumCase $stmt, array &$parent_descriptor): void
    {
        $name = $stmt->name->toString();
        $full_name = $parent_descriptor['full_name'] . '::' . $name;
        $descriptor = [
            'type' => 'enum_case',
            'name' => $parent_descriptor['name'] . '::' . $name,
            'short_name' => $name,
            'full_name' => $full_name,
            'start_line' => $stmt->getStartLine(),
            'stmt' => $stmt,
            'docblock' => $stmt->getDocComment()?->getText() ?? '',
            'val_type' => $this->nativeType($parent_descriptor['stmt']->scalarType),
            'name_context' => $parent_descriptor['name_context'],
        ];
        $descriptor['source_file'] = &$parent_descriptor['source_file'];
        $descriptor['parent'] = &$parent_descriptor;
        $parent_descriptor['constants'][$full_name] = &$descriptor;
        $this->dictionary['lookup'][$full_name] = &$descriptor;
    } // analyzeEnumCase

    /**
     * Records the resolved names of traits used by a class-like declaration.
     *
     * @param Stmt\TraitUse $stmt
     * The trait-use statement.
     *
     * @param array<string, mixed> $parent_descriptor
     * The class-like descriptor that uses these traits.
     *
     * @return void
     * Appends fully qualified trait names.
     *
     * @author Oleg Schildt
     */
    protected function analyzeClassUseTraitExpression(Stmt\TraitUse $stmt, array &$parent_descriptor): void
    {
        foreach ($stmt->traits as $trait) {
            $parent_descriptor['uses_traits'][] = $trait->getAttribute('resolvedName', $trait)->toCodeString();
        }
    } // analyzeClassUseTraitExpression

    /**
     * Indexes property declarations and their individual doc comments.
     *
     * @param Stmt\Property $stmt
     * The statement containing the declarations and any property hooks.
     *
     * @param array<string, mixed> $parent_descriptor
     * The owning class-like descriptor.
     *
     * @return void
     * Registers each declaration and its source location.
     *
     * @author Oleg Schildt
     */
    protected function analyzeClassPropertyExpression(Stmt\Property $stmt, array &$parent_descriptor): void
    {
        $common_docblock = $stmt->getDocComment()?->getText() ?? '';
        $type = $this->nativeType($stmt->type);

        foreach ($stmt->props as $item) {
            $name = '$' . $item->name->toString();
            $full_name = $parent_descriptor['full_name'] . '::' . $name;
            $descriptor = [
                'type' => 'property',
                'promoted' => $stmt->getAttribute('docPromoted', false),
                'flags' => $stmt->flags,
                'val_type' => $type,
                'name_context' => $parent_descriptor['name_context'],
                'name' => $parent_descriptor['name'] . '::' . $name,
                'short_name' => $name,
                'full_name' => $full_name,
                'start_line' => count($stmt->props) === 1 ? $stmt->getStartLine() : $item->getStartLine(),
                'docblock' => $item->getDocComment()?->getText() ?? $common_docblock,
                'stmt' => $item,
                'parent_stmt' => $stmt,
            ];
            $descriptor['source_file'] = &$parent_descriptor['source_file'];
            $descriptor['parent'] = &$parent_descriptor;
            $parent_descriptor['properties'][$full_name] = &$descriptor;
            $this->dictionary['lookup'][$full_name] = &$descriptor;

            // Each dictionary entry owns a distinct descriptor.
            unset($descriptor);
        }
    } // analyzeClassPropertyExpression

    /**
     * Indexes class constant declarations and their individual doc comments.
     *
     * @param Stmt\ClassConst $stmt
     * The statement containing the declarations and attributes.
     *
     * @param array<string, mixed> $parent_descriptor
     * The owning class-like descriptor.
     *
     * @return void
     * Registers each declaration and its source location.
     *
     * @author Oleg Schildt
     */
    protected function analyzeClassConstExpression(Stmt\ClassConst $stmt, array &$parent_descriptor): void
    {
        $common_docblock = $stmt->getDocComment()?->getText() ?? '';
        $type = $this->nativeType($stmt->type);

        foreach ($stmt->consts as $item) {
            $name = $item->name->toString();
            $full_name = $parent_descriptor['full_name'] . '::' . $name;
            $descriptor = [
                'type' => 'class_constant',
                'flags' => $stmt->flags,
                'val_type' => $type,
                'name_context' => $parent_descriptor['name_context'],
                'name' => $parent_descriptor['name'] . '::' . $name,
                'short_name' => $name,
                'full_name' => $full_name,
                'start_line' => count($stmt->consts) === 1 ? $stmt->getStartLine() : $item->getStartLine(),
                'docblock' => $item->getDocComment()?->getText() ?? $common_docblock,
                'stmt' => $item,
                'parent_stmt' => $stmt,
            ];
            $descriptor['source_file'] = &$parent_descriptor['source_file'];
            $descriptor['parent'] = &$parent_descriptor;
            $parent_descriptor['constants'][$full_name] = &$descriptor;
            $this->dictionary['lookup'][$full_name] = &$descriptor;

            // Each dictionary entry owns a distinct descriptor.
            unset($descriptor);
        }
    } // analyzeClassConstExpression

    /**
     * Registers a free function and records its native signature.
     *
     * @param Stmt\Function_ $stmt
     * The function declaration.
     *
     * @return void
     * Adds the function and its parameters to the documentation model.
     *
     * @author Oleg Schildt
     */
    protected function analyzeFunctionExpression(Stmt\Function_ $stmt): void
    {
        $name = $stmt->name->toString() . '()';
        $descriptor = [
            'type' => 'function',
            'by_ref' => $stmt->byRef,
            'name' => $name,
            'short_name' => $name,
            'full_name' => '\\' . $stmt->namespacedName->toString() . '()',
            'start_line' => $stmt->getStartLine(),
            'docblock' => $stmt->getDocComment()?->getText() ?? '',
            'stmt' => $stmt,
            'params' => $this->describeParameters($stmt->params),
            'return_type' => $this->nativeType($stmt->returnType),
        ];
        $descriptor['source_file'] = &$this->dictionary['source_files'][$this->current_source_file];

        $this->injectChild($descriptor, 'functions');
    } // analyzeFunctionExpression

    /**
     * Indexes constant declarations and their individual doc comments.
     *
     * @param Stmt\Const_ $stmt
     * The statement containing the declarations and attributes.
     *
     * @return void
     * Registers each declaration and its source location.
     *
     * @author Oleg Schildt
     */
    protected function analyzeConstExpression(Stmt\Const_ $stmt): void
    {
        $common_docblock = $stmt->getDocComment()?->getText() ?? '';

        foreach ($stmt->consts as $item) {
            $name = $item->name->toString();
            $full_name = '\\' . $item->namespacedName->toString();
            $descriptor = [
                'type' => 'constant',
                'name' => $name,
                'short_name' => $name,
                'full_name' => $full_name,
                'start_line' => count($stmt->consts) === 1 ? $stmt->getStartLine() : $item->getStartLine(),
                'docblock' => $item->getDocComment()?->getText() ?? $common_docblock,
                'stmt' => $item,
                'parent_stmt' => $stmt,
            ];
            $descriptor['source_file'] = &$this->dictionary['source_files'][$this->current_source_file];

            $this->injectChild($descriptor, 'constants');

            // Each dictionary entry owns a distinct descriptor.
            unset($descriptor);
        }
    } // analyzeConstExpression

    /**
     * Creates a namespace descriptor and registers it in the namespace and symbol indexes.
     *
     * @param string $full_name
     * The fully qualified namespace name with a leading backslash. A single backslash denotes the global namespace.
     *
     * @return array{type: string, name: string, short_name: string, full_name: string, children: array<string, array<string, array<string, mixed>>>}
     * Returns the registered namespace descriptor by reference, with empty child collections on creation.
     *
     * @author Oleg Schildt
     */
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

    /**
     * Indexes declarations in a named or global namespace block.
     *
     * @param Stmt\Namespace_ $stmt
     * The namespace block, whose name may be null for the global namespace.
     *
     * @return void
     * Registers the namespace and its declarations.
     *
     * @author Oleg Schildt
     */
    protected function analyzeNamespace(Stmt\Namespace_ $stmt): void
    {
        $name = '\\' . ($stmt->name?->toString() ?? '');
        $this->current_namespace = $name;

        if (empty($this->dictionary['namespaces'][$name])) {
            $this->addNamespace($name);
        }

        $this->analyze($stmt->stmts);
    } // analyzeNamespace

    /**
     * Parses a source file and adds its declarations to the current model.
     *
     * @param string $code
     * The complete source file, including any inline HTML.
     *
     * @param string $source_file
     * The file path relative to its source directory, qualified by a directory label for multiple sources.
     *
     * @return void
     * Stores the source and registers its declarations.
     *
     * @throws \PhpParser\Error
     * If the source cannot be parsed or contains invalid imports.
     *
     * @author Oleg Schildt
     */
    protected function parseFile(string $code, string $source_file): void
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
        $object_descriptor['source_root'] = $this->current_source_root;
        $object_descriptor['source_label'] = $this->current_source_label;
        $object_descriptor["source_file"] = &$object_descriptor; // for unification - each object must have a source file
        $object_descriptor["start_line"] = 1;
        $object_descriptor['code'] = $code;
        $stmts = $this->parser->parse($code) ?? [];
        $traverser = new \PhpParser\NodeTraverser(new SourceNameResolver(null, ['replaceNodes' => false]));
        $stmts = $traverser->traverse($stmts);

        foreach (\PhpToken::tokenize($code) as $token) {
            if (!$token->is(T_DOC_COMMENT)) {
                continue;
            }

            try {
                $metadata = $this->docblock_reader->read($token->text)['metadata'];
                $package = $metadata->getTagsByName('package')[0] ?? null;

                if ($package instanceof Tags\Generic) {
                    $this->current_package = trim((string) $package->getDescription());
                }

                if ($this->current_package !== '' || !$this->isDeclarationDocBlock($stmts, $token->pos)) {
                    $object_descriptor['docblock'] = $token->text;
                    $object_descriptor['doc_line'] = $token->line;
                }
            } catch (ParserException | \InvalidArgumentException $exception) {
                PhpDocWatcher::trackWarning($source_file . ':' . $token->line . ': ' . $exception->getMessage());
            }

            break;
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

        $this->analyze($stmts);
    } // parseFile

    /**
     * Determines whether the first doc comment belongs to a declaration.
     *
     * @param list<Node\Stmt> $statements
     * The statements at file or namespace scope.
     *
     * @param int $position
     * The byte position of the doc comment in the source.
     *
     * @return bool
     * Returns true when the declaration already owns the comment.
     *
     * @author Oleg Schildt
     */
    protected function isDeclarationDocBlock(array $statements, int $position): bool
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Stmt\Namespace_) {
                if ($this->isDeclarationDocBlock($statement->stmts, $position)) {
                    return true;
                }
            } elseif (
                $statement instanceof Stmt\ClassLike || $statement instanceof Stmt\Function_
                || $statement instanceof Stmt\Const_ || $statement instanceof Stmt\Expression
            ) {
                if ($statement->getDocComment()?->getStartFilePos() === $position) {
                    return true;
                }
            }
        }

        return false;
    } // isDeclarationDocBlock

    /**
     * Scans a source directory once in stable order and skips linked entries.
     *
     * @param string $dir
     * The absolute path to the directory being scanned.
     *
     * @param string $relative_path
     * The path relative to the source root, with its label when multiple sources are configured.
     *
     * @return void
     * Processes PHP files, records Markdown documents, and descends into regular child directories.
     *
     * @throws \RuntimeException
     * If the source directory cannot be listed.
     *
     * @author Oleg Schildt
     */
    protected function processDir(string $dir, string $relative_path): void
    {
        $directory_key = DIRECTORY_SEPARATOR === '\\' ? mb_strtolower($dir, 'UTF-8') : $dir;

        if (isset($this->scanned_directories[$directory_key])) {
            return;
        }

        echo_highlighted("\n\nProcessing path: " . ($relative_path === '' ? '.' : $relative_path));

        $files = scandir($dir);

        if ($files === false) {
            throw new \RuntimeException("Cannot list source directory '$dir'.");
        }

        $this->scanned_directories[$directory_key] = true;
        $directories = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $file;
            $relative = $relative_path === '' ? $file : $relative_path . '/' . $file;
            $resolved = realpath($path);

            if ($resolved === false || is_link($path) || !OutputDirectory::samePath($resolved, $path)) {
                continue;
            }

            if (is_dir($path)) {
                $directories[$relative] = $resolved;
            } elseif (is_file($path)) {
                switch (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
                    case 'php':
                        $this->processFile($path, $relative);
                        break;

                    case 'md':
                        $this->dictionary['markdown_files'][$relative] = $resolved;
                        break;
                }
            }
        }

        foreach ($directories as $relative => $path) {
            $this->processDir($path, $relative);
        }
    } // processDir

    /**
     * Reads and indexes one PHP source file and reports failures with source locations.
     *
     * @param string $file
     * The absolute file path.
     *
     * @param string $relative_path
     * The source root relative file path used in documentation and diagnostics.
     *
     * @return void
     * Logs the source file's declaration counts after successful analysis.
     *
     * @author Oleg Schildt
     */
    protected function processFile(string $file, string $relative_path): void
    {
        $this->foundSourceFilesTotal++;

        echo_highlighted2("\n\nProcessing file: $relative_path\n");

        $code = file_get_contents($file);

        if ($code === false) {
            PhpDocWatcher::trackError("Cannot read source file '$relative_path'.");

            return;
        }

        try {
            $this->parseFile($code, $relative_path);
        } catch (\PhpParser\Error $exception) {
            PhpDocWatcher::trackError($relative_path . ':' . $exception->getStartLine() . ': ' . $exception->getRawMessage());

            return;
        }

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

    /**
     * Parses the configured source trees and links the documented declarations across them.
     *
     * @param array{sources: non-empty-list<string>} $config
     * Generation settings containing the source directory paths in priority order.
     *
     * @return array<string, array>
     * Returns the documentation model by reference, including formatted declarations.
     *
     * @throws \RuntimeException
     * If the source list is empty, a directory is invalid, or a directory cannot be scanned.
     *
     * @author Oleg Schildt
     */
    public function &process(array &$config): array
    {
        PhpDocWatcher::reset();

        $source_dirs = $this->resolveSourceDirectories($config['sources'] ?? []);

        $this->scanned_directories = [];
        $this->foundSourceFilesTotal = 0;
        $this->foundNamespacesTotal = 0;
        $this->foundGlobalVarsTotal = 0;
        $this->foundConstantsTotal = 0;
        $this->foundFunctionTotal = 0;
        $this->foundClassesTotal = 0;
        $this->foundInterfacesTotal = 0;
        $this->foundTraitsTotal = 0;
        $this->dictionary = [];
        $this->docblock_reader = new DocBlockReader();
        $this->parser = (new \PhpParser\ParserFactory())->createForVersion(PhpVersion::fromString('8.5'));
        $this->printer = new DeclarationPrinter(['phpVersion' => PhpVersion::fromString('8.5')]);

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
        $this->dictionary['markdown_files'] = [];
        $this->dictionary["todos"] = [];
        $this->dictionary["since"] = [];
        $this->dictionary["deprecated"] = [];

        $this->processSources($source_dirs);

        $this->postProcess();

        echo_highlighted("\n\nProcessed source files: " . $this->foundSourceFilesTotal . "\n");

        $somethingFound = false;

        if ($this->dictionary['markdown_files'] !== []) {
            $somethingFound = true;

            echo_standard("\nMarkdown documents: " . count($this->dictionary['markdown_files']));
        }


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

    /**
     * Resolves configured source paths and retains each directory only once.
     *
     * @param list<string> $sources
     * The configured source directories in scan order.
     *
     * @return non-empty-list<string>
     * Returns the canonical source directories in their original order.
     *
     * @throws \RuntimeException
     * If the source list is empty or one of the paths is not a directory.
     *
     * @author Oleg Schildt
     */
    protected function resolveSourceDirectories(array $sources): array
    {
        if ($sources === []) {
            throw new \RuntimeException('At least one source directory is required.');
        }

        $directories = [];

        foreach ($sources as $source) {
            $directory = $source === '' ? false : realpath($source);

            if ($directory === false || !is_dir($directory)) {
                throw new \RuntimeException("Source directory '$source' is missing or invalid.");
            }

            $key = DIRECTORY_SEPARATOR === '\\' ? mb_strtolower($directory, 'UTF-8') : $directory;
            $directories[$key] ??= $directory;
        }

        return array_values($directories);
    } // resolveSourceDirectories

    /**
     * Assigns unique source directory labels and scans the directories in order.
     *
     * @param non-empty-list<string> $directories
     * The resolved source directories in priority order.
     *
     * @throws \RuntimeException
     * If a directory cannot be listed.
     *
     * @author Oleg Schildt
     */
    protected function processSources(array $directories): void
    {
        $labels = [];
        $multiple_sources = count($directories) > 1;

        foreach ($directories as $directory) {
            $label = '';

            if ($multiple_sources) {
                $base = basename(rtrim($directory, '/\\'));
                $base = $base === '' ? 'source' : $base;
                $label = $base;
                $suffix = 2;

                while (isset($labels[mb_strtolower($label, 'UTF-8')])) {
                    $label = $base . '-' . $suffix++;
                }

                $labels[mb_strtolower($label, 'UTF-8')] = true;
            }

            $this->current_source_root = $directory;
            $this->current_source_label = $label;

            echo_highlighted("\n\nProcessing source files from: " . $directory);

            $this->processDir($directory, $label);
        }
    } // processSources

    /**
     * Builds the symbol index and resolves documentation after all files have been scanned.
     *
     * @return void
     * Completes hierarchy, documentation, and display ordering in the model.
     *
     * @author Oleg Schildt
     */
    protected function postProcess(): void
    {
        $this->buildNamespaceHierarchy();

        $this->references = new ReferenceResolver($this->dictionary['lookup']);

        $this->processDocBlocks();

        $this->sortObjectLists();
    } // postProcess

    /**
     * Sorts declarations and log entries for predictable page output.
     *
     * @return void
     * Orders the collections in the documentation model.
     *
     * @author Oleg Schildt
     */
    protected function sortObjectLists(): void
    {
        foreach ($this->dictionary as $key => &$block) {
            if ($key == "source_files") {
                continue;
            }

            ksort($block);
        }

        $sort_function = static function (array $first, array $second): int {
            return strcmp($first['name'], $second['name']) ?: strcmp($first['full_name'], $second['full_name']);
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

    /**
     * Strips everything through the last namespace separator in each supplied name.
     *
     * @param string|array<array-key, string> $name
     * A PHP name or an array of names to shorten.
     *
     * @return string|array<array-key, string>|null
     * Returns the shortened name or array of shortened names with the original keys,
     * or null if the replacement fails.
     *
     * @author Oleg Schildt
     */
    protected function removeNamespacePrefix($name)
    {
        return preg_replace("/.*\\\\/", "", $name);
    } // removeNamespacePrefix

    /**
     * Finds the immediate parent of a namespace.
     *
     * @param string $name
     * The fully qualified namespace name, including its leading backslash.
     *
     * @return string
     * Returns the fully qualified parent name, or a single backslash for a top-level or global namespace.
     *
     * @author Oleg Schildt
     */
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

    /**
     * Links nested namespace descriptors to their immediate parents.
     *
     * @return void
     * Registers any missing parent namespaces and adds child descriptors to them by reference.
     *
     * @author Oleg Schildt
     */
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

    /**
     * Reads doc blocks for indexed declarations and resolves their inherited members.
     *
     * @return void
     * Completes member documentation and inheritance links.
     *
     * @author Oleg Schildt
     */
    protected function processDocBlocks(): void
    {
        $types_to_process = ["source_files", "constants", "global_variables", "functions", "classes", "interfaces", "traits"];

        foreach ($types_to_process as $type_to_process) {
            if (empty($this->dictionary[$type_to_process])) {
                continue;
            }

            foreach ($this->dictionary[$type_to_process] as &$object_descriptor) {
                $this->processObjectDocBlock($object_descriptor);

                foreach (['methods', 'properties', 'constants'] as $members) {
                    foreach ($object_descriptor[$members] ?? [] as $key => $unused) {
                        $this->processObjectDocBlock($object_descriptor[$members][$key]);
                    }
                }
            } // foreach object
        } // foreach type

        $types_to_process = ["classes", "interfaces", "traits"];

        foreach ($types_to_process as $type_to_process) {
            foreach ($this->dictionary[$type_to_process] as &$object_descriptor) {

                if (!empty($object_descriptor["properties"])) {
                    foreach ($object_descriptor["properties"] as &$property) {
                        $property["overrides"] = '';

                        $this->checkOverrides($object_descriptor, $property["short_name"], $property["overrides"], "properties");
                    }
                }

                if (!empty($object_descriptor["constants"])) {
                    foreach ($object_descriptor["constants"] as &$constant) {
                        $constant["overrides"] = '';

                        $this->checkOverrides($object_descriptor, $constant["short_name"], $constant["overrides"], "constants");
                    }
                }

                if (!empty($object_descriptor["methods"])) {
                    foreach ($object_descriptor["methods"] as &$method) {
                        $method["overrides"] = '';

                        $this->checkOverrides($object_descriptor, $method["short_name"], $method["overrides"], "methods");
                    }
                }
            } // foreach object
        } // foreach type
    } // processDocBlocks

    /**
     * Returns the human-readable declaration name used in diagnostics.
     *
     * @param string $type
     * The model's declaration type.
     *
     * @return string
     * Returns the display name, or the original type if it has no special label.
     *
     * @author Oleg Schildt
     */
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

            case "enum_case":
                $type = "enum case";
                break;

            case "class_constant":
                $type = "class constant";
                break;
        }

        return $type;
    } // getNameByType

    /**
     * Combines structured PHPDoc with the native data already recorded for a declaration.
     *
     * @param array<string, mixed> $object_descriptor
     * The model entry to complete by reference.
     *
     * @return void
     * Stores parsed documentation and reports invalid tags at their source lines.
     *
     * @author Oleg Schildt
     */
    protected function processObjectDocBlock(array &$object_descriptor): void
    {
        $read_name = $this->getNameByType($object_descriptor["type"]);
        $appendix = "\nFile: " . $object_descriptor["source_file"]["full_name"] . ", line: " . $object_descriptor["start_line"];

        $this->completeDeclaration($object_descriptor);

        if (empty($object_descriptor['docblock'])) {
            if ($object_descriptor['type'] !== 'source_file' && empty($object_descriptor['promoted'])) {
                PhpDocWatcher::trackWarning("The $read_name '" . $object_descriptor['name'] . "' has no doc block!" . $appendix);
            }

            return;
        }

        $statement = $object_descriptor['stmt'] ?? null;
        $parent_statement = $object_descriptor['parent_stmt'] ?? null;
        $doc_line = $object_descriptor['doc_line'] ?? $statement?->getDocComment()?->getStartLine()
            ?? $parent_statement?->getDocComment()?->getStartLine() ?? $object_descriptor['start_line'];

        try {
            $document = $this->docblock_reader->read($object_descriptor['docblock']);
        } catch (ParserException | \InvalidArgumentException $exception) {
            PhpDocWatcher::trackWarning(
                $object_descriptor['source_file']['full_name'] . ':' . $doc_line . ': ' . $exception->getMessage()
            );

            return;
        }

        $docblock = $document['metadata'];
        $object_descriptor["summary"] = trim($docblock->getSummary());
        $object_descriptor["description"] = trim((string) $docblock->getDescription());

        $tags = $docblock->getTags();

        foreach ($tags as $tag) {
            if ($tag instanceof Tags\InvalidTag) {
                PhpDocWatcher::trackWarning('Invalid @' . $tag->getName() . ' tag on ' . $object_descriptor['name'] . $appendix);

                continue;
            }

            if ($tag instanceof Tags\Author) {
                $object_descriptor['authors'][] = [
                    'name' => $tag->getAuthorName(),
                    'email' => $tag->getEmail(),
                ];
            } elseif ($tag instanceof Tags\Deprecated) {
                $object_descriptor['deprecated_description'] = trim((string) $tag->getDescription());
                $object_descriptor['deprecated_version'] = $tag->getVersion() ?: ($object_descriptor['deprecated_version'] ?? '');
                $object_descriptor['deprecated'] = true;
                $this->dictionary['deprecated'][$object_descriptor['full_name']] = &$object_descriptor;
            } elseif ($tag instanceof Tags\Link) {
                $object_descriptor['links'][] = [
                    'url' => $tag->getLink(),
                    'description' => $tag->getDescription(),
                ];
            } elseif ($tag instanceof Tags\Version) {
                $object_descriptor['version_description'] = trim((string) $tag->getDescription());
                $object_descriptor['version'] = $tag->getVersion();
            } elseif ($tag instanceof Tags\Since) {
                $object_descriptor['since'][] = [
                    'version' => $tag->getVersion(),
                    'description' => $tag->getDescription(),
                ];
                $this->dictionary['since'][$object_descriptor['full_name']] = &$object_descriptor;
            } elseif ($tag instanceof Tags\Generic) {
                $name = $tag->getName();

                if (in_array($name, ['copyright', 'internal', 'license'], true)) {
                    $object_descriptor[$name] = trim((string) $tag->getDescription());
                } elseif ($name === 'ignore') {
                    $object_descriptor['ignore'] = 1;
                } elseif ($name === 'todo') {
                    $object_descriptor['todos'][] = ['description' => $tag->getDescription()];
                    $this->dictionary['todos'][$object_descriptor['full_name']] = &$object_descriptor;
                }
            }
        }

        foreach ($document['references'] as $kind => $references) {
            if ($kind === 'uses' && $object_descriptor['type'] === 'source_file') {
                PhpDocWatcher::trackWarning('The tag @uses is inappropriate for a file-level doc block.' . $appendix);
            } else {
                $object_descriptor[$kind] = $references;
            }
        }

        if ($object_descriptor['type'] !== 'source_file') {
            foreach ($document['types'] as $tag) {
                $this->applyTypeTag($tag, $object_descriptor, $doc_line);
            }
        }

        $this->checkObjectDocCompleteness($object_descriptor);

        $this->resolveObjectReferences($object_descriptor);

        $this->checkDeadSeeReferences($object_descriptor);
    } // processObjectDocBlock

    /**
     * Applies one parsed type annotation to the documented declaration.
     *
     * @param PhpDocTagNode $tag
     * A typed tag or template from the source comment.
     *
     * @param array<string, mixed> $descriptor
     * The declaration receiving the annotation.
     *
     * @param int $doc_line
     * The physical source line at which the doc comment opens.
     *
     * @return void
     * Stores the type and description or logs a diagnostic for an invalid annotation.
     *
     * @author Oleg Schildt
     */
    protected function applyTypeTag(PhpDocTagNode $tag, array &$descriptor, int $doc_line): void
    {
        $value = $tag->value;
        $line = $doc_line + max(0, (int) $tag->getAttribute(Attribute::START_LINE) - 1);
        $location = $descriptor['source_file']['full_name'] . ':' . $line;
        $callable = in_array($descriptor['type'], ['function', 'method'], true);

        if ($value instanceof InvalidTagValueNode) {
            PhpDocWatcher::trackWarning("$location: Invalid {$tag->name}: " . $value->exception?->getMessage());

            return;
        }

        if ($value instanceof TemplateTagValueNode) {
            $descriptor['templates'][$value->name] = $value;

            return;
        }

        if ($tag->name === '@param' && ($value instanceof ParamTagValueNode || $value instanceof TypelessParamTagValueNode)) {
            if (!$callable || !isset($descriptor['params'][$value->parameterName])) {
                PhpDocWatcher::trackWarning("$location: Unexpected @param {$value->parameterName} for {$descriptor['name']}.");

                return;
            }

            $parameter = &$descriptor['params'][$value->parameterName];
            $parameter['description'] = trim($value->description);
            $parameter['doc_type'] = $value instanceof ParamTagValueNode ? $value->type : null;

            if (($value->isReference && !$parameter['by_ref']) || ($value->isVariadic && !$parameter['variadic'])) {
                PhpDocWatcher::trackWarning("$location: @param {$value->parameterName} disagrees with the PHP signature.");
            }
        } elseif ($tag->name === '@return' && $value instanceof ReturnTagValueNode) {
            if (!$callable) {
                PhpDocWatcher::trackWarning("$location: Unexpected @return for {$descriptor['name']}.");

                return;
            }

            $descriptor['return_doc_type'] = $value->type;
            $descriptor['return_description'] = trim($value->description);
        } elseif ($tag->name === '@throws' && $value instanceof ThrowsTagValueNode) {
            if (!$callable) {
                PhpDocWatcher::trackWarning("$location: Unexpected @throws for {$descriptor['name']}.");

                return;
            }

            $descriptor['throws'][] = ['doc_type' => $value->type, 'description' => trim($value->description)];
        } elseif ($tag->name === '@var' && $value instanceof VarTagValueNode) {
            if (!in_array($descriptor['type'], ['constant', 'class_constant', 'enum_case', 'property', 'global_variable'], true)) {
                PhpDocWatcher::trackWarning("$location: Unexpected @var for {$descriptor['name']}.");

                return;
            }

            if ($value->variableName !== '' && $value->variableName !== $descriptor['short_name']) {
                return;
            }

            $descriptor['doc_type'] = $value->type;

            if ($value->description !== '') {
                if (empty($descriptor['description'])) {
                    $descriptor['description'] = trim($value->description);
                } else {
                    PhpDocWatcher::trackWarning("$location: Redundant @var description for {$descriptor['name']}.");
                }
            }
        }
    } // applyTypeTag

    /**
     * Reports missing descriptions or types after PHPDoc and native types are available.
     *
     * @param array<string, mixed> $object_descriptor
     * The declaration to validate.
     *
     * @return void
     * Records completeness diagnostics.
     *
     * @author Oleg Schildt
     */
    protected function checkObjectDocCompleteness(array &$object_descriptor): void
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
                if (empty($object_descriptor["val_type"]) && empty($object_descriptor["doc_type"])) {
                    PhpDocWatcher::trackWarning("The type of the $read_name '" . $object_descriptor["name"] . "' is not specified!" . $appendix);
                }
                break;

            case "method":
            case "function":
                if (
                    !($object_descriptor["type"] == "method" && ($object_descriptor["short_name"] == "__construct()" || $object_descriptor["short_name"] == "__destruct()")) &&
                    empty($object_descriptor["return_type"]) && empty($object_descriptor["return_doc_type"])
                ) {
                    PhpDocWatcher::trackWarning("The return type of the $read_name '" . $object_descriptor["name"] . "' is not specified!" . $appendix);
                }

                foreach ($object_descriptor["params"] as $param) {
                    if (empty($param["val_type"]) && empty($param["doc_type"])) {
                        PhpDocWatcher::trackWarning("The type of the parameter '" . $param["name"] . "' of the $read_name '" . $object_descriptor["name"] . "' is not specified!" . $appendix);
                    }

                    if (empty($param["description"])) {
                        PhpDocWatcher::trackWarning("The description of the parameter '" . $param["name"] . "' of the $read_name '" . $object_descriptor["name"] . "' is not specified!" . $appendix);
                    }
                }
                break;
        } // switch
    } // checkObjectDocCompleteness

    /**
     * Checks inline see links in the documentation fields of a declaration.
     *
     * @param array<string, mixed> $object_descriptor
     * The documented declaration and its name-resolution scope.
     *
     * @return void
     * Reports each field containing unresolved see references.
     *
     * @author Oleg Schildt
     */
    protected function checkDeadSeeReferences(array &$object_descriptor): void
    {
        $read_name = $this->getNameByType($object_descriptor["type"]);
        $appendix = "\nFile: " . $object_descriptor["source_file"]["full_name"] . ", line: " . $object_descriptor["start_line"];

        if (!empty($object_descriptor["summary"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor, $object_descriptor["summary"]);
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The summary in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["description"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor, $object_descriptor["description"]);
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The description in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["return_description"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor, $object_descriptor["return_description"]);
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The description of the return value in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["copyright"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor, $object_descriptor["copyright"]);
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The copyright text in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["license"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor, $object_descriptor["license"]);
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The license text in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["deprecated_description"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor, $object_descriptor["deprecated_description"]);
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The deprectation notice in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["version_description"])) {
            $dead_refs = $this->hasDeadSeeReferences($object_descriptor, $object_descriptor["version_description"]);
            if (!empty($dead_refs)) {
                PhpDocWatcher::trackWarning("The version description in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
            }
        }

        if (!empty($object_descriptor["see"])) {
            foreach ($object_descriptor["see"] as $object_data) {
                if (empty($object_data["description"])) {
                    continue;
                }

                $dead_refs = $this->hasDeadSeeReferences($object_descriptor, $object_data["description"]);
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

                $dead_refs = $this->hasDeadSeeReferences($object_descriptor, $object_data["description"]);
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

                $dead_refs = $this->hasDeadSeeReferences($object_descriptor, $object_data["description"]);
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

                $dead_refs = $this->hasDeadSeeReferences($object_descriptor, $object_data["description"]);
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

                $dead_refs = $this->hasDeadSeeReferences($object_descriptor, $object_data["description"]);
                if (!empty($dead_refs)) {
                    PhpDocWatcher::trackWarning("The description of the parameter '$object_data[name]' in the docblock of the $read_name '" . $object_descriptor["name"] . "' has references to non-existing objects: " . implode(", ", $dead_refs) . "!" . $appendix);
                }
            }
        }
    } // checkDeadSeeReferences

    /**
     * Finds unresolved inline see references in one description.
     *
     * @param array<string, mixed> $scope
     * The declaration that owns the description.
     *
     * @param string $contents
     * The description to scan.
     *
     * @return array<string, string>
     * Returns unresolved names, indexed by their displayed spelling.
     *
     * @author Oleg Schildt
     */
    protected function hasDeadSeeReferences(array $scope, string $contents): array
    {
        $dead_refs = [];

        preg_match_all('/\{@see\s+([^\s{}]+)/', $contents, $matches);

        foreach ($matches[1] as $name) {
            if (!preg_match('~^https?://~i', $name) && $this->references->resolve($name, $scope) === null) {
                $dead_refs[$name] = $name;
            }
        }

        return $dead_refs;
    } // hasDeadSeeReferences

    /**
     * Resolves see and uses tags in their lexical scope and records reverse uses links.
     *
     * @param array<string, mixed> $descriptor
     * The declaration containing the references.
     *
     * @return void
     * Stores resolvable targets and reports unknown or ambiguous names.
     *
     * @author Oleg Schildt
     */
    protected function resolveObjectReferences(array &$descriptor): void
    {
        $location = $descriptor['source_file']['full_name'] . ':' . $descriptor['start_line'];

        foreach (['see', 'uses'] as $kind) {
            if (empty($descriptor[$kind])) {
                continue;
            }

            foreach ($descriptor[$kind] as &$tag) {
                $reference = (string) $tag['reference'];

                if ($kind === 'see' && preg_match('~^https?://~i', $reference)) {
                    $tag['reference'] = $reference;

                    continue;
                }

                $target = $this->references->resolve($reference, $descriptor);

                if ($target === null) {
                    $tag['reference'] = $reference;

                    PhpDocWatcher::trackWarning("$location: @{$kind} on {$descriptor['name']} refers to unknown '$reference'.");

                    continue;
                }

                $tag['reference'] = $target;

                if ($kind === 'uses') {
                    $this->dictionary['lookup'][$target]['used-by'][$descriptor['full_name']] = [
                        'reference' => $descriptor['full_name'],
                        'description' => $tag['description'],
                    ];
                }
            }

            unset($tag);
        }
    } // resolveObjectReferences

    /**
     * Prints the native declaration and records PHP attributes and hierarchy relationships.
     *
     * @param array<string, mixed> $descriptor
     * The model entry to complete.
     *
     * @return void
     * Adds a signature and any documented reverse relationships.
     *
     * @author Oleg Schildt
     */
    protected function completeDeclaration(array &$descriptor): void
    {
        $statement = $descriptor['parent_stmt'] ?? $descriptor['stmt'] ?? null;

        if ($statement === null) {
            return;
        }

        $item = isset($descriptor['parent_stmt']) ? $descriptor['stmt'] : null;
        $descriptor['php_statement'] = $this->getPhpStatement($statement, $item);

        $this->applyDeprecationAttribute($descriptor, $statement);

        if (!empty($descriptor['promoted'])) {
            $constructor = $descriptor['parent']['full_name'] . '::__construct()';
            $parameter = $descriptor['parent']['methods'][$constructor]['params'][$descriptor['short_name']] ?? [];
            $descriptor['doc_type'] = $parameter['doc_type'] ?? null;
            $descriptor['description'] = $parameter['description'] ?? '';
        }

        foreach ([
            'extends' => 'known_inheritances',
            'implements' => 'known_implementations',
            'uses_traits' => 'known_usages'
        ] as $relation => $reverse) {
            if (empty($descriptor[$relation])) {
                continue;
            }

            foreach ($descriptor[$relation] as &$name) {
                $target = $this->references->resolve($name, $descriptor, true);

                if ($target !== null) {
                    $name = $target;
                    $this->dictionary['lookup'][$target][$reverse][$descriptor['full_name']] = $descriptor['full_name'];
                }
            }

            unset($name);
        }
    } // completeDeclaration

    /**
     * Adds native Deprecated attributes to the same log used for PHPDoc deprecations.
     *
     * @param array<string, mixed> $descriptor
     * The documented declaration.
     *
     * @param Stmt $statement
     * The native statement carrying the attributes.
     *
     * @return void
     * Records the attribute and its literal message or version, if present.
     *
     * @author Oleg Schildt
     */
    protected function applyDeprecationAttribute(array &$descriptor, Stmt $statement): void
    {
        foreach ($statement->attrGroups ?? [] as $group) {
            foreach ($group->attrs as $attribute) {
                $resolved = $attribute->name->getAttribute('resolvedName', $attribute->name);

                if (strcasecmp($resolved->toString(), 'Deprecated') !== 0) {
                    continue;
                }

                $descriptor['deprecated'] = true;
                $this->dictionary['deprecated'][$descriptor['full_name']] = &$descriptor;

                foreach ($attribute->args as $index => $argument) {
                    $name = $argument->name?->toString() ?? (['message', 'since'][$index] ?? '');

                    if ($argument->value instanceof Node\Scalar\String_) {
                        if ($name === 'message') {
                            $descriptor['deprecated_description'] = $argument->value->value;
                        } elseif ($name === 'since') {
                            $descriptor['deprecated_version'] = $argument->value->value;
                        }
                    }
                }
            }
        }
    } // applyDeprecationAttribute

    /**
     * Finds the nearest documented declaration of an inherited member.
     *
     * @param array<string, mixed> $object_descriptor
     * The owning class-like declaration.
     *
     * @param string $short_name
     * The member name, including parentheses for a method.
     *
     * @param string $target
     * Receives the inherited member's lookup key when found.
     *
     * @param string $type
     * The member collection, such as methods or properties.
     *
     * @return void
     * Leaves the target empty when there is no documented match.
     *
     * @author Oleg Schildt
     */
    protected function checkOverrides(array $object_descriptor, string $short_name, string &$target, string $type): void
    {
        $owners = array_merge(
            $object_descriptor['extends'] ?? [],
            $object_descriptor['implements'] ?? [],
            $object_descriptor['uses_traits'] ?? []
        );
        $visited = [$object_descriptor['full_name'] => true];

        for ($index = 0; isset($owners[$index]); ++$index) {
            $name = $owners[$index];
            $owner = $this->dictionary['lookup'][$name] ?? null;

            if ($owner === null || isset($visited[$name])) {
                continue;
            }

            $visited[$name] = true;
            $reference = $this->references->resolve($name . '::' . $short_name, $object_descriptor);
            $member = $owner[$type][$reference ?? ''] ?? null;

            if ($member !== null && (($member['flags'] ?? 0) & \PhpParser\Modifiers::PRIVATE) === 0) {
                $target = $reference;

                return;
            }

            array_push($owners, ...($owner['extends'] ?? []), ...($owner['implements'] ?? []), ...($owner['uses_traits'] ?? []));
        }
    } // checkOverrides
} // PhpDocParser



