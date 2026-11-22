<?php

namespace PhpDoxy;

function aux_dump_array(array $array, int $level, int $max, array $filter = []): void
{
    if (!empty($array["short_name"]) && !empty($filter) &&
        !in_array($array["short_name"], $filter)) {
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
}

function preg_p_escape(string $pttr): string
{
    return preg_replace("/[\\\\\\[\\]\\+\\?\\-\\^\\$\\(\\)\\/\\.\\|\\{\\}\\|]/", "\\\\$0", $pttr);
} // preg_p_escape

function get_templates_dir(): false|string
{
    return realpath(__DIR__ . DIRECTORY_SEPARATOR . "../templates");
} // get_templates_dir

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
