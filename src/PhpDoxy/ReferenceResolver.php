<?php

namespace PhpDoxy;

use PhpParser\NameContext;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Name\Relative;
use PhpParser\Node\Stmt\Use_;

/**
 * Resolves documentation links using PHP imports and the documented symbol index.
 *
 * @author Oleg Schildt
 */
final class ReferenceResolver
{
    private array $symbols = [];
    private array $classes = [];
    private array $constants = [];
    private array $shortNames = [];

    /**
     * Indexes documented symbols by full name and by unambiguous short name.
     *
     * @param array<string, array<string, mixed>> $lookup
     * The symbol descriptors from the documentation model.
     *
     * @author Oleg Schildt
     */
    public function __construct(array $lookup)
    {
        foreach ($lookup as $name => $descriptor) {
            $type = $descriptor['type'];

            if ($type === 'constant') {
                $this->constants[$this->constantKey($name)] = $name;
            } else {
                $this->symbols[$this->key($name)] = $name;
            }

            if (in_array($type, ['class', 'interface', 'trait', 'enum'], true)) {
                $this->classes[strtolower($name)] = $name;
            }

            if (str_starts_with($name, '\\') && !str_contains($name, '::') && $type !== 'constant') {
                $short = $this->key('\\' . $descriptor['short_name']);
                $this->shortNames[$short][] = $name;
            }
        }
    } // __construct

    /**
     * Looks up a class name or a documented reference in its declaration's scope.
     *
     * @param string $reference
     * A PHP name, member reference, function reference, or other documentation symbol.
     *
     * @param array<string, mixed> $scope
     * The descriptor providing the namespace, imports, and enclosing class when applicable.
     *
     * @param bool $classOnly
     * Whether to use class-name rules and disallow short-name fallback.
     *
     * @return string|null
     * Returns the indexed name, or null if the reference is unknown or ambiguous.
     *
     * @author Oleg Schildt
     */
    public function resolve(string $reference, array $scope, bool $classOnly = false): ?string
    {
        if (str_starts_with($reference, '$')) {
            return $this->symbols[$this->key($reference)] ?? null;
        }

        [$base, $member] = array_pad(explode('::', $reference, 2), 2, null);
        $suffix = $member === null ? '' : '::' . $member;
        $function = $member === null && str_ends_with($base, '()');

        if ($function) {
            $base = substr($base, 0, -2);
            $suffix = '()';
        }

        if ($member !== null && $base === '') {
            $base = 'self';
        }

        $owner = $scope['parent'] ?? $scope;
        $special = match (strtolower($base)) {
            'self', 'static' => isset($owner['methods']) ? $owner['full_name'] : null,
            'parent' => $owner['extends'][0] ?? null,
            default => null,
        };

        if ($special !== null) {
            return $this->symbols[$this->key($special . $suffix)] ?? null;
        }

        if (!preg_match('/^\\\\?(?:[a-z_\x80-\xff][a-z_0-9\x80-\xff]*\\\\)*[a-z_\x80-\xff][a-z_0-9\x80-\xff]*$/iD', $base)) {
            return $classOnly ? null : ($this->symbols[$this->key($reference)] ?? null);
        }

        $name = match (true) {
            str_starts_with($base, '\\') => new FullyQualified(substr($base, 1)),
            str_starts_with(strtolower($base), 'namespace\\') => new Relative(substr($base, 10)),
            default => new Name($base),
        };
        $context = $scope['name_context'] ?? null;

        if ($context instanceof NameContext) {
            $kinds = match (true) {
                $function => [Use_::TYPE_FUNCTION],
                $classOnly || $member !== null => [Use_::TYPE_NORMAL],
                default => [Use_::TYPE_NORMAL, Use_::TYPE_CONSTANT],
            };

            foreach ($kinds as $kind) {
                $resolved = $context->getResolvedName($name, $kind);
                $namespace = $context->getNamespace();
                $candidate = $resolved?->toString() ?? ($namespace === null ? $base : $namespace . '\\' . $base);
                $full_name = '\\' . $candidate . $suffix;
                $found = match (true) {
                    $kind === Use_::TYPE_CONSTANT => $this->constants[$this->constantKey($full_name)] ?? null,
                    $classOnly => $this->classes[strtolower($full_name)] ?? null,
                    default => $this->symbols[$this->key($full_name)] ?? null,
                };

                if ($found !== null) {
                    return $found;
                }

                $local = ($namespace === null ? '' : $namespace . '\\') . $base;

                if ($name->isUnqualified() && strcasecmp($candidate, $local) !== 0) {
                    return null;
                }
            }
        }

        $global = '\\' . ltrim($base, '\\') . $suffix;

        if ($classOnly) {
            return $context === null ? ($this->classes[strtolower($global)] ?? null) : null;
        }

        if (!$name->isUnqualified() || $member !== null) {
            if ($context !== null) {
                return null;
            }

            return $this->symbols[$this->key($global)] ?? $this->constants[$this->constantKey($global)] ?? null;
        }

        $key = $this->key($global);
        $short = $this->shortNames[$key] ?? [];

        return $this->symbols[$key] ?? $this->constants[$this->constantKey($global)]
            ?? $this->symbols[$this->key($reference)] ?? (count($short) === 1 ? $short[0] : null);
    } // resolve

    /**
     * Normalizes the case-insensitive parts of a documented PHP symbol name.
     *
     * @param string $name
     * A full or short lookup key.
     *
     * @return string
     * Returns the normalized key with case-sensitive member names and global variables intact.
     *
     * @author Oleg Schildt
     */
    private function key(string $name): string
    {
        [$class, $member] = array_pad(explode('::', $name, 2), 2, null);

        if ($member !== null) {
            return strtolower($class) . '::' . (str_ends_with($member, '()') ? strtolower($member) : $member);
        }

        return str_starts_with($name, '$') ? $name : strtolower($name);
    } // key

    /**
     * Normalizes only the namespace of a constant, whose own name is case sensitive.
     *
     * @param string $name
     * A fully qualified constant name.
     *
     * @return string
     * Returns the constant lookup key.
     *
     * @author Oleg Schildt
     */
    private function constantKey(string $name): string
    {
        $separator = strrpos($name, '\\');

        if ($separator === false) {
            return $name;
        }

        return strtolower(substr($name, 0, $separator)) . substr($name, $separator);
    } // constantKey
} // ReferenceResolver
