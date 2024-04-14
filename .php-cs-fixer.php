<?php
$finder = PhpCsFixer\Finder::create();

if (!defined('__PHPCS_ROOT__')) {
    define('__PHPCS_ROOT__', getcwd());
}

$directories = [
    __PHPCS_ROOT__.'/src',
    __PHPCS_ROOT__.'/bin',
    __PHPCS_ROOT__.'/db',
    __PHPCS_ROOT__.'/tests',
];

if (isset($additionalDirectories)) {
    $directories = array_merge($directories, $additionalDirectories);
}

foreach ($directories as $directory) {
    if (file_exists($directory) && is_dir($directory)) {
        $finder->in($directory);
    }
}

if (file_exists(__PHPCS_ROOT__.'/vendor/benzine')) {
    foreach (new DirectoryIterator(__PHPCS_ROOT__.'/vendor/benzine') as $file) {
        if (!$file->isDot()) {
            if ($file->isDir()) {
                if (file_exists($file->getRealPath().'/src')) {
                    $finder->in($file->getRealPath().'/src');
                }
                if (file_exists($file->getRealPath().'/tests')) {
                    $finder->in($file->getRealPath().'/tests');
                }
            }
        }
    }
}

return (new PhpCsFixer\Config)
    ->setRiskyAllowed(true)
    ->setHideProgress(false)
    ->setRules([
        '@PhpCsFixer'                      => true,
        // '@PhpCsFixer:risky'                => true,
        '@PHP82Migration'                  => true,
        '@PHP80Migration:risky'            => true,
        '@PSR12'                           => true,
        '@PSR12:risky'                     => true,
        '@PHPUnit100Migration:risky'       => true,

        'binary_operator_spaces'     => [
            'default'   => 'align_single_space_minimal',
            'operators' => [
                '='  => 'align_single_space',
                '=>' => 'align_single_space',
            ],
        ],
        'types_spaces'               => [
            'space'                => 'single',
            'space_multiple_catch' => 'single',
        ],

        // Annoyance-fixers:
        'concat_space'               => ['spacing' => 'one'], // This one is a matter of taste.
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed'         => false,
            'allow_unused_params' => false,
            'remove_inheritdoc'   => true,
        ],
        'yoda_style'                 => false, // Disabled as its annoying. Comes with @PhpCsFixer
        'native_function_invocation' => false, // Disabled as adding count($i) -> \count($i) is annoying, but supposedly more performant
    ])
    ->setFinder($finder)
    ;
