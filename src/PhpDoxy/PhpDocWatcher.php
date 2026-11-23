<?php

namespace PhpDoxy;

/**
 * Collects and reports unique errors and warnings from a documentation run.
 *
 * @author Oleg Schildt
 */
class PhpDocWatcher
{
    /**
     * Error messages keyed by message text to deduplicate repeated reports.
     *
     * @var array<array-key, string>
     *
     * @author Oleg Schildt
     */
    public static array $errors = [];

    /**
     * Warning messages keyed by message text to deduplicate repeated reports.
     *
     * @var array<array-key, string>
     *
     * @author Oleg Schildt
     */
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

    /**
     * Records a warning for reporting.
     *
     * @param string $warning
     * The warning message to record.
     *
     * @return void
     * Stores the message once even if it is reported repeatedly.
     *
     * @author Oleg Schildt
     */
    public static function trackWarning(string $warning): void
    {
        self::$warnings[$warning] = $warning;
    } // trackWarning

    /**
     * Records an error for reporting.
     *
     * @param string $error
     * The error message to record.
     *
     * @return void
     * Stores the message once even if it is reported repeatedly.
     *
     * @author Oleg Schildt
     */
    public static function trackError(string $error): void
    {
        self::$errors[$error] = $error;
    } // trackError

    /**
     * Counts the distinct error messages recorded for the current run.
     *
     * @return int
     * Returns the number of recorded errors, or zero if there are none.
     *
     * @author Oleg Schildt
     */
    public static function hasErrors(): int
    {
        return count(self::$errors);
    } // hasErrors

    /**
     * Counts the distinct warning messages recorded for the current run.
     *
     * @return int
     * Returns the number of recorded warnings, or zero if there are none.
     *
     * @author Oleg Schildt
     */
    public static function hasWarnings(): int
    {
        return count(self::$warnings);
    } // hasWarnings

    /**
     * Prints the recorded errors with their position and total count.
     *
     * @return void
     * Produces no output when there are no recorded errors.
     *
     * @author Oleg Schildt
     */
    public static function reportErrors(): void
    {
        $cnt = count(self::$errors);
        if ($cnt == 0)
            return;

        $counter = 1;
        foreach (self::$errors as $error) {
            echo ("\n\n" . "Error " . ($counter++) . " of $cnt:\n\n");
            echo_error($error);
        }
    } // reportErrors

    /**
     * Prints the recorded warnings with their position and total count.
     *
     * @return void
     * Produces no output when there are no recorded warnings.
     *
     * @author Oleg Schildt
     */
    public static function reportWarnings(): void
    {
        $cnt = count(self::$warnings);
        if ($cnt == 0)
            return;

        $counter = 1;
        foreach (self::$warnings as $warning) {
            echo ("\n\n" . "Warning " . ($counter++) . " of $cnt:\n\n");
            echo_warning($warning);
        }
    } // reportWarnings
} // PhpDocWatcher

