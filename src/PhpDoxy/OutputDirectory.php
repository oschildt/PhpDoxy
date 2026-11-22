<?php

namespace PhpDoxy;

use FilesystemIterator;
use RuntimeException;

/**
 * Publishes documentation built in a sibling directory of the configured output.
 *
 * @author Oleg Schildt
 */
final class OutputDirectory
{
    private string $target;

    /**
     * Validates the output location against all source directories, the template, and user files.
     *
     * @param array{sources: non-empty-list<string>, target: string, template: string, user_files?: list<array{path: string}>} $config
     * The input and output paths. The output's parent directory must already exist.
     *
     * @throws RuntimeException
     * If the source list is empty, or the output path is invalid, unsafe, or overlaps an input.
     *
     * @author Oleg Schildt
     */
    public function __construct(array $config)
    {
        if (empty($config['sources'])) {
            throw new RuntimeException('At least one source directory is required.');
        }

        $raw_target = rtrim($config['target'], '/\\');
        $name = basename($raw_target);
        $parent = realpath(dirname($raw_target));

        if ($parent === false || !is_dir($parent) || in_array($name, ['', '.', '..'], true)
            || (DIRECTORY_SEPARATOR === '\\' && (strpbrk($name, ':<>|?*"') !== false || rtrim($name, ' .') !== $name))
        ) {
            throw new RuntimeException('The target must have a name and an existing parent directory.');
        }

        $this->target = rtrim($parent, '/\\') . DIRECTORY_SEPARATOR . $name;

        if ($this->isLinked($this->target)) {
            throw new RuntimeException("The target '{$this->target}' must be a regular directory.");
        }

        foreach ([...$config['sources'], $config['template']] as $input) {
            $resolved = realpath($input);

            if ($resolved === false || !is_dir($resolved)) {
                throw new RuntimeException("Input directory '$input' does not exist.");
            }

            if (self::contains($this->target, $resolved) || self::contains($resolved, $this->target)) {
                throw new RuntimeException("The target '{$this->target}' overlaps input directory '$resolved'.");
            }
        }

        foreach ($config['user_files'] ?? [] as $file) {
            $resolved = realpath($file['path']);

            if ($resolved !== false && self::contains($this->target, $resolved)) {
                throw new RuntimeException("User file '$resolved' is inside the target directory.");
            }
        }
    } // __construct

    /**
     * Builds a site and publishes it under an exclusive lock for the target.
     *
     * @param callable(string): void $build
     * Receives an empty staging directory and writes the complete site there.
     *
     * @return void
     * Publishes the completed site and removes the previous site after publication.
     *
     * @throws RuntimeException
     * If the output is not an empty directory or a PhpDoxy site, or if staging, locking, or publication fails.
     *
     * @throws \Throwable
     * Propagates a build failure while keeping the previous site available.
     *
     * @author Oleg Schildt
     */
    public function publish(callable $build): void
    {
        $lock_path = $this->target . '.phpdoxy.lock';

        if ($this->isLinked($lock_path)) {
            throw new RuntimeException("The output lock '$lock_path' must be a regular file.");
        }

        $lock = @fopen($lock_path, 'c+');

        if ($lock === false) {
            throw new RuntimeException("Cannot open output lock '$lock_path'.");
        }

        $stage = null;

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException("Cannot lock '$lock_path'.");
            }

            $this->checkExistingTarget();

            $suffix = bin2hex(random_bytes(8));
            $stage_path = $this->target . '.phpdoxy-stage-' . $suffix;
            $previous = $this->target . '.phpdoxy-previous-' . $suffix;

            if (!@mkdir($stage_path)) {
                throw new RuntimeException("Cannot create staging directory '$stage_path'.");
            }

            $stage = $stage_path;

            $build($stage);

            $this->checkExistingTarget();

            $has_previous = is_dir($this->target);

            if ($has_previous && !@rename($this->target, $previous)) {
                throw new RuntimeException("Cannot move the previous site '{$this->target}'.");
            }

            if (!@rename($stage, $this->target)) {
                if ($has_previous && !@rename($previous, $this->target)) {
                    throw new RuntimeException("Cannot publish '{$this->target}'. The previous site is at '$previous'.");
                }

                throw new RuntimeException("Cannot publish '{$this->target}'.");
            }

            $stage = null;

