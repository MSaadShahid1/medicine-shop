<?php
/** CLI-only: export a read-only static copy of the running app for GitHub Pages. Usage: README "Live demo". */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Static export is available from the command line only.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once dirname(__DIR__) . '/config.php';

const KEEP_PARAMS = ['id', 'bill_id', 'edit_id'];   // read-only parameters; same order as static-demo.js
const SKIP_FILES = ['reset_system.php', 'ai_agent.php', 'search_product.php', 'config.php', 'sidebar.php',
    'page_overview.php', 'pdf_style.php', 'print_controls.php', 'workspace_experience.php'];
const MARKER = '.static-demo';
// An HTML attribute follows whitespace inside a tag; "location.href = '…'" in a script follows a dot.
const STATIC_ATTRIBUTE = '~(?<=\s)(href|src|action)="([^"]*)"~i';

$root = dirname(__DIR__);
$base = rtrim($argv[1] ?? '', '/');
$out = rtrim($argv[2] ?? '', '/\\');
$repository = '';
foreach ($argv as $argument) if (strpos($argument, '--repository=') === 0) $repository = substr($argument, 13);
if (!$base || !$out || strpos($out, '--') === 0) {
    fwrite(STDERR, "Usage: php tools/build_static_demo.php http://127.0.0.1:8000 C:\\path\\to\\output [--repository=https://github.com/owner/repo] [--allow-real-data]\n");
    exit(1);
}

// The export is public: refuse unless every record is labelled demo data (see DEMO_DATA.md).
if (!in_array('--allow-real-data', $argv, true)) {
    $checks = ['companies' => "company_name NOT LIKE '[DEMO]%'", 'customers' => "customer_name NOT LIKE '[DEMO]%'",
        'products' => "product_name NOT LIKE '[DEMO]%'", 'sales_bills' => "bill_number NOT LIKE 'DEMO-%'", 'purchase_bills' => "bill_number NOT LIKE 'DEMO-%'"];
    foreach ($checks as $table => $condition) {
        $count = (int) $conn->query("SELECT COUNT(*) AS n FROM `$table` WHERE $condition")->fetch_assoc()['n'];
        if ($count) {
            fwrite(STDERR, "Refusing to export: $table has $count non-demo record(s). A static demo is public; pass --allow-real-data only if that is intended.\n");
            exit(1);
        }
    }
}

// Only ever clear a folder this script created.
if (is_dir($out) && !is_file("$out/" . MARKER) && count(array_diff(scandir($out), ['.', '..', '.git'])) > 0) {
    fwrite(STDERR, "Refusing to write into $out: it is not empty and was not created by this script.\n");
    exit(1);
}
function staticClear(string $dir): void {
    foreach (array_diff(scandir($dir), ['.', '..', '.git']) as $entry) {
        $path = "$dir/$entry";
        if (is_dir($path) && !is_link($path)) { staticClear($path); rmdir($path); }
        else unlink($path);
    }
}
if (is_dir($out)) staticClear($out); else mkdir($out, 0777, true);

