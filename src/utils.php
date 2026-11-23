<?php

namespace PhpDoxy;

/**
 * Prints an HTML-escaped, indented dump of an array.
 *
 * @param array<array-key, mixed> $array
 * The array to dump. Nonempty leaf values must be convertible to strings.
 *
 * @param int $level
 * The current indentation level, starting at zero for the outer array.
 *
 * @param int $max
 * The maximum level to expand. Deeper arrays are shown as an ellipsis.
 *
 * @param array<array-key, string> $filter
 * Allowed short names for the supplied array. An empty filter accepts all names,
 * and arrays without a short name pass the filter.
 *
 * @return void
 * Writes the dump directly to the output, omitting known descriptor links and syntax nodes.
 *
 * @throws \TypeError
 * If a nonempty leaf value cannot be converted to a string.
 *
 * @throws \ValueError
 * If the starting indentation level is negative.
 *
 * @author Oleg Schildt
 */
function aux_dump_array(array $array, int $level, int $max, array $filter = []): void
{
    if (
        !empty($array["short_name"]) && !empty($filter) &&
        !in_array($array["short_name"], $filter)
    ) {
        return;
    }

    $indent = str_repeat("   ", ($level + 1));
    $indent2 = str_repeat("   ", $level);
    if ($level > $max) {
        echo "...\n";
        return;
    }

    echo "[\n";
    foreach ($array as $key => $val) {
        if ($key == "namespace" || $key == "parent" || $key == "stmt" || $key == "parent_stmt") {
            continue;
        }

        if (is_array($val)) {
            echo htmlspecialchars($indent . $key . " = ");
            aux_dump_array($val, $level + 1, $max);
        } else {
            if (empty($val)) {
                $val = "";
            }
            echo htmlspecialchars($indent . $key . " = " . substr(preg_replace("/[\n\r\t]+/", " ", $val), 0, 80)) . "\n";
        }
    }
    echo $indent2 . "]\n\n";
} // aux_dump_array

/**
 * Escapes selected characters for use in a slash-delimited regular expression.
 *
 * @param string $pttr
 * The text to escape.
 *
 * @return string
 * Returns the text with backslashes added before backslashes, slashes, brackets, braces,
 * parentheses, plus signs, question marks, hyphens, carets, dollar signs, periods, and vertical bars.
 *
 * @author Oleg Schildt
 */
function preg_p_escape(string $pttr): string
{
    return preg_replace("/[\\\\\\[\\]\\+\\?\\-\\^\\$\\(\\)\\/\\.\\|\\{\\}\\|]/", "\\\\$0", $pttr);
} // preg_p_escape

/**
 * Resolves the path to the bundled templates directory.
 *
 * @return string|false
 * Returns the canonical absolute path, or false if the path cannot be resolved.
 *
 * @author Oleg Schildt
 */
function get_templates_dir(): false|string
{
    return realpath(__DIR__ . DIRECTORY_SEPARATOR . "../templates");
} // get_templates_dir

/**
 * Passes a conditional template block to a callback for processing.
 *
 * @param string $token_name
 * The name used in the `if` and `endif` comment markers.
 *
 * @param string $contents
 * The template text, updated by reference.
 *
 * @param callable(string &$body, bool $found): mixed $fun
 * Receives the block body by reference and true when a block is found.
 * Otherwise it receives the entire template by reference and false. Its return value is ignored.
 *
 * @return void
 * Replaces a matched block and its markers with the processed body.
 *
 * @throws \Throwable
 * If the callback throws an exception or error, it is propagated to the caller.
 *
 * @author Oleg Schildt
 */
function checkIf(string $token_name, string &$contents, callable $fun): void
{
    if (!preg_match("/<!-- if $token_name -->(.*?)<!-- endif $token_name -->/ism", $contents, $matches)) {
        $fun($contents, false);
    } else {
        $block = $matches[0];
        $body = $matches[1];

        $fun($body, true);

        $contents = str_replace($block, $body, $contents);
    }
} // checkIf

/**
 * Passes a matching foreach template block to a callback for expansion.
 *
 * @param string $token_name
 * The name used in the `foreach` and `endforeach` comment markers.
 *
 * @param string $contents
 * The template text, updated by reference when a block is found.
 *
 * @param callable(string &$body, string $item_template): mixed $fun
 * Receives the body by reference and its original text as the item template. Its return value is ignored.
 *
 * @return void
 * Replaces the matched block and its markers with the expanded body. Leaves the template intact if the block is absent.
 *
 * @throws \Throwable
 * If the callback throws while expanding the matched block.
 *
 * @author Oleg Schildt
 */
function checkForeach(string $token_name, string &$contents, callable $fun): void
{
    if (!preg_match("/<!-- foreach $token_name -->(.*?)<!-- endforeach $token_name -->/ism", $contents, $matches)) {
        return;
    }

    $block = $matches[0];
    $body = $matches[1];

    $fun($body, $body);

    $contents = str_replace($block, $body, $contents);
} // checkForeach

/**
 * Fills placeholders and nested blocks in a template from one row of data.
 *
 * @param array<array-key, scalar|array> $row
 * Values keyed by placeholder name. Scalars are inserted as text, and arrays supply rows for nested blocks.
 *
 * @param string $contents
 * The row template to update by reference.
 *
 * @return void
 * Replaces placeholders and removes a matching conditional block when its value is empty.
 *
 * @author Oleg Schildt
 */
function explodeRow(array $row, string &$contents): void
{
    foreach ($row as $key => $val) {
        checkIf($key, $contents, function (&$body, $iffound) use ($key, $val) {
            if (empty($val) && $iffound) {
                $body = "";
                return;
            }

            if (is_array($val)) {
                explodeArray($key, $val, $body);
            } else {
                $body = str_replace("{" . $key . "}", $val, $body);
            }
        });
    }
} // explodeRow

/**
 * Expands a foreach template block by rendering its item template for every row.
 *
 * @param string $token_name
 * The name of the foreach block and any enclosing conditional block of the same name.
 *
 * @param array<array-key, array<array-key, scalar|array>> $array
 * The rows to render in iteration order, keyed by placeholder name within each row.
 *
 * @param string $contents
 * The template text to update by reference.
 *
 * @return void
 * Replaces the foreach block with concatenated rows and trims the processed section.
 * Removes the matching conditional block when the row list is empty.
 *
 * @author Oleg Schildt
 */
function explodeArray(string $token_name, array $array, string &$contents): void
{
    checkIf($token_name, $contents, function (&$body, $iffound) use ($token_name, $array) {
        if (empty($array) && $iffound) {
            $body = "";
            return;
        }

        checkForeach($token_name, $body, function (&$body, $item_template) use ($array) {
            $body = "";

            if (empty($array)) {
                return;
            }

            foreach ($array as $row) {
                $item = $item_template;

                explodeRow($row, $item);

                $body .= $item;
            }
        });

        $body = trim($body);
    });
} // explodeArray

