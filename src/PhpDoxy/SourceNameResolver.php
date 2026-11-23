<?php

namespace PhpDoxy;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

/**
 * Captures the PHP import context at the declarations included in the documentation.
 *
 * @author Oleg Schildt
 */
final class SourceNameResolver extends NameResolver
{
    /**
     * Associates declarations with their scope while resolving native PHP names.
     *
     * @param Node $node
     * The node visited by PHP-Parser.
     *
     * @return int|null
     * Returns the traversal directive for function bodies, or null to continue normally.
     *
     * @throws \PhpParser\Error
     * If the node contains an invalid name or import.
     *
     * @author Oleg Schildt
     */
    public function enterNode(Node $node): ?int
    {
        parent::enterNode($node);

        if (
            $node instanceof Node\Stmt\ClassLike || $node instanceof Node\Stmt\Function_
            || $node instanceof Node\Stmt\Const_ || $node instanceof Node\Stmt\Expression
        ) {
            $node->setAttribute('docNameContext', clone $this->getNameContext());
        }

        // The source pages render bodies directly from the file, without resolved names.
        return $node instanceof Node\FunctionLike ? NodeTraverser::DONT_TRAVERSE_CHILDREN : null;
    } // enterNode
} // SourceNameResolver

