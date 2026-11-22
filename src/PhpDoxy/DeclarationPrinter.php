<?php

namespace PhpDoxy;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\PrettyPrinter\Standard;

/**
 * Prints native declarations with indented array entries in constant values.
 *
 * @author Oleg Schildt
 */
final class DeclarationPrinter extends Standard
{
    private bool $printingConstant = false;

    /**
     * Enables multiline arrays while printing a global or class constant value.
     *
     * @param Node\Const_ $node
     * The constant declaration item, including its name and value.
     *
     * @return string
     * Returns the constant item as PHP source.
     *
     * @author Oleg Schildt
     */
    protected function pConst(Node\Const_ $node): string
    {
        $previous = $this->printingConstant;
        $this->printingConstant = true;

        try {
            return parent::pConst($node);
        } finally {
            $this->printingConstant = $previous;
        }
    } // pConst

    /**
     * Prints each nonempty array in a constant value with one entry per line.
     *
     * @param Array_ $node
     * An array expression from the declaration.
     *
     * @return string
     * Returns the array in its original bracket or array() syntax.
     *
     * @author Oleg Schildt
     */
    protected function pExpr_Array(Array_ $node): string
    {
        if (!$this->printingConstant || $node->items === []) {
            return parent::pExpr_Array($node);
        }

        $syntax = $node->getAttribute('kind', $this->shortArraySyntax ? Array_::KIND_SHORT : Array_::KIND_LONG);
        $entries = $this->pCommaSeparatedMultiline($node->items, true) . $this->nl;

        return $syntax === Array_::KIND_SHORT ? '[' . $entries . ']' : 'array(' . $entries . ')';
    } // pExpr_Array
} // DeclarationPrinter
