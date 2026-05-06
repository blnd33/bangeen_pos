<?php
define('API_REQUEST', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_login();

$q = trim($_GET['q'] ?? '');
if (!$q) json_response(['success'=>false,'error'=>'No query']);

// Try exact barcode match first, then name search
$stmt = DB::get()->prepare("
    SELECT p.id, p.name_ar, p.name_en, p.price, p.stock_qty, 
           p.barcode_text, p.category_id, p.unit_ar, p.unit_en 
    FROM products p 
    WHERE p.is_active=1 
    AND (p.barcode_text = ? OR p.name_ar LIKE ? OR p.name_en LIKE ?) 
    LIMIT 1
");
$stmt->execute([$q, "%$q%", "%$q%"]);
$p = $stmt->fetch();

if (!$p) {
    json_response(['success'=>false,'error'=>'Product not found']);
}

json_response(['success'=>true, 'product'=>[
    'id'          => (int)$p['id'],
    'name'        => LANG==='ar' ? $p['name_ar'] : ($p['name_en'] ?: $p['name_ar']),
    'name_ar'     => $p['name_ar'],
    'name_en'     => $p['name_en'],
    'price'       => (float)$p['price'],
    'stock_qty'   => (int)$p['stock_qty'],
    'barcode_text'=> $p['barcode_text'],
    'category_id' => $p['category_id'],
    'unit'        => LANG==='ar' ? $p['unit_ar'] : $p['unit_en'],
]]);