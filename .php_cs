<?php
$finder = PhpCsFixer\Finder::create();

if (!defined('__PHPCS_ROOT__')) {
    define('__PHPCS_ROOT__', __DIR__);
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

return PhpCsFixer\Config::create()
    ->setRiskyAllowed(true)
    ->setHideProgress(false)
    ->setRules([
        '@PSR2' => true,
        'strict_param' => true,
        'array_syntax' => ['syntax' => 'short'],
        '@PhpCsFixer' => true,
        '@PHP73Migration' => true,
        'no_php4_constructor' => true,
        'no_unused_imports' => true,
        'no_useless_else' => true,
        'no_superfluous_phpdoc_tags' => false,
        'void_return' => true,
        'yoda_style' => false,
    ])
    ->setFinder($finder)
    ;
