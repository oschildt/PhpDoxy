<?php

use PhpDoxy\PhpDocWatcher;

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    $dtrace = debug_backtrace();

    PhpDocWatcher::trackError(format_backtrace($dtrace));
});

function format_backtrace(&$info)
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

            if (false && !empty($info_entry["args"][4])) {
                $trace .= "\r\nLocal variables:\r\n\r\n";

                foreach ($info_entry["args"][4] as $nm => $val) {
                    $trace .= $nm . " = ";
                    if (is_array($val)) {
                        $trace .= deep_implode($val);
                    } elseif (is_object($val)) {
                        $trace .= "obj:" . get_class($val);
                    } else {
                        $trace .= $val;
                    }
                    $trace .= "\r\n";
                }
            }

            $trace .= "\r\nCall stack:\r\n\r\n" . extract_call_stack($info);

            continue;
        }

        $args = (isset($info_entry["args"])) ? $info_entry["args"] : array();
        $trace .= val_or_empty($info_entry["file"]) . "\r\n" .
            "line: " . val_or_empty($info_entry["line"]) . "\r\n" .
            $info_entry["function"] . "(" . make_arg_list($args) . ")";
    }

    return $trace;
} // format_backtrace

function deep_implode(&$arr)
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

function make_arg_list(&$args)
{
    return;

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

function val_or_empty(&$param)
{
    return isset($param) ? $param : "";
}

function extract_call_stack($btrace)
{
    if (empty($btrace) || !is_array($btrace)) {
        return "";
    }

    $trace = "";

    $indent = "";
    foreach ($btrace as $btrace_entry) {


        if (!empty($btrace_entry["function"]) &&
            ($btrace_entry["function"] == "handle_error" ||
                strpos($btrace_entry["function"], "{closure}") !== false ||
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
