<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'ordered_imports' => true,                      // Order "use" alphabetically
        'array_syntax' => ['syntax' => 'short'],        // Replace array() by []
        'no_useless_return' => true,                    // Keep return null;
        'phpdoc_order' => true,                         // Clean up the /** php doc */
        'linebreak_after_opening_tag' => true,
        'multiline_whitespace_before_semicolons' => false,
        'phpdoc_add_missing_param_annotation' => true,
        'single_trait_insert_per_statement' => false
    ])
    ->setUsingCache(false)
    ->setFinder($finder)
;
