<?php
function compute_order($order_items, $discountPercent = 0) {
    $subtotal = 0;
    foreach ($order_items as $item) {
        $subtotal += $item['price'] * $item['qty'];
    }
    $service_charge = round($subtotal * 0.10, 2);
    $tax = round($subtotal * 0.12, 2);

    $discount = 0;
    if ($discountPercent > 0) {
        $discount = round(($subtotal + $service_charge + $tax) * $discountPercent, 2);
    }

    $total = $subtotal + $service_charge + $tax - $discount;
    return [
        'subtotal' => $subtotal,
        'service_charge' => $service_charge,
        'tax' => $tax,
        'discount' => $discount,
        'total' => $total
    ];
}