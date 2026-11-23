<?php

/**
 * Formats an error handler backtrace as diagnostic text.
 *
 * @param array<int, array{function: string, args?: array<array-key, mixed>, file?: string, line?: int, ...}> $info
 * The backtrace passed by reference. Frame zero must contain the error handler arguments,
 * with the message, filename, and line number at indexes 1, 2, and 3 of `args`.
 *
 * @return string
 * Returns the error details, call stack, and frame summaries, or `backtrace empty` for an empty trace.
 *
 * @author Oleg Schildt
 */
function format_backtrace(array &$info): string
{
    if (!isset($info) || count($info) == 0) {
        return "backtrace empty";
    }

    $trace = "";

    foreach ($info as $nr => &$info_entry) {
        if (!empty($trace)) {
            $trace .= "\r\n------------------\r\n";
        }

        if ($nr == 0) {
            $trace .= $info_entry["args"][2] . "\r\n" .
                "line: " . $info_entry["args"][3] . "\r\n" .
                $info_entry["args"][1] . "\r\n";

            $trace .= "\r\nCall stack:\r\n\r\n" . extract_call_stack($info);

            continue;
        }

        $args = (isset($info_entry["args"])) ? $info_entry["args"] : array();
        $trace .= ($info_entry["file"] ?? "") . "\r\n" .
            "line: " . ($info_entry["line"] ?? "") . "\r\n" .
            $info_entry["function"] . "(" . make_arg_list($args) . ")";
    }

    return $trace;
} // format_backtrace

/**
 * Formats a nested array as a bracketed diagnostic string.
 *
 * @param array<array-key, mixed> $arr
 * An acyclic array passed by reference. Values may be nested arrays, objects,
 * or values that can be concatenated as strings.
 *
 * @return string
 * Returns comma-separated entries inside brackets. Scalar entries include their keys,
 * nested arrays use the same bracketed format, and objects are shown as `obj:` followed by their class name.
 *
 * @author Oleg Schildt
 */
function deep_implode(array &$arr): string
{
    $list = "";

    foreach ($arr as $nm => &$val) {
        if (is_array($val)) {
            $list .= deep_implode($val) . ", ";
        } elseif (is_object($val)) {
            $list .= "obj:" . get_class($val) . ", ";
        } else {
            $list .= $nm . "=" . $val . ", ";
        }
    }

    return "[" . trim($list, ", ") . "]";
} // deep_implode

/**
 * Supplies the argument text for a formatted stack frame while argument display is disabled.
 *
 * @param array<array-key, mixed> $args
 * The frame arguments, passed by reference.
 *
 * @return string
 * Always returns an empty string.
 *
 * @author Oleg Schildt
 */
function make_arg_list(array &$args): string
{
    // Argument values are omitted from the formatted backtrace.
    return "";

    $list = "";

    foreach ($args as $arg) {
        if (is_array($arg)) {
            $list .= deep_implode($arg) . ", ";
        } elseif (is_object($arg)) {
            $list .= "obj:" . get_class($arg) . ", ";
        } else {
            $list .= $arg . ", ";
        }
    }

    return trim($list, ", ");
} // make_arg_list

/**
 * Builds an indented call stack for the backtrace report.
 *
 * @param array<array-key, array{function?: string, args?: array<array-key, mixed>, file?: string, line?: int, ...}> $btrace
 * The backtrace frames in their original order. Frames from error handlers, closures, and
 * `trigger_error`, along with frames without a filename, are skipped.
 *
 * @return string
 * Returns the formatted frame lines with function names and file locations when available,
 * or an empty string when no frames qualify.
 *
 * @author Oleg Schildt
 */
function extract_call_stack(array $btrace): string
{
    if (empty($btrace) || !is_array($btrace)) {
        return "";
    }

    $trace = "";

    $indent = "";
    foreach ($btrace as $btrace_entry) {


        if (
            !empty($btrace_entry["function"]) &&
            ($btrace_entry["function"] == "handle_error" ||
                str_contains($btrace_entry["function"], "{closure}") ||
                $btrace_entry["function"] == "handleError" ||
                $btrace_entry["function"] == "trigger_error"
            )
        ) {
            continue;
        }

        if (empty($btrace_entry["file"])) {
            continue;
        }

        if (!empty($btrace_entry["function"])) {
            $trace .= $indent . $btrace_entry["function"] . "() ";
        }

        $trace .= "[";

        $trace .= basename($btrace_entry["file"]);

        $trace .= ", ";

        if (empty($btrace_entry["line"])) {
            $trace .= "line number undefined";
        } else {
            $trace .= $btrace_entry["line"];
        }

        $trace .= "]";

        $args = (isset($btrace_entry["args"])) ? $btrace_entry["args"] : [];
        $args_str = make_arg_list($args);

        if (!empty($btrace_entry["function"]) && !empty($args_str)) {
            $trace .= "  " . $btrace_entry["function"] . "(" . $args_str . ")";
        }

        $trace .= "\r\n";

        $indent .= "  ";
    }

    return trim($trace);
} // extract_call_stack

