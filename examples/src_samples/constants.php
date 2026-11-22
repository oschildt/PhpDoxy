<?php
/**
 * This is a file for demonstration of the global constants.
 *
 * @author Oleg Schildt
 */

/**
 * This is the namespace SmartFactory.
 *
 * @author Oleg Schildt
 */
namespace SmartFactory;

/**
 * Error codes for parsing, content, and operating system failures.
 *
 * @author Oleg Schildt
 */
const
    /**
     * Identifies an error encountered while parsing input.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    ERR_PARSE = "parse_error",

    /**
     * Identifies an error in the content being processed.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    ERR_CONTENT = "content_error",

    /**
     * Identifies an operating system error.
     *
     * @var string
     *
     * @author Oleg Schildt
     */
    ERR_OS = "os_error"
;

/**
 * Identifies a system-level error.
 *
 * @var string
 *
 * @author Oleg Schildt
 */
const ERR_SYSTEM = "system_error";

/**
 * Identifies a user error.
 *
 * @var string
 *
 * @author Oleg Schildt
 */
const ERR_USER = "user_error";
