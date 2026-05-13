<?php
define('API_REQUEST',true);
require_once dirname(__DIR__).'/includes/config.php';
require_login();
$id = (int)($_GET['id']??0);
$db = DB::get();
$s = $db->prepare("SELECT s.*, u.full_name_".LANG." as uname FROM sales s LEFT JOIN users u ON s.user_id=u.id WHERE s.id=?");
$s->execute([$id]); $sale = $s->fetch();
if (!$sale) json_response(['success'=>false]);
$sale['total_fmt'] = format_currency((float)$sale['total']);
$items = $db->prepare("SELECT si.*, COALESCE(si.product_name_".LANG.", si.product_name_ar) as product_name FROM sale_items si WHERE si.sale_id=?");
$items->execute([$id]);
$items = $items->fetchAll();
foreach ($items as &$i) {
    $i['unit_price'] = format_currency((float)$i['unit_price']);
    $i['subtotal']   = format_currency((float)$i['subtotal']);
}
json_response(['success'=>true,'sale'=>$sale,'items'=>$items]);
