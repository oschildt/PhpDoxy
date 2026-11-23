<?php

namespace PhpDoxy;

use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\Type;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * Formats type expressions with structural indentation and links to documented classes.
 *
 * @author Oleg Schildt
 */
final class DocTypeRenderer
{
    /**
     * Lexer used to tokenize native type expressions before rendering.
     *
     * @var Lexer
     *
     * @author Oleg Schildt
     */
    private Lexer $lexer;

    /**
     * Parser that turns native type expressions into a syntax tree for rendering.
     *
     * @var TypeParser
     *
     * @author Oleg Schildt
     */
    private TypeParser $parser;

    /**
     * Initializes the type parser used for native type expressions.
     *
     * @author Oleg Schildt
     */
    public function __construct()
    {
        $config = new ParserConfig([]);
        $this->lexer = new Lexer($config);
        $this->parser = new TypeParser($config, new ConstExprParser($config));
    } // __construct

    /**
     * Formats a native type using the same structure as a documented type.
     *
     * @param string $type
     * A nonempty PHP type expression from a native declaration.
     *
     * @param callable(string): ?string $href
     * Looks up a link destination for a class name.
     *
     * @return string
     * Returns escaped HTML, using the original text if the type cannot be parsed.
     *
     * @author Oleg Schildt
     */
    public function renderNative(string $type, callable $href): string
    {
        $tokens = new TokenIterator($this->lexer->tokenize($type));

        try {
            $node = $this->parser->parse($tokens);
        } catch (ParserException) {
            return htmlspecialchars($type);
        }

        return $tokens->isCurrentTokenType(Lexer::TOKEN_END) ? $this->render($node, $href) : htmlspecialchars($type);
    } // renderNative

    /**
     * Renders a type tree into escaped HTML without changing the original nodes.
     *
     * @param TypeNode $type
     * The PHPDoc type to display.
     *
     * @param callable(string): ?string $href
     * Looks up a link destination for a class name and returns null when no page exists.
     *
     * @return string
     * Returns the type with line breaks, indentation, and anchors for linkable class names.
     *
     * @author Oleg Schildt
     */
    public function render(TypeNode $type, callable $href): string
    {
        $expression = (string) $type;
        $prefix = 'PhpDoxyType' . hash('sha256', $expression);
        $links = [];

        while (str_contains($expression, $prefix)) {
            $prefix .= 'X';
        }

        $name = static function (string $value) use ($href, $prefix, &$links): string {
            $url = $href($value);

            if ($url === null) {
                return $value;
            }

            $placeholder = $prefix . count($links) . 'End';
            $links[$placeholder] = '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($value) . '</a>';

            return $placeholder;
        };

        $copy = $this->copyWithLinks($type, $name);

        return strtr(htmlspecialchars($this->format($copy)), $links);
    } // render

