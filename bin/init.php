<?php

declare(strict_types=1);

/**
 * Interactive project initializer for the MIDDAG WordPress plugin boilerplate.
 *
 * Runs automatically after `composer create-project middag-io/wp-boilerplate
 * my-plugin` (wired as `post-create-project-cmd`), or by hand via
 * `composer init-plugin` when the boilerplate was cloned / "Use this template".
 *
 * It rewrites the boilerplate's six identity tokens across every tracked source
 * file, renames the main plugin file, and then DELETES ITSELF so the new project
 * carries no scaffolding residue. The OSS library dependencies
 * (`middag-io/framework`, `middag-io/wordpress`, `@middag-io/react`, and the
 * `Middag\Framework\*` / `Middag\WordPress\*` namespaces) are deliberately NOT
 * touched — the generated plugin keeps consuming them.
 *
 * Non-interactive safety: if STDIN is not a TTY and no `--answers=<file>` is
 * given, it prints a notice and no-ops (exit 0) so an unattended `composer
 * install` never hangs.
 *
 * Flags:
 *   --answers=<path>  Read answers from a JSON file instead of prompting
 *                     (keys: package, repo, slug, namespace, prefix, display,
 *                      author_name, author_email, author_uri, description,
 *                      minimal). Enables scripted/tested runs.
 *   --dry-run         Show what would change; write nothing, delete nothing.
 *   --keep-self       Do not delete bin/init.php or the composer scripts.
 *   --minimal         Strip the example domain (Widget/RemoteCatalog/OptionalVendor).
 */

const BOILERPLATE = [
    'package' => 'middag-io/wp-boilerplate',
    'repo_full' => 'middag-io/wp-plugin-middag-boilerplate',
    'repo_name' => 'wp-plugin-middag-boilerplate',
    'slug' => 'middag-boilerplate',
    'namespace' => 'Middag\\WpBoilerplate',
    'namespace_concat' => 'MiddagWpBoilerplate',
    'const_prefix' => 'MIDDAG_BOILERPLATE',
    'display' => 'MIDDAG Boilerplate',
    'prefix' => 'mdgbp',
    'prefix_upper' => 'MDGBP',
    'main_file' => 'middag-boilerplate.php',
    'author_name' => 'Michael Meneses',
    'author_email' => 'michael@middag.com.br',
    'author_uri' => 'https://middag.io',
    'description_composer' => "MIDDAG's reference architecture for a WordPress plugin — composes the OSS framework/wordpress/ui libraries end to end.",
    'description_header' => "MIDDAG's reference architecture for a WordPress plugin — composes the OSS framework, WordPress adapter, and UI libraries end to end.",
];

/** File extensions scanned for token rewrites. */
const SCAN_EXT = ['php', 'json', 'ts', 'tsx', 'mjs', 'js', 'xml', 'neon', 'md', 'txt'];

/**
 * Directory NAMES skipped at ANY depth — dependency trees, build artifacts and
 * VCS metadata. Matched by basename (not just the top-level segment) so nested
 * dirs like `ui/node_modules`, `ui/dist` or a stray `vendor-prefixed/` are never
 * rewritten. Only the plugin's own authored source is rebranded.
 */
const SKIP_DIR_NAMES = [
    'vendor', 'vendor-prefixed', 'node_modules', '.git', '.github',
    'dist', 'build', 'assets', '.idea', 'coverage',
];
const SKIP_FILES = ['composer.lock', 'ui/package-lock.json', 'bin/init.php'];

/**
 * The example domain: teaching code the developer replaces. Removed only with
 * --minimal. Kept by default (it is the boilerplate's demonstrative value).
 */
const EXAMPLE_PATHS = [
    'src/Domain/Widget',
    'src/Api/V1/WidgetController.php',
    'src/UI',
    'src/Integration',
    'src/WordPress/Repository/WidgetRepository.php',
    'src/WordPress/Persistence/WidgetMapper.php',
    'src/WordPress/Schema/SchemaInstaller.php',
    'src/WordPress/Settings/WidgetSettings.php',
    'src/WordPress/Hook/DemoHooks.php',
    'src/WordPress/Hook/OptionalVendorHooks.php',
    'tests/Unit/Domain/Widget',
    'tests/Unit/Integration',
    'tests/Unit/WordPress',
    'ui/src/pages/Widgets',
];

/**
 * @return array{
 *   flags: array<string, bool>,
 *   answers_file: ?string
 * }
 */
