<?php

namespace PhpDoxy;

use PhpToken;

/**
 * Renders PHP tokens with the documentation theme's syntax colors and optional line anchors.
 *
 * @author Oleg Schildt
 */
final class PhpHighlighter
{
    /**
     * Escapes and highlights a complete PHP file or a PHP fragment.
     *
     * @param string $source
     * The original PHP source, including any surrounding HTML for a complete file.
     *
     * @param bool $fragment
     * Whether to start tokenization in PHP mode for a fragment without an opening tag.
     *
     * @param bool $lineNumbers
     * Whether to prefix each source line with a numbered HTML anchor.
     *
     * @return string
     * Returns HTML that retains the source's line boundaries and indentation.
     *
     * @author Oleg Schildt
     */
    public function highlight(string $source, bool $fragment = false, bool $lineNumbers = false): string
    {
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $prefix = $fragment && !preg_match('/^\s*<\?(?:php\b|=)/i', $source) ? '<?php ' : '';
        $line = 1;
        $html = $lineNumbers ? $this->lineStart($line) : '';

        $tokens = PhpToken::tokenize($prefix . $source);

        foreach ($tokens as $index => $token) {
            $text = $token->pos === 0 ? substr($token->text, strlen($prefix)) : $token->text;
            $class = $this->tokenClass($token, $tokens[$index - 1] ?? null, $tokens[$index + 1] ?? null);
            $parts = explode("\n", $text);

            foreach ($parts as $index => $part) {
                if ($index > 0) {
                    $html .= $lineNumbers ? "</span>\n" . $this->lineStart(++$line) : "\n";
                }

                if ($part !== '') {
                    $escaped = htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $html .= $class === '' ? $escaped : '<span class="' . $class . '">' . $escaped . '</span>';
                }
            }
        }

        return $lineNumbers ? $html . '</span>' : $html;
    } // highlight

    /**
     * Opens the source line wrapper and creates its navigation anchor.
     *
     * @param int $line
     * The one-based physical line number.
     *
     * @return string
     * Returns the opening wrapper and a numbered anchor preceding the source text.
     *
     * @author Oleg Schildt
     */
    private function lineStart(int $line): string
    {
        return '<span class="source-line" id="line-' . $line . '"><a class="line" id="' . $line . '" href="#'
            . $line . '">' . $line . ':</a> ';
    } // lineStart

    /**
     * Maps a PHP token to an existing stylesheet class.
     *
     * @param PhpToken $token
     * A token returned by the PHP lexer.
     *
     * @param PhpToken|null $previous
     * The preceding token, if one exists.
     *
     * @param PhpToken|null $next
     * The following token, if one exists.
     *
     * @return string
     * Returns the CSS class, or an empty string for ordinary text and punctuation.
     *
     * @author Oleg Schildt
     */
    private function tokenClass(PhpToken $token, ?PhpToken $previous, ?PhpToken $next): string
    {
        return match ($token->id) {
            T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG => 'xlang',
            T_COMMENT, T_DOC_COMMENT => 'php-comment',
            T_VARIABLE => 'php-var',
            T_LNUMBER, T_DNUMBER, T_NUM_STRING => 'php-num',
            T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_START_HEREDOC, T_END_HEREDOC => 'php-quote',
            T_ATTRIBUTE, T_INT_CAST, T_BOOL_CAST, T_DOUBLE_CAST, T_STRING_CAST, T_ARRAY_CAST, T_OBJECT_CAST,
            T_UNSET_CAST => 'php-keyword2',
            T_PUBLIC_SET, T_PROTECTED_SET, T_PRIVATE_SET => 'php-keyword1',
            default => match (true) {
                $token->getTokenName() === 'T_VOID_CAST', $token->text === '|>' => 'php-keyword2',
                // PHP 8.4 splits a pipe operator into adjacent punctuation tokens.
                $token->id === ord('|') && $next?->id === ord('>'),
                $token->id === ord('>') && $previous?->id === ord('|') => 'php-keyword2',
                $token->id === T_STRING => in_array(
                    strtolower($token->text),
                    ['true', 'false', 'null', 'int', 'float', 'string', 'bool', 'object', 'iterable', 'mixed', 'void',
                        'never', 'self', 'parent'],
                    true
                ) ? 'php-keyword1' : '',
                $token->id >= 256 && preg_match('/^[a-z_][a-z_0-9]*$/iD', $token->text) => 'php-keyword1',
                default => '',
            },
        };
    } // tokenClass
} // PhpHighlighter
