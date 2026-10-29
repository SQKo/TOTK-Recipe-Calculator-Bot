<?php

// Test the real rendering closure without including the server startup code,
// loading Composer/token.php, opening sockets, or running control endpoints.
function pageRenderer($TOTK, $port, $sub): Closure
{
    $source = dirname(__DIR__) . '/src/TOTK/webapi.php';
    $tokens = token_get_all(file_get_contents($source));
    $found = false;
    $expression = '';
    $depth = 0;
    foreach ($tokens as $token) {
        if (!$found) {
            if (is_array($token) && $token[0] === T_VARIABLE && $token[1] === '$webpage_content') {
                $found = true;
            }
            continue;
        }
        if ($expression === '') {
            if ($token === '=') {
                $expression = ' ';
            }
            continue;
        }
        if ($token === ';' && $depth === 0) {
            // __DIR__ in eval would otherwise refer to this test directory.
            $renderer = eval('return ' . $expression . ';');
            if (!$renderer instanceof Closure) {
                throw new RuntimeException('Page renderer is not a closure');
            }
            return $renderer;
        }
        if ($token === '{') {
            ++$depth;
        } elseif ($token === '}') {
            --$depth;
        }
        $expression .= is_array($token)
            ? ($token[0] === T_DIR ? var_export(dirname($source), true) : $token[1])
            : $token;
    }
    throw new RuntimeException('Page rendering closure was not found');
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$originalDirectory = getcwd();
$originalCookies = $_COOKIE;
$fixtureDirectory = sys_get_temp_dir() . '/totk-webapi-' . bin2hex(random_bytes(8));
mkdir($fixtureDirectory);
file_put_contents($fixtureDirectory . '/webapi_content.php', 'WRONG TEMPLATE FROM WORKING DIRECTORY');
$TOTK = (object) [
    'github' => 'https://example.invalid/totk?tab=code',
    'discord' => (object) ['user' => (object) ['displayname' => "Link's Cooking Bot"]],
];
$port = '54321';
$log = '[' . date('Y') . '-01-02] first [] [] second' . PHP_EOL . 'third [] fourth';
$scripts = [];
$cases = 0;

try {
    chdir($fixtureDirectory);
    foreach (['botlog', 'botlog2'] as $sub) {
        foreach ([null, 'false', 'true'] as $cookie) {
            $_COOKIE = $cookie === null ? [] : ['auto-reload' => $cookie];
            $renderer = pageRenderer($TOTK, $port, $sub);
            $level = ob_get_level();
            ob_start();
            echo 'outer buffer';
            $html = $renderer($log);
            check(ob_get_level() === $level + 1, 'Renderer changed the caller output buffer depth');
            check(ob_get_clean() === 'outer buffer', 'Renderer leaked HTML into the caller output buffer');
            check(str_contains($html, '<meta name="color-scheme"'), 'Template did not render from a different working directory');
            check(str_contains($html, "Link's Cooking Bot"), 'Display name missing');
            check(str_contains($html, "window.open('https://example.invalid/totk?tab=code')"), 'Repository link missing');
            check(str_contains($html, "':54321/' + endpoint"), 'Configured port missing');
            check(str_contains($html, 'first </div><div> second</div><div>third</div><div>fourth'), 'Log separators changed');
            $nextLog = $sub === 'botlog' ? 'botlog2' : 'botlog';
            check(str_contains($html, "location.href='/$nextLog'"), 'Log navigation points to the wrong page');
            preg_match('/<input[^>]+id=[\'"]auto-reload-checkbox[\'"][^>]*>/', $html, $input);
            check(isset($input[0]), 'Auto-reload checkbox missing');
            check(str_contains($input[0], 'checked') === ($cookie === 'true'), 'Auto-reload cookie state changed');
            preg_match_all('/<script>(.*?)<\/script>/s', $html, $blocks);
            check(count($blocks[1]) === 2, 'Expected both script blocks');
            preg_match_all('/onclick=([\'"])(.*?)\1/s', $html, $handlers);
            check(count($handlers[2]) === 7, 'Expected all action, repository, reload and navigation handlers');
            array_push($scripts, ...$blocks[1], ...$handlers[2]);
            ++$cases;
        }
    }

    $brokenTOTK = (object) [
        'github' => 'https://example.invalid',
        'discord' => (object) ['user' => new class {
            public function __get($name) {
                throw new RuntimeException('fixture rendering failure');
            }
        }],
    ];
    $renderer = pageRenderer($brokenTOTK, $port, 'botlog');
    $level = ob_get_level();
    ob_start();
    echo 'outer buffer';
    try {
        $renderer($log);
        throw new RuntimeException('Expected fixture rendering failure');
    } catch (RuntimeException $error) {
        check($error->getMessage() === 'fixture rendering failure', 'Unexpected rendering failure');
    }
    check(ob_get_level() === $level + 1, 'Failed render left an output buffer open');
    check(ob_get_clean() === 'outer buffer', 'Failed render leaked partial HTML');
} finally {
    chdir($originalDirectory);
    $_COOKIE = $originalCookies;
    unlink($fixtureDirectory . '/webapi_content.php');
    rmdir($fixtureDirectory);
    restore_error_handler();
}

if (in_array('--scripts', $argv, true)) {
    echo json_encode($scripts, JSON_THROW_ON_ERROR);
} else {
    echo "PASS: $cases page variants, working-directory collision and failed-render buffer cleanup\n";
}