    /**
     * Formats a type while keeping unions and intersections inline when none of their members span lines.
     *
     * @param Node $node
     * A PHPDoc syntax node with link placeholders already assigned.
     *
     * @return string
     * Returns the formatted expression before HTML escaping.
     *
     * @author Oleg Schildt
     */
    private function format(Node $node): string
    {
        if ($node instanceof Type\ArrayShapeNode || $node instanceof Type\ObjectShapeNode) {
            $items = array_map($this->format(...), $node->items);

            if ($node instanceof Type\ArrayShapeNode && !$node->sealed) {
                $items[] = '...' . ($node->unsealedType === null ? '' : $this->format($node->unsealedType));
            }

            return ($node instanceof Type\ArrayShapeNode ? $node->kind : 'object')
                . $this->formatList('{', $items, '}', multiline: true);
        }

        if ($node instanceof Type\ArrayShapeItemNode || $node instanceof Type\ObjectShapeItemNode) {
            $key = $node->keyName === null ? '' : $node->keyName . ($node->optional ? '?: ' : ': ');

            return $key . $this->format($node->valueType);
        }

        if ($node instanceof Type\UnionTypeNode || $node instanceof Type\IntersectionTypeNode) {
            $operator = $node instanceof Type\UnionTypeNode ? '| ' : '& ';
            $members = array_map(function (TypeNode $type): string {
                $formatted = $this->format($type);

                return $type instanceof Type\NullableTypeNode ? '(' . $formatted . ')' : $formatted;
            }, $node->types);
            $inline = implode(' ' . $operator, $members);

            if (!str_contains($inline, "\n")) {
                return '(' . $inline . ')';
            }

            return "(\n" . $this->indent(implode("\n" . $operator, $members)) . "\n)";
        }

        if ($node instanceof Type\GenericTypeNode) {
            $arguments = [];

            foreach ($node->genericTypes as $index => $type) {
                $variance = $node->variances[$index] ?? Type\GenericTypeNode::VARIANCE_INVARIANT;
                $arguments[] = match ($variance) {
                    Type\GenericTypeNode::VARIANCE_INVARIANT => $this->format($type),
                    Type\GenericTypeNode::VARIANCE_BIVARIANT => '*',
                    default => $variance . ' ' . $this->format($type),
                };
            }

            return $node->type . $this->formatList('<', $arguments, '>');
        }

        if ($node instanceof Type\ArrayShapeUnsealedTypeNode) {
            $arguments = $node->keyType === null ? [$node->valueType] : [$node->keyType, $node->valueType];

            return $this->formatList('<', array_map($this->format(...), $arguments), '>');
        }

        if ($node instanceof Type\CallableTypeNode) {
            $templates = array_map($this->format(...), $node->templateTypes);
            $parameters = array_map($this->format(...), $node->parameters);
            $returnType = $this->format($node->returnType);

            if ($node->returnType instanceof Type\CallableTypeNode) {
                $returnType = '(' . $returnType . ')';
            }

            return $node->identifier
                . ($templates === [] ? '' : $this->formatList('<', $templates, '>'))
                . $this->formatList('(', $parameters, ')', multiline: count($parameters) > 1)
                . ': ' . $returnType;
        }

        if ($node instanceof Type\CallableTypeParameterNode) {
            $markers = ($node->isReference ? '&' : '') . ($node->isVariadic ? '...' : '') . $node->parameterName;

            return rtrim($this->format($node->type) . ' ' . $markers) . ($node->isOptional ? '=' : '');
        }

        if ($node instanceof TemplateTagValueNode) {
            $declaration = $node->name;

            foreach (['bound' => ' of ', 'lowerBound' => ' super ', 'default' => ' = '] as $field => $prefix) {
                if ($node->$field !== null) {
                    $declaration .= $prefix . $this->format($node->$field);
                }
            }

            return rtrim($declaration . ' ' . $node->description);
        }

        if ($node instanceof Type\ArrayTypeNode || $node instanceof Type\OffsetAccessTypeNode) {
            $base = $this->format($node->type);

            if (
                $node->type instanceof Type\CallableTypeNode || $node->type instanceof Type\NullableTypeNode
                || ($node instanceof Type\ArrayTypeNode && $node->type instanceof Type\ConstTypeNode)
            ) {
                $base = '(' . $base . ')';
            }

            return $base . ($node instanceof Type\ArrayTypeNode ? '[]' : '[' . $this->format($node->offset) . ']');
        }

        if ($node instanceof Type\NullableTypeNode) {
            $type = $this->format($node->type);

            return $node->type instanceof Type\NullableTypeNode ? '?(' . $type . ')' : '?' . $type;
        }

        if ($node instanceof Type\ConditionalTypeNode || $node instanceof Type\ConditionalTypeForParameterNode) {
            $subject = $node instanceof Type\ConditionalTypeNode ? $this->format($node->subjectType) : $node->parameterName;

            if ($node instanceof Type\ConditionalTypeNode && $node->subjectType instanceof Type\NullableTypeNode) {
                $subject = '(' . $subject . ')';
            }

            $body = $subject . ($node->negated ? ' is not ' : ' is ') . $this->format($node->targetType)
                . "\n? " . $this->format($node->if) . "\n: " . $this->format($node->else);

            return "(\n" . $this->indent($body) . "\n)";
        }

        return (string) $node;
    } // format

    /**
     * Prints a comma-separated list with a line per item when requested or when a child spans lines.
     *
     * @param string $open
     * The opening delimiter.
     *
     * @param list<string> $items
     * The formatted entries.
     *
     * @param string $close
     * The closing delimiter.
     *
     * @param bool $multiline
     * Whether to place each entry on a separate line even when the entries are short.
     *
     * @return string
     * Returns the delimited list.
     *
     * @author Oleg Schildt
     */
    private function formatList(string $open, array $items, string $close, bool $multiline = false): string
    {
        $joined = implode(', ', $items);

        if ($items === [] || (!$multiline && !str_contains($joined, "\n"))) {
            return $open . $joined . $close;
        }

        return $open . "\n" . $this->indent(implode(",\n", $items)) . "\n" . $close;
    } // formatList

    /**
     * Indents every line of a nested type by one level.
     *
     * @param string $text
     * The formatted child expression.
     *
     * @return string
     * Returns the child expression indented by four spaces per line.
     *
     * @author Oleg Schildt
     */
    private function indent(string $text): string
    {
        return '    ' . str_replace("\n", "\n    ", $text);
    } // indent

    /**
     * Copies the tree while replacing class names with placeholders for HTML links.
     *
     * @param Node $node
     * The original PHPDoc node.
     *
     * @param callable(string): string $name
     * Returns a class name or its rendering placeholder.
     *
     * @return Node
     * Returns a copy with the same concrete node class.
     *
     * @author Oleg Schildt
     */
    private function copyWithLinks(Node $node, callable $name): Node
    {
        $copy = clone $node;

        if ($copy instanceof Type\IdentifierTypeNode) {
            $copy->name = $name($copy->name);
        } elseif ($copy instanceof ConstFetchNode && $copy->className !== '') {
            $copy->className = $name($copy->className);
        }

        foreach (get_object_vars($node) as $key => $value) {
            // A shape's key can be an identifier node whose text is a field name.
            if ($key === 'keyName' && !$value instanceof ConstFetchNode) {
                continue;
            }

            if ($value instanceof Node) {
                $copy->$key = $this->copyWithLinks($value, $name);
            } elseif (is_array($value)) {
                foreach ($value as $index => $item) {
                    if ($item instanceof Node) {
                        $copy->{$key}[$index] = $this->copyWithLinks($item, $name);
                    }
                }
            }
        }

        return $copy;
    } // copyWithLinks
} // DocTypeRenderer

