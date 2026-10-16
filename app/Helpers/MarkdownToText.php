<?php

namespace App\Helpers;

/**
 * Converts basic Markdown to plain text for chat responses.
 * Handles headings, lists, bold/italic, code blocks, etc.
 * Designed to preserve paragraph separation and list structure.
 */
class MarkdownToText
{
    /**
     * Strip all Markdown formatting and return clean plain text.
     */
    public static function convert(string $markdown): string
    {
        $text = $markdown;

        // Remove code blocks (```...```) — replace with plain text placeholder
        $text = preg_replace('/```[\s\S]*?```/', ' [code block]', $text);

        // Remove inline code (`code`) but keep content
        $text = preg_replace('/`([^`]+)`/', '$1', $text);

        // Convert headings (# to ######) to just text with spacing before
        $text = preg_replace('/^#+\s+(.+)$/m', "\n\n\$1", $text);

        // Convert bold (**) to plain text
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text);

        // Convert italic (*) to plain text
        $text = preg_replace('/\*([^*]+)\*/', '$1', $text);

        // Convert underscores (_) to plain text
        $text = preg_replace('/_([^_]+)_/', '$1', $text);

        // Remove blockquote markers (>)
        $text = preg_replace('/^\s*>\s*/m', '', $text);

        // Convert numbered lists (1., 2.,) to plain text with proper spacing
        $text = preg_replace('/^(\s*)\d+\.\s+/m', '$1• ', $text);

        // Convert bullet lists (* or -) to plain bullets
        $text = preg_replace('/^(\s*)[\*\-]\s+/m', '$1• ', $text);

        // Remove horizontal rules (---, ***, ___)
        $text = preg_replace('/^[-*_]{3,}\s*$/m', "\n", $text);

        // Convert links [text](url) to just text
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);

        // Remove HTML tags
        $text = strip_tags($text);

        // Collapse multiple blank lines but keep at least one
        $text = preg_replace("/\n{4,}/", "\n\n\n", $text);

        // Clean up multiple spaces
        $text = preg_replace('/  +/', ' ', $text);

        // Trim but preserve single leading/trailing space if needed
        $text = trim($text);

        return $text;
    }
}
