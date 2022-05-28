<?php

namespace PhpDoxy;

class PhpDocWatcher
{
    public static $errors = [];
    public static $warnings = [];

    public static function trackWarning($warning)
    {
        self::$warnings[$warning] = $warning;
    } // trackWarning

    public static function trackError($error)
    {
        self::$errors[$error] = $error;
    } // trackError

    public static function hasErrors()
    {
        return count(self::$errors);
    } // hasErrors

    public static function hasWarnings()
    {
        return count(self::$warnings);
    } // hasWarnings

    public static function reportErrors()
    {
        $cnt = count(self::$errors);
        if($cnt == 0) return;

        $counter = 1;
        foreach(self::$errors as $error) {
            echo("\n\n" . "Error " . ($counter++) . " of $cnt:\n\n");
            echo_error($error);
        }
    } // reportErrors

    public static function reportWarnings()
    {
        $cnt = count(self::$warnings);
        if($cnt == 0) return;

        $counter = 1;
        foreach(self::$warnings as $warning) {
            echo("\n\n" . "Warning " . ($counter++) . " of $cnt:\n\n");
            echo_warning($warning);
        }
    } // reportWarnings
} // PhpDocWatcher

