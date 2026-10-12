<?php
/**
 * Checks for the public showcase repository.
 *
 * - Every image and relative link in the Markdown files points at a file that exists.
 * - The repository holds no source code, configuration or data files (the code stays private).
 * - Screenshots stay light enough to load quickly on GitHub.
 *
 * Usage: php scripts/check_showcase.php
 */

$root = dirname(__DIR__);
$maxImageBytes = 1_000_000;
$allowedExtensions = ['md', 'webp', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'yml', 'php', ''];
$allowedScripts = ['scripts/check_showcase.php'];
$imageExtensions = ['webp', 'png', 'jpg', 'jpeg', 'gif'];

exec('git -C ' . escapeshellarg($root) . ' ls-files', $files, $status);
if ($status !== 0) {
    fwrite(STDERR, "FAIL: could not list the repository files\n");
    exit(1);
}

$errors = [];
foreach ($files as $name) {
    $base = basename($name);
    // A dotfile such as .gitignore has no extension.
    $extension = str_starts_with($base, '.') && substr_count($base, '.') === 1
        ? ''
        : strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)
        || ($extension === 'php' && !in_array($name, $allowedScripts, true))
        || ($extension === 'yml' && !str_starts_with($name, '.github/workflows/'))) {
        $errors[] = "$name: not expected in the public showcase (the code stays private)";
    }
    if (str_starts_with($base, '.env') || in_array($base, ['local.config.php', 'composer.json', 'package.json'], true)) {
        $errors[] = "$name: configuration must never be published";
    }
    if (in_array($extension, $imageExtensions, true)) {
        $size = filesize("$root/$name");
        if ($size > $maxImageBytes) {
            $errors[] = sprintf('%s: %d KB, keep screenshots under %d KB', $name, intdiv($size, 1024), intdiv($maxImageBytes, 1000));
        }
    }
}

foreach ($files as $name) {
    if (!str_ends_with($name, '.md')) {
        continue;
    }
    $text = file_get_contents("$root/$name");
    preg_match_all('/!?\[[^\]]*\]\(([^)\s]+)\)|<img[^>]*\ssrc="([^"]+)"/', $text, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $target = explode('#', ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? ''))[0];
        if ($target === '' || preg_match('/^[a-z]+:/i', $target)) {
            continue;
        }
        if (!file_exists(dirname("$root/$name") . '/' . $target)) {
            $errors[] = "$name: broken link to $target";
        }
    }
}

if ($errors) {
    echo implode(PHP_EOL, array_map(fn($error) => "FAIL: $error", $errors)), PHP_EOL;
    exit(1);
}
printf("PASS: %d files checked; links resolve, no code or configuration, images under %d KB.\n", count($files), intdiv($maxImageBytes, 1000));