// [file, params, deletes] for a link to a root-level PHP page, or null for anything else.
function staticPageKey(string $url): ?array {
    if ($url === '' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|#)~i', $url)) return null;
    $parts = parse_url($url);
    if (!preg_match('~^(?:\./)?([a-z_]+\.php)$~i', $parts['path'] ?? '', $match)) return null;
    parse_str($parts['query'] ?? '', $query);
    $params = [];
    foreach (KEEP_PARAMS as $key) if (isset($query[$key]) && ctype_digit((string) $query[$key])) $params[$key] = $query[$key];
    return [$match[1], $params, isset($query['delete_id'])];
}
function staticName(string $file, array $params): string {
    $name = basename($file, '.php');
    foreach ($params as $key => $value) $name .= "-$key-$value";
    return $name . '.html';
}
function staticFetch(string $url): ?string {
    $context = stream_context_create(['http' => ['timeout' => 30, 'ignore_errors' => true, 'follow_location' => 0]]);
    $body = @file_get_contents($url, false, $context);
    $headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : ($http_response_header ?? []);
    return $body !== false && isset($headers[0]) && strpos($headers[0], ' 200') !== false ? $body : null;
}

// Crawl from the dashboard, following links only through read-only parameters.
$queue = [['dashboard.php', []]];
$seen = [];
$pages = [];
while ($queue && count($pages) < 2000) {
    [$file, $params] = array_shift($queue);
    $name = staticName($file, $params);
    if (isset($seen[$name])) continue;
    $seen[$name] = true;
    $html = staticFetch($base . '/' . $file . ($params ? '?' . http_build_query($params) : ''));
    if ($html === null) { echo "skipped $name (not a 200 response)\n"; continue; }
    if (preg_match('~<b>(?:Fatal error|Parse error|Warning|Deprecated)</b>~', $html)) echo "warning: PHP message in $name\n";
    $pages[$name] = $html;
    preg_match_all(STATIC_ATTRIBUTE, $html, $refs);
    foreach ($refs[2] as $ref) {
        $key = staticPageKey(html_entity_decode($ref, ENT_QUOTES));
        if ($key && !$key[2] && !in_array($key[0], SKIP_FILES, true)) $queue[] = [$key[0], $key[1]];
    }
}

// Rewrite links: exported pages -> .html; deletes and other server-only links -> a demo notice.
$assets = [];
foreach ($pages as $name => $html) {
    $html = preg_replace_callback(STATIC_ATTRIBUTE, function ($m) use ($pages, &$assets) {
        [$attribute, $name, $value] = $m;
        $raw = html_entity_decode($value, ENT_QUOTES);
        $key = staticPageKey($raw);
        if (!$key) {
            $path = parse_url($raw, PHP_URL_PATH);
            if ($path && !preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|#)~i', $raw)) $assets[ltrim($path, './')] = true;
            return $attribute;
        }
        $target = staticName($key[0], $key[1]);
        if (!$key[2] && !in_array($key[0], SKIP_FILES, true) && isset($pages[$target])) {
            $fragment = ($at = strpos($raw, '#')) !== false ? substr($raw, $at) : '';
            return $name . '="' . $target . htmlspecialchars($fragment, ENT_QUOTES) . '"';
        }
        return strtolower($name) === 'action' ? $attribute : $name . '="#" data-demo-off';   // demo script blocks every submit
    }, $html);
    // Links built in inline JavaScript, e.g. onclick="window.location='company_details.php?id=5'".
    $html = preg_replace_callback('~([\'"])([a-z_]+\.php(?:\?[^\'"<>\s]*)?)\1~i', function ($m) use ($pages) {
        $key = staticPageKey(html_entity_decode($m[2], ENT_QUOTES));
        if (!$key || $key[2] || in_array($key[0], SKIP_FILES, true)) return $m[0];
        $target = staticName($key[0], $key[1]);
        return isset($pages[$target]) ? $m[1] . $target . $m[1] : $m[0];
    }, $html);
    $head = stripos($html, '</head>');
    if ($head !== false) $html = substr_replace($html, "    <link rel=\"stylesheet\" href=\"demo/static-demo.css\">\n", $head, 0);
    $body = strripos($html, '</body>');
    $scripts = "<script src=\"demo/static-demo-data.js\"></script>\n<script src=\"demo/static-demo.js\"></script>\n";
    $html = $body !== false ? substr_replace($html, $scripts, $body, 0) : $html . $scripts;
    file_put_contents("$out/$name", $html);
}
copy("$out/dashboard.html", "$out/index.html");

// Assets: stylesheets, scripts and any other local file the pages reference.
function staticCopyTree(string $from, string $to): void {
    if (!is_dir($to)) mkdir($to, 0777, true);
    foreach (array_diff(scandir($from), ['.', '..']) as $entry) {
        is_dir("$from/$entry") ? staticCopyTree("$from/$entry", "$to/$entry") : copy("$from/$entry", "$to/$entry");
    }
}
staticCopyTree("$root/css", "$out/css");
staticCopyTree("$root/js", "$out/js");
foreach (array_keys($assets) as $asset) {
    $source = realpath("$root/$asset");
    if ($source && is_file($source) && strpos($source, realpath($root)) === 0 && !preg_match('~\.php$|(^|[\\\\/])\.~', $asset) && !is_file("$out/$asset")) {
        if (!is_dir(dirname("$out/$asset"))) mkdir(dirname("$out/$asset"), 0777, true);
        copy($source, "$out/$asset");
    }
}
mkdir("$out/demo");
copy(__DIR__ . '/static-demo/static-demo.js', "$out/demo/static-demo.js");
copy(__DIR__ . '/static-demo/static-demo.css', "$out/demo/static-demo.css");

// Product search data, matching search_product.php's response shape.
$products = [];
$result = $conn->query('SELECT id, product_name, composition, sale_rate, current_stock FROM products ORDER BY product_name');
while ($row = $result->fetch_assoc()) {
    $products[] = ['id' => (int) $row['id'], 'label' => $row['product_name'] . ' (' . $row['composition'] . ')', 'sale_rate' => $row['sale_rate'], 'stock' => (int) $row['current_stock']];
}
$data = ['pages' => array_keys($pages), 'products' => $products, 'repository' => $repository];
file_put_contents("$out/demo/static-demo-data.js", 'window.__staticDemo = ' . json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n");

// GitHub Pages serves this for any missing path: send stray .php addresses to their exported page.
file_put_contents("$out/404.html", <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Not in the demo - Medicine Shop</title>
    <style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;font-family:Inter,system-ui,sans-serif;background:#f6efe9;color:#3e3047;text-align:center}a{color:#6f4c9a}</style>
</head>
<body>
    <p id="notice">This page is not part of the read-only demo. <a href="index.html">Back to the dashboard</a></p>
    <script src="demo/static-demo-data.js"></script>
    <script>
        (function () {
            var pages = (window.__staticDemo && window.__staticDemo.pages) || [];
            var match = location.pathname.match(/([a-z_]+)\.php$/i);
            var params = new URLSearchParams(location.search);
            if (!match) return;
            if (params.has('delete_id')) {
                document.getElementById('notice').firstChild.textContent = 'This is a read-only demo, so nothing can be deleted. ';
                return;
            }
            var name = match[1];
            ['id', 'bill_id', 'edit_id'].forEach(function (key) { var value = params.get(key); if (value && /^\d+$/.test(value)) name += '-' + key + '-' + value; });
            if (pages.indexOf(name + '.html') > -1) location.replace(name + '.html' + location.hash);
        })();
    </script>
</body>
</html>

HTML);
file_put_contents("$out/.nojekyll", '');
file_put_contents("$out/" . MARKER, "Generated by tools/build_static_demo.php on " . date(DATE_ATOM) . "\n");
echo 'Exported ' . count($pages) . " pages and " . count($products) . " products to $out\n";
