<?php
/**
 * Build a release zip of the application, ready to drag-and-drop onto
 * a shared host via FTP. The zip includes vendor/ (after composer
 * install --no-dev) and skips development assets.
 *
 * Usage: php bin/build-release.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$version = trim((string)file_get_contents($root . '/VERSION'));
$outDir  = $root . '/release';
@mkdir($outDir, 0775, true);
$outFile = $outDir . '/porra-mundial-2026-' . $version . '.zip';
@unlink($outFile);

$exclude = [
    '.git', '.github', '.gitignore', 'release', 'tests', 'phpunit.xml.dist',
    '.phpunit.cache', '.phpunit.result.cache', 'composer.lock',
    'config/config.php', 'storage/installed.lock', 'storage/logs', 'storage/cache',
    'storage/mail', 'database/schema.lock', 'bin/build-release.php',
    '.idea', '.vscode',
];

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive extension not available.\n");
    exit(1);
}
$zip = new ZipArchive();
if ($zip->open($outFile, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot create $outFile\n");
    exit(1);
}

$baseLen = strlen($root) + 1;
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$skip = function (string $relative) use ($exclude): bool {
    foreach ($exclude as $ex) {
        if ($relative === $ex || str_starts_with($relative, $ex . DIRECTORY_SEPARATOR)) {
            return true;
        }
    }
    return false;
};

foreach ($it as $file) {
    /** @var SplFileInfo $file */
    $real = $file->getPathname();
    $rel  = substr($real, $baseLen);
    $rel  = str_replace(DIRECTORY_SEPARATOR, '/', $rel);
    if ($skip($rel)) {
        continue;
    }
    if ($file->isDir()) {
        $zip->addEmptyDir($rel);
    } else {
        $zip->addFile($real, $rel);
    }
}

$zip->close();
echo "Built $outFile\n";
