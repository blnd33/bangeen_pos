<?php
// ============================================================
// Bangeen Crystal — Category POS Checkout API
// ============================================================
define('API_REQUEST', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_login();

// Log raw input for debugging
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!$body || empty($body['cart'])) {
    json_response(['success'=>false,'error'=>'Empty cart. Raw: '.substr($raw,0,200)], 400);
}

$db   = DB::get();
$user = current_user();

try {
    $db->beginTransaction();

    // Generate invoice number
    do {
        $invoice = generate_invoice_number();
        $exists  = $db->prepare("SELECT COUNT(*) FROM sales WHERE invoice_number=?");
        $exists->execute([$invoice]);
    } while ((int)$exists->fetchColumn() > 0);

    $sub    = (float)($body['subtotal']        ?? 0);
    $disc   = (float)($body['discount_total']  ?? 0);
    $total  = (float)($body['total']           ?? 0);
    $method = $body['payment_method']           ?? 'cash';
    $cash   = (float)($body['cash_tendered']   ?? 0);
    $change = (float)($body['change_given']    ?? 0);

    // Insert sale header
    $stmt = $db->prepare("INSERT INTO sales
        (invoice_number, user_id, subtotal, discount_total, tax_amount, total, payment_method, cash_tendered, change_given)
        VALUES (?,?,?,?,0,?,?,?,?)");
    $stmt->execute([$invoice, $user['id'], $sub, $disc, $total, $method, $cash, $change]);
    $sale_id = (int)$db->lastInsertId();

    // Insert sale items — one row per category
    $items_out = [];
    foreach ($body['cart'] as $item) {
        $cat_id  = (int)($item['cat_id']  ?? 0);
        $cat_ar  = trim($item['cat_ar']   ?? '');
        $cat_en  = trim($item['cat_en']   ?? $cat_ar);

        // Support both old field names (total) and new (gross/net)
        $gross   = (float)($item['gross']   ?? $item['total'] ?? 0);
        $itemDisc= (float)($item['discount'] ?? 0);
        $net     = (float)($item['net']     ?? ($gross - $itemDisc));

        if ($gross <= 0) continue;

        $db->prepare("INSERT INTO sale_items
            (sale_id, product_id, product_name_ar, product_name_en, barcode_text, unit_price, quantity, discount_pct, subtotal)
            VALUES (?, NULL, ?, ?, ?, ?, 1, ?, ?)")
           ->execute([
               $sale_id,
               $cat_ar ?: 'فئة',
               $cat_en ?: 'Category',
               'CAT-' . $cat_id,
               $gross,       // unit_price = gross before discount
               0,            // discount_pct = 0 (we store absolute discount in subtotal difference)
               $net,         // subtotal = net after discount
           ]);

        $items_out[] = [
            'name'    => LANG === 'ar' ? $cat_ar : $cat_en,
            'name_ar' => $cat_ar,
            'name_en' => $cat_en,
            'gross'   => $gross,
            'disc'    => $itemDisc,
            'net'     => $net,
            'subtotal'=> $net,
        ];
    }

    $db->commit();

    // Load store settings
    $settings = [];
    foreach ($db->query("SELECT key_name, value FROM settings")->fetchAll() as $r) {
        $settings[$r['key_name']] = $r['value'];
    }

    json_response([
        'success'          => true,
        'invoice'          => $invoice,
        'sale_id'          => $sale_id,
        'cashier'          => $user['name'],
        'date'             => date('Y-m-d H:i:s'),
        'items'            => $items_out,
        'subtotal'         => $sub,
        'discount_total'   => $disc,
        'total'            => $total,
        'payment_method'   => $method,
        'cash_tendered'    => $cash,
        'change_given'     => $change,
        'currency'         => $settings['currency_symbol'] ?? ($settings['currency'] ?? 'IQD'),
        'store_name_ar'    => $settings['store_name_ar']    ?? 'بهنگین کریستال',
        'store_name_en'    => $settings['store_name_en']    ?? 'Bangeen Crystal',
        'store_address_ar' => $settings['store_address_ar'] ?? '',
        'store_address_en' => $settings['store_address_en'] ?? '',
        'store_phone'      => $settings['store_phone']      ?? '',
        'footer_ar'        => $settings['receipt_footer_ar'] ?? 'شكراً لزيارتكم',
        'footer_en'        => $settings['receipt_footer_en'] ?? 'Thank you for your visit',
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    json_response(['success'=>false,'error'=>$e->getMessage()], 500);
}