            if ($has_previous) {
                $this->cleanUp($previous);
            }
        } finally {
            if ($stage !== null) {
                $this->cleanUp($stage);
            }

            fclose($lock);
        }
    } // publish

    /**
     * Compares two absolute paths with the platform's directory separators and case rules.
     *
     * @param string $first
     * The first absolute path.
     *
     * @param string $second
     * The second absolute path.
     *
     * @return bool
     * Returns true if the normalized paths match.
     *
     * @author Oleg Schildt
     */
    public static function samePath(string $first, string $second): bool
    {
        return self::pathKey($first) === self::pathKey($second);
    } // samePath

    /**
     * Tests whether an absolute path identifies a root or one of its descendants.
     *
     * @param string $root
     * The absolute root path.
     *
     * @param string $path
     * The absolute path to check.
     *
     * @return bool
     * Returns true for the root itself or a path within its directory tree.
     *
     * @author Oleg Schildt
     */
    private static function contains(string $root, string $path): bool
    {
        return self::samePath($root, $path) || str_starts_with(self::pathKey($path), self::pathKey($root) . '/');
    } // contains

    /**
     * Converts a path to a comparison key.
     *
     * @param string $path
     * An absolute path.
     *
     * @return string
     * Returns the platform-normalized path without trailing separators.
     *
     * @author Oleg Schildt
     */
    private static function pathKey(string $path): string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = mb_strtolower(str_replace('\\', '/', $path), 'UTF-8');
        }

        return rtrim($path, '/');
    } // pathKey

    /**
     * Detects a filesystem link, including a directory that resolves to another location.
     *
     * @param string $path
     * An absolute path built from a canonical parent.
     *
     * @return bool
     * Returns true if the path names a link or junction.
     *
     * @author Oleg Schildt
     */
    private function isLinked(string $path): bool
    {
        clearstatcache(true, $path);

        $resolved = realpath($path);

        return is_link($path) || ($resolved !== false && !self::samePath($path, $resolved));
    } // isLinked

    /**
     * Verifies that an existing directory is empty or contains PhpDoxy output.
     *
     * @throws RuntimeException
     * If the target is a link, an unreadable directory, or an unrelated nonempty directory.
     *
     * @author Oleg Schildt
     */
    private function checkExistingTarget(): void
    {
        if ($this->isLinked($this->target) || (file_exists($this->target) && !is_dir($this->target))) {
            throw new RuntimeException("The target '{$this->target}' must be a regular directory.");
        }

        if (!is_dir($this->target)) {
            return;
        }

        $files = @scandir($this->target);
        $lookup = $this->target . DIRECTORY_SEPARATOR . 'elementlist.js';
        $signature = !$this->isLinked($lookup) && is_file($lookup) ? @file_get_contents($lookup, false, null, 0, 64) : false;

        if ($files === false || (count($files) > 2 && !str_starts_with((string) $signature, 'var ApiGen = ApiGen || {};'))) {
            throw new RuntimeException("The target '{$this->target}' is not an empty directory or a PhpDoxy site.");
        }
    } // checkExistingTarget

    /**
     * Cleans up a staging directory or an old site after its replacement.
     *
     * @param string $path
     * An internally created sibling directory of the output.
     *
     * @return void
     * Reports cleanup failures without hiding the build or publication result.
     *
     * @author Oleg Schildt
     */
    private function cleanUp(string $path): void
    {
        try {
            $this->removeTree($path);
        } catch (RuntimeException $exception) {
            PhpDocWatcher::trackWarning($exception->getMessage());
        }
    } // cleanUp

    /**
     * Removes an owned directory tree without descending through linked entries.
     *
     * @param string $path
     * An entry under the temporary or retired site directory.
     *
     * @throws RuntimeException
     * If an entry cannot be removed.
     *
     * @author Oleg Schildt
     */
    private function removeTree(string $path): void
    {
        $linked = $this->isLinked($path);
        $directory = is_dir($path);

        if ($directory && !$linked) {
            foreach (new FilesystemIterator($path) as $entry) {
                $this->removeTree($entry->getPathname());
            }
        }

        $removed = $directory && (!$linked || DIRECTORY_SEPARATOR === '\\') ? @rmdir($path) : @unlink($path);

        if (!$removed) {
            throw new RuntimeException("Cannot clean up '$path'.");
        }
    } // removeTree
} // OutputDirectory
