<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = $_POST['id'] ?? '';
    if (find_product($id)) $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + 1;
    header('Location: cart.php'); exit;
}
$s = settings();
header_html('Ürünler');
echo '<p>' . e($s['site_description']) . '</p><div class="grid">';
foreach (products() as $p) {
    echo '<div class="card">';
    if ($p['image']) echo '<img src="uploads/' . e($p['image']) . '" alt="">';
    echo '<h3>' . e($p['name']) . '</h3><p>' . nl2br(e($p['description'])) . '</p><p class="price">' . money($p['price']) . '</p>'
        . '<form method="post">' . csrf_field() . '<input type="hidden" name="id" value="' . e($p['id']) . '"><button>Sepete Ekle</button></form></div>';
}
echo '</div>';
if (!products()) echo '<p>Henüz ürün yok.</p>';
footer_html();
