<?php

$finder = PhpCsFixer\Finder::create()
    ->notPath('bootstrap/cache')
    ->notPath('storage')
    ->notPath('vendor')
    ->in(__DIR__)
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
;

$config = new PhpCsFixer\Config();
$config->setRules([
        '@Symfony' => true,
        'blank_line_before_statement' => false,
        'binary_operator_spaces' => false,
        'linebreak_after_opening_tag' => true,
        'ordered_imports' => true,
        'phpdoc_order' => true,
        'yoda_style' => false,
        'cast_spaces' => ['space' => 'none'],
        'phpdoc_to_comment' => false,
        'standardize_increment' => false,
        'increment_style' => false,
    ])
    ->setFinder($finder);

return $config;
