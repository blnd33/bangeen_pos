<?php
// ============================================================
// Bangeen Crystal — Category POS Checkout API
// ============================================================
define('API_REQUEST', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_login();

$body = json_decode(file_get_contents('php://input'), true);
if (!$body || empty($body['cart'])) {
    json_response(['success'=>false,'error'=>'Empty cart'], 400);
}

$db   = DB::get();
$user = current_user();

try {
    $db->beginTransaction();

    // ── Generate invoice number ──────────────────────────
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

    // ── Insert sale header ───────────────────────────────
    $stmt = $db->prepare("INSERT INTO sales
        (invoice_number, user_id, subtotal, discount_total, tax_amount, total, payment_method, cash_tendered, change_given)
        VALUES (?,?,?,?,0,?,?,?,?)");
    $stmt->execute([$invoice, $user['id'], $sub, $disc, $total, $method, $cash, $change]);
    $sale_id = (int)$db->lastInsertId();

    // ── Insert sale items (one per category) ─────────────
    $items_out = [];
    foreach ($body['cart'] as $item) {
        $cat_id  = (int)($item['cat_id'] ?? 0);
        $cat_ar  = trim($item['cat_ar']  ?? '');
        $cat_en  = trim($item['cat_en']  ?? $cat_ar);
        $amount  = (float)($item['total'] ?? 0);

        if ($amount <= 0) continue;

        // Use category name as product name, price = amount, qty = 1
        $db->prepare("INSERT INTO sale_items
            (sale_id, product_id, product_name_ar, product_name_en, barcode_text, unit_price, quantity, subtotal)
            VALUES (?, NULL, ?, ?, ?, ?, 1, ?)")
           ->execute([
               $sale_id,
               $cat_ar,
               $cat_en,
               'CAT-' . $cat_id,   // synthetic barcode for category sale
               $amount,
               $amount,
           ]);

        $items_out[] = [
            'name'  => LANG === 'ar' ? $cat_ar : $cat_en,
            'qty'   => 1,
            'price' => $amount,
        ];
    }

    $db->commit();

    // ── Load store settings for receipt ──────────────────
    $settings = [];
    foreach ($db->query("SELECT key_name, value FROM settings")->fetchAll() as $r) {
        $settings[$r['key_name']] = $r['value'];
    }

    json_response([
        'success'          => true,
        'invoice'          => $invoice,
        'sale_id'          => $sale_id,
        'cashier'          => $user['name'],
        'items'            => $items_out,
        'subtotal'         => $sub,
        'discount_total'   => $disc,
        'total'            => $total,
        'payment_method'   => $method,
        'cash_tendered'    => $cash,
        'change_given'     => $change,
        'store_name_ar'    => $settings['store_name_ar']     ?? 'بهنگین کریستال',
        'store_name_en'    => $settings['store_name_en']     ?? 'Bangeen Crystal',
        'currency'         => $settings['currency_symbol']   ?? 'IQD',
    ]);

} catch (Exception $e) {
    $db->rollBack();
    json_response(['success'=>false,'error'=>$e->getMessage()], 500);
}