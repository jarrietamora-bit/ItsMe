<?php
function load_lang(): void {
    $lang = $_SESSION['lang'] ?? setting('default_language') ?? 'es';
    if (!in_array($lang, ['es','en'], true)) $lang = 'es';
    $_SESSION['lang'] = $lang;
    $file = __DIR__ . '/../languages/' . $lang . '.php';
    if (file_exists($file)) {
        $GLOBALS['_lang'] = require $file;
    } else {
        $GLOBALS['_lang'] = [];
    }
}

function t(string $key, array $replace = []): string {
    $str = $GLOBALS['_lang'][$key] ?? $key;
    foreach ($replace as $k => $v) {
        $str = str_replace(':' . $k, $v, $str);
    }
    return $str;
}

function current_lang(): string {
    return $_SESSION['lang'] ?? 'es';
}

function switch_lang(string $lang): void {
    if (in_array($lang, ['es','en'], true)) {
        $_SESSION['lang'] = $lang;
        if (isset($_SESSION['uid'])) {
            db()->prepare("UPDATE users SET language = ? WHERE id = ?")->execute([$lang, $_SESSION['uid']]);
        }
    }
}
