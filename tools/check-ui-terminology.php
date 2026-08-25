<?php
declare(strict_types=1);

/** User-visible terminology gate adapted from xinzhou-code-standard. */
$root = realpath(__DIR__ . '/..') ?: getcwd();
$configPath = $root . '/config/ui-terminology.json';
$config = is_file($configPath) ? json_decode((string) file_get_contents($configPath), true) : null;
if (!is_array($config)) { fwrite(STDERR, "Invalid UI terminology config\n"); exit(2); }

function uiTermMatch(string $relative, array $include): bool {
    foreach ($include as $pattern) {
        if (fnmatch($pattern, $relative, FNM_PATHNAME)) return true;
    }
    return false;
}
function uiTermExcluded(string $relative, array $exclude): bool {
    foreach ($exclude as $prefix) if (str_starts_with($relative, $prefix)) return true;
    return false;
}
function uiTermNodes(string $html): array {
    $nodes = [];
    foreach (preg_split('/<[^>]*>/u', $html) ?: [] as $text) {
        $text = html_entity_decode(trim($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($text !== '') $nodes[] = $text;
    }
    return $nodes;
}
function uiTermPhp(string $source): array {
    $result = []; $echo = false;
    foreach (token_get_all($source) as $token) {
        if (is_string($token)) { if ($token === ';') $echo = false; continue; }
        [$id, $text, $line] = $token;
        if ($id === T_INLINE_HTML) foreach (uiTermNodes($text) as $node) $result[] = [$line, $node];
        if ($id === T_ECHO || $id === T_PRINT) { $echo = true; continue; }
        if ($echo && $id === T_CONSTANT_ENCAPSED_STRING) {
            $literal = trim($text, "'\"");
            if (str_contains($literal, '<')) foreach (uiTermNodes($literal) as $node) if ($node !== '') $result[] = [$line, $node];
        }
    }
    return $result;
}
function uiTermJs(string $source): array {
    $result = []; $sink = '/(?:textContent|innerHTML|insertAdjacentHTML|setAttribute\\s*\\(\\s*[\'\"](?:title|aria-label)|alert\\s*\\(|confirm\\s*\\()/i';
    foreach (preg_split('/\R/u', $source) ?: [] as $i => $line) {
        if (!preg_match($sink, $line)) continue;
        if (preg_match_all('/([\'\"])(.*?)\\1|`([^`]*)`/u', $line, $matches, PREG_SET_ORDER)) foreach ($matches as $m) {
            $template = isset($m[3]) && $m[3] !== '' ? $m[3] : ($m[2] ?? '');
            foreach (uiTermNodes($template) as $node) $result[] = [$i + 1, $node];
        }
    }
    return $result;
}
function uiTermChinese(string $text): bool { return preg_match('/[\x{3400}-\x{9FFF}]/u', $text) === 1; }
function uiTermRenderedText(string $text, array $config): string {
    foreach (($config['rendered_replacements'] ?? []) as $from => $to) $text = str_replace($from, $to, $text);
    return $text;
}

$issues = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iter as $info) {
    if (!$info->isFile()) continue;
    $path = $info->getPathname(); $relative = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
    if (!uiTermMatch($relative, $config['include']) || uiTermExcluded($relative, $config['exclude'])) continue;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION)); if (!in_array($ext, ['php', 'js', 'html', 'htm'], true)) continue;
    $source = (string) file_get_contents($path);
    $snippets = $ext === 'php' ? uiTermPhp($source) : ($ext === 'js' ? uiTermJs($source) : array_map(fn($line, $i) => [$i + 1, $line], preg_split('/\R/u', $source) ?: [], array_keys(preg_split('/\R/u', $source) ?: [])));
    foreach ($snippets as [$line, $text]) {
        if (str_contains($text, $config['allow_marker'])) continue;
        $text = uiTermRenderedText($text, $config);
        foreach ($config['blocking_patterns'] as $rule) if (@preg_match('/' . $rule . '/iu', $text) === 1) $issues[] = [$relative, $line, $rule, $text];
        foreach ($config['internal_enum_patterns'] as $rule) if (@preg_match('/' . $rule . '/iu', $text) === 1) $issues[] = [$relative, $line, $rule, $text];
        foreach ($config['contextual_terms'] as $term) if (preg_match('/\\b' . preg_quote($term, '/') . '\\b/iu', $text) === 1 && !uiTermChinese($text)) $issues[] = [$relative, $line, $term . ' requires Chinese context', $text];
    }
}
if ($issues) {
    fwrite(STDERR, "UI Terminology Gate: BLOCKED\n");
    foreach ($issues as [$file, $line, $rule, $text]) fwrite(STDERR, sprintf("%s:%d [%s] %s\n", $file, $line, $rule, mb_strimwidth($text, 0, 120, '...', 'UTF-8')));
    exit(1);
}
echo "UI Terminology Gate: PASS\n";
