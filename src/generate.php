<?php

namespace PhpDoxy;

/**
 * Loads the XML configuration and generates a documentation site.
 *
 * @param list<string> $argv
 * CLI arguments, with the optional configuration path at index 1.
 *
 * @return int
 * Returns 0 for a completed generation, including warnings, or 1 if generation fails.
 *
 * @author Oleg Schildt
 */
function generate(array $argv): int
{
    $start = time();
    $working_dir = getcwd();

    if (PHP_SAPI !== 'cli' && !empty($_SERVER['SCRIPT_FILENAME'])) {
        $working_dir = dirname($_SERVER['SCRIPT_FILENAME']);
    }

    PhpDocWatcher::reset();

    echo_highlighted("\nDocumentation generation started");

    echo_standard("\nPHP version: " . PHP_VERSION . "\nWorking directory: " . $working_dir);

    try {
        $config_file = resolve_config_path($argv[1] ?? 'phpdoxy_config.xml', [$working_dir]);
        $config = read_config($config_file, $working_dir);

        echo_standard(
            "\nConfig file: " . $config_file . "\nSource directories:\n  " . implode("\n  ", $config['sources'])
            . "\nTarget directory: " . $config['target'] . "\nTemplate: " . $config['template']
        );

        // Validate before scanning and repeat the check when the generator publishes.
        new OutputDirectory($config);

        $parser = new PhpDocParser();
        $dictionary = &$parser->process($config);

        if (!PhpDocWatcher::hasErrors()) {
            $generator = new PhpDocGenerator($dictionary);

            $generator->generate($config);
        }
    } catch (\Throwable $exception) {
        PhpDocWatcher::trackError($exception->getMessage());
    }

    $errors = PhpDocWatcher::hasErrors();
    $warnings = PhpDocWatcher::hasWarnings();

    if ($warnings) {
        PhpDocWatcher::reportWarnings();
    }

    if ($errors) {
        PhpDocWatcher::reportErrors();

        echo_error("\n\nDocumentation generation failed with $errors error(s).\n");

        return 1;
    }

    $duration = time() - $start;

    if ($warnings) {
        echo_warning("\n\nDocumentation generation completed with $warnings warning(s) in $duration second(s).\n");
    } else {
        echo_success("\n\nDocumentation generation succeeded in $duration second(s).\n");
    }

    return 0;
} // generate

/**
 * Reads PhpDoxy's XML settings and resolves paths relative to the configuration file.
 *
 * @param string $file
 * The absolute configuration filename.
 *
 * @param string $working_dir
 * The fallback directory for relative paths.
 *
 * @return array<string, mixed>
 * Returns the ordered list of source directories, target, template, title, and optional user-file settings.
 *
 * @throws \RuntimeException
 * If the XML is invalid, a required setting is empty, or an input path cannot be resolved.
 *
 * @author Oleg Schildt
 */
function read_config(string $file, string $working_dir): array
{
    $xml = new \DOMDocument();

    if (!@$xml->load($file, LIBXML_NONET)) {
        throw new \RuntimeException("Cannot read XML configuration '$file'.");
    }

    $xpath = new \DOMXPath($xml);
    $directories = [dirname($file), $working_dir];
    $source_nodes = $xpath->query('/config/sources/source');
    $target = trim($xpath->evaluate('string(/config/target)'));
    $template = trim($xpath->evaluate('string(/config/template)'));

    if ($xpath->query('/config/source')->length > 0) {
        throw new \RuntimeException('Put each source directory in a <source> element inside <sources>.');
    }

    if ($source_nodes->length === 0 || $target === '' || $template === '') {
        throw new \RuntimeException('The configuration must specify at least one <source> inside <sources>, a target, and a template.');
    }

    $sources = [];

    foreach ($source_nodes as $node) {
        $sources[] = resolve_config_path(trim($node->textContent), $directories, true);
    }

    $target = rtrim($target, '/\\');

    if ($target === '' || preg_match('/^[a-z]:$/iD', $target)) {
        throw new \RuntimeException('The target must name a site directory.');
    }

    $target_parent = resolve_config_path(dirname($target), $directories, true);
    $config = [
        'sources' => $sources,
        'target' => rtrim($target_parent, '/\\') . DIRECTORY_SEPARATOR . basename($target),
        'template' => resolve_config_path($template, [...$directories, __DIR__ . '/../templates'], true),
        'title' => trim($xpath->evaluate('string(/config/title)')) ?: 'Library Documentation',
    ];

    foreach ($xpath->query('/config/user_files/user_file') as $node) {
        $path = resolve_config_path(trim($node->textContent), $directories);
        $title = trim($node->getAttribute('title')) ?: basename($path);
        $menu_title = trim($node->getAttribute('menu_title')) ?: $title;
        $config['user_files'][] = ['path' => $path, 'title' => $title, 'menu_title' => $menu_title];
    }

    return $config;
} // read_config

/**
 * Finds an existing file or directory in the configured search locations.
 *
 * @param string $path
 * The relative or absolute path to look for.
 *
 * @param list<string> $directories
 * Directories searched in order when the path is relative.
 *
 * @param bool $directory
 * Whether the result must be a directory. The default requires a file.
 *
 * @return string
 * Returns the canonical absolute path.
 *
 * @throws \RuntimeException
 * If the path is empty or no candidate matches the requested kind.
 *
 * @author Oleg Schildt
 */
function resolve_config_path(string $path, array $directories, bool $directory = false): string
{
    $absolute = str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[a-z]:/i', $path);
    $candidates = $absolute ? [$path] : array_map(static fn (string $base): string => $base . DIRECTORY_SEPARATOR . $path, $directories);

    foreach ($candidates as $candidate) {
        $resolved = $path === '' ? false : realpath($candidate);

        if ($resolved !== false && ($directory ? is_dir($resolved) : is_file($resolved))) {
            return $resolved;
        }
    }

    throw new \RuntimeException("Cannot resolve " . ($directory ? 'directory ' : 'file ') . "'$path'.");
} // resolve_config_path
