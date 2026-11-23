<?php

namespace PhpDoxy;

use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use PHPStan\PhpDocParser\Ast\Attribute;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * Reads PHPDoc types as syntax trees and parses descriptive tags with phpDocumentor.
 *
 * @author Oleg Schildt
 */
final class DocBlockReader
{
    /**
     * Tags whose parsed type nodes are returned with their source positions.
     *
     * @var list<string>
     *
     * @author Oleg Schildt
     */
    private const TYPE_TAGS = [
        '@param',
        '@return',
        '@var',
        '@throws',
        '@template',
        '@template-covariant',
        '@template-contravariant',
    ];

    /**
     * Tags retained in the comment passed to phpDocumentor for descriptive parsing.
     *
     * @var list<string>
     *
     * @author Oleg Schildt
     */
    private const METADATA_TAGS = [
        '@author',
        '@copyright',
        '@deprecated',
        '@ignore',
        '@internal',
        '@license',
        '@link',
        '@package',
        '@since',
        '@todo',
        '@version',
    ];

    /**
     * Lexer configured to track token positions within doc comments.
     *
     * @var Lexer
     *
     * @author Oleg Schildt
     */
    private Lexer $lexer;

    /**
     * Parser that extracts PHPDoc tag nodes and their positions.
     *
     * @var PhpDocParser
     *
     * @author Oleg Schildt
     */
    private PhpDocParser $parser;

    /**
     * Factory that parses the prose and descriptive tags retained from a doc comment.
     *
     * @var DocBlockFactory
     *
     * @author Oleg Schildt
     */
    private DocBlockFactory $factory;

    /**
     * Initializes the parsers and enables tag positions for diagnostics.
     *
     * @author Oleg Schildt
     */
    public function __construct()
    {
        $config = new ParserConfig(['lines' => true, 'indexes' => true]);
        $this->lexer = new Lexer($config);
        $constants = new ConstExprParser($config);
        $this->parser = new PhpDocParser($config, new TypeParser($config, $constants), $constants);
        $this->factory = DocBlockFactory::createInstance();
    } // __construct

    /**
     * Extracts prose, descriptive tags, and typed tags from one doc block.
     *
     * @param string $source
     * A complete PHP doc comment, including its delimiters.
     *
     * @return array{metadata: DocBlock, types: list<PhpDocTagNode>, references: array<string, list<array{reference: string, description: string}>>}
     * Returns descriptive tags, type nodes with positions, and references with their original spelling.
     *
     * @throws \PHPStan\PhpDocParser\Parser\ParserException
     * If the input lacks a valid opening doc comment delimiter.
     *
     * @throws \InvalidArgumentException
     * If phpDocumentor cannot read the descriptive part of the comment.
     *
     * @author Oleg Schildt
     */
    public function read(string $source): array
    {
        $tokens = new TokenIterator($this->lexer->tokenize($source));
        $tree = $this->parser->parse($tokens);
        $types = [];
        $references = [];
        $metadata = '';
        $cursor = 0;

        foreach ($tree->getTags() as $tag) {
            if (in_array($tag->name, self::METADATA_TAGS, true)) {
                continue;
            }

            if (in_array($tag->name, self::TYPE_TAGS, true)) {
                $types[] = $tag;
            }

            if ($tag->name === '@see' || $tag->name === '@uses') {
                // A short reference must keep its original spelling until it can be resolved in the PHP scope.
                $parts = preg_split('/\s+/', trim((string) $tag->value), 2);
                $references[substr($tag->name, 1)][] = [
                    'reference' => $parts[0],
                    'description' => $parts[1] ?? '',
                ];
            }

            $start = $tag->getAttribute(Attribute::START_INDEX);
            $end = $tag->getAttribute(Attribute::END_INDEX);

            if (is_int($start) && is_int($end) && $start >= $cursor && $end >= $start) {
                $metadata .= $tokens->getContentBetween($cursor, $start);
                $cursor = $end + 1;
            }
        }

        $metadata .= $tokens->getContentBetween($cursor, $tokens->getTokenCount());

        return ['metadata' => $this->factory->create($metadata), 'types' => $types, 'references' => $references];
    } // read
} // DocBlockReader

