<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = $_POST['id'] ?? '';
    if (($_POST['action'] ?? '') === 'remove') unset($_SESSION['cart'][$id]);
    if (($_POST['action'] ?? '') === 'update' && isset($_SESSION['cart'][$id])) {
        $q = (int)($_POST['qty'] ?? 1);
        if ($q > 0) $_SESSION['cart'][$id] = min($q, 999); else unset($_SESSION['cart'][$id]);
    }
    header('Location: cart.php'); exit;
}
header_html('Sepet');
[$items, $total] = cart_items();
if (!$items) { echo '<p>Sepetiniz boş.</p><a class="btn" href="index.php">Alışverişe devam</a>'; footer_html(); exit; }
echo '<table><tr><th>Ürün</th><th>Fiyat</th><th>Adet</th><th>Toplam</th><th></th></tr>';
foreach ($items as $i) {
    echo '<tr><td>' . e($i['name']) . '</td><td>' . money($i['price']) . '</td><td><form method="post">' . csrf_field()
        . '<input type="hidden" name="action" value="update"><input type="hidden" name="id" value="' . e($i['id']) . '">'
        . '<input type="number" name="qty" min="0" value="' . $i['qty'] . '" style="width:70px;margin:0"> <button>Güncelle</button></form></td>'
        . '<td>' . money($i['line']) . '</td><td><form method="post">' . csrf_field()
        . '<input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="' . e($i['id']) . '"><button class="red btn">Sil</button></form></td></tr>';
}
echo '</table><p class="price">Genel Toplam: ' . money($total) . '</p><a class="btn" href="checkout.php">Satın Al</a>';
footer_html();
