<?php

namespace PhpDoxy;

function echo_standard($message): void
{
    echo $message;
} // echo_standard

function echo_highlighted($message): void
{
    if (php_sapi_name() == "cli") {
        echo "\033[95m";
        echo $message;
    } else {
        echo "<span style='color:magenta'>";
        echo htmlspecialchars($message);
    }

    if (php_sapi_name() == "cli") {
        echo "\033[39m";
    } else {
        echo "</span>";
    }
} // echo_highlighted

function echo_highlighted2($message): void
{
    if (php_sapi_name() == "cli") {
        echo "\033[96m";
        echo $message;
    } else {
        echo "<span style='color:cyan'>";
        echo htmlspecialchars($message);
    }

    if (php_sapi_name() == "cli") {
        echo "\033[39m";
    } else {
        echo "</span>";
    }
} // echo_highlighted2

function echo_success($message): void
{
    if (php_sapi_name() == "cli") {
        echo "\033[92m";
        echo $message;
    } else {
        echo "<span style='color:lime'>";
        echo htmlspecialchars($message);
    }

    if (php_sapi_name() == "cli") {
        echo "\033[39m";
    } else {
        echo "</span>";
    }
} // echo_success

function echo_warning($message): void
{
    if (php_sapi_name() == "cli") {
        echo "\033[93m";
        echo $message;
    } else {
        echo "<span style='color:yellow'>";
        echo htmlspecialchars($message);
    }

    if (php_sapi_name() == "cli") {
        echo "\033[39m";
    } else {
        echo "</span>";
    }
} // echo_warning

function echo_error($message): void
{
    if (php_sapi_name() == "cli") {
        echo "\033[91m";
        echo $message;
    } else {
        echo "<span style='color:red'>";
        echo htmlspecialchars($message);
    }

    if (php_sapi_name() == "cli") {
        echo "\033[39m";
    } else {
        echo "</span>";
    }
} // echo_error

