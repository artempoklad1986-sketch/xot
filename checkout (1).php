<?php
/**
 * Страница оформления заказа - Sasha's Sushi
 * Версия: 5.9.1
 */

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../database.php';

$sessionId = $_SESSION['cart_id'] ?? null;
if (!$sessionId) {
    $_SESSION['cart_id'] = 'cart_' . uniqid() . '_' . time();
    $sessionId = $_SESSION['cart_id'];
}

$editOrderId   = $_GET['edit_order'] ?? null;
$editOrderData = null;
$isEdit        = false;

if ($editOrderId) {
    try {
        $editOrderData = $db->findOne('orders', ['id' => $editOrderId]);
        if ($editOrderData) {
            $isEdit = true;

            if (isset($editOrderData['items']) && is_string($editOrderData['items']))
                $editOrderData['items'] = json_decode($editOrderData['items'], true) ?? [];
            if (!isset($editOrderData['items']) || !is_array($editOrderData['items']))
                $editOrderData['items'] = [];

            if (isset($editOrderData['delivery']) && is_string($editOrderData['delivery']))
                $editOrderData['delivery'] = json_decode($editOrderData['delivery'], true) ?? [];
            if (!isset($editOrderData['delivery']) || !is_array($editOrderData['delivery'])) {
                $editOrderData['delivery'] = [
                    'type'      => $editOrderData['delivery_type']      ?? 'delivery',
                    'date'      => $editOrderData['delivery_date']      ?? '',
                    'time'      => $editOrderData['delivery_time']      ?? '',
                    'slot_time' => $editOrderData['delivery_time']      ?? '',
                    'address'   => $editOrderData['delivery_address']   ?? '',
                    'street'    => $editOrderData['delivery_street']    ?? '',
                    'house'     => $editOrderData['delivery_house']     ?? '',
                    'entrance'  => $editOrderData['delivery_entrance']  ?? '',
                    'floor'     => $editOrderData['delivery_floor']     ?? '',
                    'apartment' => $editOrderData['delivery_apartment'] ?? '',
                    'slot_id'   => $editOrderData['slot_id']            ?? null,
                    'zone_id'   => $editOrderData['zone_id']            ?? null,
                    'zone_name' => $editOrderData['zone_name']          ?? null,
                    'zone_slug' => $editOrderData['zone_slug']          ?? null,
                ];
            }

            if (isset($editOrderData['customer']) && is_string($editOrderData['customer']))
                $editOrderData['customer'] = json_decode($editOrderData['customer'], true) ?? [];
            if (!isset($editOrderData['customer']) || !is_array($editOrderData['customer'])) {
                $editOrderData['customer'] = [
                    'id'    => $editOrderData['customer_id']    ?? null,
                    'name'  => $editOrderData['customer_name']  ?? '',
                    'phone' => $editOrderData['customer_phone'] ?? '',
                    'email' => $editOrderData['customer_email'] ?? '',
                ];
            }
        } else {
            $editOrderId = null;
        }
    } catch (Exception $e) {
        $editOrderData = null;
        $editOrderId   = null;
        $isEdit        = false;
    }
}

$currentCart = null;
$cartItems   = [];
try {
    $currentCart = $db->getCart($sessionId);
    $cartItems   = $currentCart['items'] ?? [];
} catch (Exception $e) {
    $cartItems = [];
}

$catalogProducts = [];
if ($isEdit) {
    try {
        $allProducts = $db->findAll('products', ['status' => 'active']) ?: [];
        usort($allProducts, fn($a, $b) => strcmp(
            mb_strtolower($a['name'] ?? ''),
            mb_strtolower($b['name'] ?? '')
        ));
        $catalogProducts = array_map(fn($p) => [
            'id'       => $p['id'],
            'name'     => $p['name']     ?? '',
            'price'    => floatval($p['price'] ?? 0),
            'image'    => $p['image']    ?? '',
            'category' => $p['category'] ?? '',
            'weight'   => $p['weight']   ?? '',
            'pieces'   => $p['pieces']   ?? '',
        ], $allProducts);
    } catch (Exception $e) {
        error_log('[CHECKOUT v5.9.1] catalog load error: ' . $e->getMessage());
    }
}

$zones       = [];
$streetsData = [];
try {
    $zones = $db->findAll('delivery_zones', ['status' => 'active']) ?: [];
    foreach ($zones as $zone) {
        $streetsJson = $zone['streets'] ?? '[]';
        $zoneStreets = is_array($streetsJson) ? $streetsJson : (json_decode($streetsJson, true) ?: []);
        foreach ($zoneStreets as $street) {
            $streetsData[] = [
                'name'          => $street,
                'zone_id'       => $zone['id'],
                'zone_name'     => $zone['name'],
                'zone_slug'     => $zone['slug'],
                'zone_color'    => $zone['color'],
                'min_order'     => floatval($zone['min_order']),
                'delivery_cost' => floatval($zone['delivery_cost']),
                'delivery_time' => $zone['delivery_time'],
            ];
        }
    }
    usort($streetsData, fn($a, $b) => strcmp(mb_strtolower($a['name']), mb_strtolower($b['name'])));
} catch (Exception $e) {}

$editOrderSlotId = null;
if ($editOrderData) {
    $editOrderSlotId = $editOrderData['slot_id'] ?? ($editOrderData['delivery']['slot_id'] ?? null);
}

try {
    $siteSettings   = $settings->getAll();
    $availableSlots = [];
    $today          = date('Y-m-d');
    $currentTime    = date('H:i');

    for ($i = 0; $i < 7; $i++) {
        $date     = date('Y-m-d', strtotime("+{$i} days"));
        $daySlots = $db->findAll('delivery_slots', ['date' => $date]);

        $filteredSlots = array_filter($daySlots, function ($slot) use ($date, $today, $currentTime, $editOrderSlotId, $editOrderId) {
            $booked   = intval($slot['booked_count'] ?? $slot['occupied'] ?? 0);
            $capacity = intval($slot['capacity'] ?? 10);
            $status   = $slot['status'] ?? 'closed';
            $slotTime = $slot['time']   ?? $slot['time_slot'] ?? '00:00';
            $isOwnSlot = false;
            if ($editOrderId) {
                if ($editOrderSlotId && ($slot['id'] ?? null) === $editOrderSlotId) $isOwnSlot = true;
                $bookedOrders = $slot['booked_orders'] ?? [];
                if (is_array($bookedOrders) && in_array($editOrderId, $bookedOrders)) $isOwnSlot = true;
            }
            if ($isOwnSlot) return true;
            if (!in_array($status, ['active', 'available']) || $booked >= $capacity) return false;
            if ($date === $today) {
                if (strtotime($slotTime) < strtotime('+60 minutes', strtotime($currentTime))) return false;
            }
            return true;
        });

        $filteredSlots = array_map(function($slot) use ($editOrderSlotId, $editOrderId) {
            $isOwn = false;
            if ($editOrderId) {
                if ($editOrderSlotId && ($slot['id'] ?? null) === $editOrderSlotId) $isOwn = true;
                $bo = $slot['booked_orders'] ?? [];
                if (is_array($bo) && in_array($editOrderId, $bo)) $isOwn = true;
            }
            $slot['is_own_slot'] = $isOwn;
            return $slot;
        }, $filteredSlots);

        usort($filteredSlots, fn($a, $b) =>
            strcmp($a['time'] ?? $a['time_slot'] ?? '', $b['time'] ?? $b['time_slot'] ?? '')
        );
        $availableSlots[$date] = array_values($filteredSlots);
    }

    if (empty($siteSettings)) {
        $siteSettings = [
            'site_name'        => "Sasha's Sushi",
            'delivery_cost'    => 200,
            'min_order_amount' => 800,
            'phones'           => ['+7 999 123-45-67'],
            'work_hours'       => ['start' => '10:00', 'end' => '23:00'],
            'address'          => 'г. Сосновый Бор, ул. Красных Фортов, 49',
        ];
    }
    $dbConnected = true;
} catch (Exception $e) {
    $availableSlots = [];
    $dbConnected    = false;
    $siteSettings   = [
        'site_name'        => "Sasha's Sushi",
        'delivery_cost'    => 200,
        'min_order_amount' => 800,
        'phones'           => ['+7 999 123-45-67'],
        'work_hours'       => ['start' => '10:00', 'end' => '23:00'],
        'address'          => 'г. Сосновый Бор, ул. Красных Фортов, 49',
    ];
}

function safe_output($value, $default = '') {
    return htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}
