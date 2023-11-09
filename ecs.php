<?php

use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->paths([__DIR__ . '/src', __DIR__ . '/tests']);
    $ecsConfig->fileExtensions(['php']);

    $ecsConfig->sets([
        SetList::PSR_12,
        SetList::COMMON,
        SetList::SYMPLIFY
    ]);

    $ecsConfig->ruleWithConfiguration(
        ArraySyntaxFixer::class, [
            'syntax' => 'short',
        ],
        [
            IncrementStyleFixer::class,
            ['style' => 'post'],
        ],
        [ 
            CastSpacesFixer::class,
            ['space' => 'none'],
        ],
        [
            YodaStyleFixer::class,
            [
                'equal' => false,
                'identical' => false,
                'less_and_greater' => false,
            ],
        ],
        [
            ConcatSpaceFixer::class,
            ['spacing' => 'one'],
        ],
        [
            CastSpacesFixer::class,
            ['space' => 'none'],
        ],
        [
            OrderedImportsFixer::class,
            [
                'imports_order' => [
                    'class', 
                    'function', 
                    'const'
                ]
            ],
        ],
        [
            NoSuperfluousPhpdocTagsFixer::class,
            [
                'remove_inheritdoc' => false,
                'allow_mixed' => true,
                'allow_unused_params' => false,
            ],
        ],
        [
            DeclareEqualNormalizeFixer::class,
            ['space' => 'single'],
        ],
        [
            BlankLineBeforeStatementFixer::class,
            [
                'statements' => [
                    'continue', 
                    'declare', 
                    'return', 
                    'throw', 
                    'try'
                ]
            ],
        ],
        [
            BinaryOperatorSpacesFixer::class,
            [
                'operators' => [
                    '&' => 'align'
                ]
            ],
        ]
    );

    // C. dynamics sets
    $ecsConfig->dynamicSets(['@Symfony']);
};