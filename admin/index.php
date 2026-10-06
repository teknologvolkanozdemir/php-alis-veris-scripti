<?php
define('IN_ADMIN', 1);
require __DIR__ . '/../includes/bootstrap.php';
$s = settings();
$msg = '';
$page = $_GET['p'] ?? 'products';

if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }

if (empty($_SESSION['admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        if (hash_equals($s['admin_user'], $_POST['user'] ?? '') && password_verify($_POST['pass'] ?? '', $s['admin_pass_hash'])) {
            session_regenerate_id(true); $_SESSION['admin'] = 1; header('Location: index.php'); exit;
        }
        $msg = 'Hatalı kullanıcı adı veya şifre.';
    }
    header_html('Yönetici Girişi');
    if ($msg) echo '<div class="msg err">' . e($msg) . '</div>';
    echo '<form method="post" class="box">' . csrf_field() . '<label>Kullanıcı<input name="user"></label><label>Şifre<input type="password" name="pass"></label><button>Giriş</button></form><p>İlk giriş: admin / admin123 (hemen değiştirin)</p>';
    footer_html(); exit;
}

function save_image() {
    if (empty($_FILES['image']['name']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) return null;
    $info = @getimagesize($_FILES['image']['tmp_name']);
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'][$info[2] ?? 0] ?? null;
    if (!$ext) return false;
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    return move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . '/' . $name) ? $name : false;
}
function del_image($n) { if ($n && preg_match('/^[a-f0-9]+\.\w+$/', $n)) @unlink(UPLOAD_DIR . '/' . $n); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'save_product') {
        $list = products(); $id = $_POST['id'] ?? ''; $idx = null;
        foreach ($list as $k => $p) if ($p['id'] === $id) $idx = $k;
        $row = ['id' => $idx === null ? bin2hex(random_bytes(6)) : $id,
            'name' => trim($_POST['name'] ?? ''), 'description' => trim($_POST['description'] ?? ''),
            'price' => max(0, (float)str_replace(',', '.', $_POST['price'] ?? '0')),
            'iban' => trim($_POST['iban'] ?? ''), 'image' => $idx === null ? '' : $list[$idx]['image']];
        $img = save_image();
        if ($row['name'] === '') $msg = 'Ürün adı gerekli.';
        elseif ($img === false) $msg = 'Geçersiz görsel (jpg, png, gif, webp).';
        else {
            if ($img) { del_image($row['image']); $row['image'] = $img; }
            if ($idx === null) $list[] = $row; else $list[$idx] = $row;
            db_write('products', array_values($list)); $msg = 'Ürün kaydedildi.';
        }
    } elseif ($act === 'delete_product') {
        $list = [];
        foreach (products() as $p) { if ($p['id'] === ($_POST['id'] ?? '')) del_image($p['image']); else $list[] = $p; }
        db_write('products', $list); $msg = 'Ürün silindi.';
    } elseif ($act === 'save_settings') {
        foreach (['site_name', 'site_description', 'admin_email', 'currency', 'admin_user'] as $k) $s[$k] = trim($_POST[$k] ?? $s[$k]);
        if (($_POST['admin_pass'] ?? '') !== '') $s['admin_pass_hash'] = password_hash($_POST['admin_pass'], PASSWORD_DEFAULT);
        db_write('settings', $s); $msg = 'Ayarlar kaydedildi.';
    } elseif ($act === 'save_smtp') {
        foreach (['smtp_host', 'smtp_user', 'smtp_from', 'smtp_from_name'] as $k) $s[$k] = trim($_POST[$k] ?? '');
        $s['smtp_enabled'] = empty($_POST['smtp_enabled']) ? 0 : 1;
        $s['smtp_port'] = (int)($_POST['smtp_port'] ?? 587);
        $s['smtp_secure'] = in_array($_POST['smtp_secure'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_secure'] : 'none';
        if (($_POST['smtp_pass'] ?? '') !== '') $s['smtp_pass'] = $_POST['smtp_pass'];
        db_write('settings', $s); $msg = 'SMTP ayarları kaydedildi.';
        if (!empty($_POST['test']))
            $msg .= send_mail($s['admin_email'], 'SMTP test', 'Test maili.') ? ' Test maili gönderildi.' : ' Test maili gönderilemedi.';
    }
}

header_html('Yönetim');
echo '<div class="nav"><a href="?p=products">Ürünler</a><a href="?p=orders">Siparişler</a><a href="?p=settings">Site Ayarları</a><a href="?p=smtp">SMTP</a><a href="?logout=1">Çıkış</a></div><br>';
if ($msg) echo '<div class="msg">' . e($msg) . '</div>';

if ($page === 'orders') {
    echo '<h2>Siparişler</h2>';
    foreach (db_read('orders') as $o) {
        echo '<div class="box"><b>#' . e($o['id']) . '</b> ' . e($o['date']) . '<br>' . e($o['customer']['name']) . ' - ' . e($o['customer']['email']) . ' - ' . e($o['customer']['phone'])
            . '<br>' . nl2br(e($o['customer']['address'])) . '<ul>';
        foreach ($o['items'] as $i) echo '<li>' . e($i['name']) . ' x ' . (int)$i['qty'] . '</li>';
        echo '</ul><b>' . money($o['total']) . '</b></div><br>';
    }
} elseif ($page === 'settings') {
    echo '<h2>Site Ayarları</h2><form method="post" class="box">' . csrf_field() . '<input type="hidden" name="action" value="save_settings">'
        . '<label>Site adı<input name="site_name" value="' . e($s['site_name']) . '"></label>'
        . '<label>Açıklama<input name="site_description" value="' . e($s['site_description']) . '"></label>'
        . '<label>Yönetici e-posta (sipariş bildirimi)<input type="email" name="admin_email" value="' . e($s['admin_email']) . '"></label>'
        . '<label>Para birimi<input name="currency" value="' . e($s['currency']) . '"></label>'
        . '<label>Yönetici kullanıcı adı<input name="admin_user" value="' . e($s['admin_user']) . '"></label>'
        . '<label>Yeni şifre (boş = değiştirme)<input type="password" name="admin_pass" autocomplete="new-password"></label><button>Kaydet</button></form>';
} elseif ($page === 'smtp') {
    echo '<h2>SMTP Ayarları</h2><form method="post" class="box">' . csrf_field() . '<input type="hidden" name="action" value="save_smtp">'
        . '<label><input type="checkbox" name="smtp_enabled" value="1" style="width:auto"' . ($s['smtp_enabled'] ? ' checked' : '') . '> SMTP kullan (kapalıysa PHP mail())</label><br><br>'
        . '<label>Sunucu<input name="smtp_host" value="' . e($s['smtp_host']) . '"></label>'
        . '<label>Port<input type="number" name="smtp_port" value="' . (int)$s['smtp_port'] . '"></label>'
        . '<label>Güvenlik<select name="smtp_secure">';
    foreach (['tls' => 'STARTTLS', 'ssl' => 'SSL', 'none' => 'Yok'] as $k => $v) echo '<option value="' . $k . '"' . ($s['smtp_secure'] === $k ? ' selected' : '') . '>' . $v . '</option>';
    echo '</select></label><label>Kullanıcı<input name="smtp_user" value="' . e($s['smtp_user']) . '"></label>'
        . '<label>Şifre (boş = değiştirme)<input type="password" name="smtp_pass" autocomplete="new-password"></label>'
        . '<label>Gönderen e-posta<input name="smtp_from" value="' . e($s['smtp_from']) . '"></label>'
        . '<label>Gönderen adı<input name="smtp_from_name" value="' . e($s['smtp_from_name']) . '"></label>'
        . '<label><input type="checkbox" name="test" value="1" style="width:auto"> Kaydederken yönetici adresine test maili gönder</label><br><br><button>Kaydet</button></form>';
} else {
    $edit = ['id' => '', 'name' => '', 'description' => '', 'price' => '', 'iban' => '', 'image' => ''];
    if (!empty($_GET['edit']) && ($p = find_product($_GET['edit']))) $edit = $p;
    echo '<h2>' . ($edit['id'] ? 'Ürünü Düzenle' : 'Yeni Ürün') . '</h2><form method="post" enctype="multipart/form-data" class="box">' . csrf_field()
        . '<input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="' . e($edit['id']) . '">'
        . '<label>Ürün adı<input name="name" value="' . e($edit['name']) . '" required></label>'
        . '<label>Açıklama<textarea name="description" rows="3">' . e($edit['description']) . '</textarea></label>'
        . '<label>Fiyat<input name="price" value="' . e($edit['price']) . '" required></label>'
        . '<label>IBAN<input name="iban" value="' . e($edit['iban']) . '" required></label>'
        . '<label>Görsel<input type="file" name="image" accept="image/*"></label><button>Kaydet</button></form><h2>Ürünler</h2><table>';
    foreach (products() as $p) {
        echo '<tr><td>' . ($p['image'] ? '<img class="thumb" src="../uploads/' . e($p['image']) . '" alt="">' : '') . '</td><td>' . e($p['name']) . '</td><td>' . money($p['price'])
            . '</td><td><a href="?p=products&edit=' . e($p['id']) . '">Düzenle</a></td><td><form method="post" onsubmit="return confirm(\'Silinsin mi?\')">' . csrf_field()
            . '<input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="' . e($p['id']) . '"><button class="btn red">Sil</button></form></td></tr>';
    }
    echo '</table>';
}
footer_html();
