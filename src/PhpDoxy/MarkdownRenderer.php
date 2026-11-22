<?php

namespace PhpDoxy;

/**
 * Resolves Markdown destinations and opens authored links in a new tab.
 *
 * @author Oleg Schildt
 */
class MarkdownRenderer extends \ParsedownExtra
{
    private ?\Closure $resolve_link = null;

    /**
     * Renders one Markdown document with its source-specific link resolver.
     *
     * @param string $text
     * The Markdown source to render.
     *
     * @param \Closure(string): ?string $resolve_link
     * Accepts a link destination and returns its URL or null for an unresolved reference.
     *
     * @return string
     * Returns HTML with safe links and formatted Markdown content.
     *
     * @author Oleg Schildt
     */
    public function render(string $text, \Closure $resolve_link): string
    {
        $this->resolve_link = $resolve_link;

        try {
            return parent::text($text);
        } finally {
            $this->resolve_link = null;
        }
    } // render

    /**
     * Resolves authored anchors before Parsedown filters their URLs.
     *
     * @param array<string, mixed> $element
     * The Parsedown element and its attributes.
     *
     * @return array<string, mixed>
     * Returns the sanitized element. An unresolved destination retains its formatted label.
     *
     * @author Oleg Schildt
     */
    protected function sanitiseElement(array $element): array
    {
        $href = $element['attributes']['href'] ?? null;

        if ($this->resolve_link !== null && ($element['name'] ?? '') === 'a' && $href !== null
            && !in_array($element['attributes']['class'] ?? '', ['footnote-ref', 'footnote-backref'], true)
        ) {
            $href = ($this->resolve_link)($href);

            if ($href === null) {
                unset($element['name'], $element['attributes']);
            } else {
                $element['attributes']['href'] = $href;
                $element['attributes']['target'] = '_blank';
                $element['attributes']['rel'] = 'noopener';
            }
        }

        return parent::sanitiseElement($element);
    } // sanitiseElement
} // MarkdownRenderer