function parse_argv(array $argv): array
{
    $flags = ['dry-run' => false, 'keep-self' => false, 'minimal' => false];
    $answersFile = null;

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') {
            $flags['dry-run'] = true;
        } elseif ($arg === '--keep-self') {
            $flags['keep-self'] = true;
        } elseif ($arg === '--minimal') {
            $flags['minimal'] = true;
        } elseif (str_starts_with($arg, '--answers=')) {
            $answersFile = substr($arg, strlen('--answers='));
        }
    }

    return ['flags' => $flags, 'answers_file' => $answersFile];
}

function is_tty(): bool
{
    return defined('STDIN') && function_exists('stream_isatty') && @stream_isatty(STDIN);
}

function out(string $line = ''): void
{
    fwrite(STDOUT, $line . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, "init.php: {$message}" . PHP_EOL);
    exit(1);
}

/**
 * @param callable(string):bool $validate
 */
function ask(string $label, string $default, callable $validate, string $hint = ''): string
{
    while (true) {
        $suffix = $default !== '' ? " [{$default}]" : '';
        fwrite(STDOUT, $label . ($hint !== '' ? " ({$hint})" : '') . $suffix . ': ');
        $line = fgets(STDIN);
        $value = $line === false ? '' : trim($line);
        if ($value === '') {
            $value = $default;
        }
        if ($validate($value)) {
            return $value;
        }
        out('  ↳ invalid value, try again.');
    }
}

/**
 * Derive the full answer set from the six core inputs.
 *
 * @param array<string, mixed> $raw
 *
 * @return array<string, string>
 */
function normalize_answers(array $raw): array
{
    $slug = (string) ($raw['slug'] ?? '');
    $namespace = (string) ($raw['namespace'] ?? '');
    $prefix = (string) ($raw['prefix'] ?? '');

    $repo = (string) ($raw['repo'] ?? '');
    $repoName = $repo !== '' && str_contains($repo, '/') ? substr($repo, strpos($repo, '/') + 1) : ('wp-plugin-' . $slug);

    return [
        'package' => (string) ($raw['package'] ?? ''),
        'repo_full' => $repo !== '' ? $repo : ('your-org/wp-plugin-' . $slug),
        'repo_name' => $repoName,
        'slug' => $slug,
        'namespace' => $namespace,
        'namespace_concat' => str_replace('\\', '', $namespace),
        'const_prefix' => strtoupper(str_replace('-', '_', $slug)),
        'display' => (string) ($raw['display'] ?? ''),
        'prefix' => $prefix,
        'prefix_upper' => strtoupper($prefix),
        'author_name' => (string) ($raw['author_name'] ?? ''),
        'author_email' => (string) ($raw['author_email'] ?? ''),
        'author_uri' => (string) ($raw['author_uri'] ?? ''),
        'description' => (string) ($raw['description'] ?? ''),
    ];
}

/**
 * @return array<string, mixed>
 */
function gather_interactive(): array
{
    $reKebab = static fn (string $v): bool => (bool) preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $v);
    $rePkg = static fn (string $v): bool => (bool) preg_match('#^[a-z0-9]([a-z0-9._-]*)/[a-z0-9]([a-z0-9._-]*)$#', $v);
    $reNs = static fn (string $v): bool => (bool) preg_match('/^[A-Z][A-Za-z0-9]*(\\\\[A-Z][A-Za-z0-9]*)+$/', $v);
    $rePrefix = static fn (string $v): bool => (bool) preg_match('/^[a-z][a-z0-9]{1,7}$/', $v);
    $any = static fn (): bool => true;
    $optUrl = static fn (string $v): bool => $v === '' || (bool) preg_match('#^https?://#', $v);
    $optEmail = static fn (string $v): bool => $v === '' || str_contains($v, '@');

    out('MIDDAG WordPress plugin boilerplate — project initializer');
    out('Answer six questions to rebrand the scaffold. Enter accepts the default.');
    out('');

    $slug = ask('Plugin slug (kebab-case)', '', $reKebab, 'e.g. acme-widgets');
    $defNs = 'Acme\\' . str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
    $namespace = ask('PHP namespace (PascalCase\\PascalCase)', $defNs, $reNs);
    $defPrefix = substr(preg_replace('/[^a-z]/', '', strtolower(str_replace('\\', '', $namespace))) ?: 'plg', 0, 5);
    $prefix = ask('Short prefix (snake, 2-8 chars) for tables/options/cron/caps', $defPrefix, $rePrefix);
    $defDisplay = ucwords(str_replace('-', ' ', $slug));
    $display = ask('Display name', $defDisplay, $any);
    $defPkg = 'your-org/' . $slug;
    $package = ask('Composer package (vendor/name)', $defPkg, $rePkg);
    $defRepo = 'your-org/wp-plugin-' . $slug;
    $repo = ask('GitHub repo (owner/name)', $defRepo, static fn (string $v): bool => str_contains($v, '/'));

    out('');
    out('Optional (Enter to skip — edit later):');
    $authorName = ask('Author name', '', $any);
    $authorEmail = ask('Author email', '', $optEmail);
    $authorUri = ask('Author URI', '', $optUrl);
    $description = ask('Description', '', $any);

    return [
        'slug' => $slug,
        'namespace' => $namespace,
        'prefix' => $prefix,
        'display' => $display,
        'package' => $package,
        'repo' => $repo,
        'author_name' => $authorName,
        'author_email' => $authorEmail,
        'author_uri' => $authorUri,
        'description' => $description,
    ];
}

