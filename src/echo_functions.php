<?php

namespace PhpDoxy;

/**
 * Writes a message directly to the output.
 *
 * @param string $message
 * The text to write as supplied.
 *
 * @return void
 * Emits the message without adding formatting or a newline.
 *
 * @author Oleg Schildt
 */
function echo_standard(string $message): void
{
    echo $message;
} // echo_standard

/**
 * Writes a highlighted message in magenta.
 *
 * @param string $message
 * The text to display. HTML special characters are escaped outside the CLI.
 *
 * @return void
 * Writes ANSI-colored text in the CLI or a magenta HTML span in other environments.
 *
 * @author Oleg Schildt
 */
function echo_highlighted(string $message): void
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

/**
 * Writes a highlighted message in cyan.
 *
 * @param string $message
 * The text to display. HTML special characters are escaped outside the CLI.
 *
 * @return void
 * Writes ANSI-colored text in the CLI or a cyan HTML span in other environments.
 *
 * @author Oleg Schildt
 */
function echo_highlighted2(string $message): void
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

/**
 * Writes a success message in bright green.
 *
 * @param string $message
 * The text to display. HTML special characters are escaped outside the CLI.
 *
 * @return void
 * Writes ANSI-colored text in the CLI or a bright green HTML span in other environments.
 *
 * @author Oleg Schildt
 */
function echo_success(string $message): void
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

/**
 * Writes a warning message in bright yellow.
 *
 * @param string $message
 * The text to display. HTML special characters are escaped outside the CLI.
 *
 * @return void
 * Writes ANSI-colored text in the CLI or a bright yellow HTML span in other environments.
 *
 * @author Oleg Schildt
 */
function echo_warning(string $message): void
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

/**
 * Writes an error message in bright red.
 *
 * @param string $message
 * The text to display. HTML special characters are escaped outside the CLI.
 *
 * @return void
 * Writes ANSI-colored text in the CLI or a bright red HTML span in other environments.
 *
 * @author Oleg Schildt
 */
function echo_error(string $message): void
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

