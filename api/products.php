<?php
define('API_REQUEST', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_login();

$cat    = (int)($_GET['cat'] ?? 0);
$search = trim($_GET['search'] ?? '');

$where  = 'WHERE p.is_active=1';
$params = [];
if ($cat)    { $where .= ' AND p.category_id=?'; $params[] = $cat; }
if ($search) { $where .= ' AND (p.name_ar LIKE ? OR p.name_en LIKE ? OR p.barcode_text LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = DB::get()->prepare("
    SELECT p.id, p.name_ar, p.name_en, p.price, p.stock_qty, 
           p.barcode_text, p.category_id, p.unit_ar, p.unit_en 
    FROM products p $where ORDER BY p.name_ar
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$products = [];
foreach ($rows as $p) {
    $products[] = [
        'id'          => (int)$p['id'],
        'name'        => LANG==='ar' ? $p['name_ar'] : ($p['name_en'] ?: $p['name_ar']),
        'name_ar'     => $p['name_ar'],
        'name_en'     => $p['name_en'],
        'price'       => (float)$p['price'],
        'stock_qty'   => (int)$p['stock_qty'],
        'barcode_text'=> $p['barcode_text'],
        'category_id' => $p['category_id'],
        'unit'        => LANG==='ar' ? $p['unit_ar'] : $p['unit_en'],
    ];
}

json_response(['success'=>true, 'products'=>$products]);