/**
 * Ordered, case-sensitive replacement pairs. Longest / most-specific first so a
 * later, shorter token can never corrupt a value written by an earlier one
 * (e.g. the repo path is rewritten before the bare slug).
 *
 * @param array<string, string> $a normalized answers
 *
 * @return list<array{0: string, 1: string}>
 */
function replacement_pairs(array $a): array
{
    $pairs = [
        [BOILERPLATE['repo_full'], $a['repo_full']],
        [BOILERPLATE['repo_name'], $a['repo_name']],
        [BOILERPLATE['package'], $a['package']],
        // JSON escapes backslashes, so composer.json carries the namespace with
        // DOUBLED backslashes (Middag\\WpBoilerplate). Rewrite that byte-form
        // first, then the single-backslash PHP form (namespace decls + use).
        [str_replace('\\', '\\\\', BOILERPLATE['namespace']), str_replace('\\', '\\\\', $a['namespace'])],
        [BOILERPLATE['namespace'], $a['namespace']],
        [BOILERPLATE['namespace_concat'], $a['namespace_concat']],
        [BOILERPLATE['const_prefix'], $a['const_prefix']],
        [BOILERPLATE['display'], $a['display']],
        [BOILERPLATE['slug'], $a['slug']],
        [BOILERPLATE['prefix_upper'], $a['prefix_upper']],
        [BOILERPLATE['prefix'], $a['prefix']],
    ];

    // Optional metadata: only replace the known defaults when a value is given.
    if ($a['author_name'] !== '') {
        $pairs[] = [BOILERPLATE['author_name'], $a['author_name']];
    }
    if ($a['author_email'] !== '') {
        $pairs[] = [BOILERPLATE['author_email'], $a['author_email']];
    }
    if ($a['author_uri'] !== '') {
        $pairs[] = [BOILERPLATE['author_uri'], $a['author_uri']];
    }
    if ($a['description'] !== '') {
        $pairs[] = [BOILERPLATE['description_composer'], $a['description']];
        $pairs[] = [BOILERPLATE['description_header'], $a['description']];
    }

    return $pairs;
}

/**
 * @return list<string> tracked-source files under $root, relative paths
 */
function scan_files(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $current): bool {
                if ($current->isDir()) {
                    return !in_array($current->getFilename(), SKIP_DIR_NAMES, true);
                }

                return true;
            },
        ),
    );

    foreach ($iterator as $info) {
        if (!$info->isFile()) {
            continue;
        }
        $rel = ltrim(str_replace($root, '', $info->getPathname()), '/\\');
        $rel = str_replace('\\', '/', $rel);
        if (in_array($rel, SKIP_FILES, true)) {
            continue;
        }
        if (!in_array(strtolower($info->getExtension()), SCAN_EXT, true)) {
            continue;
        }
        $files[] = $rel;
    }

    sort($files);

    return $files;
}

/**
 * @param list<array{0: string, 1: string}> $pairs
 */
function apply_pairs(string $content, array $pairs): string
{
    foreach ($pairs as [$from, $to]) {
        $content = str_replace($from, $to, $content);
    }

    return $content;
}

function run_git(string $root, string ...$args): bool
{
    $cmd = 'git -C ' . escapeshellarg($root);
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg($arg);
    }
    exec($cmd . ' 2>/dev/null', $_, $code);

    return $code === 0;
}

