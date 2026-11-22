<?php

namespace PhpDoxy;

class PhpDocWatcher
{
    public static array $errors = [];
    public static array $warnings = [];

    /**
     * Clears diagnostics from the previous generation in this process.
     *
     * @author Oleg Schildt
     */
    public static function reset(): void
    {
        self::$errors = [];
        self::$warnings = [];
    } // reset

    public static function trackWarning(string $warning): void
    {
        self::$warnings[$warning] = $warning;
    } // trackWarning

    public static function trackError(string $error): void
    {
        self::$errors[$error] = $error;
    } // trackError

    public static function hasErrors(): int
    {
        return count(self::$errors);
    } // hasErrors

    public static function hasWarnings(): int
    {
        return count(self::$warnings);
    } // hasWarnings

    public static function reportErrors(): void
    {
        $cnt = count(self::$errors);
        if ($cnt == 0) return;

        $counter = 1;
        foreach (self::$errors as $error) {
            echo("\n\n" . "Error " . ($counter++) . " of $cnt:\n\n");
            echo_error($error);
        }
    } // reportErrors

    public static function reportWarnings(): void
    {
        $cnt = count(self::$warnings);
        if ($cnt == 0) return;

        $counter = 1;
        foreach (self::$warnings as $warning) {
            echo("\n\n" . "Warning " . ($counter++) . " of $cnt:\n\n");
            echo_warning($warning);
        }
    } // reportWarnings
} // PhpDocWatcher