$logoUrl = $siteSettings['site_logo'] ?? null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Редактирование заказа' : 'Оформление заказа' ?> — <?= safe_output($siteSettings['site_name']) ?></title>

    <?php if ($logoUrl): ?>
        <link rel="icon" href="<?= safe_output($logoUrl) ?>">
    <?php else: ?>
        <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🍣</text></svg>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --red:        #E31E24;
            --red-dark:   #C41E24;
            --black:      #1A1A1A;
            --white:      #FFFFFF;
            --gray-light: #F5F5F5;
            --gray-mid:   #E0E0E0;
            --text:       #1A1A1A;
            --text-sub:   #666666;
            --green:      #10B981;
            --yellow:     #F59E0B;
            --catalog-w:  290px;
            --header-h:   74px;
        }
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--gray-light);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* TOAST */
        .checkout-toast {
            position: fixed; top: 90px; left: 50%;
            transform: translateX(-50%);
            z-index: 9999; width: 100%; max-width: 480px;
            padding: 0 16px; pointer-events: none; display: none;
        }
        .checkout-toast.show { display: block; }
        .checkout-toast-inner {
            pointer-events: all; background: var(--white);
            border-radius: 14px; box-shadow: 0 8px 32px rgba(0,0,0,.18);
            border-left: 5px solid var(--green);
            padding: 15px 18px; display: flex; align-items: center; gap: 13px;
            animation: toastSlide .4s cubic-bezier(.34,1.56,.64,1);
            position: relative; overflow: hidden;
        }
        .checkout-toast.error .checkout-toast-inner { border-left-color: var(--red); }
        @keyframes toastSlide {
            from { opacity: 0; transform: translateY(-18px) scale(.95); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .checkout-toast-icon {
            width: 38px; height: 38px; border-radius: 50%;
            background: #d1fae5;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; flex-shrink: 0;
        }
        .checkout-toast.error .checkout-toast-icon { background: #fee2e2; }
        .checkout-toast-body { flex: 1; }
        .checkout-toast-title { font-weight: 700; color: #065f46; font-size: .95rem; }
        .checkout-toast.error .checkout-toast-title { color: #991b1b; }
        .checkout-toast-sub   { color: #047857; font-size: .82rem; margin-top: 1px; }
        .checkout-toast.error .checkout-toast-sub { color: #b91c1c; }
        .checkout-toast-progress {
            position: absolute; bottom: 0; left: 0;
            height: 3px; background: var(--green);
            animation: toastBar 4s linear forwards;
        }
        .checkout-toast.error .checkout-toast-progress { background: var(--red); }
        @keyframes toastBar { from{width:100%} to{width:0} }

        /* HEADER */
        .header {
            background: var(--white);
            box-shadow: 0 2px 10px rgba(0,0,0,.08);
            position: sticky; top: 0; z-index: 900;
            border-bottom: 3px solid var(--red);
            height: var(--header-h);
        }
        .container { max-width: 1500px; margin: 0 auto; padding: 0 1.25rem; }
        .header-inner {
            display: flex; align-items: center;
            justify-content: space-between;
            height: var(--header-h); gap: 1.5rem;
        }
        .logo {
            display: flex; align-items: center; gap: .65rem;
            text-decoration: none; color: var(--black);
            font-weight: 800; font-size: 1.4rem; flex-shrink: 0;
        }
        .logo-img   { width: 46px; height: 46px; object-fit: contain; }
        .logo-emoji { font-size: 2.2rem; }
        .header-nav { display: flex; gap: 1.75rem; }
        .nav-link { color: var(--text); text-decoration: none; font-weight: 500; font-size: .95rem; transition: color .2s; }
        .nav-link:hover { color: var(--red); }
        .cart-btn {
            position: relative; background: var(--red); color: var(--white);
            border: none; padding: .65rem 1.25rem; border-radius: 50px;
            cursor: pointer; font-weight: 600; font-size: .9rem;
            transition: all .25s; display: flex; align-items: center; gap: .6rem;
        }
        .cart-btn:hover { background: var(--red-dark); transform: translateY(-1px); }
        .cart-badge {
            position: absolute; top: -7px; right: -7px;
            background: var(--black); color: var(--white);
            width: 22px; height: 22px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; font-weight: 700; border: 2px solid var(--white);
        }
        .mob-toggle {
            display: none; flex-direction: column; gap: 4px;
            background: none; border: none; cursor: pointer; padding: 4px;
        }
        .mob-toggle span { width: 26px; height: 2.5px; background: var(--black); border-radius: 2px; transition: all .25s; }

        /* PAGE LAYOUT */
        .page-layout {
            display: flex;
            align-items: flex-start;
            min-height: calc(100vh - var(--header-h));
        }
        .page-center {
            flex: 1; min-width: 0;
            padding: 1.5rem 1.25rem 3rem;
        }

        /* КАТАЛОГ СПРАВА */
        .catalog-sidebar {
            width: var(--catalog-w);
            min-width: var(--catalog-w);
            background: var(--white);
            border-left: 2px solid var(--gray-mid);
            position: sticky;
            top: var(--header-h);
            height: calc(100vh - var(--header-h));
            display: flex;
            flex-direction: column;
            overflow: hidden;
            flex-shrink: 0;
            order: 3;
        }
        body.mode-checkout .catalog-sidebar { display: none !important; }

        .cat-head {
            padding: .875rem 1rem;
            border-bottom: 2px solid var(--gray-light);
            flex-shrink: 0; background: var(--white);
        }
        .cat-title {
            font-size: .95rem; font-weight: 700; color: var(--black);
            display: flex; align-items: center; gap: .45rem; margin-bottom: .65rem;
        }
        .cat-title i { color: var(--red); font-size: 1rem; }
        .cat-search-wrap { position: relative; }
        .cat-search {
            width: 100%; padding: .55rem .75rem .55rem 2rem;
            border: 2px solid var(--gray-mid); border-radius: 8px;
            font-size: .82rem; font-family: inherit;
            background: var(--gray-light);
            transition: border-color .2s, background .2s; color: var(--text);
        }
        .cat-search::placeholder { color: #aaa; }
        .cat-search:focus {
            outline: none; border-color: var(--red);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(227,30,36,.08);
        }
        .cat-search-ico {
            position: absolute; left: .6rem; top: 50%;
            transform: translateY(-50%); color: #aaa; font-size: .8rem; pointer-events: none;
        }
        .cat-list { flex: 1; overflow-y: auto; padding: .25rem 0; }
        .cat-list::-webkit-scrollbar { width: 3px; }
        .cat-list::-webkit-scrollbar-thumb { background: var(--gray-mid); border-radius: 3px; }
        .cat-list::-webkit-scrollbar-thumb:hover { background: #c0c0c0; }
        .cat-item {
            display: flex; align-items: center; gap: .6rem;
            padding: .55rem .875rem; cursor: pointer;
            transition: background .12s; border-bottom: 1px solid #f0f0f0; position: relative;
        }
        .cat-item:hover { background: #fff5f5; }
        .cat-item:last-child { border-bottom: none; }
        .cat-item-img {
            width: 48px; height: 48px; border-radius: 8px;
            object-fit: cover; flex-shrink: 0; background: var(--gray-light);
        }
        .cat-item-body { flex: 1; min-width: 0; }
        .cat-item-name {
            font-size: .8rem; font-weight: 600; color: var(--black);
            line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .cat-item-meta { font-size: .7rem; color: var(--text-sub); margin-top: .1rem; }
        .cat-item-price { font-size: .8rem; font-weight: 700; color: var(--red); white-space: nowrap; margin-top: .15rem; }
        .cat-add-btn {
            width: 26px; height: 26px; border-radius: 50%;
            background: var(--red); color: var(--white);
            border: none; font-size: 1rem; line-height: 1;
            cursor: pointer; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transform: scale(.75);
            transition: opacity .18s, transform .18s, background .18s;
        }
        .cat-item:hover .cat-add-btn { opacity: 1; transform: scale(1); }
        .cat-add-btn:hover { background: var(--red-dark); }
        .cat-qty { display: none; align-items: center; gap: .25rem; flex-shrink: 0; }
        .cat-qty.on { display: flex; }
        .cat-qty-btn {
            width: 22px; height: 22px; border-radius: 50%;
            border: 2px solid var(--red); background: var(--white);
            color: var(--red); font-size: .85rem; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: background .15s, color .15s; flex-shrink: 0;
        }
        .cat-qty-btn:hover { background: var(--red); color: var(--white); }
        .cat-qty-num {
            font-size: .8rem; font-weight: 700;
            min-width: 16px; text-align: center; color: var(--black);
        }
        .cat-loader {
            padding: 1.5rem 1rem; text-align: center;
            color: var(--text-sub); font-size: .82rem;
        }
        .cat-loader i { display: block; font-size: 1.3rem; margin-bottom: .4rem; animation: spin 1s linear infinite; }
        .cat-empty { padding: 1.5rem 1rem; text-align: center; color: var(--text-sub); font-size: .82rem; }

        /* ШАПКА СТРАНИЦЫ */
        .page-head { text-align: center; margin-bottom: 1.75rem; }
        .page-head h1 { font-size: 1.9rem; font-weight: 800; color: var(--black); margin-bottom: .25rem; }
        .page-head p  { color: var(--text-sub); font-size: .95rem; }
        .back-link {
            display: inline-flex; align-items: center; gap: .45rem;
            color: var(--text-sub); text-decoration: none;
            font-weight: 600; font-size: .9rem;
            margin-bottom: 1.25rem; transition: all .2s;
        }
        .back-link:hover { color: var(--red); gap: .65rem; }
        .edit-banner {
            background: linear-gradient(135deg,#fef3c7,#fde68a);
            border: 2px solid #f59e0b; border-radius: 12px;
            padding: .875rem 1.25rem; margin-bottom: 1.5rem;
            display: flex; align-items: center; gap: .875rem;
        }
        .edit-banner > i { color: #d97706; font-size: 1.3rem; flex-shrink: 0; }
        .edit-banner-txt { flex: 1; }
        .edit-banner-txt strong { display: block; color: #92400e; font-size: 1rem; }
        .edit-banner-txt span   { color: #78350f; font-size: .85rem; }
        .edit-cancel {
            background: #92400e; color: #fff; border: none;
            padding: .45rem .9rem; border-radius: 8px; font-weight: 600; font-size: .85rem;
            cursor: pointer; white-space: nowrap; text-decoration: none;
            display: inline-flex; align-items: center; gap: .4rem; transition: background .2s;
        }
        .edit-cancel:hover { background: #78350f; }

        /* СЕТКА */
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 1.5rem;
        }

        /* ФОРМА */
        .checkout-form {
            background: var(--white); border-radius: 16px;
            padding: 1.75rem; box-shadow: 0 4px 20px rgba(0,0,0,.07);
        }
        .form-sec {
            margin-bottom: 1.75rem; padding-bottom: 1.75rem;
            border-bottom: 2px solid var(--gray-light);
        }
        .form-sec:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .form-sec-title {
            font-size: 1.1rem; font-weight: 700; margin-bottom: 1.1rem;
            display: flex; align-items: center; gap: .6rem; color: var(--black);
        }
        .form-sec-title i { color: var(--red); font-size: 1.2rem; }
        .form-group { margin-bottom: 1.1rem; }
        .form-label { display: block; font-weight: 600; margin-bottom: .35rem; color: var(--text); font-size: .88rem; }
        .form-label.req::after { content: ' *'; color: var(--red); }
        .f-input, .f-textarea {
            width: 100%; padding: .75rem .9rem;
            border: 2px solid var(--gray-mid); border-radius: 10px;
            font-size: .9rem; font-family: inherit;
            transition: border-color .2s, background .2s, box-shadow .2s;
            background: var(--gray-light); color: var(--text);
        }
        .f-input:focus, .f-textarea:focus {
            outline: none; border-color: var(--red);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(227,30,36,.09);
        }
        .f-textarea { min-height: 85px; resize: vertical; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        /* ── ПОИСК УЛИЦЫ ── */
        .street-wrap { position: relative; }
        .street-input {
            width: 100%;
            padding: .75rem 2.5rem .75rem 2.35rem;
            border: 2px solid var(--gray-mid); border-radius: 10px;
            font-size: .9rem; font-family: inherit;
            background: var(--gray-light); transition: all .2s;
            color: var(--text);
        }
        .street-input:focus {
            outline: none; border-color: var(--red);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(227,30,36,.09);
        }
        .street-ico {
            position: absolute; left: .8rem; top: 50%;
            transform: translateY(-50%);
            color: var(--text-sub); pointer-events: none;
            font-size: .85rem;
        }
        .street-arrow-btn {
            position: absolute; right: .5rem; top: 50%;
            transform: translateY(-50%);
            width: 28px; height: 28px;
            background: none; border: none; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: var(--text-sub); font-size: .75rem;
            border-radius: 6px;
            transition: color .2s, background .2s, transform .2s;
            z-index: 2;
        }
        .street-arrow-btn:hover { color: var(--red); background: rgba(227,30,36,.07); }
        .street-arrow-btn.open {
            color: var(--red);
            transform: translateY(-50%) rotate(180deg);
        }
        .street-drop {
            position: absolute; top: calc(100% + 4px); left: 0; right: 0;
            background: var(--white); border: 2px solid var(--gray-mid);
            border-radius: 10px;
            max-height: 300px; overflow-y: auto;
            display: none;
            z-index: 200; box-shadow: 0 8px 24px rgba(0,0,0,.13);
        }
        .street-drop.on { display: block; }
        .street-opt {
            padding: .7rem .9rem; cursor: pointer; transition: background .15s;
            display: flex; align-items: center; gap: .6rem;
            border-left: 3px solid transparent;
            border-bottom: 1px solid var(--gray-light);
            font-size: .875rem;
            user-select: none;
        }
        .street-opt:last-child { border-bottom: none; }
        .street-opt:hover,
        .street-opt:focus { background: var(--gray-light); border-left-color: var(--red); outline: none; }

        .zone-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .zone-dot.green  { background: var(--green); }
        .zone-dot.yellow { background: var(--yellow); }
        .zone-dot.red    { background: #EF4444; }
        .zone-box {
            margin-top: .65rem; padding: .75rem 1rem;
            border-radius: 10px; display: none;
            align-items: center; justify-content: space-between; border: 2px solid;
        }
        .zone-box.on { display: flex; }
        .zone-box.green  { background: #f0fdf4; border-color: var(--green); }
        .zone-box.yellow { background: #fef3c7; border-color: var(--yellow); }
        .zone-box.red    { background: #fee2e2; border-color: #EF4444; }
        .zone-box-left  { display: flex; align-items: center; gap: .6rem; }
        .zone-circle    { width: 16px; height: 16px; border-radius: 50%; }
        .zone-name      { font-weight: 700; font-size: .95rem; }
        .zone-box.green  .zone-name { color: #065f46; }
        .zone-box.yellow .zone-name { color: #92400e; }
        .zone-box.red    .zone-name { color: #991b1b; }
        .zone-cost { font-size: 1.1rem; font-weight: 800; }
        .zone-box.green  .zone-cost { color: #065f46; }
        .zone-box.yellow .zone-cost { color: #92400e; }
        .zone-box.red    .zone-cost { color: #991b1b; }

        .del-opts { display: grid; grid-template-columns: 1fr 1fr; gap: .875rem; }
        .del-opt {
            position: relative; padding: 1rem; border: 2px solid var(--gray-mid);
            border-radius: 12px; cursor: pointer; transition: all .2s; background: var(--gray-light);
        }
        .del-opt:hover { border-color: var(--red); transform: translateY(-1px); }
        .del-opt input[type="radio"] { position: absolute; opacity: 0; }
        .del-opt:has(input:checked) { border-color: var(--red); background: rgba(227,30,36,.04); }
        .del-opt:has(input:checked) .opt-label { color: var(--red); }
        .opt-label { display: flex; align-items: center; gap: .6rem; font-weight: 600; font-size: .9rem; }
        .opt-label i { font-size: 1.25rem; }

        .time-btn {
            width: 100%; padding: 1rem; border: 2px solid var(--gray-mid);
            border-radius: 12px; background: var(--gray-light);
            cursor: pointer; transition: all .2s;
            display: flex; align-items: center; justify-content: space-between;
            font-size: .9rem; font-weight: 600; color: var(--text-sub);
        }
        .time-btn:hover { border-color: var(--red); background: rgba(227,30,36,.04); }
        .time-btn.sel   { border-color: var(--red); background: rgba(227,30,36,.04); color: var(--red); }
        .time-sel-inner { display: flex; align-items: center; gap: .6rem; }
        .time-sel-inner i { color: var(--red); }

        .cutlery-row { display: flex; align-items: center; gap: .75rem; margin-top: .4rem; }
        .ctr-btn {
            width: 36px; height: 36px; border: 2px solid var(--gray-mid);
            border-radius: 8px; background: var(--gray-light);
            font-size: 1rem; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; justify-content: center; transition: all .2s;
        }
        .ctr-btn:hover { border-color: var(--red); background: rgba(227,30,36,.04); }
        .ctr-val { font-size: 1.3rem; font-weight: 700; min-width: 36px; text-align: center; }
        .ctr-max { color: var(--text-sub); font-size: .82rem; margin-left: .5rem; }

        .min-warn {
            background: #fef3c7; border: 2px solid #fbbf24;
            border-radius: 10px; padding: .75rem .9rem;
            margin-top: .75rem; display: none; font-size: .875rem;
        }
        .min-warn.on { display: block; }
        .min-warn strong { color: #92400e; }
        .pickup-box {
            background: #f0fdf4; border: 2px solid #86efac;
            border-radius: 10px; padding: .75rem .9rem; margin-top: .75rem;
        }
        .pickup-box p { color: #166534; font-weight: 600; display: flex; align-items: center; gap: .5rem; font-size: .875rem; }

        /* ИТОГОВАЯ ПАНЕЛЬ */
        .order-summary {
            background: var(--white); border-radius: 16px;
            padding: 1.5rem; box-shadow: 0 4px 20px rgba(0,0,0,.07);
            position: sticky; top: calc(var(--header-h) + 1rem);
            max-height: calc(100vh - var(--header-h) - 2rem);
            overflow-y: auto; display: flex; flex-direction: column;
        }
        .order-summary::-webkit-scrollbar { width: 3px; }
        .order-summary::-webkit-scrollbar-thumb { background: var(--gray-mid); border-radius: 3px; }
        .sum-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 1.1rem; color: var(--black); }

        .cart-items { margin-bottom: 1.1rem; }
        .cart-item {
            display: flex; align-items: center;
            gap: .65rem; padding: .65rem 0;
            border-bottom: 1px solid var(--gray-mid);
            animation: fadeUp .22s ease;
        }
        @keyframes fadeUp { from{opacity:0;transform:translateY(-3px)} to{opacity:1;transform:translateY(0)} }
        .cart-item:last-child { border-bottom: none; }
        .cart-item-img {
            width: 48px; height: 48px; border-radius: 8px;
            overflow: hidden; flex-shrink: 0; background: var(--gray-light);
        }
        .cart-item-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .cart-item-info { flex: 1; min-width: 0; }
        .cart-item-name { font-weight: 600; font-size: .82rem; color: var(--black); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: .15rem; }
        .cart-item-unit { font-size: .75rem; color: var(--text-sub); }

        .qty-ctrl { display: flex; align-items: center; gap: .3rem; flex-shrink: 0; }
        .qty-btn {
            width: 26px; height: 26px; border-radius: 7px;
            border: 2px solid var(--gray-mid); background: var(--gray-light);
            color: var(--text); font-size: .85rem; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: all .15s; flex-shrink: 0;
        }
        .qty-btn:hover          { border-color: var(--red); background: #fff0f0; color: var(--red); }
        .qty-btn.minus:hover    { border-color: #ef4444; background: #fee2e2; color: #ef4444; }
        .qty-num { font-size: .85rem; font-weight: 700; min-width: 20px; text-align: center; color: var(--black); }
        body.mode-checkout .qty-ctrl { display: none !important; }

        .cart-item-total { font-weight: 700; font-size: .82rem; color: var(--red); white-space: nowrap; min-width: 52px; text-align: right; flex-shrink: 0; }

        .promo-sec { margin-bottom: 1.1rem; }
        .promo-row { display: flex; gap: .45rem; }
        .promo-inp { flex: 1; padding: .65rem .75rem; border: 2px solid var(--gray-mid); border-radius: 8px; font-size: .875rem; }
        .promo-inp:focus { outline: none; border-color: var(--red); }
        .promo-apply {
            padding: .65rem 1.1rem; background: var(--gray-light);
            border: 2px solid var(--gray-mid); border-radius: 8px;
            font-weight: 600; font-size: .875rem; color: var(--text);
            cursor: pointer; transition: all .2s; white-space: nowrap;
        }
        .promo-apply:hover { background: var(--gray-mid); }

        .promo-block { margin-bottom: 1.1rem; }
        .promo-block-ttl { font-weight: 700; font-size: .875rem; margin-bottom: .55rem; display: flex; align-items: center; gap: .35rem; }
        .promo-card { border-radius: 10px; padding: 9px 11px; margin-bottom: 7px; border: 2px solid; animation: fadeUp .25s ease; }
        .promo-card.gift        { background: linear-gradient(135deg,#f0fdf4,#dcfce7); border-color: #86efac; }
        .promo-card.discount    { background: linear-gradient(135deg,#fefce8,#fef9c3); border-color: #fde047; }
        .promo-card.happy-hours { background: linear-gradient(135deg,#fff7ed,#ffedd5); border-color: #fdba74; }
        .promo-card.holiday     { background: linear-gradient(135deg,#fdf4ff,#f3e8ff); border-color: #d8b4fe; }
        .promo-card-head { display: flex; align-items: flex-start; gap: 5px; margin-bottom: 3px; }
        .promo-card-ico  { font-size: 1rem; }
        .promo-card-name { font-weight: 700; font-size: .78rem; flex: 1; color: var(--black); word-break: break-word; }
        .promo-card-badge { font-size: .66rem; font-weight: 800; padding: 1px 6px; border-radius: 20px; text-transform: uppercase; }
        .promo-card.gift        .promo-card-badge { background: #16a34a; color: #fff; }
        .promo-card.discount    .promo-card-badge { background: #ca8a04; color: #fff; }
        .promo-card.happy-hours .promo-card-badge { background: #ea580c; color: #fff; }
        .promo-card.holiday     .promo-card-badge { background: #9333ea; color: #fff; }
        .promo-card-desc { font-size: .75rem; color: #475569; margin-bottom: 4px; }
        .promo-card-cond { font-size: .72rem; color: #64748b; }
        .promo-ok { display: inline-flex; align-items: center; gap: 3px; font-size: .72rem; font-weight: 700; color: #16a34a; margin-top: 4px; }
        .gift-row { display: flex; align-items: center; gap: 7px; margin-top: 5px; background: rgba(255,255,255,.7); border-radius: 6px; padding: 5px 7px; }
        .gift-img { width: 36px; height: 36px; border-radius: 5px; object-fit: cover; flex-shrink: 0; }
        .gift-name { font-size: .77rem; font-weight: 600; }
        .gift-tag  { font-size: .68rem; color: #16a34a; font-weight: 700; }

        .sum-rows { border-top: 2px solid var(--gray-mid); padding-top: .75rem; margin-bottom: 1.1rem; }
        .sum-row  { display: flex; justify-content: space-between; padding: .45rem 0; color: var(--text-sub); font-size: .875rem; }
        .sum-row.total { border-top: 2px solid var(--gray-mid); margin-top: .35rem; padding-top: .75rem; font-size: 1.1rem; font-weight: 700; color: var(--black); }
        .sum-row.total .amt { color: var(--red); }

        .submit-btn {
            width: 100%; padding: 1rem;
            background: var(--red); color: var(--white);
            border: none; border-radius: 12px;
            font-size: .95rem; font-weight: 700; cursor: pointer;
            transition: all .25s;
            display: flex; align-items: center; justify-content: center; gap: .6rem;
            margin-bottom: .75rem;
        }
        .submit-btn:hover:not(:disabled) { background: var(--red-dark); transform: translateY(-2px); box-shadow: 0 8px 22px rgba(227,30,36,.28); }
        .submit-btn:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; }
        .privacy-note { text-align: center; color: var(--text-sub); font-size: .75rem; }
        .privacy-note a { color: var(--red); }

        .empty-cart { text-align: center; padding: 2.5rem 1rem; }
        .empty-cart-ico { font-size: 3.5rem; margin-bottom: .875rem; }
        .empty-cart h2 { font-size: 1.4rem; margin-bottom: .875rem; }
        .empty-cart p  { color: var(--text-sub); margin-bottom: 1.75rem; }
        .btn-red {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .875rem 1.75rem; background: var(--red); color: var(--white);
            text-decoration: none; border-radius: 50px; font-weight: 600; transition: all .25s;
        }
        .btn-red:hover { background: var(--red-dark); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(227,30,36,.28); }
        .cert-badge { background: linear-gradient(135deg,#667eea,#764ba2); color: var(--white); padding: .4rem .875rem; border-radius: 8px; font-size: .8rem; font-weight: 600; display: inline-flex; align-items: center; gap: .4rem; margin-top: .4rem; }

        /* МОДАЛКА ВРЕМЕНИ */
        .t-modal { position: fixed; inset: 0; background: rgba(0,0,0,.72); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 1rem; }
        .t-modal.on { display: flex; }
        .t-modal-box { background: var(--white); border-radius: 18px; max-width: 560px; width: 100%; max-height: 78vh; overflow: hidden; display: flex; flex-direction: column; animation: slideUp .28s ease; }
        @keyframes slideUp { from{transform:translateY(40px);opacity:0} to{transform:translateY(0);opacity:1} }
        .t-modal-head { padding: 1.4rem 1.75rem; border-bottom: 2px solid var(--gray-light); display: flex; justify-content: space-between; align-items: center; }
        .t-modal-head h3 { font-size: 1.25rem; font-weight: 700; display: flex; align-items: center; gap: .6rem; }
        .t-modal-head h3 i { color: var(--red); }
        .t-modal-close { width: 36px; height: 36px; border: none; background: var(--gray-light); border-radius: 50%; font-size: 1.2rem; color: var(--text-sub); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all .2s; }
        .t-modal-close:hover { background: var(--gray-mid); color: var(--black); }
        .t-modal-body { padding: 1.4rem 1.75rem; overflow-y: auto; flex: 1; }
        .date-tabs { display: flex; gap: .45rem; margin-bottom: 1.35rem; overflow-x: auto; padding-bottom: .4rem; }
        .date-tab { flex-shrink: 0; padding: .65rem 1rem; border: 2px solid var(--gray-mid); border-radius: 10px; background: var(--gray-light); cursor: pointer; transition: all .2s; text-align: center; min-width: 82px; }
        .date-tab:hover { border-color: var(--red); }
        .date-tab.on { border-color: var(--red); background: rgba(227,30,36,.05); color: var(--red); }
        .date-tab-d { font-size: .75rem; color: var(--text-sub); margin-bottom: .15rem; }
        .date-tab.on .date-tab-d { color: var(--red); }
        .date-tab-n { font-weight: 700; font-size: .95rem; }
        .slots-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(95px,1fr)); gap: .6rem; }
        .slot-item { padding: .8rem; border: 2px solid var(--gray-mid); border-radius: 10px; text-align: center; cursor: pointer; transition: all .25s; background: var(--gray-light); font-weight: 600; font-size: .95rem; position: relative; }
        .slot-item:hover:not(.full) { border-color: var(--red); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(227,30,36,.18); }
        .slot-item.sel  { border-color: var(--red); background: var(--red); color: var(--white); transform: scale(1.04); }
        .slot-item.full { background: #fee2e2; border-color: #fca5a5; color: #991b1b; cursor: not-allowed; opacity: .55; }
        .slot-item.hot  { border-color: #fbbf24; background: #fef3c7; }
        .slot-item.hot::after { content:'🔥'; position:absolute; top:.15rem; right:.2rem; font-size:.7rem; }
        .slot-dot { position: absolute; top: .35rem; right: .35rem; width: 6px; height: 6px; border-radius: 50%; background: #10b981; }
        .slot-dot.warn { background: #f59e0b; }
        .slot-dot.full { background: #ef4444; }
        .slots-empty { text-align: center; padding: 2rem 1rem; color: var(--text-sub); grid-column: 1/-1; }
        .slots-empty i { font-size: 2.2rem; margin-bottom: .75rem; opacity: .45; display: block; }

        /* ЛОАДЕР */
        .b-loader { position: fixed; inset: 0; background: rgba(0,0,0,.8); display: none; align-items: center; justify-content: center; z-index: 2000; }
        .b-loader.on { display: flex; }
        .b-loader-box { background: var(--white); padding: 2.25rem; border-radius: 18px; text-align: center; max-width: 360px; }
        .b-spinner { width: 52px; height: 52px; border: 4px solid var(--gray-light); border-top-color: var(--red); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 1.1rem; }
        @keyframes spin { to{transform:rotate(360deg)} }
        .b-loader-txt { font-size: 1.05rem; font-weight: 600; margin-bottom: .35rem; }
        .b-loader-sub { color: var(--text-sub); font-size: .875rem; }

        /* FOOTER */
        .footer { background: var(--black); color: var(--white); padding: 1.75rem 0; }
        .footer p { color: rgba(255,255,255,.55); text-align: center; font-size: .875rem; }

        /* RESPONSIVE */
        @media (max-width: 1280px) { :root { --catalog-w: 260px; } }
        @media (max-width: 1024px) { .catalog-sidebar { display: none !important; } }
        @media (max-width: 820px)  {
            .checkout-grid { grid-template-columns: 1fr; }
            .header-nav    { display: none; }
            .mob-toggle    { display: flex; }
            .order-summary { position: static; max-height: none; }
        }
        @media (max-width: 600px) {
            .form-row, .del-opts { grid-template-columns: 1fr; }
            .page-head h1 { font-size: 1.55rem; }
            .slots-grid   { grid-template-columns: repeat(auto-fill,minmax(80px,1fr)); }
        }
    </style>
</head>
<body class="<?= $isEdit ? 'mode-edit' : 'mode-checkout' ?>">

<!-- TOAST -->
<div class="checkout-toast" id="checkoutToast">
    <div class="checkout-toast-inner">
        <div class="checkout-toast-icon" id="checkoutToastIcon">✅</div>
        <div class="checkout-toast-body">
            <div class="checkout-toast-title" id="checkoutToastTitle">Готово!</div>
            <div class="checkout-toast-sub"   id="checkoutToastSub"></div>
        </div>
        <div class="checkout-toast-progress"></div>
    </div>
</div>

<!-- Лоадер -->
<div class="b-loader" id="bLoader">
    <div class="b-loader-box">
        <div class="b-spinner"></div>
        <div class="b-loader-txt" id="bLoaderTxt">Бронируем слот...</div>
        <div class="b-loader-sub">Пожалуйста, подождите</div>
    </div>
</div>

<!-- HEADER -->
<header class="header">
    <div class="container">
        <div class="header-inner">
            <a href="/" class="logo">
                <?php if ($logoUrl): ?>
                    <img src="<?= safe_output($logoUrl) ?>" alt="<?= safe_output($siteSettings['site_name']) ?>" class="logo-img">
                <?php else: ?>
                    <span class="logo-emoji">🍣</span>
                <?php endif; ?>
                <span><?= safe_output($siteSettings['site_name']) ?></span>
            </a>
            <nav class="header-nav">
                <a href="/" class="nav-link">Меню</a>
                <a href="/pages/about.php" class="nav-link">О нас</a>
                <a href="/pages/delivery.php" class="nav-link">Доставка</a>
                <a href="/pages/contacts.php" class="nav-link">Контакты</a>
            </nav>
            <div style="display:flex;align-items:center;gap:1rem;">
                <button class="cart-btn" onclick="window.location.href='/'">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-badge" id="cartCount">0</span>
                </button>
            </div>
            <button class="mob-toggle" id="mobToggle">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<!-- PAGE LAYOUT -->
<div class="page-layout">

    <div class="page-center">

        <a href="<?= $isEdit ? '/pages/account.php?tab=orders' : '/' ?>" class="back-link">
            <i class="fas fa-arrow-left"></i>
            <?= $isEdit ? 'Вернуться к заказам' : 'Вернуться в меню' ?>
        </a>

        <?php if ($isEdit && $editOrderData): ?>
        <div class="edit-banner">
            <i class="fas fa-pen-to-square"></i>
            <div class="edit-banner-txt">
                <strong>✏️ Режим редактирования заказа #<?= safe_output($editOrderId) ?></strong>
                <span>Измените нужные данные и нажмите «Сохранить изменения»</span>
            </div>
            <a href="/pages/account.php?tab=orders" class="edit-cancel">
                <i class="fas fa-times"></i> Отмена
            </a>
        </div>
        <?php endif; ?>

        <div class="page-head">
            <h1><?= $isEdit ? '✏️ Редактирование заказа' : 'Оформление заказа' ?></h1>
            <p><?= $isEdit ? 'Измените данные заказа и сохраните изменения' : 'Заполните форму, и мы доставим ваш заказ точно в срок' ?></p>
        </div>

        <?php if (!$isEdit): ?>
        <div class="empty-cart" id="emptyCart" style="display:none;">
            <div class="empty-cart-ico">🛒</div>
            <h2>Ваша корзина пуста</h2>
            <p>Добавьте товары из каталога или перейдите в меню</p>
            <a href="/" class="btn-red"><i class="fas fa-utensils"></i> Перейти в меню</a>
        </div>
        <?php endif; ?>

        <div class="checkout-grid" id="checkoutGrid" <?= !$isEdit ? 'style="display:none;"' : '' ?>>

            <!-- ФОРМА -->
            <div class="checkout-form">
                <form id="checkoutForm">

                    <div class="form-sec">
                        <h3 class="form-sec-title"><i class="fas fa-user"></i> Контактная информация</h3>
                        <div class="form-group">
                            <label class="form-label req">Телефон</label>
                            <input type="tel" class="f-input" name="phone" id="phoneInput" placeholder="+7 (999) 123-45-67" required>
                            <div id="custInfo" style="display:none;margin-top:.4rem;">
                                <div id="certInfo"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label req">Ваше имя</label>
                            <input type="text" class="f-input" name="name" id="nameInput" placeholder="Иван Петров" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="f-input" name="email" id="emailInput" placeholder="example@mail.ru">
                        </div>
                    </div>

                    <div class="form-sec">
                        <h3 class="form-sec-title"><i class="fas fa-shipping-fast"></i> Способ получения</h3>
                        <div class="del-opts">
                            <label class="del-opt">
                                <input type="radio" name="delivery_type" value="delivery" checked>
                                <div class="opt-label"><i class="fas fa-motorcycle"></i><span>Доставка</span></div>
                            </label>
                            <label class="del-opt">
                                <input type="radio" name="delivery_type" value="pickup">
                                <div class="opt-label"><i class="fas fa-shopping-bag"></i><span>Самовывоз</span></div>
                            </label>
                        </div>

                        <div id="deliverySection" style="margin-top:1.1rem;">
                            <div class="form-group">
                                <label class="form-label req">Улица</label>
                                <div class="street-wrap">
                                    <i class="fas fa-map-marker-alt street-ico"></i>
                                    <input type="text"
                                           class="street-input"
                                           id="streetSearchInput"
                                           placeholder="Начните вводить или выберите из списка..."
                                           autocomplete="new-password"
                                           spellcheck="false">
                                    <button type="button" class="street-arrow-btn" id="streetArrowBtn" tabindex="-1">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <input type="hidden" name="street" id="streetHidden" required>
                                    <div class="street-drop" id="streetDrop">
                                        <?php foreach ($streetsData as $s): ?>
                                        <div class="street-opt"
                                             data-zone='<?= htmlspecialchars(json_encode($s, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
                                             data-name="<?= safe_output($s['name']) ?>">
                                            <span class="zone-dot <?= safe_output($s['zone_slug']) ?>"></span>
                                            <span><?= safe_output($s['name']) ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="zone-box" id="zoneBox">
                                    <div class="zone-box-left">
                                        <div class="zone-circle" id="zoneCircle"></div>
                                        <div class="zone-name"   id="zoneName"></div>
                                    </div>
                                    <div class="zone-cost" id="zoneCost"></div>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label req">Дом</label>
                                    <input type="text" class="f-input" name="house" id="houseInput" placeholder="49" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Подъезд</label>
                                    <input type="text" class="f-input" name="entrance" id="entranceInput" placeholder="1">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Этаж</label>
                                    <input type="text" class="f-input" name="floor" id="floorInput" placeholder="5">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Квартира</label>
                                    <input type="text" class="f-input" name="apartment" id="apartmentInput" placeholder="42">
                                </div>
                            </div>
                            <div class="min-warn" id="minWarn">
                                <strong>⚠️ Внимание!</strong><br>
                                Минимальная сумма заказа: <span id="minWarnVal">0</span> ₽
                            </div>
                        </div>

                        <div id="pickupSection" style="display:none;margin-top:1.1rem;">
                            <div class="pickup-box">
                                <p><i class="fas fa-map-marker-alt"></i> <?= safe_output($siteSettings['address']) ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="form-sec">
                        <h3 class="form-sec-title"><i class="fas fa-clock"></i> Время доставки</h3>
                        <div class="form-group">
                            <button type="button" class="time-btn" id="timeSelectorBtn" onclick="openTimeModal()">
                                <span id="selectedTimeText"><i class="fas fa-clock"></i> Выберите дату и время</span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <input type="hidden" name="delivery_slot" id="deliverySlot" required>
                            <input type="hidden" name="delivery_date" id="deliveryDate" required>
                            <input type="hidden" name="zone_id"       id="zoneIdHidden">
                        </div>
                    </div>

                    <div class="form-sec">
                        <h3 class="form-sec-title"><i class="fas fa-utensils"></i> Количество приборов</h3>
                        <div class="cutlery-row">
                            <button type="button" class="ctr-btn" id="cutleryMinus">−</button>
                            <span class="ctr-val" id="cutleryValue">0</span>
                            <button type="button" class="ctr-btn" id="cutleryPlus">+</button>
                            <span class="ctr-max" id="cutleryMax">Максимум: 0</span>
                        </div>
                        <input type="hidden" name="cutlery_count" id="cutleryCount" value="0">
                    </div>

                    <div class="form-sec">
                        <h3 class="form-sec-title"><i class="fas fa-comment"></i> Комментарий к заказу</h3>
                        <div class="form-group">
                            <textarea class="f-textarea" name="comment" id="commentInput"
                                      placeholder="Например: без острого, позвонить за 10 минут..."></textarea>
                        </div>
                    </div>

                </form>
            </div>

            <!-- ИТОГО -->
            <div class="order-summary">
                <h3 class="sum-title">Ваш заказ</h3>
                <div class="cart-items" id="cartItemsList"></div>

                <div class="promo-sec">
                    <div class="promo-row">
                        <input type="text" class="promo-inp" id="promoInput" placeholder="Промокод">
                        <button class="promo-apply" id="applyPromoBtn">Применить</button>
                    </div>
                </div>

                <div id="promoBlockWrap" style="display:none;" class="promo-block">
                    <div class="promo-block-ttl">🎁 Доступные акции</div>
                    <div id="promoBlockList"></div>
                </div>

                <div class="sum-rows">
                    <div class="sum-row">
                        <span>Товары (<span id="itemsCount">0</span> шт)</span>
                        <span id="subtotalAmt">0 ₽</span>
                    </div>
                    <div class="sum-row" id="deliveryRow" style="display:none;">
    <span>Доставка</span>
    <span id="deliveryAmt">—</span>
</div>
                    <div class="sum-row" id="discountRow" style="display:none;">
                        <span>Скидка (<span id="discountPct">0</span>%)</span>
                        <span id="discountAmt" style="color:#10b981;">−0 ₽</span>
                    </div>
                    <div class="sum-row total">
                        <span>Итого</span>
                        <span class="amt" id="totalAmt">0 ₽</span>
                    </div>
                </div>

                <button class="submit-btn" id="submitBtn">
                    <i class="fas fa-arrow-right"></i>
                    <span><?= $isEdit ? 'СОХРАНИТЬ ИЗМЕНЕНИЯ' : 'ОФОРМИТЬ ЗАКАЗ' ?></span>
                </button>
                <p class="privacy-note">
                    Нажимая кнопку, вы соглашаетесь с
                    <a href="/pages/privacy.php">политикой конфиденциальности</a>
                </p>
            </div>

        </div><!-- /.checkout-grid -->
    </div><!-- /.page-center -->

    <!-- КАТАЛОГ СПРАВА -->
    <aside class="catalog-sidebar" id="catalogSidebar">
        <div class="cat-head">
            <div class="cat-title">
                <i class="fas fa-book-open"></i>
                Добавить товары
            </div>
            <div class="cat-search-wrap">
                <i class="fas fa-search cat-search-ico"></i>
                <input type="text" class="cat-search" id="catSearch" placeholder="Поиск..." autocomplete="off">
            </div>
        </div>
        <div class="cat-list" id="catList">
            <div class="cat-loader">
                <i class="fas fa-circle-notch"></i>
                Загрузка товаров...
            </div>
        </div>
    </aside>

</div><!-- /.page-layout -->

<!-- Модалка времени -->
<div class="t-modal" id="tModal">
    <div class="t-modal-box">
        <div class="t-modal-head">
            <h3><i class="fas fa-calendar-alt"></i> Выберите дату и время</h3>
            <button class="t-modal-close" onclick="closeTimeModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="t-modal-body">
            <div class="date-tabs"  id="dateTabs"></div>
            <div class="slots-grid" id="slotsGrid">
                <div class="slots-empty"><i class="fas fa-clock"></i><p>Выберите дату</p></div>
            </div>
        </div>
    </div>
</div>

<footer class="footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> <?= safe_output($siteSettings['site_name']) ?>. Все права защищены.</p>
    </div>
</footer>

<script>
const IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;

const CFG = {
    deliveryCost:    <?= intval($siteSettings['delivery_cost']    ?? 200) ?>,
    minOrderAmount:  <?= intval($siteSettings['min_order_amount'] ?? 800) ?>,
    zones:           <?= json_encode($zones,           JSON_UNESCAPED_UNICODE) ?>,
    streets:         <?= json_encode($streetsData,     JSON_UNESCAPED_UNICODE) ?>,
    availableSlots:  <?= json_encode($availableSlots,  JSON_UNESCAPED_UNICODE) ?>,
    pickupAddress:   <?= json_encode($siteSettings['address'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    sessionId:       '<?= $sessionId ?>',
    serverCart:      <?= json_encode($currentCart,     JSON_UNESCAPED_UNICODE) ?>,
    editOrderId:     <?= json_encode($editOrderId,     JSON_UNESCAPED_UNICODE) ?>,
    editOrderData:   <?= json_encode($editOrderData,   JSON_UNESCAPED_UNICODE) ?>,
    catalogProducts: <?= json_encode($catalogProducts, JSON_UNESCAPED_UNICODE) ?>
};

const PROMO_CODES = {
    'SUSHI20':   { discount: 20, description: 'Скидка 20%' },
    'FIRST10':   { discount: 10, description: 'Скидка 10%' },
    'WELCOME15': { discount: 15, description: 'Скидка 15%' }
};

const DOW = ['Вс','Пн','Вт','Ср','Чт','Пт','Сб'];
const MON = ['янв','фев','мар','апр','мая','июн','июл','авг','сен','окт','ноя','дек'];
const PH  = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='60' height='60'%3E%3Crect width='60' height='60' fill='%23f5f5f5' rx='8'/%3E%3Ctext x='30' y='35' font-size='22' text-anchor='middle'%3E%F0%9F%8D%A3%3C/text%3E%3C/svg%3E";

function imgUrl(raw) {
    if (!raw || typeof raw !== 'string' || !raw.trim()) return null;
    const s = raw.trim();
    if (s.startsWith('data:')) return null;
    if (s.startsWith('http://') || s.startsWith('https://')) {
        try { return new URL(s).pathname; } catch { return null; }
    }
    let p = s.replace(/^public_html\//, '').replace(/\/\//g, '/');
    return p.startsWith('/') ? p : '/' + p;
}
function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmt(n) { return new Intl.NumberFormat('ru-RU').format(Math.round(n)) + ' ₽'; }

let currentPromo    = null;
let currentCustomer = null;
let currentZone     = null;
let selectedSlot    = null;
let selectedDate    = null;

window._pendingOrderId = 'order_' + Date.now() + '_' + Math.floor(Math.random() * 9000 + 1000);

/* ── TOAST ── */
let _toastTimer = null;
function showToast(title, sub, isError) {
    const wrap = document.getElementById('checkoutToast');
    const icon = document.getElementById('checkoutToastIcon');
    const ttl  = document.getElementById('checkoutToastTitle');
    const sb   = document.getElementById('checkoutToastSub');
    const prog = wrap.querySelector('.checkout-toast-progress');
    ttl.textContent  = title;
    sb.textContent   = sub || '';
    icon.textContent = isError ? '❌' : '✅';
    wrap.classList.toggle('error', !!isError);
    wrap.classList.add('show');
    prog.style.animation = 'none';
    prog.offsetHeight;
    prog.style.animation = '';
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => wrap.classList.remove('show'), 4200);
}

/* ── CATALOG ── */
const Catalog = {
    PAGE: 20, all: [], done: 0, obs: null, sent: null,

    init() {
        if (!IS_EDIT) {
            const list = document.getElementById('catList');
            if (list) list.innerHTML = '';
            return;
        }
        this.all = [...(CFG.catalogProducts || [])].sort((a, b) =>
            a.name.localeCompare(b.name, 'ru', { sensitivity: 'base' })
        );
        this.obs = new IntersectionObserver(e => {
            if (e[0].isIntersecting) this.renderBatch();
        }, { threshold: 0.1 });
        this.renderBatch();
        this._bindSearch();
    },

    _bindSearch() {
        const inp = document.getElementById('catSearch');
        if (!inp) return;
        let t;
        inp.addEventListener('input', () => {
            clearTimeout(t);
            t = setTimeout(() => {
                const q = inp.value.trim().toLowerCase();
                this.all = (CFG.catalogProducts || [])
                    .filter(p => !q ||
                        p.name.toLowerCase().includes(q) ||
                        (p.category || '').toLowerCase().includes(q)
                    )
                    .sort((a, b) => a.name.localeCompare(b.name, 'ru', { sensitivity: 'base' }));
                this.done = 0;
                const list = document.getElementById('catList');
                if (list) list.innerHTML = '';
                this._dropSentinel();
                this.renderBatch();
            }, 150);
        });
    },

    renderBatch() {
        const list = document.getElementById('catList');
        if (!list) return;
        list.querySelector('.cat-loader')?.remove();
        const chunk = this.all.slice(this.done, this.done + this.PAGE);
        if (!chunk.length) {
            if (!this.done) list.innerHTML = '<div class="cat-empty">Ничего не найдено</div>';
            this._dropSentinel();
            return;
        }
        const frag = document.createDocumentFragment();
        chunk.forEach(p => frag.appendChild(this._buildItem(p)));
        this.done += chunk.length;
        this._dropSentinel();
        if (this.done < this.all.length) {
            const s = document.createElement('div');
            s.className = 'cat-loader'; s.id = 'catSentinel';
            s.innerHTML = '<i class="fas fa-circle-notch"></i> Загрузка...';
            frag.appendChild(s);
            list.appendChild(frag);
            this.sent = list.querySelector('#catSentinel');
            this.obs.observe(this.sent);
        } else {
            list.appendChild(frag);
        }
    },

    _dropSentinel() {
        if (this.sent) { this.obs.unobserve(this.sent); this.sent.remove(); this.sent = null; }
    },

    _buildItem(p) {
        const src   = imgUrl(p.image) || PH;
        const price = p.price > 0 ? fmt(p.price) : '—';
        const meta  = [p.weight ? p.weight + ' г' : '', p.pieces ? p.pieces + ' шт' : ''].filter(Boolean).join(' · ');
        const id    = p.id;
        const el    = document.createElement('div');
        el.className  = 'cat-item';
        el.dataset.id = id;
        el.innerHTML  = `
            <img class="cat-item-img" src="${esc(src)}" alt="${esc(p.name)}" loading="lazy"
                 onerror="this.onerror=null;this.src='${PH}'">
            <div class="cat-item-body">
                <div class="cat-item-name">${esc(p.name)}</div>
                ${meta ? `<div class="cat-item-meta">${meta}</div>` : ''}
                <div class="cat-item-price">${price}</div>
            </div>
            <div class="cat-qty" id="cq_${id}">
                <button class="cat-qty-btn" onclick="Catalog.dec('${id}')">−</button>
                <span class="cat-qty-num" id="cn_${id}">0</span>
                <button class="cat-qty-btn" onclick="Catalog.inc('${id}')">+</button>
            </div>
            <button class="cat-add-btn" id="ca_${id}" onclick="Catalog.add('${id}')">+</button>
        `;
        return el;
    },

    sync(id, qty) {
        const qw = document.getElementById(`cq_${id}`);
        const qn = document.getElementById(`cn_${id}`);
        const ab = document.getElementById(`ca_${id}`);
        if (qn) qn.textContent = qty;
        if (qw) qw.classList.toggle('on', qty > 0);
        if (ab) ab.style.opacity = qty > 0 ? '0' : '';
    },

    syncAll(items) { if (IS_EDIT) items.forEach(i => this.sync(i.id, i.quantity)); },
    add(id)  { if (IS_EDIT) cart?.addById(id); },
    inc(id)  { if (IS_EDIT) cart?.changeQty(id, +1); },
    dec(id)  { if (IS_EDIT) cart?.changeQty(id, -1); }
};

/* ── PROMO ENGINE ── */
const PromoEngine = {
    loaded: false, timer: null, last: null,

    async load() {
        if (this.loaded) return;
        try {
            await fetch('/api/check_promotion.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ subtotal: 0, _load_all: true })
            });
            this.loaded = true;
        } catch {}
    },

    check() { clearTimeout(this.timer); this.timer = setTimeout(() => this._run(), 300); },

    async _run() {
        const wrap = document.getElementById('promoBlockWrap');
        const list = document.getElementById('promoBlockList');
        if (!wrap || !list) return;
        const sub = cart ? cart.getSubtotal() : 0;
        try {
            const r = await fetch('/api/check_promotion.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ subtotal: sub, delivery_date: selectedDate || null, delivery_time: selectedSlot?.time || null })
            });
            const d = await r.json();
            if (!d.success) { wrap.style.display = 'none'; return; }
            this.last = d;
            this._render(d);
            cart?._refreshDelivery();
        } catch { wrap.style.display = 'none'; }
    },

    freeDelivery() { return this.last?.free_delivery === true; },

    _render(d) {
        const wrap      = document.getElementById('promoBlockWrap');
        const list      = document.getElementById('promoBlockList');
        const applied   = d.applied_promotions || [];
        const gifts     = d.gifts              || [];
        const discounts = d.discounts          || [];
        const zc        = currentZone ? currentZone.delivery_cost : CFG.deliveryCost;
        const filtered  = applied.filter(p => p.type !== 'free_delivery' || (d.free_delivery && zc > 0));
        if (!filtered.length) { list.innerHTML = ''; wrap.style.display = 'none'; return; }
        wrap.style.display = 'block';
        list.innerHTML = filtered.map(p => {
            const g  = gifts.find(x => x.promotion_id == p.id);
            const dc = discounts.find(x => x.promotion_id == p.id);
            return this._card(p, g, dc);
        }).join('');
    },

    _card(p, g, dc) {
        const M = {
            gift:          { css:'gift',        ico:'🎁', badge:'Подарок' },
            discount:      { css:'discount',    ico:'💰', badge:'Скидка' },
            happy_hours:   { css:'happy-hours', ico:'⏰', badge:'Счастливые часы' },
            free_delivery: { css:'gift',        ico:'🚚', badge:'Бесплатно' },
            holiday:       { css:'holiday',     ico:'🎉', badge:'Праздник' },
        };
        const t = M[p.type] || M.gift;
        let rw = '';
        if (p.type === 'free_delivery') {
            rw = `<div class="promo-card-desc">🚚 <strong>Бесплатная доставка</strong></div>`;
        } else if (g) {
            const src = imgUrl(g.image) || PH;
            const qty = g.quantity > 1 ? ` × ${g.quantity}` : '';
            rw = `<div class="gift-row"><img class="gift-img" src="${src}" onerror="this.src='${PH}'"><div><div class="gift-name">🎁 ${esc(g.name)}${qty}</div><div class="gift-tag">Бесплатно</div></div></div>`;
        } else if (dc) {
            const v = dc.type === 'percent' ? dc.value+'%' : dc.value+' ₽';
            const sv = dc.amount > 0 ? ` (−${dc.amount} ₽)` : '';
            rw = `<div class="promo-card-desc">💰 Скидка <strong>${v}</strong>${sv}</div>`;
        } else if (p.description) {
            rw = `<div class="promo-card-desc">${p.description}</div>`;
        }
        const cond = p.min_sum > 0 ? `<div class="promo-card-cond">🛒 Мин. сумма: ${p.min_sum} ₽</div>` : '';
        return `<div class="promo-card ${t.css}">
            <div class="promo-card-head">
                <span class="promo-card-ico">${t.ico}</span>
                <span class="promo-card-name">${esc(p.name)}</span>
                <span class="promo-card-badge">${t.badge}</span>
            </div>${rw}${cond}
            <div class="promo-ok">✅ Акция применена</div>
        </div>`;
    }
};

/* ════════════════════════════════════════════════════════════════
   УЛИЦЫ — вся логика в одном месте, без onclick в HTML
════════════════════════════════════════════════════════════════ */
function selectStreet(optEl) {
    try {
        const zoneData = JSON.parse(optEl.dataset.zone);
        const name     = optEl.dataset.name || optEl.querySelector('span:last-child').textContent;
        currentZone    = zoneData;
        document.getElementById('streetSearchInput').value = name;
        document.getElementById('streetHidden').value      = name;
        document.getElementById('zoneIdHidden').value      = zoneData.zone_id;
        _streetCloseDrop();
        _showZone(zoneData);
        _resetSlot();
        cart?.updateSummary();
    } catch(e) { console.error('selectStreet error', e); }
}

function selectStreetByName(name) {
    const z = CFG.streets.find(s => s.name.toLowerCase() === name.toLowerCase());
    document.getElementById('streetSearchInput').value = name;
    document.getElementById('streetHidden').value      = name;
    if (z) {
        currentZone = z;
        document.getElementById('zoneIdHidden').value = z.zone_id;
        _showZone(z);
    }
}

function _showZone(z) {
    const box  = document.getElementById('zoneBox');
    const circ = document.getElementById('zoneCircle');
    const nm   = document.getElementById('zoneName');
    const cst  = document.getElementById('zoneCost');
    const warn = document.getElementById('minWarn');
    const wv   = document.getElementById('minWarnVal');
    box.className = `zone-box ${z.zone_slug} on`;
    circ.style.backgroundColor = z.zone_color;
    nm.textContent  = z.zone_name;
    cst.textContent = z.delivery_cost + ' ₽';
    wv.textContent  = z.min_order;
    const sub = cart ? cart.getSubtotal() : 0;
    warn.classList.toggle('on', sub < z.min_order);
}

function hideZone() {
    document.getElementById('zoneBox').className = 'zone-box';
    document.getElementById('minWarn').classList.remove('on');
    currentZone = null;
    document.getElementById('zoneIdHidden').value = '';
}

function _resetSlot() {
    selectedSlot = null; selectedDate = null;
    document.getElementById('selectedTimeText').innerHTML = '<i class="fas fa-clock"></i> Выберите дату и время';
    document.getElementById('timeSelectorBtn').classList.remove('sel');
    document.getElementById('deliverySlot').value = '';
    document.getElementById('deliveryDate').value = '';
}

/* ── Внутренние функции дропдауна улиц ── */
function _streetOpenDrop() {
    document.getElementById('streetDrop').classList.add('on');
    document.getElementById('streetArrowBtn').classList.add('open');
}
function _streetCloseDrop() {
    document.getElementById('streetDrop').classList.remove('on');
    document.getElementById('streetArrowBtn').classList.remove('open');
}
function _streetShowAll() {
    document.getElementById('streetDrop')
        .querySelectorAll('.street-opt')
        .forEach(o => o.style.display = 'flex');
}

document.addEventListener('DOMContentLoaded', () => {
    const inp   = document.getElementById('streetSearchInput');
    const drop  = document.getElementById('streetDrop');
    const arrow = document.getElementById('streetArrowBtn');

    /* Все опции — tabindex и выбор через mousedown */
    drop.querySelectorAll('.street-opt').forEach(opt => {
        opt.setAttribute('tabindex', '0');

        /* mousedown: срабатывает ДО того как document.click закроет дроп */
        opt.addEventListener('mousedown', e => {
            e.preventDefault(); // не уводить фокус с inp, не триггерить blur
            selectStreet(opt);
        });

        /* Клавиатурная навигация внутри списка */
        opt.addEventListener('keydown', e => {
            const visible = [...drop.querySelectorAll('.street-opt')]
                .filter(o => o.style.display !== 'none');
            const idx = visible.indexOf(opt);
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectStreet(opt);
            }
            if (e.key === 'ArrowDown') { e.preventDefault(); visible[idx + 1]?.focus(); }
            if (e.key === 'ArrowUp')   {
                e.preventDefault();
                idx === 0 ? inp.focus() : visible[idx - 1]?.focus();
            }
            if (e.key === 'Escape') { _streetCloseDrop(); inp.focus(); }
        });
    });

    /* Стрелка — переключает дроп, показывает всё */
    arrow.addEventListener('mousedown', e => {
        e.preventDefault();
        if (drop.classList.contains('on')) {
            _streetCloseDrop();
        } else {
            _streetShowAll();
            _streetOpenDrop();
            inp.focus();
        }
    });

    /* Ввод — фильтрация */
    inp.addEventListener('input', e => {
        const q = e.target.value.trim().toLowerCase();
        if (!q) {
            _streetCloseDrop();
            document.getElementById('streetHidden').value = '';
            hideZone();
            return;
        }
        let hasVisible = false;
        drop.querySelectorAll('.street-opt').forEach(o => {
            const match = o.dataset.name.toLowerCase().includes(q);
            o.style.display = match ? 'flex' : 'none';
            if (match) hasVisible = true;
        });
        hasVisible ? _streetOpenDrop() : _streetCloseDrop();
    });

    /* Enter / ArrowDown из поля */
    inp.addEventListener('keydown', e => {
        if (e.key === 'Escape') { _streetCloseDrop(); inp.blur(); return; }
        if (e.key === 'Enter') {
            e.preventDefault();
            const visible = [...drop.querySelectorAll('.street-opt')]
                .filter(o => o.style.display !== 'none');
            if (visible.length === 1) selectStreet(visible[0]);
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (!drop.classList.contains('on')) { _streetShowAll(); _streetOpenDrop(); }
            const visible = [...drop.querySelectorAll('.street-opt')]
                .filter(o => o.style.display !== 'none');
            if (visible.length) visible[0].focus();
        }
    });

    /* Клик вне — закрываем */
    document.addEventListener('click', e => {
        if (!inp.closest('.street-wrap').contains(e.target)) {
            _streetCloseDrop();
            /* Если в поле текст не совпадает ни с одной улицей — сбрасываем зону */
            const val   = inp.value.trim();
            const match = CFG.streets.find(s => s.name.toLowerCase() === val.toLowerCase());
            if (val && !match) {
                document.getElementById('streetHidden').value = '';
                hideZone();
            }
        }
    });
});

/* ── МОДАЛКА ВРЕМЕНИ ── */
function openTimeModal() {
    const type = document.querySelector('input[name="delivery_type"]:checked')?.value;
    if (!currentZone && type === 'delivery') {
        showToast('Выберите улицу доставки', 'Сначала укажите улицу', true);
        document.getElementById('streetSearchInput').focus();
        return;
    }
    document.getElementById('tModal').classList.add('on');
    document.body.style.overflow = 'hidden';
    _renderDateTabs();
    const dates = Object.keys(CFG.availableSlots).filter(d => CFG.availableSlots[d].length > 0);
    if (dates.length) selectDate(dates[0]);
}
function closeTimeModal() {
    document.getElementById('tModal').classList.remove('on');
    document.body.style.overflow = '';
}
function _renderDateTabs() {
    const tabs  = document.getElementById('dateTabs');
    const dates = Object.keys(CFG.availableSlots).filter(d => CFG.availableSlots[d].length > 0);
    if (!dates.length) { tabs.innerHTML = '<p style="color:#999;padding:.75rem;">Нет доступных дат</p>'; return; }
    const today = new Date().toISOString().split('T')[0];
    const tmrw  = new Date(Date.now() + 86400000).toISOString().split('T')[0];
    tabs.innerHTML = dates.map(ds => {
        const dt = new Date(ds + 'T00:00:00');
        let lbl  = DOW[dt.getDay()];
        if (ds === today) lbl = 'Сегодня';
        if (ds === tmrw)  lbl = 'Завтра';
        return `<div class="date-tab" data-date="${ds}" onclick="selectDate('${ds}')">
            <div class="date-tab-d">${lbl}</div>
            <div class="date-tab-n">${dt.getDate()} ${MON[dt.getMonth()]}</div>
        </div>`;
    }).join('');
}
function selectDate(ds) {
    selectedDate = ds;
    document.querySelectorAll('.date-tab').forEach(t => t.classList.toggle('on', t.dataset.date === ds));
    const type = document.querySelector('input[name="delivery_type"]:checked')?.value || 'delivery';
    _renderSlots(ds, type);
}
function _renderSlots(ds, type) {
    const grid  = document.getElementById('slotsGrid');
    const slots = (CFG.availableSlots[ds] || []).filter(s =>
        ((s.delivery_type || s.type || 'delivery') === 'pickup' ? 'pickup' : 'delivery') === type
    );
    if (!slots.length) {
        grid.innerHTML = `<div class="slots-empty"><i class="fas fa-info-circle"></i><p>Нет доступных слотов</p></div>`;
        return;
    }
    grid.innerHTML = slots.map(s => {
        const booked = parseInt(s.booked_count || s.occupied || 0);
        const cap    = parseInt(s.capacity || 10);
        const pct    = booked / cap * 100;
        const time   = s.time || s.time_slot || '00:00';
        let cls = 'slot-item', dot = 'slot-dot';
        if (s.is_own_slot)      cls += ' sel';
        else if (booked >= cap) { cls += ' full'; dot += ' full'; }
        else if (pct > 70)      { cls += ' hot';  dot += ' warn'; }
        if (selectedSlot?.id === s.id) cls += ' sel';
        const data = JSON.stringify({ id: s.id, time, capacity: cap, booked_count: booked, delivery_type: s.delivery_type || s.type || 'delivery', is_own_slot: !!s.is_own_slot });
        return `<div class="${cls}" onclick='_pickSlot(${data})' title="Мест: ${cap - booked}">
            <span class="${dot}"></span>${time}</div>`;
    }).join('');
}
function _pickSlot(s) {
    if (!s.is_own_slot && s.booked_count >= s.capacity) {
        showToast('Слот заполнен', 'Выберите другое время', true);
        return;
    }
    selectedSlot = { ...s, date: selectedDate };
    _renderSlots(selectedDate, document.querySelector('input[name="delivery_type"]:checked')?.value || 'delivery');
    const dt = new Date(selectedDate + 'T00:00:00');
    document.getElementById('selectedTimeText').innerHTML =
        `<div class="time-sel-inner"><i class="fas fa-check-circle"></i><span>${dt.getDate()} ${MON[dt.getMonth()]} (${DOW[dt.getDay()]}) в ${s.time}</span></div>`;
    document.getElementById('timeSelectorBtn').classList.add('sel');
    document.getElementById('deliverySlot').value = s.id;
    document.getElementById('deliveryDate').value = selectedDate;
    PromoEngine.check();
    setTimeout(closeTimeModal, 480);
}

/* ── КОРЗИНА ── */
class Cart {
    constructor() { this.items = []; }

    async init() {
        if (IS_EDIT && CFG.editOrderData) {
            this.items = (CFG.editOrderData.items || []).map(i => ({
                id:       String(i.id),
                name:     i.name,
                price:    parseFloat(i.price),
                image:    imgUrl(i.image) || '',
                quantity: parseInt(i.quantity)
            }));
        } else {
            await this._loadServer();
            if (!this.items.length) {
                const ls = localStorage.getItem('sushi_session_id');
                if (ls === CFG.sessionId)   this.items = this._loadLS();
                else if (ls)                await this._loadServerBySession(ls);
            }
        }
        this._showHide();
        if (this.items.length) this.render();
        if (IS_EDIT && CFG.editOrderData) this._prefill(CFG.editOrderData);
        this._attachEvents();
        Catalog.syncAll(this.items);
    }

    _showHide() {
        const hasItems = this.items.length > 0;
        if (!IS_EDIT) {
            const emptyEl = document.getElementById('emptyCart');
            const gridEl  = document.getElementById('checkoutGrid');
            if (emptyEl) emptyEl.style.display = hasItems ? 'none'  : 'block';
            if (gridEl)  gridEl.style.display  = hasItems ? 'grid'  : 'none';
        }
    }

    _loadLS() {
        try { return JSON.parse(localStorage.getItem('sushi_cart') || '[]'); } catch { return []; }
    }

    async _loadServer() {
        try {
            if (CFG.serverCart?.items?.length > 0) {
                this.items = CFG.serverCart.items.map(i => ({
                    id: String(i.id), name: i.name, price: parseFloat(i.price),
                    image: imgUrl(i.image) || '', quantity: parseInt(i.quantity)
                }));
                return;
            }
            const r = await fetch(`/api/cart.php?action=get&session_id=${CFG.sessionId}`);
            const t = await r.text(); if (!t?.trim()) return;
            const d = JSON.parse(t);
            if (d.success && d.cart?.items?.length) {
                this.items = d.cart.items.map(i => ({
                    id: String(i.id), name: i.name, price: parseFloat(i.price),
                    image: imgUrl(i.image) || '', quantity: parseInt(i.quantity)
                }));
            }
        } catch {}
    }

    async _loadServerBySession(sid) {
        try {
            const r = await fetch(`/api/cart.php?action=get&session_id=${sid}`);
            const t = await r.text(); if (!t?.trim()) return;
            const d = JSON.parse(t);
            if (d.success && d.cart?.items?.length) {
                this.items = d.cart.items.map(i => ({
                    id: String(i.id), name: i.name, price: parseFloat(i.price),
                    image: imgUrl(i.image) || '', quantity: parseInt(i.quantity)
                }));
            }
        } catch {}
    }

    addById(pid) {
        if (!IS_EDIT) return;
        const id  = String(pid);
        const p   = CFG.catalogProducts.find(x => String(x.id) === id);
        if (!p) return;
        const idx = this.items.findIndex(i => i.id === id);
        if (idx >= 0) this.items[idx].quantity++;
        else this.items.push({ id, name: p.name, price: parseFloat(p.price), image: imgUrl(p.image) || '', quantity: 1 });
        this._afterChange(id);
    }

    changeQty(pid, delta) {
        if (!IS_EDIT) return;
        const id  = String(pid);
        const idx = this.items.findIndex(i => i.id === id);
        if (idx < 0) return;
        this.items[idx].quantity += delta;
        if (this.items[idx].quantity <= 0) { this.items.splice(idx, 1); Catalog.sync(id, 0); }
        else Catalog.sync(id, this.items[idx].quantity);
        this._afterChange(id);
    }

    _afterChange(id) {
        const qty = this.items.find(i => i.id === id)?.quantity || 0;
        Catalog.sync(id, qty);
        this._showHide();
        this.render();
        this.updateSummary();
    }

    render() {
        const list = document.getElementById('cartItemsList');
        if (!this.items.length) { list.innerHTML = ''; return; }
        list.innerHTML = this.items.map(it => {
            const src = imgUrl(it.image) || PH;
            const qtyCtrl = IS_EDIT
                ? `<div class="qty-ctrl">
                       <button class="qty-btn minus" onclick="cart.changeQty('${it.id}',-1)">−</button>
                       <span class="qty-num">${it.quantity}</span>
                       <button class="qty-btn plus"  onclick="cart.changeQty('${it.id}',+1)">+</button>
                   </div>`
                : `<div style="font-size:.82rem;font-weight:600;color:var(--text-sub);flex-shrink:0;">${it.quantity} шт</div>`;
            return `
            <div class="cart-item">
                <div class="cart-item-img">
                    <img src="${esc(src)}" alt="${esc(it.name)}" loading="lazy"
                         onerror="this.onerror=null;this.src='${PH}'">
                </div>
                <div class="cart-item-info">
                    <div class="cart-item-name">${esc(it.name)}</div>
                    <div class="cart-item-unit">${fmt(it.price)} / шт</div>
                </div>
                ${qtyCtrl}
                <div class="cart-item-total">${fmt(it.price * it.quantity)}</div>
            </div>`;
        }).join('');

        const maxC = this.items.reduce((s, i) => s + i.quantity, 0);
        document.getElementById('cutleryMax').textContent = `Максимум: ${maxC}`;
        if (!IS_EDIT) {
            document.getElementById('cutleryValue').textContent = maxC;
            document.getElementById('cutleryCount').value       = maxC;
        }
        this.updateSummary();
    }

    _prefill(order) {
        const phone = order.customer_phone || order.customer?.phone || '';
        const name  = order.customer_name  || order.customer?.name  || '';
        const email = order.customer_email || order.customer?.email || '';
        if (phone) document.getElementById('phoneInput').value  = phone;
        if (name)  document.getElementById('nameInput').value   = name;
        if (email) document.getElementById('emailInput').value  = email;

        const dt = order.delivery_type || order.delivery?.type || 'delivery';
        const rb = document.querySelector(`input[name="delivery_type"][value="${dt}"]`);
        if (rb) {
            rb.checked = true;
            document.getElementById('deliverySection').style.display = dt === 'delivery' ? 'block' : 'none';
            document.getElementById('pickupSection').style.display   = dt === 'delivery' ? 'none'  : 'block';
        }
        if (dt === 'delivery') {
            const st = order.delivery?.street || '';
            if (st) selectStreetByName(st);
            const flds = { house:'houseInput', entrance:'entranceInput', floor:'floorInput', apartment:'apartmentInput' };
            Object.entries(flds).forEach(([k, elId]) => {
                const v = order.delivery?.[k] || '';
                if (v) document.getElementById(elId).value = v;
            });
        }
        if (order.comment) document.getElementById('commentInput').value = order.comment;
        const cut = parseInt(order.cutlery_count || 0);
        document.getElementById('cutleryValue').textContent = cut;
        document.getElementById('cutleryCount').value       = cut;
    }

    getSubtotal() { return this.items.reduce((s, i) => s + i.price * i.quantity, 0); }
    getDiscount()  { return currentPromo ? Math.floor(this.getSubtotal() * currentPromo.discount / 100) : 0; }
    getDelivery()  {
        const type = document.querySelector('input[name="delivery_type"]:checked')?.value;
        if (type === 'pickup') return 0;
        const zc = currentZone ? currentZone.delivery_cost : CFG.deliveryCost;
        return (zc === 0 || PromoEngine.freeDelivery()) ? 0 : zc;
    }
    getTotal() { return this.getSubtotal() - this.getDiscount() + this.getDelivery(); }

    _refreshDelivery() {
        const d = this.getDelivery();
       document.getElementById('deliveryAmt').textContent = fmt(d);
document.getElementById('deliveryRow').style.display = d > 0 ? 'flex' : 'none';
document.getElementById('totalAmt').textContent    = fmt(this.getTotal());
    }

    updateSummary() {
        const cnt  = this.items.reduce((s, i) => s + i.quantity, 0);
        const sub  = this.getSubtotal();
        const disc = this.getDiscount();
        const del  = this.getDelivery();
        const tot  = this.getTotal();

document.getElementById('itemsCount').textContent  = cnt;
document.getElementById('subtotalAmt').textContent = fmt(sub);
document.getElementById('deliveryAmt').textContent = fmt(del);
document.getElementById('deliveryRow').style.display = del > 0 ? 'flex' : 'none';
document.getElementById('totalAmt').textContent    = fmt(tot);

        if (disc > 0) {
            document.getElementById('discountRow').style.display = 'flex';
            document.getElementById('discountPct').textContent   = currentPromo.discount;
            document.getElementById('discountAmt').textContent   = '−' + fmt(disc);
        } else {
            document.getElementById('discountRow').style.display = 'none';
        }

        const badge = document.getElementById('cartCount');
        if (badge) { badge.textContent = cnt; badge.style.display = cnt > 0 ? 'flex' : 'none'; }

        if (currentZone) {
            const mo  = currentZone.min_order;
            const btn = document.getElementById('submitBtn');
            const mw  = document.getElementById('minWarn');
            if (sub < mo) {
                mw.classList.add('on'); btn.disabled = true;
                btn.innerHTML = `<i class="fas fa-exclamation-triangle"></i> Мин. сумма: ${mo} ₽`;
            } else {
                mw.classList.remove('on'); btn.disabled = false;
                btn.innerHTML = IS_EDIT
                    ? `<i class="fas fa-save"></i> <span>СОХРАНИТЬ ИЗМЕНЕНИЯ</span>`
                    : `<i class="fas fa-arrow-right"></i> <span>ОФОРМИТЬ ЗАКАЗ</span>`;
            }
        }
        PromoEngine.check();
    }

    _attachEvents() {
        document.querySelectorAll('input[name="delivery_type"]').forEach(r => {
            r.addEventListener('change', e => {
                const isDel = e.target.value === 'delivery';
                document.getElementById('deliverySection').style.display = isDel ? 'block' : 'none';
                document.getElementById('pickupSection').style.display   = isDel ? 'none'  : 'block';
                if (!isDel) hideZone();
                _resetSlot(); this.updateSummary();
            });
        });

        let pt;
        document.getElementById('phoneInput').addEventListener('input', e => {
            clearTimeout(pt);
            const raw = e.target.value.replace(/\D/g,'');
            if (raw.length === 11) pt = setTimeout(() => this._loadCustomer(raw), 500);
            let v = raw;
            if (v.length && v[0] === '8') v = '7' + v.slice(1);
            if (v.length && v[0] !== '7') v = '7' + v;
            let s = '+7';
            if (v.length > 1) s += ' (' + v.slice(1,4);
            if (v.length > 4) s += ') '  + v.slice(4,7);
            if (v.length > 7) s += '-'   + v.slice(7,9);
            if (v.length > 9) s += '-'   + v.slice(9,11);
            e.target.value = s;
        });

        document.getElementById('cutleryPlus').addEventListener('click', () => {
            const cur = parseInt(document.getElementById('cutleryValue').textContent);
            const max = this.items.reduce((s, i) => s + i.quantity, 0);
            if (cur < max) {
                document.getElementById('cutleryValue').textContent = cur + 1;
                document.getElementById('cutleryCount').value       = cur + 1;
            }
        });
        document.getElementById('cutleryMinus').addEventListener('click', () => {
            const cur = parseInt(document.getElementById('cutleryValue').textContent);
            if (cur > 0) {
                document.getElementById('cutleryValue').textContent = cur - 1;
                document.getElementById('cutleryCount').value       = cur - 1;
            }
        });

        document.getElementById('applyPromoBtn').addEventListener('click', () => {
            const code = document.getElementById('promoInput').value.trim().toUpperCase();
            if (PROMO_CODES[code]) {
                currentPromo = PROMO_CODES[code];
                this.updateSummary();
                showToast('Промокод применён!', currentPromo.description);
                document.getElementById('promoInput').disabled       = true;
                document.getElementById('applyPromoBtn').textContent = '✓ Применён';
                document.getElementById('applyPromoBtn').disabled    = true;
            } else {
                showToast('Неверный промокод', 'Проверьте и попробуйте снова', true);
            }
        });

        document.getElementById('submitBtn').addEventListener('click', () => this.submit());
    }

    async _loadCustomer(phone) {
        try {
            const r = await fetch('/api/get-customer.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ phone })
            });
            const d = await r.json();
            if (d.success && d.customer) {
                currentCustomer = d.customer;
                document.getElementById('nameInput').value  = d.customer.name  || '';
                document.getElementById('emailInput').value = d.customer.email || '';
                if (d.customer.certificates?.length) {
                    const act = d.customer.certificates.filter(c => c.status === 'active');
                    if (act.length) {
                        document.getElementById('custInfo').style.display = 'block';
                        document.getElementById('certInfo').innerHTML = act.map(c =>
                            `<div class="cert-badge">🎁 ${c.type === 'product' ? 'Бесплатный ролл' : c.value + ' ₽'}</div>`
                        ).join('');
                    }
                }
            }
        } catch {}
    }

    clearCart() {
        localStorage.removeItem('sushi_cart');
        localStorage.removeItem('sushi_session_id');
        fetch(`/api/cart.php?action=clear&session_id=${CFG.sessionId}`, { method: 'POST' }).catch(()=>{});
        this.items = [];
    }

    async submit() {
        const form = document.getElementById('checkoutForm');
        const type = document.querySelector('input[name="delivery_type"]:checked')?.value;
        const fd   = new FormData(form);
        const ph   = fd.get('phone');
        const nm   = fd.get('name');

        if (!ph || ph.replace(/\D/g,'').length < 10) {
            showToast('Введите корректный номер телефона', '', true);
            document.getElementById('phoneInput').focus(); return;
        }
        if (!nm || nm.trim().length < 2) {
            showToast('Введите ваше имя', '', true);
            document.getElementById('nameInput').focus(); return;
        }
        if (type === 'delivery') {
            if (!currentZone) {
                showToast('Выберите улицу доставки', '', true);
                document.getElementById('streetSearchInput').focus(); return;
            }
            if (!fd.get('house')?.trim()) {
                showToast('Укажите номер дома', '', true);
                document.getElementById('houseInput').focus(); return;
            }
            if (this.getSubtotal() < currentZone.min_order) {
                showToast(`Минимальная сумма заказа: ${currentZone.min_order} ₽`, `Сейчас: ${fmt(this.getSubtotal())}`, true);
                return;
            }
        }
        if (!selectedSlot) {
            showToast('Выберите дату и время доставки', '', true);
            openTimeModal(); return;
        }

        const addr = type === 'delivery'
            ? [currentZone?.name,
               fd.get('house')     ? 'д. '      + fd.get('house')     : '',
               fd.get('entrance')  ? 'подъезд ' + fd.get('entrance')  : '',
               fd.get('floor')     ? 'эт. '     + fd.get('floor')     : '',
               fd.get('apartment') ? 'кв. '     + fd.get('apartment') : ''
            ].filter(Boolean).join(', ')
            : (CFG.pickupAddress || 'Самовывоз');

        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        document.getElementById('bLoaderTxt').textContent = IS_EDIT ? 'Сохраняем изменения...' : 'Бронируем слот...';
        document.getElementById('bLoader').classList.add('on');

        try {
            if (IS_EDIT) {
                const oldSlot = CFG.editOrderData?.delivery?.slot_id || CFG.editOrderData?.slot_id || null;
                if (oldSlot && oldSlot === selectedSlot.id) {
                    await this._updateOrder(this._buildOrder(fd, type, addr, nm, ph));
                    return;
                }
                if (oldSlot && oldSlot !== selectedSlot.id) {
                    try {
                        await fetch('/api/release-slot.php', {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ slot_id: oldSlot, order_id: CFG.editOrderId })
                        });
                    } catch {}
                }
            }

            try {
                const br = await fetch('/api/book-slot.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        slot_id:  selectedSlot.id,
                        order_id: IS_EDIT ? CFG.editOrderId : window._pendingOrderId,
                        phone:    ph
                    })
                });
                const bd = await br.json().catch(() => ({}));
                if (!bd.success && !IS_EDIT) {
                    document.getElementById('bLoader').classList.remove('on');
                    btn.disabled = false;
                    showToast('Слот уже занят!', 'Выберите другое время', true);
                    openTimeModal();
                    return;
                }
            } catch(e) {
                if (!IS_EDIT) {
                    document.getElementById('bLoader').classList.remove('on');
                    btn.disabled = false;
                    if (!confirm('Не удалось забронировать слот.\nПродолжить оформление?')) return;
                }
            }

            const order = this._buildOrder(fd, type, addr, nm, ph);

            if (IS_EDIT) {
                await this._updateOrder(order);
                return;
            }

            order.created_at = new Date().toISOString();
            order.order_id   = window._pendingOrderId;
            try {
                sessionStorage.setItem('pendingOrder', JSON.stringify(order));
                if (!sessionStorage.getItem('pendingOrder')) throw new Error('write failed');
            } catch {
                document.getElementById('bLoader').classList.remove('on');
                btn.disabled = false;
                showToast('Ошибка сохранения данных', 'Попробуйте ещё раз', true);
                return;
            }
            window.location.href = '/pages/payment.php';

        } catch(e) {
            document.getElementById('bLoader').classList.remove('on');
            btn.disabled = false;
            showToast('Произошла ошибка', e.message, true);
        }
    }

    _buildOrder(fd, type, addr, nm, ph) {
        return {
            customer: {
                id:    currentCustomer?.id || CFG.editOrderData?.customer?.id || null,
                name:  nm, phone: ph,
                email: fd.get('email') || ''
            },
            delivery: {
                type, date: selectedDate, address: addr,
                street:    currentZone?.name     || '',
                house:     fd.get('house')     || '',
                entrance:  fd.get('entrance')  || '',
                floor:     fd.get('floor')     || '',
                apartment: fd.get('apartment') || '',
                slot_id:   selectedSlot.id,
                slot_time: selectedSlot.time,
                zone_id:   currentZone?.zone_id   || null,
                zone_name: currentZone?.zone_name  || null,
                zone_slug: currentZone?.zone_slug  || null
            },
            items:         this.items,
            cutlery_count: parseInt(fd.get('cutlery_count') || 0),
            promo_code:    currentPromo ? document.getElementById('promoInput').value : null,
            amounts: {
                subtotal: this.getSubtotal(),
                discount: this.getDiscount(),
                delivery: this.getDelivery(),
                total:    this.getTotal()
            },
            comment:          fd.get('comment') || '',
            delivery_type:    type,
            delivery_date:    selectedDate,
            delivery_time:    selectedSlot.time,
            delivery_address: addr,
            customer_name:    nm,
            customer_phone:   ph,
            customer_email:   fd.get('email') || '',
            status:           'pending',
            payment_status:   'pending',
            sync_1c:          false
        };
    }

    async _updateOrder(order) {
        const btn = document.getElementById('submitBtn');
        btn.disabled  = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Сохраняем...';
        document.getElementById('bLoaderTxt').textContent = 'Сохраняем изменения...';
        document.getElementById('bLoader').classList.add('on');

        try {
            const r = await fetch('/api/cart.php?action=update_order', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ order_id: CFG.editOrderId, ...order })
            });
            const text = await r.text();
            let d = {};
            try { d = JSON.parse(text); } catch(e) {
                throw new Error('Сервер вернул некорректный ответ: ' + text.substring(0, 100));
            }
            document.getElementById('bLoader').classList.remove('on');
            if (d.success) {
                showToast('Заказ успешно обновлён!', 'Перенаправляем в личный кабинет...');
                setTimeout(() => {
                    window.location.href = '/pages/account.php?tab=orders&updated=' + CFG.editOrderId;
                }, 1500);
            } else {
                throw new Error(d.message || 'Ошибка обновления заказа');
            }
        } catch(e) {
            document.getElementById('bLoader').classList.remove('on');
            btn.disabled  = false;
            btn.innerHTML = '<i class="fas fa-save"></i> СОХРАНИТЬ ИЗМЕНЕНИЯ';
            showToast('Ошибка при сохранении', e.message, true);
            console.error('[_updateOrder]', e);
        }
    }
}

/* ── ИНИЦИАЛИЗАЦИЯ ── */
let cart;
document.addEventListener('DOMContentLoaded', async () => {
    Catalog.init();
    PromoEngine.load();

    cart = new Cart();
    await cart.init();
    PromoEngine.check();

    document.getElementById('mobToggle')?.addEventListener('click', function () {
        this.classList.toggle('active');
        document.querySelector('.header-nav')?.classList.toggle('active');
    });
    document.getElementById('tModal').addEventListener('click', e => {
        if (e.target.id === 'tModal') closeTimeModal();
    });
});
</script>
</body>
</html>