function rrmdir(string $dir): void
{
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        is_dir($path) ? rrmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

// ── main ────────────────────────────────────────────────────────────────────

$parsed = parse_argv($argv);
$flags = $parsed['flags'];
$root = dirname(__DIR__);

$raw = null;
if ($parsed['answers_file'] !== null) {
    $json = @file_get_contents($parsed['answers_file']);
    if ($json === false) {
        fail("cannot read answers file: {$parsed['answers_file']}");
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        fail('answers file is not a JSON object');
    }
    $raw = $decoded;
} elseif (is_tty()) {
    $raw = gather_interactive();
} else {
    out('init.php: non-interactive shell and no --answers given — skipping scaffold.');
    out('          Run `composer init-plugin` in a terminal to rebrand this boilerplate.');
    exit(0);
}

if (isset($raw['minimal'])) {
    $flags['minimal'] = $flags['minimal'] || (bool) $raw['minimal'];
}

$answers = normalize_answers($raw);

foreach (['package', 'slug', 'namespace', 'prefix', 'display'] as $required) {
    if ($answers[$required] === '') {
        fail("missing required answer: {$required}");
    }
}

$pairs = replacement_pairs($answers);

out('');
out('Rebranding with:');
out(sprintf('  package    %s → %s', BOILERPLATE['package'], $answers['package']));
out(sprintf('  slug       %s → %s', BOILERPLATE['slug'], $answers['slug']));
out(sprintf('  namespace  %s → %s', BOILERPLATE['namespace'], $answers['namespace']));
out(sprintf('  prefix     %s → %s', BOILERPLATE['prefix'], $answers['prefix']));
out(sprintf('  display    %s → %s', BOILERPLATE['display'], $answers['display']));
out(sprintf('  repo       %s → %s', BOILERPLATE['repo_full'], $answers['repo_full']));
out('');

$files = scan_files($root);
$changed = 0;

foreach ($files as $rel) {
    $path = $root . '/' . $rel;
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    $rewritten = apply_pairs($content, $pairs);
    if ($rewritten === $content) {
        continue;
    }
    ++$changed;
    if ($flags['dry-run']) {
        out("  would rewrite  {$rel}");
    } else {
        file_put_contents($path, $rewritten);
        out("  rewrote        {$rel}");
    }
}

// Rename the main plugin file to <slug>.php.
$mainFrom = $root . '/' . BOILERPLATE['main_file'];
$mainTo = $root . '/' . $answers['slug'] . '.php';
if (is_file($mainFrom) && $mainFrom !== $mainTo) {
    if ($flags['dry-run']) {
        out(sprintf('  would rename   %s → %s.php', BOILERPLATE['main_file'], $answers['slug']));
    } else {
        if (!run_git($root, 'mv', BOILERPLATE['main_file'], $answers['slug'] . '.php')) {
            rename($mainFrom, $mainTo);
        }
        out(sprintf('  renamed        %s → %s.php', BOILERPLATE['main_file'], $answers['slug']));
    }
}

// Optionally strip the example domain.
if ($flags['minimal']) {
    out('');
    out('Stripping the example domain (--minimal):');
    foreach (EXAMPLE_PATHS as $rel) {
        $path = $root . '/' . $rel;
        if (!file_exists($path)) {
            continue;
        }
        if ($flags['dry-run']) {
            out("  would remove   {$rel}");

            continue;
        }
        if (!run_git($root, 'rm', '-r', '-q', $rel)) {
            is_dir($path) ? rrmdir($path) : @unlink($path);
        }
        out("  removed        {$rel}");
    }
    out('  NOTE: with --minimal you must trim src/Core/Plugin.php and the UI entry');
    out('        to stop referencing the removed example classes.');
}

// Renaming the namespace shortens/re-sorts the plugin's own `use` imports, so
// normalize them with the code-style fixer (present after create-project) — the
// generated project is then `composer check`-clean out of the box.
if (!$flags['dry-run']) {
    $fixer = $root . '/vendor/bin/php-cs-fixer';
    if (is_file($fixer)) {
        out('');
        out('Normalizing code style after rename...');
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixer) . ' fix --quiet 2>/dev/null', $_, $fixCode);
        out($fixCode === 0 ? '  style normalized.' : '  (php-cs-fixer skipped — run `composer fix` yourself)');
    }
}

out('');
out(sprintf('%s %d file(s).', $flags['dry-run'] ? 'Would change' : 'Changed', $changed));

// Remove the scaffolding: self + the composer script entries.
if (!$flags['dry-run'] && !$flags['keep-self']) {
    $composerPath = $root . '/composer.json';
    $composer = json_decode((string) file_get_contents($composerPath), true);
    if (is_array($composer) && isset($composer['scripts']) && is_array($composer['scripts'])) {
        unset($composer['scripts']['post-create-project-cmd'], $composer['scripts']['init-plugin']);
        file_put_contents(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
        );
    }
    @unlink(__FILE__);
    @rmdir(dirname(__FILE__));
    out('Removed bin/init.php and its composer scripts.');
}

out('');
out('Next steps:');
out('  composer install');
out('  cd ui && npm install && npm run build:wp');
out('  Review the plugin header (Author, License) and README, then activate.');
out('  The OSS deps (middag-io/framework, /wordpress, @middag-io/react) are unchanged.');
