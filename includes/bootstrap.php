<?php
// Flat-file alışveriş scripti - ortak fonksiyonlar
session_start();
define('ROOT', dirname(__DIR__));
define('DATA_DIR', ROOT . '/data');
define('UPLOAD_DIR', ROOT . '/uploads');

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function db_read($name, $default = []) {
    $f = DATA_DIR . "/$name.json";
    if (!is_file($f)) return $default;
    $d = json_decode((string)file_get_contents($f), true);
    return is_array($d) ? $d : $default;
}

function db_write($name, $data) {
    $f = DATA_DIR . "/$name.json";
    file_put_contents($f, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function settings() {
    $d = [
        'site_name' => 'Mağazam', 'site_description' => 'Basit alışveriş sitesi',
        'admin_email' => '', 'currency' => 'TL',
        'admin_user' => 'admin', 'admin_pass_hash' => password_hash('admin123', PASSWORD_DEFAULT),
        'smtp_enabled' => 0, 'smtp_host' => '', 'smtp_port' => 587, 'smtp_secure' => 'tls',
        'smtp_user' => '', 'smtp_pass' => '', 'smtp_from' => '', 'smtp_from_name' => '',
    ];
    return array_merge($d, db_read('settings'));
}

function products() { return db_read('products'); }
function find_product($id) {
    foreach (products() as $p) if ($p['id'] === $id) return $p;
    return null;
}

function money($v) { $s = settings(); return number_format((float)$v, 2, ',', '.') . ' ' . $s['currency']; }

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field() { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Geçersiz istek (CSRF).'); }
}

function cart_items() {
    $items = []; $total = 0;
    foreach ($_SESSION['cart'] ?? [] as $id => $qty) {
        $p = find_product($id);
        if (!$p) { unset($_SESSION['cart'][$id]); continue; }
        $p['qty'] = (int)$qty; $p['line'] = $p['price'] * $qty;
        $total += $p['line']; $items[] = $p;
    }
    return [$items, $total];
}

function header_html($title) {
    $s = settings();
    $count = array_sum($_SESSION['cart'] ?? []);
    echo '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($title) . ' - ' . e($s['site_name']) . '</title><link rel="stylesheet" href="' . (defined('IN_ADMIN') ? '../' : '') . 'assets/style.css"></head><body>'
        . '<header><div class="wrap"><a class="logo" href="' . (defined('IN_ADMIN') ? '../' : '') . 'index.php">' . e($s['site_name']) . '</a>';
    if (!defined('IN_ADMIN')) echo '<a href="cart.php">Sepet (' . $count . ')</a>';
    echo '</div></header><main class="wrap">';
}
function footer_html() { echo '</main></body></html>'; }

function send_mail($to, $subject, $body) {
    $s = settings();
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $from = $s['smtp_from'] ?: ($s['admin_email'] ?: 'noreply@localhost');
    $fromName = $s['smtp_from_name'] ?: $s['site_name'];
    $clean = fn($v) => str_replace(["\r", "\n"], ' ', $v);
    $headers = "From: =?UTF-8?B?" . base64_encode($clean($fromName)) . "?= <" . $clean($from) . ">\r\n"
        . "To: " . $clean($to) . "\r\n"
        . "Subject: =?UTF-8?B?" . base64_encode($clean($subject)) . "?=\r\n"
        . "Date: " . date('r') . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
    $payload = chunk_split(base64_encode($body));
    if (!$s['smtp_enabled'] || !$s['smtp_host']) {
        $h = preg_replace("/^To: .*\r\n|^Subject: .*\r\n/m", '', $headers);
        return @mail($to, "=?UTF-8?B?" . base64_encode($clean($subject)) . "?=", $payload, $h);
    }
    return smtp_send($s, $from, $to, $headers . "\r\n" . $payload);
}

function smtp_send($s, $from, $to, $message) {
    $host = ($s['smtp_secure'] === 'ssl' ? 'ssl://' : '') . $s['smtp_host'];
    $fp = @stream_socket_client($host . ':' . (int)$s['smtp_port'], $en, $es, 15);
    if (!$fp) return false;
    stream_set_timeout($fp, 15);
    $read = function () use ($fp) {
        $r = '';
        while (($l = fgets($fp, 515)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
        return $r;
    };
    $cmd = function ($c, $ok) use ($fp, $read) {
        if ($c !== null) fwrite($fp, $c . "\r\n");
        $r = $read();
        return in_array((int)substr($r, 0, 3), (array)$ok, true);
    };
    $name = $_SERVER['SERVER_NAME'] ?? 'localhost';
    $addr = fn($a) => '<' . str_replace(['<', '>', "\r", "\n"], '', $a) . '>';
    $okk = $cmd(null, 220) && $cmd("EHLO $name", 250);
    if ($okk && $s['smtp_secure'] === 'tls') {
        $okk = $cmd('STARTTLS', 220) && @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) && $cmd("EHLO $name", 250);
    }
    if ($okk && $s['smtp_user'] !== '') {
        $okk = $cmd('AUTH LOGIN', 334) && $cmd(base64_encode($s['smtp_user']), 334) && $cmd(base64_encode($s['smtp_pass']), 235);
    }
    $okk = $okk && $cmd('MAIL FROM:' . $addr($from), 250) && $cmd('RCPT TO:' . $addr($to), [250, 251])
        && $cmd('DATA', 354);
    if ($okk) {
        $message = preg_replace('/^\./m', '..', $message);
        $okk = $cmd($message . "\r\n.", 250);
    }
    @fwrite($fp, "QUIT\r\n"); fclose($fp);
    return $okk;
}
