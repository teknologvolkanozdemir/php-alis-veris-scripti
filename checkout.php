<?php
require __DIR__ . '/includes/bootstrap.php';
[$items, $total] = cart_items();
$s = settings();
$errors = []; $f = ['name' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($items && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($f as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
    if ($f['name'] === '') $errors[] = 'Ad Soyad gerekli.';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Geçerli bir e-posta girin.';
    if ($f['phone'] === '') $errors[] = 'Telefon gerekli.';
    if ($f['address'] === '') $errors[] = 'Adres gerekli.';
    foreach ($f as $k => $v) if (mb_strlen($v) > 1000) $errors[] = 'Alan çok uzun.';
    if (!$errors) {
        $ibans = [];
        $lines = [];
        foreach ($items as $i) {
            $lines[] = "{$i['name']} x {$i['qty']} = " . money($i['line']);
            $ibans[$i['iban']] = true;
        }
        $order = ['id' => date('ymdHis') . random_int(100, 999), 'date' => date('Y-m-d H:i:s'), 'customer' => $f,
            'items' => array_map(fn($i) => ['name' => $i['name'], 'qty' => $i['qty'], 'price' => $i['price'], 'iban' => $i['iban']], $items),
            'total' => $total];
        $orders = db_read('orders'); array_unshift($orders, $order); db_write('orders', $orders);
        $summary = implode("\n", $lines) . "\nToplam: " . money($total);
        $ibanText = implode("\n", array_keys($ibans));
        send_mail($f['email'], "Siparişiniz alındı #{$order['id']}",
            "Merhaba {$f['name']},\n\nSiparişiniz alındı (#{$order['id']}).\n\n$summary\n\nÖdemeyi aşağıdaki IBAN'a yapabilirsiniz:\n$ibanText\n\nTeşekkürler,\n{$s['site_name']}");
        send_mail($s['admin_email'], "Yeni sipariş #{$order['id']}",
            "Yeni sipariş alındı.\n\nMüşteri: {$f['name']}\nE-posta: {$f['email']}\nTelefon: {$f['phone']}\nAdres: {$f['address']}\n\n$summary");
        $_SESSION['cart'] = [];
        header_html('Sipariş Alındı');
        echo '<div class="msg">Siparişiniz alındı. Sipariş no: <b>' . e($order['id']) . '</b></div><div class="box"><p>Ödemeyi aşağıdaki IBAN numarasına yapın:</p>';
        foreach (array_keys($ibans) as $ib) echo '<p><b>' . e($ib) . '</b></p>';
        echo '<p>Toplam: ' . money($total) . '</p></div><p><a class="btn" href="index.php">Ana sayfa</a></p>';
        footer_html(); exit;
    }
}
header_html('Satın Al');
if (!$items) { echo '<p>Sepetiniz boş.</p>'; footer_html(); exit; }
foreach ($errors as $er) echo '<div class="msg err">' . e($er) . '</div>';
echo '<p>Toplam: <span class="price">' . money($total) . '</span></p><form method="post" class="box">' . csrf_field()
    . '<label>Ad Soyad<input name="name" value="' . e($f['name']) . '" required></label>'
    . '<label>E-posta<input type="email" name="email" value="' . e($f['email']) . '" required></label>'
    . '<label>Telefon<input name="phone" value="' . e($f['phone']) . '" required></label>'
    . '<label>Adres<textarea name="address" rows="3" required>' . e($f['address']) . '</textarea></label>'
    . '<button>Siparişi Tamamla</button></form>';
footer_html();
