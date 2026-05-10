<?php
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

    // Generate unique invoice number
    do {
        $invoice = generate_invoice_number();
        $exists  = $db->prepare("SELECT COUNT(*) FROM sales WHERE invoice_number=?");
        $exists->execute([$invoice]);
    } while ((int)$exists->fetchColumn() > 0);

    $sub    = (float)($body['subtotal']       ?? 0);
    $disc   = (float)($body['discount_total'] ?? 0);
    $tax    = (float)($body['tax_amount']      ?? 0);
    $total  = (float)($body['total']           ?? 0);
    $method = $body['payment_method']          ?? 'cash';
    $cash   = (float)($body['cash_tendered']   ?? 0);
    $change = (float)($body['change_given']    ?? 0);

    $stmt = $db->prepare("INSERT INTO sales 
        (invoice_number, user_id, subtotal, discount_total, tax_amount, total, payment_method, cash_tendered, change_given) 
        VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$invoice, $user['id'], $sub, $disc, $tax, $total, $method, $cash, $change]);
    $sale_id = (int)$db->lastInsertId();

    $items_out = [];
    foreach ($body['cart'] as $item) {
        $prod_stmt = $db->prepare("SELECT * FROM products WHERE id=?");
        $prod_stmt->execute([(int)$item['id']]);
        $prod = $prod_stmt->fetch();
        if (!$prod) continue;

        $qty      = max(1, (int)$item['qty']);
        $price    = (float)$item['price'];
        $line_sub = round($price * $qty, 2);

        // Insert sale item
        $db->prepare("INSERT INTO sale_items 
            (sale_id, product_id, product_name_ar, product_name_en, barcode_text, unit_price, quantity, subtotal) 
            VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$sale_id, $prod['id'], $prod['name_ar'], $prod['name_en'], $prod['barcode_text'], $price, $qty, $line_sub]);

        // Deduct stock
        $db->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id=?")
           ->execute([$qty, $prod['id']]);

        // Log stock movement
        $db->prepare("INSERT INTO stock_log (product_id, user_id, type, quantity, note_ar, note_en) VALUES (?,?,'sale',?,?,?)")
           ->execute([$prod['id'], $user['id'], $qty, 'مبيعة — فاتورة: '.$invoice, 'Sold — Invoice: '.$invoice]);

        $items_out[] = [
            'name'         => LANG==='ar' ? $prod['name_ar'] : ($prod['name_en'] ?: $prod['name_ar']),
            'qty'          => $qty,
            'price'        => $price,
            'barcode_text' => $prod['barcode_text'] ?? '', // ← FIXED: include barcode
        ];
    }

    $db->commit();

    // Load store settings for receipt
    $settings_rows = $db->query("SELECT key_name, value FROM settings")->fetchAll();
    $settings = [];
    foreach ($settings_rows as $sr) { $settings[$sr['key_name']] = $sr['value']; }

    json_response([
        'success'          => true,
        'invoice'          => $invoice,
        'sale_id'          => $sale_id,
        'cashier'          => $user['name'],
        'items'            => $items_out,
        'subtotal'         => $sub,
        'discount_total'   => $disc,
        'tax'              => $tax,
        'total'            => $total,
        'payment_method'   => $method,
        'cash_tendered'    => $cash,
        'change_given'     => $change,
        // Store info for receipt
        'store_name_ar'    => $settings['store_name_ar']    ?? 'بهنگین کریستال',
        'store_name_en'    => $settings['store_name_en']    ?? 'Bangeen Crystal',
        'store_address_ar' => $settings['store_address_ar'] ?? '',
        'store_address_en' => $settings['store_address_en'] ?? '',
        'store_phone'      => $settings['store_phone']      ?? '',
        'currency'         => $settings['currency']         ?? 'IQD',
        'footer_ar'        => $settings['receipt_footer_ar'] ?? 'شكراً لزيارتكم',
        'footer_en'        => $settings['receipt_footer_en'] ?? 'Thank you for your visit',
    ]);

} catch (Exception $e) {
    $db->rollBack();
    json_response(['success'=>false, 'error'=>$e->getMessage()], 500);
}