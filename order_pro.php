<?php
/**
 * @name        Заказы PRO
 * @icon        🚀
 * @description Канбан · расчёт суммы · архив · уведомления
 * @version     3.3
 * @sidebar     true
 * @color       #7c3aed
 */

// ============================================================
//  КОНСТАНТЫ
// ============================================================
define('OP_AI_KEY',   'sk-8d7a4a5bad594da5bd885cfad5893213');
define('OP_AI_URL',   'https://api.deepseek.com/chat/completions');
define('OP_AI_MODEL', 'deepseek-chat');
define('OP_VK_URL',   'https://srm.itmag.site/bot/vk.php');
define('OP_VK_KEY',   'vk2025notify');
define('OP_ART_URL',  'https://srm.itmag.site/bot/artemiy.php');
define('OP_ART_KEY',  'artemiy2025notify');
define('OP_CRM_URL',  'https://srm.itmag.site/bot/crm_notify.php');
define('OP_CRM_KEY',  'crm2025notify');

// ============================================================
//  ИНИЦИАЛИЗАЦИЯ
// ============================================================
if (!isset($moduleDB['order_pro_settings'])) {
    $moduleDB['order_pro_settings'] = [
        'enabled'         => true,
        'notify_urgent'   => true,
        'urgent_hours'    => 4,
        'default_manager' => '',
        'notify_vk'       => true,
        'notify_artemiy'  => true,
        'notify_crm'      => true,
        'receipt_ad'      => '',
        'receipt_ad2'     => '',
        'company_slogan'  => '',
    ];
    writeDB($moduleDB);
}
if (!isset($moduleDB['order_events'])) {
    $moduleDB['order_events'] = [];
    writeDB($moduleDB);
}

// ============================================================
//  ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ============================================================

function op_service_label(string $svc): string {
    return [
        'photo'    => 'Фотопечать',
        'copy'     => 'Копирование/Распечатка',
        'banner'   => 'Баннерная печать',
        'design'   => 'Дизайн',
        'business' => 'Бизнес-полиграфия',
        'wide'     => 'Широкоформатная печать',
        'promo'    => 'Сувенирная продукция',
        'other'    => 'Прочее',
    ][$svc] ?? 'Прочее';
}

function op_normalize_phone(string $phone): string {
    $raw = preg_replace('/\D/', '', $phone);
    if (strlen($raw) === 10) $raw = '7' . $raw;
    if (strlen($raw) === 11 && $raw[0] === '8') $raw = '7' . substr($raw, 1);
    if (strlen($raw) === 11 && $raw[0] === '7') return '+' . $raw;
    return $phone;
}

/** Логирование события заказа */
function op_log_event(array &$db, string $orderId, string $orderNum,
                       string $type, array $data = []): void {
    if (!isset($db['order_events'])) $db['order_events'] = [];
    array_unshift($db['order_events'], [
        'id'        => (int)(microtime(true) * 1000),
        'order_id'  => $orderId,
        'order_num' => $orderNum,
        'type'      => $type,
        'data'      => $data,
        'date'      => date('c'),
    ]);
    if (count($db['order_events']) > 500) {
        $db['order_events'] = array_slice($db['order_events'], 0, 500);
    }
}

/** Уведомления клиенту (ВК + Артемий) */
function op_notify_client(array &$db, array $order, string $event): void {
    $sets  = $db['order_pro_settings'] ?? [];
    $phone = $order['phone'] ?? '';
    if (!$phone) return;

    $statusLabels = [
        'new'    => 'принят',
        'work'   => 'взят в работу',
        'ready'  => 'готов к выдаче',
        'done'   => 'выдан',
        'cancel' => 'отменён',
    ];

    $texts = [
        'order_new' =>
            "✅ Заказ {$order['num']} принят!\n" .
            "Услуга: " . ($order['serviceLabel'] ?? '') . "\n" .
            "Сумма: " . number_format($order['total'] ?? 0, 0, '.', ' ') . " ₽\n" .
            "Как только будет готов — сообщим.",
        'order_status' =>
            "🔄 Заказ {$order['num']}: статус изменён на «" .
            ($statusLabels[$order['status'] ?? ''] ?? ($order['status'] ?? '')) . "»",
        'order_ready' =>
            "🎉 Заказ {$order['num']} готов!\n" .
            "Услуга: " . ($order['serviceLabel'] ?? '') . "\n" .
            "К оплате: " . number_format($order['total'] ?? 0, 0, '.', ' ') . " ₽\n" .
            "Приходите забирать!",
        'order_done' =>
            "📦 Заказ {$order['num']} выдан. Спасибо что выбрали нас! 🙏",
    ];

    $text       = $texts[$event] ?? $texts['order_status'];
    $notifySent = false;
    $channels   = [];

    if ($sets['notify_vk'] ?? true) {
        $ch = curl_init(OP_VK_URL . '?key=' . OP_VK_KEY . '&action=send_to_client');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode(['data' => ['phone' => $phone, 'text' => $text]]),
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $r = json_decode($res, true);
        if ($r['ok'] ?? false) { $notifySent = true; $channels[] = 'vk'; }
    }

    if ($sets['notify_artemiy'] ?? true) {
        $artPayload = array_merge($order, [
            'phone'       => $phone,
            'client_name' => $order['client'] ?? '',
            'text'        => $text,
            'event_type'  => $event,
        ]);
        $vkUserId = null;
        if (!empty($order['client_id'])) {
            foreach ($db['clients'] ?? [] as $cl) {
                if ((string)$cl['id'] === (string)$order['client_id']) {
                    $vkUserId = $cl['vk_user_id'] ?? null;
                    break;
                }
            }
        }
        if ($vkUserId) $artPayload['vk_user_id'] = $vkUserId;

        $ch = curl_init(OP_ART_URL . '?key=' . OP_ART_KEY . '&event=' . $event);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode(['data' => $artPayload]),
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $artRes  = curl_exec($ch);
        curl_close($ch);
        $artJson = json_decode($artRes, true);
        if ($artJson['ok'] ?? false) { $notifySent = true; $channels[] = 'artemiy'; }
    }

    op_log_event($db, $order['id'] ?? '', $order['num'] ?? '', 'notify_sent', [
        'event'   => $event,
        'phone'   => $phone,
        'text'    => mb_substr($text, 0, 100),
        'channel' => implode('+', $channels) ?: 'none',
        'sent'    => $notifySent,
    ]);
}

/** Уведомление директору (МАКс) */
function op_notify_crm(array &$db, string $event, array $data): void {
    $sets = $db['order_pro_settings'] ?? [];
    if (!($sets['notify_crm'] ?? true)) return;

    $safeData = $data;
    $safeData['_phone_info'] = $safeData['phone'] ?? '';
    unset($safeData['phone']);

    $ch = curl_init(OP_CRM_URL . '?key=' . OP_CRM_KEY . '&event=' . $event);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['data' => $safeData]),
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    op_log_event($db, $data['id'] ?? '', $data['num'] ?? '', 'crm_notify_sent', [
        'event'     => $event,
        'http_code' => $httpCode,
        'ok'        => ($httpCode === 200),
    ]);
}

/** Проверка дедлайнов */
function op_check_deadlines(array &$db): void {
    $now    = time();
    $urgent = $db['order_pro_settings']['urgent_hours'] ?? 4;

    foreach ($db['orders'] ?? [] as &$o) {
        if ($o['archived'] ?? false) continue;
        if (in_array($o['status'] ?? '', ['done', 'cancel'])) continue;
        if (empty($o['deadline'])) continue;

        $dl   = strtotime($o['deadline']);
        $diff = $dl - $now;

        if ($diff < 0 && !($o['_deadline_breach_logged'] ?? false)) {
            op_log_event($db, $o['id'], $o['num'], 'deadline_breach', [
                'client' => $o['client'] ?? '', 'deadline' => $o['deadline'], 'status' => $o['status'] ?? '',
            ]);
            op_notify_crm($db, 'order_status', array_merge($o, ['status' => 'deadline_breach']));
            $o['_deadline_breach_logged'] = true;
        }
        if ($diff > 0 && $diff < ($urgent * 3600) && !($o['_deadline_warning_logged'] ?? false)) {
            op_log_event($db, $o['id'], $o['num'], 'deadline_warning', [
                'client' => $o['client'] ?? '', 'deadline' => $o['deadline'], 'hours_left' => round($diff / 3600, 1),
            ]);
            $o['_deadline_warning_logged'] = true;
        }
    }
    unset($o);
}

/**
 * ИИ — сокращённый промпт под технику ПРИНТСС медиа.
 */
function op_ai_call(string $prompt): string {
    $systemPrompt =
        'Ты эксперт типографии ПРИНТСС медиа (Сосновый Бор). ' .
        'Оборудование: эко-сольвентный плоттер Audley 1.6м, Epson L805 (3шт), Epson 1410 A3, ' .
        '3 лазерных A4, лазерный A3 цветной, ламинатор, сублимация, плоттерная резка, ' .
        'лазерная резка, баннеры, плёнка, стенды. ' .
        'Отвечай кратко — только главное. Без лишних слов. Язык — русский.';

    $payload = json_encode([
        'model'       => OP_AI_MODEL,
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $prompt],
        ],
        'max_tokens'  => 500,
        'temperature' => 0.3,
    ]);
    $ch = curl_init(OP_AI_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OP_AI_KEY,
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if (!$res) return 'Нет ответа от ИИ';
    $json = json_decode($res, true);
    return $json['choices'][0]['message']['content']
        ?? ('Ошибка: ' . ($json['error']['message'] ?? 'неизвестно'));
}

// ============================================================
//  ПРОВЕРКА — включён?
// ============================================================
$opSettings = $moduleDB['order_pro_settings'] ?? [];
if (!($opSettings['enabled'] ?? true) &&
    !in_array($moduleAction, ['get_settings', 'save_settings'])) {
    echo json_encode(['ok' => false, 'error' => 'Модуль отключён']);
    exit;
}

op_check_deadlines($moduleDB);

// ============================================================
//  РОУТИНГ
// ============================================================
switch ($moduleAction) {

    case 'list':
        $orders   = $moduleDB['orders'] ?? [];
        $status   = $moduleParams['status']   ?? '';
        $search   = mb_strtolower($moduleParams['search']   ?? '');
        $service  = $moduleParams['service']  ?? '';
        $archived = ($moduleParams['archived'] ?? 'false') === 'true';

        $orders = array_values(array_filter($orders,
            fn($o) => ($o['archived'] ?? false) === $archived));
        if ($status)  $orders = array_values(array_filter($orders, fn($o) => ($o['status'] ?? '') === $status));
        if ($service) $orders = array_values(array_filter($orders, fn($o) => ($o['service'] ?? '') === $service));
        if ($search)  $orders = array_values(array_filter($orders,
            fn($o) =>
                mb_strpos(mb_strtolower($o['client']  ?? ''), $search) !== false ||
                mb_strpos(mb_strtolower($o['num']     ?? ''), $search) !== false ||
                mb_strpos(mb_strtolower($o['comment'] ?? ''), $search) !== false
        ));
        echo json_encode(['ok' => true, 'data' => array_values($orders)]);
        break;

    case 'get':
        $id    = $moduleParams['id'] ?? $moduleBody['id'] ?? null;
        $found = null;
        foreach ($moduleDB['orders'] ?? [] as $o) {
            if ((string)$o['id'] === (string)$id) { $found = $o; break; }
        }
        echo $found
            ? json_encode(['ok' => true, 'data' => $found])
            : json_encode(['ok' => false, 'error' => 'Не найден']);
        break;

    case 'save':
        $isEdit  = !empty($moduleBody['id']);
        $orderId = $isEdit ? $moduleBody['id'] : (string)(time() . rand(100, 999));

        if (!$isEdit) {
            $counter = $moduleDB['orderCounter'] ?? 1;
            $num     = 'PRO-' . str_pad($counter, 5, '0', STR_PAD_LEFT);
            $moduleDB['orderCounter'] = $counter + 1;
        } else {
            $num = $moduleBody['num'] ?? '';
            foreach ($moduleDB['orders'] ?? [] as $ex) {
                if ((string)$ex['id'] === (string)$orderId) { $num = $ex['num']; break; }
            }
        }

        // Поиск клиента
        $clientData = [];
        $clientId   = $moduleBody['client_id'] ?? null;
        if ($clientId) {
            foreach ($moduleDB['clients'] ?? [] as $cl) {
                if ((string)$cl['id'] === (string)$clientId) { $clientData = $cl; break; }
            }
        }
        if (!$clientData && !empty($moduleBody['phone'])) {
            $normPhone = op_normalize_phone($moduleBody['phone']);
            foreach ($moduleDB['clients'] ?? [] as $cl) {
                if (op_normalize_phone($cl['phone'] ?? '') === $normPhone) { $clientData = $cl; break; }
            }
        }

        // Файлы клиента из stamps
        $clientFiles = [];
        if ($clientData) {
            foreach ($moduleDB['stamps'] ?? [] as $stamp) {
                if (mb_strtolower($stamp['client'] ?? '') === mb_strtolower($clientData['name'] ?? '')) {
                    $clientFiles[] = [
                        'name'  => $stamp['name'] ?? ($stamp['fileName'] ?? ''),
                        'url'   => $stamp['fileUrl'] ?? '',
                        'type'  => $stamp['fileType'] ?? 'image/png',
                        'size'  => $stamp['fileSize'] ?? 0,
                        '_from' => 'stamps',
                    ];
                }
            }
        }

        $total  = floatval($moduleBody['total']  ?? 0);
        $prepay = floatval($moduleBody['prepay'] ?? 0);
        $disc   = intval($moduleBody['discount'] ?? ($clientData['discount'] ?? 0));

        // pay_status: 'none' | 'prepay' | 'paid'
        $payStatus = $moduleBody['pay_status'] ?? 'none';

        $totalBeforeDisc = $total;
        if ($disc > 0 && $total > 0) {
            $total = round($total * (1 - $disc / 100), 2);
        }

        // Внутренний учёт: paid считается по pay_status
        $paidAmount = 0;
        if ($payStatus === 'prepay') $paidAmount = $prepay;
        elseif ($payStatus === 'paid') $paidAmount = $total;

        $order = [
            'id'           => $orderId,
            'num'          => $num,
            'date'         => $moduleBody['date']     ?? date('Y-m-d\TH:i'),
            'deadline'     => $moduleBody['deadline'] ?? '',
            'client'       => $moduleBody['client']   ?? ($clientData['name']  ?? 'Без имени'),
            'client_id'    => $clientId               ?? ($clientData['id']    ?? null),
            'phone'        => $moduleBody['phone']    ?? ($clientData['phone'] ?? ''),
            'email'        => $moduleBody['email']    ?? ($clientData['email'] ?? ''),
            'manager'      => $moduleBody['manager']  ?? '',
            'service'      => $moduleBody['service']  ?? 'other',
            'serviceLabel' => op_service_label($moduleBody['service'] ?? 'other'),
            'status'       => $moduleBody['status']   ?? 'new',
            'priority'     => $moduleBody['priority'] ?? 'normal',
            'pay_status'   => $payStatus,
            'total'        => $total,
            'total_base'   => $totalBeforeDisc,
            'prepay'       => $prepay,
            'paid'         => $paidAmount,
            'discount'     => $disc,
            'comment'      => $moduleBody['comment']     ?? '',
            'options'      => $moduleBody['options']     ?? [],
            'extraFields'  => $moduleBody['extraFields'] ?? [],
            'files'        => array_merge($moduleBody['files'] ?? [], $clientFiles),
            'source'       => $moduleBody['source'] ?? 'crm',
            'archived'     => false,
            'createdAt'    => date('c'),
            'updatedAt'    => date('c'),
        ];

        if ($isEdit) {
            $found = false;
            foreach ($moduleDB['orders'] as &$o) {
                if ((string)$o['id'] === (string)$orderId) {
                    $order['archived']  = $o['archived']  ?? false;
                    $order['createdAt'] = $o['createdAt'] ?? date('c');
                    $o = $order; $found = true; break;
                }
            }
            unset($o);
            if (!$found) array_unshift($moduleDB['orders'], $order);
            op_log_event($moduleDB, $orderId, $num, 'order_updated', [
                'client' => $order['client'], 'total' => $order['total'],
            ]);
            op_notify_crm($moduleDB, 'order_status', $order);
        } else {
            if (!isset($moduleDB['orders'])) $moduleDB['orders'] = [];
            array_unshift($moduleDB['orders'], $order);

            op_log_event($moduleDB, $orderId, $num, 'order_created', [
                'client'       => $order['client'],
                'total'        => $order['total'],
                'serviceLabel' => $order['serviceLabel'],
                'pay_status'   => $payStatus,
            ]);
            if (!empty($order['phone'])) {
                op_notify_client($moduleDB, $order, 'order_new');
            }
            op_notify_crm($moduleDB, 'order_new', $order);
        }

        // Автодобавление клиента
        if (!empty($order['client']) && $order['client'] !== 'Без имени') {
            if (!isset($moduleDB['clients'])) $moduleDB['clients'] = [];
            $exists = false;
            foreach ($moduleDB['clients'] as $c) {
                if (mb_strtolower($c['name'] ?? '') === mb_strtolower($order['client'])) {
                    $exists = true; break;
                }
            }
            if (!$exists) {
                $moduleDB['clients'][] = [
                    'id'        => (int)(microtime(true) * 1000) + 1,
                    'name'      => $order['client'],
                    'phone'     => $order['phone'],
                    'email'     => $order['email'],
                    'createdAt' => date('c'),
                ];
            }
        }

        writeDB($moduleDB);
        echo json_encode(['ok' => true, 'data' => $order]);
        break;

    // Обновление статуса оплаты (внутренний учёт)
    case 'set_pay_status':
        $id        = $moduleBody['order_id'] ?? null;
        $payStatus = $moduleBody['pay_status'] ?? 'none'; // none|prepay|paid

        if (!$id) { echo json_encode(['ok' => false, 'error' => 'Нет id']); break; }

        $orderOut = null;
        foreach ($moduleDB['orders'] as &$o) {
            if ((string)$o['id'] !== (string)$id) continue;
            $o['pay_status'] = $payStatus;
            if ($payStatus === 'paid')   $o['paid'] = $o['total'] ?? 0;
         elseif ($payStatus === 'prepay') $o['paid'] = floatval($o['prepay'] ?? 0);
            else $o['paid'] = 0;
            $o['updatedAt'] = date('c');
            op_log_event($moduleDB, $o['id'], $o['num'], 'pay_status_changed', [
                'pay_status' => $payStatus, 'paid' => $o['paid'],
            ]);
            $orderOut = $o;
            break;
        }
        unset($o);
        if (!$orderOut) { echo json_encode(['ok' => false, 'error' => 'Не найден']); break; }
        writeDB($moduleDB);
        echo json_encode(['ok' => true, 'data' => $orderOut]);
        break;

    case 'status':
        $id        = $moduleBody['id']     ?? null;
        $newStatus = $moduleBody['status'] ?? null;
        $allowed   = ['new', 'work', 'ready', 'done', 'cancel'];
        if (!$id || !in_array($newStatus, $allowed)) {
            echo json_encode(['ok' => false, 'error' => 'Неверный статус']); break;
        }
        $orderOut = null;
        foreach ($moduleDB['orders'] as &$o) {
            if ((string)$o['id'] === (string)$id) {
                $oldStatus      = $o['status'];
                $o['status']    = $newStatus;
                $o['updatedAt'] = date('c');
                $orderOut       = $o;

                op_log_event($moduleDB, $id, $o['num'], 'status_changed', [
                    'from' => $oldStatus, 'to' => $newStatus, 'client' => $o['client'] ?? '',
                ]);

                $event = $newStatus === 'ready' ? 'order_ready'
                       : ($newStatus === 'done'   ? 'order_done'  : 'order_status');
                if (!empty($o['phone'])) op_notify_client($moduleDB, $o, $event);
                op_notify_crm($moduleDB, $event === 'order_status' ? 'order_status' : $event, $o);
                break;
            }
        }
        unset($o);
        writeDB($moduleDB);
        echo json_encode(['ok' => true, 'data' => $orderOut ?? null]);
        break;

    case 'archive':
        $id        = $moduleBody['id']        ?? null;
        $unarchive = (bool)($moduleBody['unarchive'] ?? false);
        $orderOut  = null;
        foreach ($moduleDB['orders'] as &$o) {
            if ((string)$o['id'] === (string)$id) {
                $o['archived']   = !$unarchive;
                $o['archivedAt'] = !$unarchive ? date('c') : null;
                op_log_event($moduleDB, $id, $o['num'], $unarchive ? 'unarchived' : 'archived', []);
                $orderOut = $o;
                break;
            }
        }
        unset($o);
        writeDB($moduleDB);
        echo json_encode(['ok' => true, 'data' => $orderOut ?? null]);
        break;

    case 'delete':
        $id = $_GET['id'] ?? $moduleBody['id'] ?? null;
        $moduleDB['orders'] = array_values(
            array_filter($moduleDB['orders'] ?? [], fn($o) => (string)$o['id'] !== (string)$id)
        );
        writeDB($moduleDB);
        echo json_encode(['ok' => true]);
        break;

    case 'events':
        $limit  = intval($moduleParams['limit']  ?? 50);
        $type   = $moduleParams['type']          ?? '';
        $events = $moduleDB['order_events']      ?? [];
        if ($type) {
            $events = array_values(array_filter($events, fn($e) => ($e['type'] ?? '') === $type));
        }
        echo json_encode(['ok' => true, 'data' => array_slice($events, 0, $limit)]);
        break;

    case 'ai_analyze':
        $order  = $moduleBody['order'] ?? [];
        $prompt =
            "Заказ: " . ($order['serviceLabel'] ?? '') . "\n" .
            "Клиент: " . ($order['client'] ?? '') . "\n" .
            "Сумма: " . ($order['total'] ?? 0) . " руб\n" .
            "Опции: " . implode(', ', (array)($order['options'] ?? [])) . "\n" .
            "Комментарий: " . ($order['comment'] ?? '') . "\n\n" .
            "Дай краткий совет: 1) на каком оборудовании печатать 2) что уточнить у клиента 3) upsell";
        echo json_encode(['ok' => true, 'data' => op_ai_call($prompt)]);
        break;

    case 'ai_kp':
        $order  = $moduleBody['order'] ?? [];
        $s      = $moduleDB['settings'] ?? [];
        $prompt =
            "Составь краткое КП. Компания: " . ($s['company'] ?? 'ПРИНТСС медиа') . "\n" .
            "Услуга: " . ($order['serviceLabel'] ?? '') . "\n" .
            "Клиент: " . ($order['client'] ?? '') . "\n" .
            "Сумма: " . ($order['total'] ?? 0) . " руб. Срок КП — 5 дней. Деловой стиль. Кратко.";
        echo json_encode(['ok' => true, 'data' => op_ai_call($prompt)]);
        break;

    case 'get_settings':
        echo json_encode(['ok' => true, 'data' => $moduleDB['order_pro_settings'] ?? []]);
        break;

    case 'save_settings':
        $keys = ['enabled','notify_urgent','urgent_hours','default_manager',
                 'notify_vk','notify_artemiy','notify_crm',
                 'receipt_ad','receipt_ad2','company_slogan'];
        foreach ($keys as $k) {
            if (array_key_exists($k, $moduleBody))
                $moduleDB['order_pro_settings'][$k] = $moduleBody[$k];
        }
        writeDB($moduleDB);
        echo json_encode(['ok' => true]);
        break;

    case 'stats':
        $orders = array_filter($moduleDB['orders'] ?? [], fn($o) => !($o['archived'] ?? false));
        $month  = date('Y-m');
        $mOrds  = array_filter($orders, fn($o) => str_starts_with($o['date'] ?? '', $month));
        $bySt   = array_count_values(array_column(array_values($orders), 'status'));
        $bySvc  = [];
        foreach ($orders as $o) { $k = $o['serviceLabel'] ?? 'Прочее'; $bySvc[$k] = ($bySvc[$k] ?? 0) + 1; }
        echo json_encode(['ok' => true, 'data' => [
            'total'     => count($orders),
            'month'     => count($mOrds),
            'byStatus'  => $bySt,
            'byService' => $bySvc,
            'revenue'   => array_sum(array_column(array_values($mOrds), 'total')),
            'paid'      => array_sum(array_column(array_values($mOrds), 'paid')),
            'archived'  => count(array_filter($moduleDB['orders'] ?? [], fn($o) => $o['archived'] ?? false)),
        ]]);
        break;

    case 'client_orders':
        $clientId   = $moduleParams['client_id']   ?? null;
        $clientName = $moduleParams['client_name'] ?? '';
        $orders     = $moduleDB['orders'] ?? [];
        $result     = array_values(array_filter($orders, function($o) use ($clientId, $clientName) {
            if ($clientId && (string)($o['client_id'] ?? '') === (string)$clientId) return true;
            if ($clientName && mb_strtolower($o['client'] ?? '') === mb_strtolower($clientName)) return true;
            return false;
        }));
        usort($result, fn($a,$b) => strcmp($b['createdAt']??'', $a['createdAt']??''));
        echo json_encode(['ok' => true, 'data' => array_slice($result, 0, 20)]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Неизвестное действие: ' . $moduleAction]);
}
?>
<!--MODULE_JS_START-->
<script>
window._op = function(method) {
    var args = Array.prototype.slice.call(arguments, 1);
    var mod  = window.CRM && window.CRM.modules && window.CRM.modules['order_pro'];
    if (!mod) { console.warn('order_pro not ready'); return; }
    if (typeof mod[method] !== 'function') { console.warn('order_pro.' + method + ' not found'); return; }
    return mod[method].apply(mod, args);
};

CRM.registerModule({
    id:    'order_pro',
    name:  'Заказы PRO',
    icon:  '🚀',
    color: '#7c3aed',

    _orders:        [],
    _archive:       [],
    _editId:        null,
    _detailId:      null,
    _currentFiles:  [],
    _existingFiles: [],
    _currentSvc:    'photo',
    _currentOptions:[],
    _dragId:        null,
    _showArchive:   false,
    _settings: {
        enabled: true, notify_urgent: true, urgent_hours: 4,
        default_manager: '',
        notify_vk: true, notify_artemiy: true, notify_crm: true,
        receipt_ad: '', receipt_ad2: '', company_slogan: '',
    },

    KB_STATUSES:      ['new','work','ready','done','cancel'],
    KB_STATUS_LABELS: { new:'Новый', work:'В работе', ready:'Готов', done:'Выдан', cancel:'Отменён' },
    KB_SVC_LABELS: {
        photo:'Фото', copy:'Копи', banner:'Баннер',
        design:'Дизайн', business:'Бизнес',
        wide:'Широкий', promo:'Сувенирка', other:'Прочее'
    },

    // ── SVG ───────────────────────────────────────────────────
    _svg: function(name, sz) {
        sz = sz || 14;
        var icons = {
            archive:   '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/></svg>',
            unarchive: '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><polyline points="10 15 12 13 14 15"/><line x1="12" y1="13" x2="12" y2="17"/></svg>',
            pay:       '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
            edit:      '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
            eye:       '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
            trash:     '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6M9 6V4h6v2"/></svg>',
            print:     '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',
            ai:        '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2z"/><path d="M12 8v4l3 3"/></svg>',
            plus:      '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
            settings:  '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
            bell:      '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
            warn:      '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            check:     '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
            search:    '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
            download:  '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
            user:      '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
            star:      '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            clock:     '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
            note:      '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
            label:     '<svg width="'+sz+'" height="'+sz+'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
        };
        return icons[name] || '';
    },

    // ── СТРАНИЦА ──────────────────────────────────────────────
    page: `
    <div class="page-header">
      <div>
        <div class="page-title">Заказы PRO</div>
        <div class="page-subtitle" id="op_subtitle">Канбан · расчёт суммы · архив</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <div class="search-bar" style="min-width:180px;">
          <svg width="14" height="14" fill="none" stroke="var(--text-muted)" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input type="text" placeholder="Поиск..." id="op_search" oninput="_op('renderKanban')">
        </div>
        <select class="form-select" style="width:120px;" id="op_svc_filter" onchange="_op('renderKanban')">
          <option value="">Все услуги</option>
          <option value="photo">Фото</option>
          <option value="copy">Копи</option>
          <option value="banner">Баннер</option>
          <option value="design">Дизайн</option>
          <option value="business">Бизнес</option>
          <option value="wide">Широкий</option>
          <option value="promo">Сувенирка</option>
          <option value="other">Прочее</option>
        </select>
        <button class="btn btn-secondary btn-sm" id="op_archive_btn" onclick="_op('toggleArchive')" title="Архив">
          <span id="op_archive_icon"></span>
          <span id="op_archive_cnt" style="background:var(--border);color:var(--text-muted);padding:1px 6px;border-radius:10px;font-size:0.7rem;margin-left:4px;">0</span>
        </button>
        <button class="btn btn-secondary btn-sm" id="op_settings_btn" onclick="_op('showSettings')" title="Настройки">
          <span id="op_settings_icon"></span>
        </button>
        <button class="btn btn-primary btn-sm" id="op_new_btn" onclick="_op('openOrderModal')">
          <span id="op_plus_icon"></span> Новый заказ
        </button>
      </div>
    </div>

    <div class="kanban-stats-bar">
      <div class="kanban-stat-pill"><span class="kanban-stat-dot" style="background:#6366f1;"></span><span id="op_cnt_new">0</span>&nbsp;Новых</div>
      <div class="kanban-stat-pill"><span class="kanban-stat-dot" style="background:#f59e0b;"></span><span id="op_cnt_work">0</span>&nbsp;В работе</div>
      <div class="kanban-stat-pill"><span class="kanban-stat-dot" style="background:#10b981;"></span><span id="op_cnt_ready">0</span>&nbsp;Готовы</div>
      <div class="kanban-stat-pill"><span class="kanban-stat-dot" style="background:#06b6d4;"></span><span id="op_cnt_done">0</span>&nbsp;Выдано</div>
      <div class="kanban-stat-pill" style="margin-left:auto;font-weight:800;color:var(--accent3);"><span id="op_total_sum">0 ₽</span></div>
      <div class="kanban-stat-pill" style="color:var(--accent4);">Долг: <span id="op_total_debt">0 ₽</span></div>
    </div>

    <div id="op_urgent_bar" style="display:none;margin-bottom:12px;"></div>
    <div id="op_kanban_board" class="kanban-board"></div>
    <div id="op_archive_view" style="display:none;"></div>
    `,

    // ── RENDER ────────────────────────────────────────────────
    render: function() {
        var self = this;
        var si = function(id, icon, sz) {
            var el = document.getElementById(id);
            if (el) el.innerHTML = self._svg(icon, sz || 14);
        };
        si('op_archive_icon','archive',13);
        si('op_settings_icon','settings',13);
        si('op_plus_icon','plus',13);

        return Promise.all([
            CRM.api('order_pro','list'),
            CRM.api('order_pro','get_settings'),
            CRM.api('order_pro','list',null,{archived:'true'}),
        ]).then(function(res) {
            self._orders   = (res[0]&&res[0].data) ? res[0].data : [];
            self._settings = (res[1]&&res[1].data) ? res[1].data : self._settings;
            self._archive  = (res[2]&&res[2].data) ? res[2].data : [];
            var cnt = document.getElementById('op_archive_cnt');
            if (cnt) cnt.textContent = self._archive.length;
            self.renderKanban();
            self._checkUrgent();
        });
    },

    // ── РЕЖИМЫ ────────────────────────────────────────────────
    _setMode: function(mode) {
        var kb=document.getElementById('op_kanban_board');
        var av=document.getElementById('op_archive_view');
        var sub=document.getElementById('op_subtitle');
        var btnA=document.getElementById('op_archive_btn');
        var purple='rgba(124,58,237,0.2)';
        if(kb) kb.style.display='none';
        if(av) av.style.display='none';
        if(btnA) btnA.style.background='';
        this._showArchive=mode==='archive';
        if(mode==='kanban'){
            if(kb)kb.style.display='';
            if(sub)sub.textContent='Канбан · расчёт суммы · архив';
            this.renderKanban();
        } else if(mode==='archive'){
            if(av)av.style.display='block';
            if(sub)sub.textContent='Архив выполненных заказов';
            if(btnA)btnA.style.background=purple;
            this._renderArchive();
        }
    },
    toggleArchive: function() { this._setMode(this._showArchive ? 'kanban' : 'archive'); },

    // ── КАНБАН ────────────────────────────────────────────────
    renderKanban: function() {
        var self   = this;
        var search = (document.getElementById('op_search')?.value||'').toLowerCase();
        var svcF   = document.getElementById('op_svc_filter')?.value||'';

        var orders = this._orders.filter(function(o) {
            if (o.archived) return false;
            var ms = !search ||
                (o.num||'').toLowerCase().includes(search) ||
                (o.client||'').toLowerCase().includes(search) ||
                (o.comment||'').toLowerCase().includes(search);
            return ms && (!svcF || o.service === svcF);
        });

        var pmap = {urgent:0,high:1,normal:2,low:3};
        orders = orders.slice().sort(function(a,b) {
            var pa=pmap[a.priority]!==undefined?pmap[a.priority]:2;
            var pb=pmap[b.priority]!==undefined?pmap[b.priority]:2;
            if(pa!==pb) return pa-pb;
            return new Date(b.date||0)-new Date(a.date||0);
        });

        var board = document.getElementById('op_kanban_board');
        if (!board) return;
        board.innerHTML=''; board.className='kanban-board';

        var counts={new:0,work:0,ready:0,done:0,cancel:0},total=0,debt=0,cols={};

        this.KB_STATUSES.forEach(function(st) {
            var col=document.createElement('div'); col.className='kanban-col';
            var hdr=document.createElement('div'); hdr.className='kanban-col-header '+st;
            hdr.innerHTML='<div class="kanban-col-title"><span class="kanban-col-badge" id="op_badge_'+st+'">0</span> '+self.KB_STATUS_LABELS[st]+'</div>'+
                (st==='new'?'<button class="kanban-add-btn" onclick="_op(\'openOrderModal\')">+</button>':'');
            col.appendChild(hdr);
            var cardsEl=document.createElement('div'); cardsEl.className='kanban-cards'; cardsEl.id='op_col_'+st;
            cardsEl.addEventListener('dragover',function(e){e.preventDefault();cardsEl.classList.add('drag-over');});
            cardsEl.addEventListener('dragleave',function(){cardsEl.classList.remove('drag-over');});
            cardsEl.addEventListener('drop',function(e){_op('onDrop',e,st);});
            col.appendChild(cardsEl); board.appendChild(col); cols[st]=cardsEl;
        });

        orders.forEach(function(o) {
            var st=o.status||'new'; var col=cols[st]; if(!col) return;
            counts[st]=(counts[st]||0)+1;
            total+=Number(o.total)||0;
           var oPaid=Number(o.paid)||0;
if(oPaid===0&&o.pay_status==='prepay'&&Number(o.prepay)>0) oPaid=Number(o.prepay)||0;
debt+=Math.max(0,(Number(o.total)||0)-oPaid);
            col.appendChild(self._buildCard(o));
        });

        this.KB_STATUSES.forEach(function(st) {
            var col=cols[st];
            if(col&&!col.children.length) col.innerHTML='<div class="kanban-empty">Нет заказов</div>';
            var b=document.getElementById('op_badge_'+st); var p=document.getElementById('op_cnt_'+st);
            if(b) b.textContent=counts[st]||0; if(p) p.textContent=counts[st]||0;
        });

        var cur=CRM.getSettings().currency||'₽';
        var ts=document.getElementById('op_total_sum'); var td=document.getElementById('op_total_debt');
        if(ts) ts.textContent=this._money(total,cur); if(td) td.textContent=this._money(debt,cur);
    },

    // ── КАРТОЧКА КАНБАНА ──────────────────────────────────────
    _buildCard: function(order) {
        var self=this; var card=document.createElement('div');
        card.className='kb-card'; card.draggable=true; card.dataset.id=order.id;

        var deadBadge='';
        if(order.deadline){
            var diff=new Date(order.deadline)-new Date(); var h=diff/3600000;
            if(h<0) deadBadge='<span class="kb-deadline-badge kb-deadline-over">Просрочен</span>';
            else if(h<24) deadBadge='<span class="kb-deadline-badge kb-deadline-warning">'+Math.ceil(h)+'ч</span>';
            else deadBadge='<span class="kb-deadline-badge kb-deadline-ok">'+Math.ceil(h/24)+'д</span>';
        }

        var prioBadge='';
        if(order.priority==='urgent') prioBadge='<span style="font-size:0.62rem;padding:2px 5px;border-radius:4px;background:rgba(239,68,68,0.2);color:#f87171;font-weight:700;margin-bottom:4px;display:inline-block;">Срочно</span><br>';
        else if(order.priority==='high') prioBadge='<span style="font-size:0.62rem;padding:2px 5px;border-radius:4px;background:rgba(245,158,11,0.2);color:#fbbf24;font-weight:700;margin-bottom:4px;display:inline-block;">Важно</span><br>';

        var imgHtml=this._miniPreview(order);

        // Бейдж статуса оплаты
        var ps=order.pay_status||'none';
        var payBadge='';
        if(ps==='paid') payBadge='<span style="font-size:0.62rem;padding:2px 6px;border-radius:4px;background:rgba(16,185,129,0.2);color:#34d399;font-weight:700;">✓ Оплачен</span>';
        else if(ps==='prepay') payBadge='<span style="font-size:0.62rem;padding:2px 6px;border-radius:4px;background:rgba(245,158,11,0.2);color:#fbbf24;font-weight:700;">◑ Предоплата</span>';
        else payBadge='<span style="font-size:0.62rem;padding:2px 6px;border-radius:4px;background:rgba(100,116,139,0.15);color:var(--text-muted);">Не оплачен</span>';

        var svc=order.service||'other'; var oid=String(order.id); var cur=CRM.getSettings().currency||'₽';

        card.innerHTML=
            '<div class="kb-card-actions">'+
            '<button class="kb-action-btn" onclick="_op(\'openDetail\',event,\''+oid+'\')">'+this._svg('eye',11)+'</button>'+
            '<button class="kb-action-btn" onclick="_op(\'toggleStatusMenu\',event,\''+oid+'\')">⇄</button>'+
            '<button class="kb-action-btn" onclick="_op(\'openOrderModal\',\''+oid+'\')">'+this._svg('edit',11)+'</button>'+
            '<button class="kb-action-btn" onclick="_op(\'_archiveOrder\',\''+oid+'\')" style="color:var(--text-muted);">'+this._svg('archive',11)+'</button>'+
            '</div>'+
            '<div class="kb-status-menu" id="op_smenu_'+oid+'">'+
            this.KB_STATUSES.map(function(st){
                return '<div class="kb-status-opt" onclick="_op(\'changeStatus\',\''+oid+'\',\''+st+'\',event)">'+self.KB_STATUS_LABELS[st]+'</div>';
            }).join('')+
            '</div>'+
            imgHtml+prioBadge+
            '<div class="kb-card-head">'+
            '<span class="kb-card-num">'+_esc(order.num||'#—')+'</span>'+
            '<span class="kb-card-service kb-svc-'+svc+'">'+(this.KB_SVC_LABELS[svc]||svc)+'</span>'+
            '</div>'+
            '<div class="kb-card-client">'+_esc(order.client||'Без имени')+'</div>'+
            '<div class="kb-card-desc">'+_esc(this._buildDesc(order))+'</div>'+
            '<div class="kb-card-foot">'+
            '<div>'+
            '<div class="kb-card-price">'+
            (order.discount>0?'<span style="font-size:0.6rem;color:var(--accent3);margin-right:3px;">-'+order.discount+'%</span>':'')+
            this._money(order.total,cur)+'</div>'+
            '<div style="margin-top:3px;">'+payBadge+'</div>'+
            '</div>'+
            '<div class="kb-card-meta"><span class="kb-card-date">'+this._dateShort(order.date)+'</span>'+deadBadge+'</div>'+
            '</div>';

        card.addEventListener('dragstart',function(e){
            self._dragId=order.id;
            setTimeout(function(){card.classList.add('dragging');},0);
            e.dataTransfer.effectAllowed='move';
            e.dataTransfer.setData('text/plain',String(order.id));
        });
        card.addEventListener('dragend',function(){card.classList.remove('dragging');self._dragId=null;});
        card.addEventListener('click',function(e){
            if(e.target.closest('.kb-card-actions')||e.target.closest('.kb-status-menu')) return;
            self.openDetail(e,order.id);
        });
        return card;
    },

    onDrop: function(event,newStatus) {
        event.preventDefault(); event.currentTarget.classList.remove('drag-over');
        var id=event.dataTransfer.getData('text/plain')||this._dragId; if(!id) return;
        this.changeStatus(id,newStatus);
    },

    changeStatus: function(id,newStatus,e) {
        if(e) e.stopPropagation();
        document.querySelectorAll('.kb-status-menu.open').forEach(function(m){m.classList.remove('open');});
        var self=this;
        var order=this._orders.find(function(o){return String(o.id)===String(id);});
        if(!order) return;
        self._doChangeStatus(id,newStatus,order);
    },

    _doChangeStatus: function(id,newStatus,order) {
        var self=this;
        CRM.api('order_pro','status',{id:id,status:newStatus}).then(function(res){
            if(!res||!res.ok){notify('Ошибка','error');return;}
            order.status=newStatus; self.renderKanban(); notify(self.KB_STATUS_LABELS[newStatus],'success');
            if(self._detailId===id) self._refreshDetail();
        });
    },

    toggleStatusMenu: function(e,id) {
        e.stopPropagation();
        document.querySelectorAll('.kb-status-menu.open').forEach(function(m){if(m.id!=='op_smenu_'+id) m.classList.remove('open');});
        var menu=document.getElementById('op_smenu_'+id); if(menu) menu.classList.toggle('open');
    },

    // ── СРОЧНЫЕ ───────────────────────────────────────────────
    _checkUrgent: function() {
        if(!this._settings.notify_urgent) return;
        var bar=document.getElementById('op_urgent_bar'); if(!bar) return;
        var hours=Number(this._settings.urgent_hours)||4; var self=this;
        var urgent=this._orders.filter(function(o){
            if(o.archived||o.status==='done'||o.status==='cancel') return false;
            if(o.priority==='urgent') return true;
            if(o.deadline) return (new Date(o.deadline)-new Date())/3600000<hours;
            return false;
        });
        if(!urgent.length){bar.style.display='none';return;}
        bar.style.display='block';
        bar.innerHTML='<div style="background:linear-gradient(135deg,rgba(239,68,68,0.15),rgba(245,158,11,0.1));border:1px solid rgba(239,68,68,0.4);border-radius:12px;padding:10px 14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">'+
            self._svg('warn',14)+'<span style="font-weight:700;color:#f87171;font-size:0.85rem;">Требуют внимания: '+urgent.length+'</span>'+
            urgent.map(function(o){return '<span style="background:rgba(239,68,68,0.2);color:#fca5a5;font-size:0.72rem;padding:3px 8px;border-radius:8px;cursor:pointer;" onclick="_op(\'openDetail\',null,\''+o.id+'\')">'+_esc(o.num)+' · '+_esc(o.client||'Б/И')+'</span>';}).join('')+
            '</div>';
    },

    // ── ПАНЕЛЬ ОПЛАТЫ (только внутренний учёт) ────────────────
    openPayment: function(e, id) {
        if(e) e.stopPropagation();
        var order=this._orders.find(function(o){return String(o.id)===String(id);});
        if(!order) order=this._archive.find(function(o){return String(o.id)===String(id);});
        if(!order) return;

        var self=this; var cur=CRM.getSettings().currency||'₽';
        var oid=String(order.id);
        var ps=order.pay_status||'none';

        document.getElementById('op_pay_modal')?.remove();

        var html=
            '<div class="modal-overlay" id="op_pay_modal" style="z-index:100001;">'+
            '<div class="modal modal-sm" style="background:#0f172a;border:1px solid #1e293b;">'+
            '<div class="modal-header">'+
            '<div class="modal-title">'+this._svg('pay',14)+' Статус оплаты · '+_esc(order.num)+'</div>'+
            '<button class="modal-close" onclick="document.getElementById(\'op_pay_modal\').remove()">✕</button>'+
            '</div>'+
            '<div style="padding:16px 20px;">'+

            // Инфо о сумме
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px;">'+
            this._infoBox('ИТОГО',     this._money(order.total||0,cur), '')+
            this._infoBox('ПРЕДОПЛАТА',this._money(order.prepay||0,cur),'var(--accent4)')+
            '</div>'+

            '<div style="font-size:0.75rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;margin-bottom:10px;">Отметить статус оплаты:</div>'+

            // 3 кнопки статуса
            '<div style="display:flex;flex-direction:column;gap:8px;">'+
            '<button style="padding:14px 16px;border-radius:10px;border:2px solid '+(ps==='none'?'rgba(100,116,139,0.6)':'rgba(100,116,139,0.25)')+';background:'+(ps==='none'?'rgba(100,116,139,0.15)':'transparent')+';color:'+(ps==='none'?'var(--text-primary)':'var(--text-muted)')+';font-weight:700;cursor:pointer;text-align:left;font-size:0.88rem;display:flex;align-items:center;gap:10px;" '+
            'onclick="_op(\'_setPayStatus\',\''+oid+'\',\'none\')">'+
            '<span style="width:20px;height:20px;border-radius:50%;border:2px solid currentColor;display:flex;align-items:center;justify-content:center;">'+(ps==='none'?'●':'')+'</span>'+
            '<div><div>Не оплачен</div><div style="font-size:0.68rem;font-weight:400;opacity:0.7;">Оплата не получена</div></div></button>'+

            '<button style="padding:14px 16px;border-radius:10px;border:2px solid '+(ps==='prepay'?'rgba(245,158,11,0.6)':'rgba(245,158,11,0.25)')+';background:'+(ps==='prepay'?'rgba(245,158,11,0.12)':'transparent')+';color:'+(ps==='prepay'?'#fbbf24':'var(--text-muted)')+';font-weight:700;cursor:pointer;text-align:left;font-size:0.88rem;display:flex;align-items:center;gap:10px;" '+
            'onclick="_op(\'_setPayStatus\',\''+oid+'\',\'prepay\')">'+
            '<span style="width:20px;height:20px;border-radius:50%;border:2px solid currentColor;display:flex;align-items:center;justify-content:center;">'+(ps==='prepay'?'●':'')+'</span>'+
            '<div><div>◑ Предоплачен</div><div style="font-size:0.68rem;font-weight:400;opacity:0.7;">Получена предоплата: '+this._money(order.prepay||0,cur)+'</div></div></button>'+

            '<button style="padding:14px 16px;border-radius:10px;border:2px solid '+(ps==='paid'?'rgba(16,185,129,0.6)':'rgba(16,185,129,0.25)')+';background:'+(ps==='paid'?'rgba(16,185,129,0.12)':'transparent')+';color:'+(ps==='paid'?'#34d399':'var(--text-muted)')+';font-weight:700;cursor:pointer;text-align:left;font-size:0.88rem;display:flex;align-items:center;gap:10px;" '+
            'onclick="_op(\'_setPayStatus\',\''+oid+'\',\'paid\')">'+
            '<span style="width:20px;height:20px;border-radius:50%;border:2px solid currentColor;display:flex;align-items:center;justify-content:center;">'+(ps==='paid'?'●':'')+'</span>'+
            '<div><div>✓ Оплачен полностью</div><div style="font-size:0.68rem;font-weight:400;opacity:0.7;">Итого: '+this._money(order.total||0,cur)+'</div></div></button>'+
            '</div>'+

            '<div style="margin-top:14px;padding:8px 10px;background:rgba(124,58,237,0.07);border:1px solid rgba(124,58,237,0.15);border-radius:8px;font-size:0.7rem;color:var(--text-muted);">'+
            '💡 Оплата проводится через кассу. Здесь только внутренний учёт статуса.'+
            '</div>'+
            '</div>'+

            '<div class="modal-footer">'+
            '<button class="btn btn-secondary" onclick="document.getElementById(\'op_pay_modal\').remove()">Закрыть</button>'+
            '<button class="btn btn-secondary" onclick="_op(\'_printReceipt\',\''+oid+'\')">'+this._svg('print',12)+' Чек</button>'+
            '<button class="btn btn-secondary" onclick="_op(\'_printLabel\',\''+oid+'\')">'+this._svg('label',12)+' Этикетка</button>'+
            '</div>'+
            '</div></div>';

        document.body.insertAdjacentHTML('beforeend',html);
        document.getElementById('op_pay_modal').classList.add('open');
        document.getElementById('op_pay_modal').addEventListener('click',function(ev){
            if(ev.target.id==='op_pay_modal') document.getElementById('op_pay_modal').remove();
        });
    },

    _setPayStatus: function(orderId, payStatus) {
    var self=this;
    CRM.api('order_pro','set_pay_status',{order_id:orderId,pay_status:payStatus}).then(function(res){
        if(!res||!res.ok){notify('Ошибка','error');return;}
        var idx=self._orders.findIndex(function(o){return String(o.id)===String(orderId);});
        if(idx!==-1){
            self._orders[idx]=res.data;
        } else {
            var aidx=self._archive.findIndex(function(o){return String(o.id)===String(orderId);});
            if(aidx!==-1) self._archive[aidx]=res.data;
        }
        document.getElementById('op_pay_modal')?.remove();
        var labels={none:'Не оплачен',prepay:'Предоплачен',paid:'Оплачен'};
        notify(labels[payStatus]||payStatus,'success');
        self.renderKanban();
        if(self._detailId===orderId) self._refreshDetail();
    });
},

    // ── АРХИВ ─────────────────────────────────────────────────
    _archiveOrder: function(id) {
        var self=this; var order=this._orders.find(function(o){return String(o.id)===String(id);});
        if(!order) return;
        if(!confirm('Переместить '+order.num+' в архив?')) return;
        CRM.api('order_pro','archive',{id:id}).then(function(res){
            if(!res||!res.ok){notify('Ошибка','error');return;}
            var idx=self._orders.findIndex(function(o){return String(o.id)===String(id);});
            if(idx!==-1){var o=self._orders.splice(idx,1)[0];o.archived=true;self._archive.unshift(o);}
            var cnt=document.getElementById('op_archive_cnt'); if(cnt) cnt.textContent=self._archive.length;
            document.getElementById('op_detail_overlay')?.remove();
            self.renderKanban(); notify('В архив','success');
        });
    },

    _unarchiveOrder: function(id) {
        var self=this;
        CRM.api('order_pro','archive',{id:id,unarchive:true}).then(function(res){
            if(!res||!res.ok){notify('Ошибка','error');return;}
            var idx=self._archive.findIndex(function(o){return String(o.id)===String(id);});
            if(idx!==-1){var o=self._archive.splice(idx,1)[0];o.archived=false;self._orders.unshift(o);}
            var cnt=document.getElementById('op_archive_cnt'); if(cnt) cnt.textContent=self._archive.length;
            notify('Возвращён из архива','success'); self._renderArchive();
        });
    },

    _renderArchive: function() {
        var self=this; var av=document.getElementById('op_archive_view'); if(!av) return;
        var orders=this._archive; var cur=CRM.getSettings().currency||'₽';
        if(!orders.length){av.innerHTML='<div class="empty-state card"><div class="icon">'+self._svg('archive',40)+'</div><div class="title">Архив пуст</div></div>';return;}
        av.innerHTML=
            '<div style="margin-bottom:10px;font-size:0.82rem;color:var(--text-muted);">'+self._svg('archive',13)+' Архив · '+orders.length+' заказов</div>'+
            '<div style="display:flex;flex-direction:column;gap:6px;">'+
            orders.map(function(o){
                return '<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:10px;padding:10px 14px;display:flex;align-items:center;gap:10px;opacity:0.85;">'+
                    self._miniPreview(o,'44px')+
                    '<div style="flex:1;min-width:0;">'+
                    '<div style="font-weight:700;font-size:0.82rem;">'+_esc(o.num||'')+'</div>'+
                    '<div style="font-size:0.78rem;">'+_esc(o.client||'—')+'</div>'+
                    '<div style="font-size:0.68rem;color:var(--text-muted);">'+_esc(o.serviceLabel||'')+' · '+(o.date?new Date(o.date).toLocaleDateString('ru-RU'):'')+'</div>'+
                    '</div>'+
                    '<div style="font-weight:800;color:var(--accent2);white-space:nowrap;">'+self._money(o.total||0,cur)+'</div>'+
                    '<div style="display:flex;gap:4px;">'+
                    '<button class="btn btn-secondary btn-xs" onclick="_op(\'openDetail\',event,\''+String(o.id)+'\')">'+self._svg('eye',11)+'</button>'+
                    '<button class="btn btn-secondary btn-xs" title="Вернуть" onclick="_op(\'_unarchiveOrder\',\''+String(o.id)+'\')">'+self._svg('unarchive',11)+'</button>'+
                    '</div></div>';
            }).join('')+
            '</div>';
    },

    // ── МОДАЛКА СОЗДАНИЯ/РЕДАКТ. ЗАКАЗА ───────────────────────
    openOrderModal: function(id) {
        this._editId=id||null; this._currentFiles=[]; this._existingFiles=[];
        var isEdit=!!id;
        var order=isEdit?this._orders.find(function(o){return String(o.id)===String(id);}):null;
        if(isEdit&&!order) order=this._archive.find(function(o){return String(o.id)===String(id);});

        var db=CRM._getCache(); var cur=(db.settings&&db.settings.currency)||'₽'; var self=this;

        var allClients=(db.clients||[]).slice();
        var cpMod=window.CRM&&window.CRM.modules&&window.CRM.modules['clients_pro'];
        if(cpMod&&cpMod._clients){
            cpMod._clients.forEach(function(c){
                var exists=allClients.some(function(x){return(x.phone&&c.phone&&x.phone===c.phone)||(x.name||'').toLowerCase()===(c.name||'').toLowerCase();});
                if(!exists) allClients.push(c);
            });
        }
        var clientsOpts=allClients.map(function(c){return '<option value="'+_esc(c.name)+'" data-phone="'+_esc(c.phone||'')+'" data-email="'+_esc(c.email||'')+'" data-id="'+_esc(String(c.id||''))+'" data-discount="'+(c.discount||0)+'">';}).join('');
        var num=isEdit?(order?order.num:''):'PRO-'+String(db.orderCounter||(this._orders.length+1)).padStart(5,'0');

        var ps=order?order.pay_status||'none':'none';

        var html=
            '<div class="modal-overlay" id="op_order_modal" style="z-index:100000;">'+
            '<div class="modal modal-lg" style="max-height:90vh;overflow-y:auto;background:#0f172a;border:1px solid #1e293b;">'+
            '<div class="modal-header" style="position:sticky;top:0;background:#0f172a;z-index:2;border-bottom:1px solid #1e293b;">'+
            '<div class="modal-title">'+(isEdit?'Редактировать '+_esc(order?order.num:''):'Новый заказ')+'</div>'+
            '<button class="modal-close" onclick="_op(\'_closeOrderModal\')">✕</button>'+
            '</div>'+
            '<div style="padding:20px;">'+

            '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:14px;">'+
            '<div class="form-group"><label class="form-label">№ Заказа</label><input class="form-input" id="op_num" value="'+_esc(order?order.num:num)+'" readonly style="opacity:0.6;"></div>'+
            '<div class="form-group"><label class="form-label">Дата приёма</label><input class="form-input" type="datetime-local" id="op_date" value="'+(order?order.date||'':this._nowDT())+'"></div>'+
            '<div class="form-group"><label class="form-label">Срок выполнения</label><input class="form-input" type="datetime-local" id="op_deadline" value="'+(order?order.deadline||'':'')+'"></div>'+
            '</div>'+

            '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:14px;">'+
            '<div class="form-group"><label class="form-label">Клиент</label>'+
            '<div style="position:relative;">'+
            '<input class="form-input" id="op_client" placeholder="Иван Иванов" list="op_clients_dl" value="'+_esc(order?order.client||'':'')+'" oninput="_op(\'_autoFillClient\',this.value)">'+
            '<datalist id="op_clients_dl">'+clientsOpts+'</datalist>'+
            '<input type="hidden" id="op_client_id" value="'+(order?order.client_id||'':'')+'">'+
            '<div id="op_client_avatar" style="position:absolute;right:8px;top:6px;width:24px;height:24px;border-radius:5px;overflow:hidden;display:none;"></div>'+
            '</div></div>'+
            '<div class="form-group"><label class="form-label">Телефон</label><input class="form-input" id="op_phone" placeholder="+7..." value="'+_esc(order?order.phone||'':'')+'"></div>'+
            '<div class="form-group"><label class="form-label">Email</label><input class="form-input" id="op_email" value="'+_esc(order?order.email||'':'')+'"></div>'+
            '</div>'+

            '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:14px;">'+
            '<div class="form-group"><label class="form-label">Менеджер</label><input class="form-input" id="op_manager" value="'+_esc(order?order.manager||'':this._settings.default_manager||'')+'"></div>'+
            '<div class="form-group"><label class="form-label">Приоритет</label>'+
            '<select class="form-select" id="op_priority">'+
            ['normal','high','urgent'].map(function(p){var l={normal:'Обычный',high:'Важный',urgent:'Срочный'};return '<option value="'+p+'" '+(order&&order.priority===p?'selected':'')+'>'+l[p]+'</option>';}).join('')+
            '</select></div>'+
            '<div class="form-group"><label class="form-label">Скидка %</label><input class="form-input" type="number" id="op_discount" min="0" max="100" value="'+(order?order.discount||0:0)+'" oninput="_op(\'_updateTotalDisplay\')"></div>'+
            '</div>'+

            '<div class="section-label">Вид услуги</div>'+
            '<div class="order-service-tabs" id="op_svc_tabs">'+
            Object.entries(this.KB_SVC_LABELS).map(function(e){
                var svc=e[0]; var lbl=e[1];
                var active=(order?order.service||'photo':'photo')===svc?'active':'';
                return '<button class="order-service-tab '+active+'" onclick="_op(\'_switchSvc\',\''+svc+'\',this)">'+lbl+'</button>';
            }).join('')+
            '</div>'+
            '<div id="op_svc_params"></div><hr class="sep">'+

            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">'+
            '<div class="form-group"><label class="form-label">Статус</label>'+
            '<select class="form-select" id="op_status">'+
            this.KB_STATUSES.map(function(s){return '<option value="'+s+'" '+(order&&order.status===s?'selected':'')+'>'+self.KB_STATUS_LABELS[s]+'</option>';}).join('')+
            '</select></div>'+
            '<div class="form-group"><label class="form-label">Источник</label>'+
            '<select class="form-select" id="op_source">'+
            ['crm','website','telegram','vk','phone','email','walk-in'].map(function(s){return '<option value="'+s+'" '+(order&&order.source===s?'selected':'')+'>'+s+'</option>';}).join('')+
            '</select></div>'+
            '</div>'+

            '<div class="form-group" style="margin-bottom:14px;"><label class="form-label">Комментарий</label>'+
            '<textarea class="form-textarea" id="op_comment" rows="2" placeholder="Пожелания клиента...">'+_esc(order?order.comment||'':'')+'</textarea></div>'+

            '<div class="section-label">Файлы и макеты <span style="font-size:0.65rem;color:var(--text-muted);">(до 10 файлов, до 50МБ каждый)</span></div>'+
            '<div id="op_files_zone" style="border:2px dashed var(--border);border-radius:10px;padding:14px;margin-bottom:10px;cursor:pointer;transition:all 0.2s;" '+
            'ondragover="event.preventDefault();this.style.borderColor=\'var(--accent)\'" '+
            'ondragleave="this.style.borderColor=\'var(--border)\'" '+
            'ondrop="_op(\'_handleFileDrop\',event)">'+
            '<div style="text-align:center;color:var(--text-muted);font-size:0.8rem;">Перетащите файлы или '+
            '<label style="color:var(--accent2);cursor:pointer;text-decoration:underline;">выберите'+
            '<input type="file" multiple id="op_files_input" style="display:none;" onchange="_op(\'_handleFileInput\',event)" '+
            'accept=".jpg,.jpeg,.png,.gif,.pdf,.ai,.cdr,.eps,.tif,.tiff,.psd,.doc,.docx,.xlsx,.zip">'+
            '</label></div>'+
            '</div>'+
            '<div id="op_files_list" style="margin-bottom:10px;"></div>'+

            (isEdit&&order&&order.files&&order.files.length?
                '<div style="margin-bottom:14px;"><div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:6px;">Загруженные файлы</div>'+
                '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:5px;">'+
                order.files.map(function(f){return self._renderFileThumb(f,false);}).join('')+
                '</div></div>':'')+

            // ── БЛОК РАСЧЁТА СУММЫ ──
            '<div style="background:linear-gradient(135deg,rgba(124,58,237,0.1),rgba(6,182,212,0.06));border:1px solid rgba(124,58,237,0.2);border-radius:12px;padding:16px;margin-bottom:14px;">'+
            '<div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:12px;">Расчёт стоимости</div>'+
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">'+
            '<div><label class="form-label">Сумма '+cur+'</label>'+
            '<input class="form-input" type="number" id="op_total" value="'+(order?order.total||0:0)+'" oninput="_op(\'_updateTotalDisplay\')"></div>'+
            '<div><label class="form-label">Предоплата '+cur+'</label>'+
            '<input class="form-input" type="number" id="op_prepay" value="'+(order?order.prepay||0:0)+'" oninput="_op(\'_updateTotalDisplay\')"></div>'+
            '</div>'+
            '<div style="text-align:center;padding:10px;background:rgba(0,0,0,0.2);border-radius:10px;margin-bottom:12px;">'+
            '<div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">К ОПЛАТЕ</div>'+
            '<div style="font-size:2rem;font-weight:900;color:var(--accent2);" id="op_total_display">'+this._money(order?order.total||0:0,cur)+'</div>'+
            '<div style="font-size:0.72rem;margin-top:3px;" id="op_total_hint"></div>'+
            '</div>'+
            // Статус оплаты
            '<div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px;">Статус оплаты</div>'+
            '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;">'+
            '<button id="op_ps_none" onclick="_op(\'_setLocalPayStatus\',\'none\')" style="padding:9px;border-radius:8px;border:2px solid '+(ps==='none'?'rgba(100,116,139,0.8)':'rgba(100,116,139,0.25)')+';background:'+(ps==='none'?'rgba(100,116,139,0.2)':'transparent')+';color:'+(ps==='none'?'var(--text-primary)':'var(--text-muted)')+';font-size:0.8rem;font-weight:700;cursor:pointer;">Не оплачен</button>'+
            '<button id="op_ps_prepay" onclick="_op(\'_setLocalPayStatus\',\'prepay\')" style="padding:9px;border-radius:8px;border:2px solid '+(ps==='prepay'?'rgba(245,158,11,0.8)':'rgba(245,158,11,0.25)')+';background:'+(ps==='prepay'?'rgba(245,158,11,0.15)':'transparent')+';color:'+(ps==='prepay'?'#fbbf24':'var(--text-muted)')+';font-size:0.8rem;font-weight:700;cursor:pointer;">◑ Предоплачен</button>'+
            '<button id="op_ps_paid" onclick="_op(\'_setLocalPayStatus\',\'paid\')" style="padding:9px;border-radius:8px;border:2px solid '+(ps==='paid'?'rgba(16,185,129,0.8)':'rgba(16,185,129,0.25)')+';background:'+(ps==='paid'?'rgba(16,185,129,0.15)':'transparent')+';color:'+(ps==='paid'?'#34d399':'var(--text-muted)')+';font-size:0.8rem;font-weight:700;cursor:pointer;">✓ Оплачен</button>'+
            '</div>'+
            '<div style="margin-top:8px;font-size:0.68rem;color:var(--text-muted);padding:6px 8px;background:rgba(0,0,0,0.15);border-radius:6px;">'+
            '💡 Статус только для внутреннего учёта. Оплату проводите через кассу.'+
            '</div>'+
            '</div>'+

            '</div>'+
            '<div class="modal-footer" style="position:sticky;bottom:0;background:#0f172a;border-top:1px solid #1e293b;padding:10px 20px;display:flex;gap:6px;flex-wrap:wrap;">'+
            '<button class="btn btn-secondary" onclick="_op(\'_closeOrderModal\')">Отмена</button>'+
            '<button class="btn btn-secondary" onclick="_op(\'_printBlank\')">'+this._svg('print',12)+' Бланк</button>'+
            '<button class="btn btn-secondary" onclick="_op(\'_aiAnalyzeModal\')">'+this._svg('ai',12)+' ИИ</button>'+
            '<button class="btn btn-primary" id="op_save_btn" onclick="_op(\'saveOrder\')">'+
            (isEdit?'Сохранить':'Создать заказ')+
            '</button>'+
            '</div>'+
            '</div></div>';

        document.getElementById('op_order_modal')?.remove();
        document.body.insertAdjacentHTML('beforeend',html);
        document.getElementById('op_order_modal').classList.add('open');

        this._currentSvc=(order&&order.service)?order.service:'photo';
        this._currentOptions=(order&&order.options)?order.options.slice():[];
        this._existingFiles=(order&&order.files)?order.files.slice():[];
        this._localPayStatus=ps;
        this._renderSvcParams(this._currentSvc,(order&&order.extraFields)?order.extraFields:{});
        this._updateTotalDisplay();

        if(order&&order.client_id) this._showClientAvatar(order.client_id);
        var modal=document.getElementById('op_order_modal');
        modal.addEventListener('click',function(e){if(e.target===modal) self._closeOrderModal();});
    },

    // Локальный статус оплаты в форме заказа
    _localPayStatus: 'none',
    _setLocalPayStatus: function(ps) {
        this._localPayStatus=ps;
        var styles={
            none:   {border:'rgba(100,116,139,0.8)',bg:'rgba(100,116,139,0.2)',color:'var(--text-primary)'},
            prepay: {border:'rgba(245,158,11,0.8)',  bg:'rgba(245,158,11,0.15)', color:'#fbbf24'},
            paid:   {border:'rgba(16,185,129,0.8)',  bg:'rgba(16,185,129,0.15)', color:'#34d399'},
        };
        ['none','prepay','paid'].forEach(function(k){
            var btn=document.getElementById('op_ps_'+k);
            if(!btn) return;
            var s=styles[k];
            btn.style.borderColor=(k===ps?s.border:(k==='none'?'rgba(100,116,139,0.25)':k==='prepay'?'rgba(245,158,11,0.25)':'rgba(16,185,129,0.25)'));
            btn.style.background=(k===ps?s.bg:'transparent');
            btn.style.color=(k===ps?s.color:'var(--text-muted)');
        });
    },

    _autoFillClient: function(val) {
        if(!val||val.length<2) return;
        var db=CRM._getCache(); var allClients=(db.clients||[]).slice();
        var cpMod=window.CRM&&window.CRM.modules&&window.CRM.modules['clients_pro'];
        if(cpMod&&cpMod._clients){
            cpMod._clients.forEach(function(c){
                var exists=allClients.some(function(x){return(x.name||'').toLowerCase()===(c.name||'').toLowerCase();});
                if(!exists) allClients.push(c);
            });
        }
        var found=allClients.find(function(c){return(c.name||'').toLowerCase()===val.toLowerCase();});
        if(!found) return;
        var phone=document.getElementById('op_phone'); var email=document.getElementById('op_email');
        var disc=document.getElementById('op_discount'); var cidEl=document.getElementById('op_client_id');
        if(phone&&!phone.value&&found.phone) phone.value=found.phone;
        if(email&&!email.value&&found.email) email.value=found.email;
        if(disc&&found.discount) disc.value=found.discount;
        if(cidEl&&found.id) cidEl.value=found.id;
        this._updateTotalDisplay();
        if(found.id) this._showClientAvatar(found.id);
    },

    _showClientAvatar: function(clientId) {
        var cpMod=window.CRM&&window.CRM.modules&&window.CRM.modules['clients_pro'];
        if(!cpMod||!cpMod._clients) return;
        var cl=cpMod._clients.find(function(c){return String(c.id)===String(clientId);});
        if(!cl) return;
        var av=cl.vk_avatar||cl.avatar_url;
        var el=document.getElementById('op_client_avatar');
        if(!el||!av) return;
        el.style.display='block';
        el.innerHTML='<img src="'+av+'" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.style.display=\'none\'">';
    },

    _closeOrderModal: function() {
        document.getElementById('op_order_modal')?.remove();
        this._editId=null; this._currentFiles=[]; this._existingFiles=[];
        this._localPayStatus='none';
    },

    _switchSvc: function(svc,btn) {
        this._currentSvc=svc; this._currentOptions=[];
        document.querySelectorAll('#op_svc_tabs .order-service-tab').forEach(function(b){b.classList.remove('active');});
        if(btn) btn.classList.add('active');
        this._renderSvcParams(svc,{});
    },

    _renderSvcParams: function(svc,extra) {
        var el=document.getElementById('op_svc_params'); if(!el) return;
        extra=extra||{}; var self=this;
        var sm=function(sizes,type){
            return '<div class="size-matrix">'+sizes.map(function(s){
                return '<button class="size-btn '+(extra[type+'_size']===s?'selected':'')+'" onclick="_op(\'_selSize\',this,\''+type+'\',\''+s+'\')">'+s+'</button>';
            }).join('')+'</div>';
        };
        var cg=function(items){
            return '<div class="checkbox-group">'+items.map(function(label){
                var checked=self._currentOptions&&self._currentOptions.indexOf(label)!==-1;
                return '<label class="checkbox-item '+(checked?'checked':'')+'" onclick="_op(\'_toggleOpt\',this,\''+label.replace(/'/g,"\\'")+'\')"><span class="checkbox-dot">'+(checked?'✓':'')+'</span>'+label+'</label>';
            }).join('')+'</div>';
        };
        var tpls={
            photo:'<div class="section-label">Фотопечать</div>'+sm(['10×15','13×18','15×21','20×30','21×30 (А4)','30×40','30×45','40×60','50×70','60×90','Свой'],'photo')+
                '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:10px;">'+
                '<div class="form-group"><label class="form-label">Кол-во</label><input class="form-input" type="number" id="op_photo_qty" value="'+(extra.photo_qty||1)+'" oninput="_op(\'_calcTotal\')"></div>'+
                '<div class="form-group"><label class="form-label">Материал</label><select class="form-select" id="op_photo_material">'+['Глянец','Матовый','Холст','Шёлк'].map(function(m){return '<option '+(extra.photo_material===m?'selected':'')+'>'+m+'</option>';}).join('')+'</select></div>'+
                '<div class="form-group"><label class="form-label">Цена/шт</label><input class="form-input" type="number" id="op_photo_price" value="'+(extra.photo_price||0)+'" oninput="_op(\'_calcTotal\')"></div>'+
                '</div>'+cg(['Ламинация','Обрезка','Рамка','Коллаж','Ретушь','Ч/Б','Срочно ×2']),
            copy:'<div class="section-label">Копирование</div>'+sm(['А6','А5','А4','А3','А2','А1','А0','Свой'],'copy')+
                '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:10px;">'+
                '<div class="form-group"><label class="form-label">Листов</label><input class="form-input" type="number" id="op_copy_qty" value="'+(extra.copy_qty||1)+'" oninput="_op(\'_calcTotal\')"></div>'+
                '<div class="form-group"><label class="form-label">Стороны</label><select class="form-select" id="op_copy_sides">'+['Одностороннее','Двустороннее'].map(function(s){return '<option '+(extra.copy_sides===s?'selected':'')+'>'+s+'</option>';}).join('')+'</select></div>'+
                '<div class="form-group"><label class="form-label">Цена/лист</label><input class="form-input" type="number" id="op_copy_price" value="'+(extra.copy_price||0)+'" oninput="_op(\'_calcTotal\')"></div>'+
                '</div>'+cg(['Цветная','Ч/Б','Плотная бумага','Переплёт пружина','Переплёт клей','Ламинация','Срочно ×2']),
            banner:'<div class="section-label">Баннер</div>'+sm(['0.5×1','1×2','1×3','1×4','1×5','2×3','2×5','3×6','Свой'],'banner')+
                '<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-top:10px;">'+
                '<div class="form-group"><label class="form-label">Ширина м</label><input class="form-input" type="number" id="op_ban_w" value="'+(extra.ban_w||'')+'" step="0.1" oninput="_op(\'_calcBanner\')"></div>'+
                '<div class="form-group"><label class="form-label">Высота м</label><input class="form-input" type="number" id="op_ban_h" value="'+(extra.ban_h||'')+'" step="0.1" oninput="_op(\'_calcBanner\')"></div>'+
                '<div class="form-group"><label class="form-label">Площадь м²</label><input class="form-input" id="op_ban_area" readonly value="'+(extra.ban_area||'')+'" style="opacity:0.6;"></div>'+
                '<div class="form-group"><label class="form-label">Цена м²</label><input class="form-input" type="number" id="op_ban_price" value="'+(extra.ban_price||0)+'" oninput="_op(\'_calcBanner\')"></div>'+
                '</div>'+
                '<div class="form-group"><label class="form-label">Кол-во</label><input class="form-input" type="number" id="op_ban_qty" value="'+(extra.ban_qty||1)+'" style="max-width:80px;" oninput="_op(\'_calcBanner\')"></div>'+
                cg(['Люверсы','Усиленный кант','Монтаж','Дизайн макета','Frontlit','Backlit','Срочно']),
            design:'<div class="section-label">Дизайн</div>'+
                cg(['Разработка макета','Правки макета','Логотип','Визитка','Листовка','Буклет','Плакат','Брендбук','Соцсети'])+
                '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:10px;">'+
                '<div class="form-group"><label class="form-label">Правок</label><input class="form-input" type="number" id="op_des_revisions" value="'+(extra.des_revisions||2)+'"></div>'+
                '<div class="form-group"><label class="form-label">Стоимость</label><input class="form-input" type="number" id="op_des_price" value="'+(extra.des_price||0)+'" oninput="_op(\'_calcTotal\')"></div>'+
                '<div class="form-group"><label class="form-label">Формат</label><select class="form-select" id="op_des_format">'+['AI','CDR','PSD','PDF','PNG','SVG'].map(function(f){return '<option '+(extra.des_format===f?'selected':'')+'>'+f+'</option>';}).join('')+'</select></div>'+
                '</div>',
            business:'<div class="section-label">Бизнес-печать</div>'+
                cg(['Визитки','Листовки','Буклеты','Брошюры','Каталоги','Плакаты','Наклейки','Бланки','Конверты'])+
                '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:10px;">'+
                '<div class="form-group"><label class="form-label">Тираж</label><input class="form-input" type="number" id="op_biz_qty" value="'+(extra.biz_qty||100)+'" oninput="_op(\'_calcTotal\')"></div>'+
                '<div class="form-group"><label class="form-label">Формат</label><select class="form-select" id="op_biz_size">'+['90×50 (Визитка)','А6','А5','А4','А3','Евро','Свой'].map(function(s){return '<option '+(extra.biz_size===s?'selected':'')+'>'+s+'</option>';}).join('')+'</select></div>'+
                '<div class="form-group"><label class="form-label">Цена/шт</label><input class="form-input" type="number" id="op_biz_price" value="'+(extra.biz_price||0)+'" oninput="_op(\'_calcTotal\')"></div>'+
                '</div>'+cg(['Ламинация глянец','Ламинация матовая','Скругление','Тиснение','УФ-лак','Биговка','Срочно']),
            wide:'<div class="section-label">Широкоформатная</div>'+
                cg(['Фотообои','Холст','Roll-Up','Pop-Up','Наклейки (плёнка)','Витражная плёнка','Пенокартон','ПВХ-плата'])+
                '<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-top:10px;">'+
                '<div class="form-group"><label class="form-label">Ширина см</label><input class="form-input" type="number" id="op_wide_w" value="'+(extra.wide_w||'')+'" oninput="_op(\'_calcWide\')"></div>'+
                '<div class="form-group"><label class="form-label">Высота см</label><input class="form-input" type="number" id="op_wide_h" value="'+(extra.wide_h||'')+'" oninput="_op(\'_calcWide\')"></div>'+
                '<div class="form-group"><label class="form-label">Площадь м²</label><input class="form-input" id="op_wide_area" readonly value="'+(extra.wide_area||'')+'" style="opacity:0.6;"></div>'+
                '<div class="form-group"><label class="form-label">Цена м²</label><input class="form-input" type="number" id="op_wide_price" value="'+(extra.wide_price||0)+'" oninput="_op(\'_calcWide\')"></div>'+
                '</div>',
            promo:'<div class="section-label">Сувенирная продукция</div>'+
                cg(['Кружка с фото','Подушка','Футболка','Холст','Пазл','Фотокнига','Фотомагнит','Чехол','Постер'])+
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;">'+
                '<div class="form-group"><label class="form-label">Кол-во</label><input class="form-input" type="number" id="op_promo_qty" value="'+(extra.promo_qty||1)+'" oninput="_op(\'_calcTotal\')"></div>'+
                '<div class="form-group"><label class="form-label">Цена/шт</label><input class="form-input" type="number" id="op_promo_price" value="'+(extra.promo_price||0)+'" oninput="_op(\'_calcTotal\')"></div>'+
                '</div>',
            other:'<div class="section-label">Прочие услуги</div>'+
                '<div class="form-group"><label class="form-label">Описание</label><textarea class="form-textarea" id="op_other_desc" placeholder="Опишите услугу...">'+_esc(extra.other_desc||'')+'</textarea></div>'+
                '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">'+
                '<div class="form-group"><label class="form-label">Кол-во</label><input class="form-input" type="number" id="op_other_qty" value="'+(extra.other_qty||1)+'" oninput="_op(\'_calcTotal\')"></div>'+
                '<div class="form-group"><label class="form-label">Стоимость</label><input class="form-input" type="number" id="op_other_price" value="'+(extra.other_price||0)+'" oninput="_op(\'_calcTotal\')"></div>'+
                '</div>',
        };
        el.innerHTML=tpls[svc]||tpls.other;
    },

    _selSize: function(btn,type,size){
        var p=btn.closest('.size-matrix');
        if(p) p.querySelectorAll('.size-btn').forEach(function(b){b.classList.remove('selected');});
        btn.classList.add('selected');
        if(type==='banner'){var m=size.match(/([\d.]+)×([\d.]+)/);if(m){var w=document.getElementById('op_ban_w');var h=document.getElementById('op_ban_h');if(w)w.value=m[1];if(h)h.value=m[2];this._calcBanner();}}
    },
    _toggleOpt: function(label,text){
        label.classList.toggle('checked');
        var dot=label.querySelector('.checkbox-dot');var on=label.classList.contains('checked');
        if(dot)dot.textContent=on?'✓':'';
        if(!this._currentOptions)this._currentOptions=[];
        if(on){if(this._currentOptions.indexOf(text)===-1)this._currentOptions.push(text);}
        else{this._currentOptions=this._currentOptions.filter(function(o){return o!==text;});}
    },
    _calcTotal: function(){
        var s=this._currentSvc;
        var gv=function(id){var el=document.getElementById(id);return el?parseFloat(el.value)||0:0;};
        var gi=function(id){var el=document.getElementById(id);return el?parseInt(el.value)||0:0;};
        var t=0;
        if(s==='photo')t=gi('op_photo_qty')*gv('op_photo_price');
        else if(s==='copy')t=gi('op_copy_qty')*gv('op_copy_price');
        else if(s==='business')t=gi('op_biz_qty')*gv('op_biz_price');
        else if(s==='promo')t=gi('op_promo_qty')*gv('op_promo_price');
        else if(s==='other')t=gi('op_other_qty')*gv('op_other_price');
        else if(s==='design')t=gv('op_des_price');
        if(t>0){var el=document.getElementById('op_total');if(el)el.value=t.toFixed(0);this._updateTotalDisplay();}
    },
    _calcBanner: function(){
        var w=parseFloat(document.getElementById('op_ban_w')?.value)||0;
        var h=parseFloat(document.getElementById('op_ban_h')?.value)||0;
        var p=parseFloat(document.getElementById('op_ban_price')?.value)||0;
        var q=parseInt(document.getElementById('op_ban_qty')?.value)||1;
        var area=(w*h).toFixed(2);
        var aEl=document.getElementById('op_ban_area');if(aEl)aEl.value=area;
        var t=document.getElementById('op_total');if(t)t.value=(parseFloat(area)*p*q).toFixed(0);
        this._updateTotalDisplay();
    },
    _calcWide: function(){
        var w=(parseFloat(document.getElementById('op_wide_w')?.value)||0)/100;
        var h=(parseFloat(document.getElementById('op_wide_h')?.value)||0)/100;
        var p=parseFloat(document.getElementById('op_wide_price')?.value)||0;
        var area=(w*h).toFixed(4);
        var aEl=document.getElementById('op_wide_area');if(aEl)aEl.value=parseFloat(area).toFixed(2);
        var t=document.getElementById('op_total');if(t)t.value=(parseFloat(area)*p).toFixed(0);
        this._updateTotalDisplay();
    },
    _updateTotalDisplay: function(){
        var total=parseFloat(document.getElementById('op_total')?.value)||0;
        var prepay=parseFloat(document.getElementById('op_prepay')?.value)||0;
        var disc=parseInt(document.getElementById('op_discount')?.value)||0;
        var cur=CRM.getSettings().currency||'₽';
        var el=document.getElementById('op_total_display');
        var hint=document.getElementById('op_total_hint');
        var disp=disc>0?total*(1-disc/100):total;
        if(el){
            el.textContent=this._money(disp,cur);
            if(disc>0) el.textContent+=' (скидка -'+disc+'%)';
        }
        if(hint){
            var parts=[];
            if(disc>0) parts.push('<span style="color:var(--accent3);">Скидка: -'+disc+'%</span>');
            if(prepay>0) parts.push('<span style="color:var(--accent4);">Предоплата: '+this._money(prepay,cur)+'</span>');
            if(prepay>0&&disp>prepay) parts.push('<span style="color:#fbbf24;">Остаток: '+this._money(disp-prepay,cur)+'</span>');
            hint.innerHTML=parts.join(' &nbsp;·&nbsp; ');
        }
    },
    _collectExtra: function(){
        var s=this._currentSvc;
        var gv=function(id){var el=document.getElementById(id);return el?el.value:'';};
        var gi=function(id){var el=document.getElementById(id);return el?parseInt(el.value)||0:0;};
        var gf=function(id){var el=document.getElementById(id);return el?parseFloat(el.value)||0:0;};
        var gs=function(id){var el=document.getElementById(id);return el?(el.options?el.options[el.selectedIndex]?.value||el.value:el.value):'';};
        var ss=function(){var el=document.querySelector('#op_svc_params .size-btn.selected');return el?el.textContent.trim():'';};
        var x={};
        if(s==='photo'){x.photo_size=ss();x.photo_qty=gi('op_photo_qty');x.photo_material=gs('op_photo_material');x.photo_price=gf('op_photo_price');}
        else if(s==='copy'){x.copy_size=ss();x.copy_qty=gi('op_copy_qty');x.copy_sides=gs('op_copy_sides');x.copy_price=gf('op_copy_price');}
        else if(s==='banner'){x.ban_w=gv('op_ban_w');x.ban_h=gv('op_ban_h');x.ban_area=gv('op_ban_area');x.ban_price=gf('op_ban_price');x.ban_qty=gi('op_ban_qty');}
        else if(s==='wide'){x.wide_w=gv('op_wide_w');x.wide_h=gv('op_wide_h');x.wide_area=gv('op_wide_area');x.wide_price=gf('op_wide_price');}
        else if(s==='business'){x.biz_qty=gi('op_biz_qty');x.biz_size=gs('op_biz_size');x.biz_price=gf('op_biz_price');}
        else if(s==='design'){x.des_revisions=gi('op_des_revisions');x.des_price=gf('op_des_price');x.des_format=gs('op_des_format');}
        else if(s==='promo'){x.promo_qty=gi('op_promo_qty');x.promo_price=gf('op_promo_price');}
        else if(s==='other'){x.other_desc=gv('op_other_desc');x.other_qty=gi('op_other_qty');x.other_price=gf('op_other_price');}
        return x;
    },

    // ── ФАЙЛЫ ─────────────────────────────────────────────────
    _handleFileDrop: function(e){e.preventDefault();e.currentTarget.style.borderColor='var(--border)';this._addFiles(Array.from(e.dataTransfer.files));},
    _handleFileInput: function(e){this._addFiles(Array.from(e.target.files));e.target.value='';},
    _addFiles: function(files){
        var self=this;
        var MAX=50*1024*1024;
        var ok=/\.(jpg|jpeg|png|gif|pdf|ai|cdr|eps|tif|tiff|psd|doc|docx|xlsx|zip)$/i;
        var currentTotal=(this._existingFiles||[]).length+(this._currentFiles||[]).length;
        files.forEach(function(f){
            if(currentTotal>=10){notify('Максимум 10 файлов на заказ','error');return;}
            if(f.size>MAX){notify('Файл '+f.name+' > 50МБ','error');return;}
            if(!ok.test(f.name)){notify('Формат не поддерживается: '+f.name,'error');return;}
            self._currentFiles.push(f); currentTotal++;
        });
        this._renderFilesList();
    },
    _renderFilesList: function(){
        var self=this;var el=document.getElementById('op_files_list');if(!el)return;
        if(!this._currentFiles.length){el.innerHTML='';return;}
        var total=(this._existingFiles||[]).length+this._currentFiles.length;
        el.innerHTML='<div style="font-size:0.68rem;color:var(--text-muted);margin-bottom:6px;">Новых файлов: '+this._currentFiles.length+' / Всего: '+total+'/10</div>'+
            this._currentFiles.map(function(f,i){
                var isImg=f.type&&f.type.startsWith('image/');
                var obj=isImg?URL.createObjectURL(f):null;
                return '<div style="background:var(--bg-dark);border-radius:7px;padding:6px 10px;display:flex;align-items:center;gap:7px;border:1px solid var(--border);margin-bottom:3px;">'+
                    '<span>'+(isImg?'🖼️':'📄')+'</span>'+
                    '<div style="flex:1;overflow:hidden;"><div style="font-size:0.76rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'+_esc(f.name)+'</div><div style="font-size:0.62rem;color:var(--text-muted);">'+self._formatSize(f.size)+'</div></div>'+
                    (obj?'<img src="'+obj+'" style="width:32px;height:32px;object-fit:cover;border-radius:4px;">':'')+
                    '<button class="btn btn-danger btn-xs" onclick="_op(\'_removeFile\','+i+')">✕</button>'+
                    '</div>';
            }).join('');
    },
    _removeFile: function(idx){this._currentFiles.splice(idx,1);this._renderFilesList();},

    _renderFileThumb: function(f,withDownload){
        var isImg=(f.type&&f.type.startsWith('image/'))||/\.(jpg|jpeg|png|gif|webp)$/i.test(f.name||'');
        var downloadBtn='';
        if(withDownload&&f.url){
            downloadBtn='<a href="'+f.url+'" download="'+_esc(f.name||'file')+'" target="_blank" style="display:flex;align-items:center;justify-content:center;gap:3px;padding:3px 6px;background:rgba(124,58,237,0.25);border:1px solid rgba(124,58,237,0.4);border-radius:0 0 6px 6px;font-size:0.56rem;color:#a78bfa;text-decoration:none;" title="Скачать">'+
                '<svg width="9" height="9" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Скачать</a>';
        }
        return '<div style="border-radius:7px;overflow:hidden;border:1px solid var(--border);background:var(--bg-dark);">'+
            (isImg&&f.url?'<img src="'+f.url+'" style="width:100%;height:60px;object-fit:cover;display:block;" onerror="this.style.display=\'none\'">':'<div style="height:60px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;">📄</div>')+
            '<div style="padding:2px 4px;font-size:0.56rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+_esc(f.name)+'">'+_esc(f.name)+'</div>'+
            downloadBtn+'</div>';
    },

    _uploadFiles: function(){
        var self=this;var result=[];var chain=Promise.resolve();
        var apiUrl=window.API_URL||(typeof API_URL!=='undefined'?API_URL:'/api/api.php');
        var apiKey=window.API_KEY||(typeof API_KEY!=='undefined'?API_KEY:'12345');
        this._currentFiles.forEach(function(file){
            chain=chain.then(function(){
                var fd=new FormData();fd.append('file',file,file.name);
                return fetch(apiUrl+'?action=upload&key='+apiKey,{method:'POST',body:fd})
                    .then(function(r){return r.json();})
                    .then(function(data){
                        if(data.url||data.file_url) result.push({name:file.name,size:file.size,type:file.type,url:data.url||data.file_url});
                        else notify('Ошибка загрузки '+file.name,'error');
                    }).catch(function(){notify('Ошибка загрузки '+file.name,'error');});
            });
        });
        return chain.then(function(){return result;});
    },

    // ── СОХРАНЕНИЕ ЗАКАЗА ─────────────────────────────────────
    saveOrder: function() {
        var self=this;
        var btn=document.getElementById('op_save_btn');
        if(btn){btn.disabled=true;btn.textContent='⏳...';}
        var gv=function(id){var el=document.getElementById(id);return el?(el.value||'').trim():'';};
        var gs=function(id){var el=document.getElementById(id);return el?(el.options?el.options[el.selectedIndex]?.value||el.value:el.value):'';};
        var isEdit=!!this._editId;

        var doSave=function(uploadedFiles){
            var allFiles=(self._existingFiles||[]).concat(uploadedFiles||[]);
            var data={
                date:gv('op_date'), deadline:gv('op_deadline'),
                client:gv('op_client')||'Без имени',
                client_id:document.getElementById('op_client_id')?.value||null,
                phone:gv('op_phone'), email:gv('op_email'), manager:gv('op_manager'),
                priority:gs('op_priority'), service:self._currentSvc,
                status:gs('op_status'),
                source:gs('op_source'),
                comment:gv('op_comment'), discount:parseInt(gv('op_discount'))||0,
                total:parseFloat(gv('op_total'))||0,
                prepay:parseFloat(gv('op_prepay'))||0,
                pay_status: self._localPayStatus||'none',
                options:self._currentOptions||[],
                extraFields:self._collectExtra(), files:allFiles,
            };
            if(isEdit) data.id=self._editId;

            return CRM.api('order_pro','save',data).then(function(res){
                if(!res||!res.ok){notify('Ошибка: '+(res?res.error:'нет ответа'),'error');return null;}
                if(isEdit){
                    var idx=self._orders.findIndex(function(o){return String(o.id)===String(self._editId);});
                    if(idx!==-1) self._orders[idx]=res.data; else self._orders.unshift(res.data);
                    notify('Заказ обновлён','success');
                } else {
                    self._orders.unshift(res.data);
                    notify('Заказ создан: '+res.data.num,'success');
                }
                self._closeOrderModal();
                self.renderKanban();
                self._checkUrgent();
                if(typeof refreshDashboard==='function') refreshDashboard();
                return res.data;
            }).finally(function(){
                if(btn){btn.disabled=false;btn.textContent=isEdit?'Сохранить':'Создать заказ';}
            });
        };

        if(this._currentFiles.length){notify('⏳ Загрузка файлов...','info');this._uploadFiles().then(doSave);}
        else doSave([]);
    },

    // ── ДЕТАЛЬНЫЙ ПРОСМОТР ────────────────────────────────────
    openDetail: function(e, id) {
        if(e) e.stopPropagation();
        var order=this._orders.find(function(o){return String(o.id)===String(id);});
        if(!order) order=this._archive.find(function(o){return String(o.id)===String(id);});
        if(!order) return;

        this._detailId=id;
        var self=this; var cur=CRM.getSettings().currency||'₽';
        var st=order.status||'new';
        var ps=order.pay_status||'none';
       var effectivePaid=Number(order.paid)||0;
if(effectivePaid===0&&order.pay_status==='prepay'&&Number(order.prepay)>0) effectivePaid=Number(order.prepay)||0;
var remaining=Math.max(0,(order.total||0)-effectivePaid);

        var svcIcon={photo:'📸',copy:'🖨️',banner:'🏳️',design:'🎨',business:'💼',wide:'🖼️',promo:'🎁',other:'⚙️'}[order.service]||'📋';
        var stGrad={new:'linear-gradient(135deg,#6366f1,#a78bfa)',work:'linear-gradient(135deg,#f59e0b,#fbbf24)',ready:'linear-gradient(135deg,#10b981,#34d399)',done:'linear-gradient(135deg,#06b6d4,#22d3ee)',cancel:'linear-gradient(135deg,#ef4444,#f87171)'}[st]||'';

        var ex=order.extraFields||{}; var params=[];
        if(ex.photo_size) params.push(ex.photo_size); if(ex.photo_qty) params.push('×'+ex.photo_qty);
        if(ex.ban_w) params.push(ex.ban_w+'×'+ex.ban_h+'м'); if(ex.biz_qty) params.push(ex.biz_qty+' шт');
        (order.options||[]).forEach(function(o){params.push(o);});
        var chips=params.map(function(p){return '<span class="od-chip">'+_esc(p)+'</span>';}).join('');

        // Файлы
        var filesHtml='';
        if(order.files&&order.files.length){
            filesHtml='<div class="od-section-title">'+self._svg('download',11)+' Файлы ('+order.files.length+')</div>'+
                '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(86px,1fr));gap:5px;margin-bottom:14px;">'+
                order.files.map(function(f){return self._renderFileThumb(f,true);}).join('')+'</div>';
        }

        // Бейдж статуса оплаты
        var payStatusHtml='';
        if(ps==='paid') payStatusHtml='<div style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.3);color:#34d399;font-weight:700;font-size:0.82rem;">✓ Оплачен полностью</div>';
        else if(ps==='prepay') payStatusHtml='<div style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.3);color:#fbbf24;font-weight:700;font-size:0.82rem;">◑ Предоплачен · '+self._money(order.prepay||0,cur)+'</div>';
        else payStatusHtml='<div style="display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:8px;background:rgba(100,116,139,0.1);border:1px solid rgba(100,116,139,0.2);color:var(--text-muted);font-weight:700;font-size:0.82rem;">Не оплачен</div>';

        document.getElementById('op_detail_overlay')?.remove();

        var html=
            '<div class="order-detail-overlay" id="op_detail_overlay">'+
            '<div class="order-detail-modal" style="max-width:860px;width:95vw;">'+

            // ── ШАПКА ──
            '<div class="od-header" style="background:linear-gradient(135deg,rgba(124,58,237,0.15),rgba(6,182,212,0.08));border-bottom:1px solid rgba(255,255,255,0.08);padding:20px 24px;display:flex;align-items:center;gap:14px;">'+
            '<div class="od-icon-wrap" style="background:'+stGrad+';width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0;">'+svcIcon+'</div>'+
            '<div style="flex:1;min-width:0;">'+
            '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">'+
            '<div style="font-size:1.15rem;font-weight:800;">'+_esc(order.num||'#—')+'</div>'+
            (order.archived?'<span style="font-size:0.62rem;padding:2px 8px;border-radius:10px;background:rgba(100,116,139,0.2);color:var(--text-muted);">Архив</span>':'')+
            (order.priority==='urgent'?'<span style="font-size:0.62rem;padding:2px 8px;border-radius:10px;background:rgba(239,68,68,0.2);color:#f87171;font-weight:700;">🔥 Срочно</span>':'')+
            (order.priority==='high'?'<span style="font-size:0.62rem;padding:2px 8px;border-radius:10px;background:rgba(245,158,11,0.2);color:#fbbf24;font-weight:700;">⚡ Важно</span>':'')+
            '</div>'+
            '<div style="font-size:1rem;font-weight:600;color:var(--text-primary);margin-top:2px;">'+_esc(order.client||'Без имени')+'</div>'+
            '<div style="font-size:0.75rem;color:var(--text-muted);margin-top:2px;">'+
            (this.KB_SVC_LABELS[order.service]||order.service)+' &bull; '+this._dateShort(order.date)+
            (order.discount>0?' &bull; <span style="color:var(--accent3);">Скидка -'+order.discount+'%</span>':'')+
            '</div>'+
            '</div>'+
            '<button class="od-close" style="flex-shrink:0;" onclick="document.getElementById(\'op_detail_overlay\').remove()">✕</button>'+
            '</div>'+

            // ── СТАТУС БАР ──
            '<div style="padding:14px 24px;border-bottom:1px solid rgba(255,255,255,0.06);background:rgba(0,0,0,0.15);">'+
            '<div style="display:flex;gap:4px;flex-wrap:wrap;">'+
            this.KB_STATUSES.map(function(s){
                var isActive=s===st;
                var colors={new:'#6366f1',work:'#f59e0b',ready:'#10b981',done:'#06b6d4',cancel:'#ef4444'};
                var c=colors[s]||'var(--accent)';
                return '<button onclick="_op(\'changeStatus\',\''+String(order.id)+'\',\''+s+'\',null);setTimeout(function(){_op(\'_refreshDetail\')},300)" '+
                    'style="padding:7px 14px;border-radius:8px;border:1px solid '+(isActive?c:'rgba(255,255,255,0.1)')+';background:'+(isActive?'rgba('+c+',0.2)':'transparent')+';color:'+(isActive?c:'var(--text-muted)')+';font-size:0.78rem;font-weight:'+(isActive?'700':'400')+';cursor:pointer;transition:all 0.15s;">'+
                    self.KB_STATUS_LABELS[s]+'</button>';
            }).join('')+
            '</div>'+
            '</div>'+

            // ── ТЕЛО: 2 КОЛОНКИ ──
            '<div style="display:grid;grid-template-columns:1fr 320px;gap:0;overflow:hidden;">'+

            // ЛЕВАЯ КОЛОНКА
            '<div style="padding:20px 24px;overflow-y:auto;max-height:calc(80vh - 180px);border-right:1px solid rgba(255,255,255,0.06);">'+

            // Финансы
            '<div style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;margin-bottom:16px;">'+
            '<div style="font-size:0.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">'+self._svg('pay',11)+' Стоимость</div>'+
            '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px;">'+
            self._infoBox('ИТОГО',      self._money(order.total||0,cur),  '')+
            self._infoBox('ПРЕДОПЛАТА', self._money(order.prepay||0,cur), 'var(--accent4)')+
            self._infoBox('ОСТАТОК',    self._money(remaining,cur),       remaining>0?'#fbbf24':'var(--accent3)')+
            '</div>'+
            '<div style="margin-bottom:8px;">'+payStatusHtml+'</div>'+
            '<div style="font-size:0.68rem;color:var(--text-muted);">Оплата проводится через кассу. Статус — внутренний учёт.</div>'+
            '</div>'+

            // Детали
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;">'+
            self._detailInfoBlock(self._svg('clock',11)+' Принят', self._dateShort(order.date))+
            self._detailInfoBlock(self._svg('warn',11)+' Дедлайн',
                order.deadline?('<span style="color:'+self._deadlineColor(order.deadline)+'">' + self._dateShort(order.deadline)+'</span>'):'—')+
            self._detailInfoBlock(self._svg('user',11)+' Менеджер', _esc(order.manager||'—'))+
            self._detailInfoBlock('📱 Телефон', _esc(order.phone||'—'))+
            (order.email?self._detailInfoBlock('✉️ Email', _esc(order.email)):'') +
            self._detailInfoBlock('📌 Источник', _esc(order.source||'crm'))+
            '</div>'+

            (chips?'<div style="font-size:0.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px;">Параметры</div><div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:14px;">'+chips+'</div>':'')+
            filesHtml+
            (order.comment?'<div style="background:rgba(124,58,237,0.07);border:1px solid rgba(124,58,237,0.15);border-radius:10px;padding:10px 12px;font-size:0.8rem;line-height:1.55;margin-bottom:12px;"><span style="font-size:0.65rem;font-weight:700;color:var(--accent);display:block;margin-bottom:4px;">КОММЕНТАРИЙ</span>'+_esc(order.comment)+'</div>':'')+
            '<div id="op_detail_ai" style="display:none;margin-top:10px;"></div>'+
            '</div>'+

            // ПРАВАЯ КОЛОНКА — клиент
            '<div style="padding:20px;overflow-y:auto;max-height:calc(80vh - 180px);background:rgba(0,0,0,0.12);" id="op_detail_client_col">'+
            '<div style="font-size:0.68rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">'+self._svg('user',11)+' Клиент</div>'+
            self._renderClientSidePanel(order)+
            '</div>'+

            '</div>'+

            // КНОПКИ
            '<div class="od-actions" style="border-top:1px solid rgba(255,255,255,0.08);padding:12px 20px;display:flex;gap:6px;flex-wrap:wrap;background:rgba(0,0,0,0.2);">'+
            '<button class="od-btn od-btn-edit" onclick="_op(\'openOrderModal\',\''+String(order.id)+'\');document.getElementById(\'op_detail_overlay\').remove();">'+self._svg('edit',12)+' Ред.</button>'+
            '<button class="od-btn od-btn-print" onclick="_op(\'_printReceipt\',\''+String(order.id)+'\')">'+self._svg('print',12)+' Чек</button>'+
            '<button class="od-btn" style="background:rgba(6,182,212,0.15);border-color:rgba(6,182,212,0.3);color:#22d3ee;" onclick="_op(\'_printLabel\',\''+String(order.id)+'\')">'+self._svg('label',12)+' Этикетка</button>'+
            '<button class="od-btn" style="background:rgba(6,182,212,0.2);border-color:rgba(6,182,212,0.3);color:#22d3ee;" onclick="_op(\'_generateKP\',\''+String(order.id)+'\')">КП</button>'+
            '<button class="od-btn" style="background:rgba(124,58,237,0.2);border-color:rgba(124,58,237,0.4);color:#a78bfa;" onclick="_op(\'_aiAnalyzeDetail\',\''+String(order.id)+'\')">'+self._svg('ai',12)+' ИИ</button>'+
            '<button class="od-btn" style="background:rgba(16,185,129,0.2);border-color:rgba(16,185,129,0.3);color:#34d399;" onclick="_op(\'openPayment\',null,\''+String(order.id)+'\')">'+self._svg('pay',12)+' Оплата</button>'+
            (st!=='done'?'<button class="od-btn od-btn-done" onclick="_op(\'changeStatus\',\''+String(order.id)+'\',\'done\',null);document.getElementById(\'op_detail_overlay\').remove();">Выдать</button>':'')+
            (!order.archived?'<button class="od-btn" style="background:rgba(100,116,139,0.15);color:var(--text-muted);" onclick="_op(\'_archiveOrder\',\''+String(order.id)+'\')">'+self._svg('archive',12)+' Архив</button>':'<button class="od-btn" style="color:#a78bfa;" onclick="_op(\'_unarchiveOrder\',\''+String(order.id)+'\')">'+self._svg('unarchive',12)+' Вернуть</button>')+
            '<button class="od-btn od-btn-delete" onclick="_op(\'_deleteOrder\',\''+String(order.id)+'\')">'+self._svg('trash',12)+' Удалить</button>'+
            '</div>'+

            '</div></div>';

        document.body.insertAdjacentHTML('beforeend',html);
        requestAnimationFrame(function(){document.getElementById('op_detail_overlay')?.classList.add('open');});
        document.getElementById('op_detail_overlay').addEventListener('click',function(e){
            if(e.target.id==='op_detail_overlay') document.getElementById('op_detail_overlay').remove();
        });

        this._loadClientHistory(order);
    },

    _detailInfoBlock: function(label, valueHtml) {
        return '<div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:8px;padding:8px 10px;">'+
            '<div style="font-size:0.58rem;color:var(--text-muted);margin-bottom:3px;">'+label+'</div>'+
            '<div style="font-size:0.8rem;font-weight:600;">'+valueHtml+'</div>'+
            '</div>';
    },

    _renderClientSidePanel: function(order) {
        var self=this;
        var cl=null;
        var cpMod=window.CRM&&window.CRM.modules&&window.CRM.modules['clients_pro'];
        if(cpMod&&cpMod._clients&&order.client_id){
            cl=cpMod._clients.find(function(c){return String(c.id)===String(order.client_id);});
        }
        var av=cl?(cl.vk_avatar||cl.avatar_url||''):'';
        var name=cl?cl.name:(order.client||'Без имени');
        var phone=cl?cl.phone:(order.phone||'');
        var email=cl?cl.email:(order.email||'');

        var clientOrders=this._orders.filter(function(o){
            if(order.client_id&&o.client_id) return String(o.client_id)===String(order.client_id);
            return(o.client||'').toLowerCase()===(order.client||'').toLowerCase();
        });
        var totalSpent=clientOrders.reduce(function(s,o){return s+(Number(o.paid)||0);},0);
        var orderCount=clientOrders.length;
        var loyalty=self._getLoyaltyBadge(orderCount, totalSpent);

        var html=
            '<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">'+
            (av?'<img src="'+av+'" style="width:48px;height:48px;border-radius:12px;object-fit:cover;border:2px solid rgba(124,58,237,0.4);" onerror="this.style.display=\'none\'">':
                '<div style="width:48px;height:48px;border-radius:12px;background:rgba(124,58,237,0.2);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">👤</div>')+
            '<div style="min-width:0;">'+
            '<div style="font-weight:700;font-size:0.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'+_esc(name)+'</div>'+
            (phone?'<div style="font-size:0.72rem;color:var(--text-muted);">'+_esc(phone)+'</div>':'')+
            (email?'<div style="font-size:0.68rem;color:var(--text-muted);">'+_esc(email)+'</div>':'')+
            '</div></div>'+
            '<div style="margin-bottom:12px;">'+
            '<div style="font-size:0.6rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:5px;">Лояльность</div>'+
            '<div style="display:flex;align-items:center;gap:8px;">'+
            '<div style="padding:6px 12px;border-radius:8px;background:'+loyalty.bg+';border:1px solid '+loyalty.border+';color:'+loyalty.color+';font-weight:700;font-size:0.8rem;">'+loyalty.icon+' '+loyalty.label+'</div>'+
            (cl&&cl.discount?'<div style="font-size:0.72rem;color:var(--accent3);">Скидка -'+cl.discount+'%</div>':'')+
            '</div></div>'+
            '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:14px;">'+
            '<div style="background:rgba(124,58,237,0.08);border:1px solid rgba(124,58,237,0.15);border-radius:8px;padding:8px 10px;text-align:center;">'+
            '<div style="font-size:1.1rem;font-weight:800;color:var(--accent2);">'+orderCount+'</div>'+
            '<div style="font-size:0.6rem;color:var(--text-muted);">заказов</div></div>'+
            '<div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.15);border-radius:8px;padding:8px 10px;text-align:center;">'+
            '<div style="font-size:0.85rem;font-weight:800;color:var(--accent3);">'+self._money(totalSpent,CRM.getSettings().currency||'₽')+'</div>'+
            '<div style="font-size:0.6rem;color:var(--text-muted);">потрачено</div></div>'+
            '</div>'+
            (cl&&cl.notes?'<div style="background:rgba(245,158,11,0.07);border:1px solid rgba(245,158,11,0.2);border-radius:8px;padding:8px 10px;margin-bottom:12px;">'+
                '<div style="font-size:0.6rem;color:#fbbf24;text-transform:uppercase;margin-bottom:3px;">'+self._svg('note',9)+' Заметки</div>'+
                '<div style="font-size:0.76rem;line-height:1.5;">'+_esc(cl.notes)+'</div></div>':'')+
            '<div id="op_client_history_block">'+
            '<div style="font-size:0.6rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:5px;">'+self._svg('clock',9)+' История заказов</div>'+
            '<div style="color:var(--text-muted);font-size:0.72rem;padding:8px 0;">Загружаем...</div>'+
            '</div>'+
            (cl?'<button style="width:100%;padding:8px;border-radius:8px;border:1px solid rgba(124,58,237,0.3);background:rgba(124,58,237,0.08);color:#a78bfa;font-size:0.75rem;cursor:pointer;margin-top:6px;" '+
                'onclick="CRM.modules.clients_pro&&CRM.modules.clients_pro.openDetail(\''+String(cl.id)+'\')">'+
                'Открыть профиль клиента →</button>':'');
        return html;
    },

    _getLoyaltyBadge: function(count, spent) {
        if(count===0||spent===0) return {level:'new',label:'Новый',icon:'🌱',color:'#94a3b8',bg:'rgba(148,163,184,0.1)',border:'rgba(148,163,184,0.2)'};
        if(count>=20||spent>=100000) return {level:'vip',label:'VIP',icon:'👑',color:'#fbbf24',bg:'rgba(251,191,36,0.12)',border:'rgba(251,191,36,0.3)'};
        if(count>=10||spent>=50000) return {level:'loyal',label:'Постоянный',icon:'⭐',color:'#a78bfa',bg:'rgba(167,139,250,0.12)',border:'rgba(167,139,250,0.3)'};
        if(count>=5||spent>=20000)  return {level:'regular',label:'Активный',icon:'🔥',color:'#34d399',bg:'rgba(52,211,153,0.1)',border:'rgba(52,211,153,0.25)'};
        return {level:'new_client',label:'Начинающий',icon:'🌟',color:'#60a5fa',bg:'rgba(96,165,250,0.1)',border:'rgba(96,165,250,0.2)'};
    },

    _loadClientHistory: function(order) {
        var self=this;
        var block=document.getElementById('op_client_history_block');
        if(!block) return;
        var params={};
        if(order.client_id) params.client_id=order.client_id;
        else params.client_name=order.client||'';
        CRM.api('order_pro','client_orders',null,params).then(function(res){
            var block=document.getElementById('op_client_history_block');
            if(!block) return;
            var history=(res&&res.data)?res.data:[];
            history=history.filter(function(o){return String(o.id)!==String(order.id);});
            var cur=CRM.getSettings().currency||'₽';
            var histHtml='<div style="font-size:0.6rem;color:var(--text-muted);text-transform:uppercase;margin-bottom:5px;">'+self._svg('clock',9)+' История заказов</div>';
            if(!history.length){
                histHtml+='<div style="font-size:0.72rem;color:var(--text-muted);padding:6px 0;">Других заказов нет</div>';
            } else {
                histHtml+='<div style="display:flex;flex-direction:column;gap:4px;max-height:200px;overflow-y:auto;">'+
                    history.slice(0,8).map(function(o){
                        var stColors={new:'#6366f1',work:'#f59e0b',ready:'#10b981',done:'#06b6d4',cancel:'#ef4444'};
                        var stc=stColors[o.status]||'var(--text-muted)';
                        return '<div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:7px;padding:7px 8px;cursor:pointer;" '+
                            'onclick="document.getElementById(\'op_detail_overlay\').remove();_op(\'openDetail\',null,\''+String(o.id)+'\')">'+
                            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;">'+
                            '<span style="font-size:0.72rem;font-weight:700;">'+_esc(o.num||'')+'</span>'+
                            '<span style="font-size:0.65rem;font-weight:700;color:'+stc+';">'+self.KB_STATUS_LABELS[o.status||'new']+'</span>'+
                            '</div>'+
                            '<div style="display:flex;justify-content:space-between;align-items:center;">'+
                            '<span style="font-size:0.65rem;color:var(--text-muted);">'+(o.serviceLabel||'')+' · '+(o.date?new Date(o.date).toLocaleDateString('ru-RU'):'')+'</span>'+
                            '<span style="font-size:0.72rem;font-weight:700;color:var(--accent2);">'+self._money(o.total||0,cur)+'</span>'+
                            '</div></div>';
                    }).join('')+'</div>';
            }
            block.innerHTML=histHtml;
        });
    },

    _refreshDetail: function() {
        if(this._detailId){
            document.getElementById('op_detail_overlay')?.remove();
            this.openDetail(null,this._detailId);
        }
    },

    _deleteOrder: function(id) {
        var self=this;
        if(!confirm('Удалить заказ?')) return;
        CRM.api('order_pro','delete',null,{id:id}).then(function(){
            self._orders=self._orders.filter(function(o){return String(o.id)!==String(id);});
            self._archive=self._archive.filter(function(o){return String(o.id)!==String(id);});
            document.getElementById('op_detail_overlay')?.remove();
            self.renderKanban(); notify('Заказ удалён','error');
        });
    },

    // ── ПРЕВЬЮ ────────────────────────────────────────────────
    _miniPreview: function(order,height){
        height=height||'70px';
        var firstImg=null;
        if(order.files&&order.files.length){
            for(var i=0;i<order.files.length;i++){
                var f=order.files[i];
                if((f.type&&f.type.startsWith('image/'))||/\.(jpg|jpeg|png|gif|webp)$/i.test(f.name||'')){firstImg=f;break;}
            }
        }
        if(!firstImg&&order.client_id){
            var cpMod=window.CRM&&window.CRM.modules&&window.CRM.modules['clients_pro'];
            if(cpMod&&cpMod._clients){
                var cl=cpMod._clients.find(function(c){return String(c.id)===String(order.client_id);});
                if(cl&&(cl.vk_avatar||cl.avatar_url)){
                    return '<div style="margin-bottom:5px;border-radius:7px;overflow:hidden;height:'+height+';background:var(--bg-dark);position:relative;">'+
                        '<img src="'+(cl.vk_avatar||cl.avatar_url)+'" style="width:100%;height:100%;object-fit:cover;opacity:0.35;" onerror="this.parentElement.style.display=\'none\'">'+
                        '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:0.62rem;color:var(--text-muted);">'+_esc(cl.name||'')+'</div>'+
                        '</div>';
                }
            }
        }
        if(!firstImg||!firstImg.url) return '';
        return '<div style="margin-bottom:5px;border-radius:7px;overflow:hidden;height:'+height+';background:var(--bg-dark);">'+
            '<img src="'+firstImg.url+'" style="width:100%;height:100%;object-fit:cover;" onerror="this.parentElement.style.display=\'none\'">'+
            '</div>';
    },

    // ── ИИ ────────────────────────────────────────────────────
    _aiAnalyzeModal: function(){
        var self=this;
        var total=parseFloat(document.getElementById('op_total')?.value)||0;
        var comment=document.getElementById('op_comment')?.value||'';
        notify('Анализирую...','info');
        CRM.api('order_pro','ai_analyze',{order:{service:self._currentSvc,serviceLabel:self.KB_SVC_LABELS[self._currentSvc]||self._currentSvc,total:total,comment:comment,options:self._currentOptions||[],extraFields:self._collectExtra()}})
            .then(function(res){if(!res||!res.ok){notify('Ошибка ИИ','error');return;}self._showAIModal('ИИ-анализ',res.data);});
    },

    _aiAnalyzeDetail: function(orderId){
        var self=this;
        var order=this._orders.find(function(o){return String(o.id)===String(orderId);});
        if(!order) return;
        var aiEl=document.getElementById('op_detail_ai');
        if(aiEl){aiEl.style.display='block';aiEl.innerHTML='<div style="padding:10px;background:rgba(124,58,237,0.08);border:1px solid rgba(124,58,237,0.2);border-radius:8px;color:var(--text-muted);font-size:0.8rem;">Анализирую...</div>';}
        CRM.api('order_pro','ai_analyze',{order:order}).then(function(res){
            if(!res||!res.ok){if(aiEl)aiEl.innerHTML='<div style="color:var(--danger);">Ошибка ИИ</div>';return;}
            if(aiEl){aiEl.innerHTML='<div style="background:rgba(124,58,237,0.08);border:1px solid rgba(124,58,237,0.2);border-radius:8px;padding:12px;">'+
                '<div style="font-size:0.68rem;font-weight:700;color:var(--accent);text-transform:uppercase;margin-bottom:6px;">'+self._svg('ai',11)+' ИИ-совет</div>'+
                '<div style="font-size:0.8rem;line-height:1.6;white-space:pre-wrap;">'+_esc(res.data)+'</div>'+
                '<button class="btn btn-secondary btn-xs" style="margin-top:7px;" onclick="navigator.clipboard.writeText(this.previousElementSibling.innerText).then(function(){notify(\'Скопировано\',\'success\')})">Скопировать</button>'+
                '</div>';}
        });
    },

    _generateKP: function(orderId){
        var self=this;var order=this._orders.find(function(o){return String(o.id)===String(orderId);});
        if(!order) return;
        notify('Генерирую КП...','info');
        CRM.api('order_pro','ai_kp',{order:order}).then(function(res){
            if(!res||!res.ok){notify('Ошибка КП','error');return;}
            self._kpText=res.data;self._kpOrder=order;self._showKPModal(res.data,order);
        });
    },

    _showAIModal: function(title,text){
        document.getElementById('op_ai_modal')?.remove();
        var html='<div class="modal-overlay" id="op_ai_modal" style="z-index:100002;">'+
            '<div class="modal modal-sm" style="background:#0f172a;border:1px solid #1e293b;max-height:80vh;overflow-y:auto;">'+
            '<div class="modal-header"><div class="modal-title">'+title+'</div><button class="modal-close" onclick="document.getElementById(\'op_ai_modal\').remove()">✕</button></div>'+
            '<div style="padding:14px;font-size:0.83rem;line-height:1.6;white-space:pre-wrap;background:var(--bg-dark);border-radius:8px;margin:14px;">'+_esc(text)+'</div>'+
            '<div class="modal-footer"><button class="btn btn-secondary" onclick="document.getElementById(\'op_ai_modal\').remove()">Закрыть</button>'+
            '<button class="btn btn-primary" onclick="navigator.clipboard.writeText('+JSON.stringify(text)+').then(function(){notify(\'Скопировано\',\'success\')})">Скопировать</button></div></div></div>';
        document.body.insertAdjacentHTML('beforeend',html);
        document.getElementById('op_ai_modal').classList.add('open');
    },

    _showKPModal: function(text,order){
        var self=this;
        document.getElementById('op_kp_modal')?.remove();
        var html='<div class="modal-overlay" id="op_kp_modal" style="z-index:100002;">'+
            '<div class="modal modal-sm" style="background:#0f172a;border:1px solid #1e293b;max-height:85vh;overflow-y:auto;">'+
            '<div class="modal-header"><div class="modal-title">КП</div><button class="modal-close" onclick="document.getElementById(\'op_kp_modal\').remove()">✕</button></div>'+
            '<div style="padding:14px;"><div style="background:var(--bg-dark);border-radius:8px;padding:14px;font-size:0.83rem;line-height:1.6;white-space:pre-wrap;border:1px solid var(--border);" id="op_kp_text">'+_esc(text)+'</div></div>'+
            '<div class="modal-footer"><button class="btn btn-secondary" onclick="document.getElementById(\'op_kp_modal\').remove()">Закрыть</button>'+
            '<button class="btn btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById(\'op_kp_text\').innerText).then(function(){notify(\'КП скопировано\',\'success\')})">Копировать</button>'+
            '<button class="btn btn-primary" onclick="_op(\'_printKP\',\''+String(order?order.id:'')+'\')">'+self._svg('print',12)+' Печать</button>'+
            '</div></div></div>';
        document.body.insertAdjacentHTML('beforeend',html);
        document.getElementById('op_kp_modal').classList.add('open');
    },

    // ── ПЕЧАТЬ ТОВАРНОГО ЧЕКА ─────────────────────────────────
    _printReceipt: function(orderId){
        var order=this._orders.find(function(o){return String(o.id)===String(orderId);});
        if(!order) order=this._archive.find(function(o){return String(o.id)===String(orderId);});
        if(!order) return;

        var s=CRM.getSettings(); var cur=s.currency||'₽';
        var sets=this._settings;
        var remaining=Math.max(0,(order.total||0)-(order.paid||0));

        // Параметры из extraFields
        var ex=order.extraFields||{};
        var paramLines=[];
        if(ex.photo_size) paramLines.push(['Размер', ex.photo_size]);
        if(ex.photo_qty)  paramLines.push(['Количество', ex.photo_qty+' шт.']);
        if(ex.photo_material) paramLines.push(['Материал', ex.photo_material]);
        if(ex.copy_size)  paramLines.push(['Формат', ex.copy_size]);
        if(ex.copy_qty)   paramLines.push(['Листов', ex.copy_qty]);
        if(ex.copy_sides) paramLines.push(['Печать', ex.copy_sides]);
        if(ex.ban_w&&ex.ban_h) paramLines.push(['Размер', ex.ban_w+'×'+ex.ban_h+' м']);
        if(ex.ban_area)   paramLines.push(['Площадь', ex.ban_area+' м²']);
        if(ex.ban_qty)    paramLines.push(['Кол-во', ex.ban_qty+' шт.']);
        if(ex.wide_w&&ex.wide_h) paramLines.push(['Размер', ex.wide_w+'×'+ex.wide_h+' см']);
        if(ex.wide_area)  paramLines.push(['Площадь', ex.wide_area+' м²']);
        if(ex.biz_qty)    paramLines.push(['Тираж', ex.biz_qty+' шт.']);
        if(ex.biz_size)   paramLines.push(['Формат', ex.biz_size]);
        if(ex.promo_qty)  paramLines.push(['Количество', ex.promo_qty+' шт.']);
        if(ex.other_desc) paramLines.push(['Описание', ex.other_desc]);
        if(ex.des_format) paramLines.push(['Формат файла', ex.des_format]);

        // Опции
        var opts=order.options||[];

        // Статус оплаты
        var psLabel={none:'Не оплачен',prepay:'Предоплата получена',paid:'Оплачен полностью'}[order.pay_status||'none']||'';

        // Дата как читаемая строка
        var fmtDate=function(iso){
            if(!iso) return '—';
            try{return new Date(iso).toLocaleString('ru-RU',{day:'2-digit',month:'long',year:'numeric',hour:'2-digit',minute:'2-digit'});}
            catch(e){return iso;}
        };

        // Рекламный блок из настроек
        var adHtml='';
        if(sets.receipt_ad||sets.receipt_ad2){
            adHtml='<div style="border-top:2px dashed #bbb;margin-top:20px;padding-top:14px;">'+
                '<div style="text-align:center;font-size:11px;color:#555;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">🎉 Специальные предложения</div>'+
                (sets.receipt_ad?'<div style="background:#f8f8f8;border-radius:6px;padding:10px 12px;font-size:11px;color:#333;line-height:1.6;margin-bottom:6px;">'+sets.receipt_ad+'</div>':'')+
                (sets.receipt_ad2?'<div style="background:#f0f7ff;border-radius:6px;padding:10px 12px;font-size:11px;color:#333;line-height:1.6;">'+sets.receipt_ad2+'</div>':'')+
                '</div>';
        }

        var html='<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Чек '+_esc(order.num)+'</title>'+
        '<style>'+
        '*{box-sizing:border-box;margin:0;padding:0;}'+
        'body{font-family:"Arial",sans-serif;font-size:12px;color:#1a1a1a;background:#fff;padding:24px;}'+
        '.wrap{max-width:760px;margin:0 auto;border:2px solid #1a1a1a;border-radius:4px;overflow:hidden;}'+
        // Шапка
        '.header{background:#1a1a1a;color:#fff;padding:16px 20px;display:flex;justify-content:space-between;align-items:flex-start;}'+
        '.header-left .company{font-size:20px;font-weight:900;letter-spacing:1px;margin-bottom:4px;}'+
        '.header-left .company-info{font-size:10px;color:#aaa;line-height:1.6;}'+
        '.header-right{text-align:right;}'+
        '.header-right .doc-title{font-size:13px;font-weight:700;letter-spacing:2px;color:#ccc;text-transform:uppercase;}'+
        '.header-right .doc-num{font-size:22px;font-weight:900;color:#fff;line-height:1;}'+
        '.header-right .doc-date{font-size:10px;color:#aaa;margin-top:4px;}'+
        // Секция клиента
        '.client-section{padding:14px 20px;background:#f9f9f9;border-bottom:1px solid #ddd;display:flex;gap:20px;}'+
        '.client-block{flex:1;}'+
        '.block-label{font-size:9px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;}'+
        '.block-value{font-size:13px;font-weight:700;color:#1a1a1a;}'+
        '.block-sub{font-size:11px;color:#555;margin-top:2px;}'+
        // Таблица услуг
        '.services{padding:16px 20px;}'+
        '.services-title{font-size:10px;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;}'+
        'table.svc-table{width:100%;border-collapse:collapse;}'+
        'table.svc-table th{background:#f0f0f0;border:1px solid #ddd;padding:7px 10px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;text-align:left;}'+
        'table.svc-table td{border:1px solid #ddd;padding:7px 10px;font-size:11px;vertical-align:top;}'+
        'table.svc-table tr:nth-child(even) td{background:#fafafa;}'+
        // Параметры
        '.params-section{padding:0 20px 14px;}'+
        '.params-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;}'+
        '.param-item{background:#f5f5f5;border-radius:4px;padding:6px 8px;border-left:3px solid #1a1a1a;}'+
        '.param-label{font-size:9px;color:#888;text-transform:uppercase;letter-spacing:0.5px;}'+
        '.param-value{font-size:11px;font-weight:700;color:#1a1a1a;margin-top:1px;}'+
        // Опции
        '.options-section{padding:0 20px 14px;}'+
        '.options-wrap{display:flex;flex-wrap:wrap;gap:5px;}'+
        '.opt-badge{border:1px solid #1a1a1a;border-radius:3px;padding:3px 7px;font-size:10px;font-weight:700;}'+
        // Итого
        '.totals-section{padding:14px 20px;background:#1a1a1a;color:#fff;}'+
        '.totals-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:10px;}'+
        '.total-cell{text-align:center;}'+
        '.total-cell .t-label{font-size:9px;color:#aaa;text-transform:uppercase;letter-spacing:1px;}'+
        '.total-cell .t-val{font-size:18px;font-weight:900;color:#fff;line-height:1.2;}'+
        '.total-cell .t-val.accent{color:#4ade80;}'+
        '.total-cell .t-val.warn{color:#fbbf24;}'+
        '.pay-status-bar{background:rgba(255,255,255,0.1);border-radius:4px;padding:8px 12px;text-align:center;font-size:11px;font-weight:700;color:#4ade80;margin-top:4px;}'+
        '.pay-status-bar.prepay{color:#fbbf24;}'+
        '.pay-status-bar.none{color:#94a3b8;}'+
        // Подписи
        '.signatures{padding:16px 20px;display:flex;justify-content:space-between;align-items:flex-end;border-top:1px solid #eee;}'+
        '.sig-block{text-align:center;width:200px;}'+
        '.sig-line{border-top:1px solid #1a1a1a;padding-top:5px;font-size:10px;color:#555;margin-top:30px;}'+
        // Подвал с рекламой
        '.footer-section{padding:14px 20px;border-top:1px solid #eee;}'+
        '.footer-note{font-size:10px;color:#888;text-align:center;margin-bottom:4px;}'+
        '@media print{body{padding:8px;}button{display:none!important;}}</style></head><body>'+
        '<div class="wrap">'+

        // ── ШАПКА ──
        '<div class="header">'+
        '<div class="header-left">'+
        '<div class="company">'+(s.company||'ПРИНТСС медиа')+'</div>'+
        '<div class="company-info">'+
        (s.address?s.address+'<br>':'')+
        (s.phone?'Тел.: '+s.phone+'<br>':'')+
        (s.email?s.email+'<br>':'')+
        (s.inn?'ИНН: '+s.inn+(s.ogrn?' &nbsp; ОГРН: '+s.ogrn:'')+'<br>':'')+
        (sets.company_slogan?'<em>'+sets.company_slogan+'</em>':'')+
        '</div>'+
        '</div>'+
        '<div class="header-right">'+
        '<div class="doc-title">Товарный чек</div>'+
        '<div class="doc-num">№ '+_esc(order.num||'—')+'</div>'+
        '<div class="doc-date">от '+fmtDate(order.date)+'</div>'+
        '</div>'+
        '</div>'+

        // ── КЛИЕНТ / МЕНЕДЖЕР ──
        '<div class="client-section">'+
        '<div class="client-block">'+
        '<div class="block-label">Клиент</div>'+
        '<div class="block-value">'+_esc(order.client||'Без имени')+'</div>'+
        (order.phone?'<div class="block-sub">📱 '+_esc(order.phone)+'</div>':'')+
        (order.email?'<div class="block-sub">✉️ '+_esc(order.email)+'</div>':'')+
        '</div>'+
        '<div class="client-block">'+
        '<div class="block-label">Срок выполнения</div>'+
        '<div class="block-value">'+(order.deadline?fmtDate(order.deadline):'Не указан')+'</div>'+
        (order.manager?'<div class="block-sub">👤 Менеджер: '+_esc(order.manager)+'</div>':'')+
        '</div>'+
        '<div class="client-block">'+
        '<div class="block-label">Статус заказа</div>'+
        '<div class="block-value">'+({new:'Новый',work:'В работе',ready:'Готов к выдаче',done:'Выдан',cancel:'Отменён'}[order.status]||order.status)+'</div>'+
        (order.priority==='urgent'?'<div class="block-sub" style="color:#dc2626;font-weight:700;">🔥 Срочный заказ</div>':'')+
        (order.priority==='high'?'<div class="block-sub" style="color:#d97706;font-weight:700;">⚡ Важный</div>':'')+
        '</div>'+
        '</div>'+

        // ── ТАБЛИЦА УСЛУГ ──
        '<div class="services">'+
        '<div class="services-title">Состав заказа</div>'+
        '<table class="svc-table">'+
        '<thead><tr><th style="width:40px;">№</th><th>Наименование услуги</th><th style="width:120px;">Исполнение</th><th style="width:100px;text-align:right;">Стоимость</th></tr></thead>'+
        '<tbody>'+
        '<tr>'+
        '<td style="text-align:center;font-weight:700;">1</td>'+
        '<td>'+
        '<div style="font-weight:700;font-size:12px;margin-bottom:3px;">'+_esc(order.serviceLabel||'Услуги типографии')+'</div>'+
        (order.comment?'<div style="font-size:10px;color:#555;">'+_esc(order.comment)+'</div>':'')+
        '</td>'+
        '<td style="font-size:10px;color:#555;">'+
        (order.manager?_esc(order.manager):'Мастер')+
        '</td>'+
        '<td style="text-align:right;font-weight:700;font-size:13px;">'+
        (order.total||0).toLocaleString('ru-RU')+' '+cur+
        '</td>'+
        '</tr>'+
        '</tbody>'+
        '</table>'+
        '</div>'+

        // ── ПАРАМЕТРЫ ──
        (paramLines.length?
        '<div class="params-section">'+
        '<div class="services-title">Параметры</div>'+
        '<div class="params-grid">'+
        paramLines.map(function(p){
            return '<div class="param-item"><div class="param-label">'+_esc(p[0])+'</div><div class="param-value">'+_esc(String(p[1]))+'</div></div>';
        }).join('')+
        '</div></div>':'')+

        // ── ОПЦИИ ──
        (opts.length?
        '<div class="options-section">'+
        '<div class="services-title">Дополнительные услуги</div>'+
        '<div class="options-wrap">'+
        opts.map(function(o){return '<div class="opt-badge">✓ '+_esc(o)+'</div>';}).join('')+
        '</div></div>':'')+

       // ── ИТОГО ──
        '<div class="totals-section">'+
        '<div class="totals-grid">'+
        '<div class="total-cell">'+
        '<div class="t-label">ИТОГО</div>'+
        '<div class="t-val">'+(order.total||0).toLocaleString('ru-RU')+' '+cur+'</div>'+
        (order.discount>0?'<div style="font-size:9px;color:#4ade80;margin-top:2px;">Скидка -'+order.discount+'%</div>':'')+
        '</div>'+
        '<div class="total-cell">'+
        '<div class="t-label">ПРЕДОПЛАТА</div>'+
        '<div class="t-val warn">'+(order.prepay>0?(order.prepay).toLocaleString('ru-RU')+' '+cur:'—')+'</div>'+
        '</div>'+
        '<div class="total-cell">'+
        '<div class="t-label">ОСТАТОК</div>'+
        '<div class="t-val '+(remaining>0?'warn':'accent')+'">'+(remaining>0?remaining.toLocaleString('ru-RU')+' '+cur:'Оплачено')+'</div>'+
        '</div>'+
        '</div>'+
        '<div class="pay-status-bar '+(order.pay_status==='paid'?'paid':(order.pay_status==='prepay'?'prepay':'none'))+'">'+
        psLabel+
        '</div>'+
        '</div>'+

        // ── ПОДПИСИ ──
        '<div class="signatures">'+
        '<div class="sig-block">'+
        '<div class="sig-line">'+(s.signatoryTitle||'Менеджер')+': '+(s.signatory||'')+'</div>'+
        '</div>'+
        '<div style="text-align:center;font-size:10px;color:#888;">'+
        'Претензии принимаются в течение 24 часов<br>'+
        'с момента получения заказа'+
        '</div>'+
        '<div class="sig-block">'+
        '<div class="sig-line">Клиент получил: '+_esc(order.client||'')+'</div>'+
        '</div>'+
        '</div>'+

        // ── РЕКЛАМА ──
        (adHtml?'<div class="footer-section">'+adHtml+'</div>':'')+

        '</div>'+
        '<script>window.onload=function(){window.print();}<\/script>'+
        '</body></html>';

        var w=window.open('','_blank');if(w){w.document.write(html);w.document.close();}
    },

    // ── ПЕЧАТЬ ЭТИКЕТКИ 2×4 ───────────────────────────────────
    _printLabel: function(orderId){
        var order=this._orders.find(function(o){return String(o.id)===String(orderId);});
        if(!order) order=this._archive.find(function(o){return String(o.id)===String(orderId);});
        if(!order) return;

        var s=CRM.getSettings();
        var fmtDate=function(iso){
            if(!iso) return '—';
            try{return new Date(iso).toLocaleDateString('ru-RU',{day:'2-digit',month:'2-digit',year:'numeric'});}
            catch(e){return iso;}
        };
        var fmtDateFull=function(iso){
            if(!iso) return '—';
            try{return new Date(iso).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}
            catch(e){return iso;}
        };

        // Что делали — из serviceLabel + опции
        var services=[order.serviceLabel||'Услуги типографии'];
        var ex=order.extraFields||{};
        if(ex.photo_size) services.push(ex.photo_size);
        if(ex.photo_qty)  services.push(ex.photo_qty+' шт.');
        if(ex.ban_w&&ex.ban_h) services.push(ex.ban_w+'×'+ex.ban_h+' м');
        if(ex.biz_qty)    services.push('Тираж: '+ex.biz_qty);
        (order.options||[]).slice(0,4).forEach(function(o){services.push(o);});
        var serviceStr=services.slice(0,3).join(' · ');

        var html='<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Этикетка '+_esc(order.num)+'</title>'+
        '<style>'+
        '@page{size:4in 2in;margin:0;}'+
        '*{box-sizing:border-box;margin:0;padding:0;}'+
        'body{'+
        '  font-family:Arial,sans-serif;'+
        '  width:4in;height:2in;'+
        '  background:#fff;'+
        '  overflow:hidden;'+
        '}'+
        '.label{'+
        '  width:4in;height:2in;'+
        '  padding:6pt 8pt;'+
        '  display:flex;'+
        '  flex-direction:column;'+
        '  justify-content:space-between;'+
        '  border:1.5pt solid #000;'+
        '}'+
        // Шапка этикетки
        '.lbl-header{'+
        '  display:flex;justify-content:space-between;align-items:center;'+
        '  border-bottom:1pt solid #000;padding-bottom:5pt;margin-bottom:5pt;'+
        '}'+
        '.lbl-company{font-size:12pt;font-weight:900;letter-spacing:0.5pt;}'+
        '.lbl-num{font-size:14pt;font-weight:900;color:#000;}'+
        // Тело
        '.lbl-body{flex:1;display:grid;grid-template-columns:1fr 1fr;gap:4pt;}'+
        '.lbl-field{margin-bottom:3pt;}'+
        '.lbl-field-label{font-size:6pt;font-weight:700;text-transform:uppercase;letter-spacing:0.5pt;color:#555;}'+
        '.lbl-field-value{font-size:9pt;font-weight:700;color:#000;line-height:1.2;margin-top:1pt;}'+
        '.lbl-field-value.big{font-size:11pt;}'+
        // Услуга — на всю ширину
        '.lbl-service{margin-bottom:4pt;}'+
        '.lbl-service .lbl-field-value{font-size:8pt;font-weight:700;}'+
        // Подвал
        '.lbl-footer{'+
        '  border-top:1pt solid #000;padding-top:4pt;margin-top:3pt;'+
        '  display:flex;justify-content:space-between;align-items:center;'+
        '}'+
        '.lbl-footer-company{font-size:7pt;color:#555;}'+
        '.lbl-footer-status{font-size:7pt;font-weight:700;color:#000;border:1pt solid #000;padding:1pt 5pt;border-radius:2pt;}'+
        '@media print{body{padding:0;}button{display:none!important;}}'+
        '</style></head><body>'+
        '<div class="label">'+

        // ШАПКА
        '<div class="lbl-header">'+
        '<div class="lbl-company">'+(s.company||'ПРИНТСС медиа')+'</div>'+
        '<div class="lbl-num">'+_esc(order.num||'—')+'</div>'+
        '</div>'+

        // ТЕЛО
        '<div class="lbl-body">'+
        // Клиент
        '<div>'+
        '<div class="lbl-field">'+
        '<div class="lbl-field-label">Клиент</div>'+
        '<div class="lbl-field-value big">'+_esc(order.client||'Без имени')+'</div>'+
        '</div>'+
        (order.phone?
        '<div class="lbl-field">'+
        '<div class="lbl-field-label">Телефон</div>'+
        '<div class="lbl-field-value">'+_esc(order.phone)+'</div>'+
        '</div>':'')+'</div>'+
        // Даты
        '<div>'+
        '<div class="lbl-field">'+
        '<div class="lbl-field-label">Дата выдачи</div>'+
        '<div class="lbl-field-value big" style="color:'+(order.deadline&&(new Date(order.deadline)-new Date())<0?'#dc2626':'#000')+';">'+
        (order.deadline?fmtDate(order.deadline):'По готовности')+
        '</div></div>'+
        '<div class="lbl-field">'+
        '<div class="lbl-field-label">Принят</div>'+
        '<div class="lbl-field-value">'+fmtDate(order.date)+'</div>'+
        '</div></div>'+
        '</div>'+

        // Услуга
        '<div class="lbl-service">'+
        '<div class="lbl-field-label">Что делали</div>'+
        '<div class="lbl-field-value">'+_esc(serviceStr)+'</div>'+
        '</div>'+

        // ПОДВАЛ
        '<div class="lbl-footer">'+
        '<div class="lbl-footer-company">'+
        (s.phone?s.phone:'')+(s.address?' · '+s.address:'')+
        '</div>'+
        '<div class="lbl-footer-status">'+
        ({new:'Новый',work:'В работе',ready:'ГОТОВ',done:'ВЫДАН',cancel:'Отменён'}[order.status]||order.status)+
        '</div>'+
        '</div>'+

        '</div>'+
        '<script>window.onload=function(){window.print();}<\/script>'+
        '</body></html>';

        var w=window.open('','_blank');if(w){w.document.write(html);w.document.close();}
    },

    _printKP: function(orderId){
        var order=orderId?this._orders.find(function(o){return String(o.id)===String(orderId);}):this._kpOrder;
        var s=CRM.getSettings();var kpText=document.getElementById('op_kp_text')?.innerText||(this._kpText||'');
        var html='<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif;font-size:13px;padding:36px;max-width:800px;margin:0 auto;}h1{font-size:18px;}.hdr{margin-bottom:24px;border-bottom:2px solid #333;padding-bottom:12px;}.body{line-height:1.7;white-space:pre-wrap;}.ftr{margin-top:32px;border-top:1px solid #ccc;padding-top:12px;font-size:11px;color:#666;}.sigs{display:flex;justify-content:space-between;margin-top:32px;}.sig{text-align:center;min-width:160px;border-top:1px solid #333;padding-top:5px;font-size:10px;}</style></head><body>'+
            '<div class="hdr"><h1>'+(s.company||'КП')+'</h1><div style="font-size:11px;color:#555;line-height:1.6;">'+(s.address?s.address+'<br>':'')+(s.phone?'Тел: '+s.phone+' ':'')+(s.email?'• '+s.email+' ':'')+(s.website?'• '+s.website:'')+'<br>'+(s.inn?'ИНН: '+s.inn+' ':'')+(s.ogrn?'• ОГРН: '+s.ogrn:'')+'</div><div style="margin-top:5px;font-size:11px;color:#555;">Дата: '+new Date().toLocaleDateString('ru')+(order?' &nbsp; '+order.num+' &nbsp; '+order.client:'')+'</div></div>'+
            '<div class="body">'+kpText+'</div>'+
            '<div class="ftr"><div class="sigs"><div class="sig">'+(s.signatoryTitle||'Директор')+': '+(s.signatory||'____________')+'</div><div class="sig">Клиент: '+(order?order.client||'____________':'____________')+'</div></div></div>'+
            '<script>window.onload=function(){window.print();}<\/script></body></html>';
        var w=window.open('','_blank');if(w){w.document.write(html);w.document.close();}
    },

    _printBlank: function(){
        var gv=function(id){var el=document.getElementById(id);return el?(el.value||'').trim():'';};
        var gs=function(id){var el=document.getElementById(id);return el?(el.options?el.options[el.selectedIndex]?.value||el.value:el.value):'';};
        var tmp={id:'__blank__',num:gv('op_num'),date:gv('op_date'),deadline:gv('op_deadline'),client:gv('op_client')||'Без имени',phone:gv('op_phone'),manager:gv('op_manager'),service:this._currentSvc,serviceLabel:(this.KB_SVC_LABELS[this._currentSvc]||this._currentSvc),status:gs('op_status'),pay_status:this._localPayStatus||'none',comment:gv('op_comment'),total:parseFloat(gv('op_total'))||0,prepay:parseFloat(gv('op_prepay'))||0,paid:0,options:this._currentOptions||[],extraFields:this._collectExtra(),files:[]};
        this._orders.push(tmp);
try { this._printReceipt('__blank__'); }
finally { this._orders = this._orders.filter(function(o){return o.id!=='__blank__';}); }
    },

    // ── НАСТРОЙКИ ─────────────────────────────────────────────
    showSettings: function(){
        var self=this;var s=this._settings;
        document.getElementById('op_settings_modal')?.remove();
        var tgl=function(id,on,label,desc){
            return '<div style="display:flex;align-items:center;justify-content:space-between;padding:9px 12px;background:var(--bg-dark);border-radius:8px;border:1px solid var(--border);margin-bottom:8px;">'+
                '<div><div style="font-weight:700;font-size:0.83rem;">'+label+'</div><div style="font-size:0.68rem;color:var(--text-muted);">'+desc+'</div></div>'+
                '<div class="toggle-switch '+(on?'on':'')+'" id="'+id+'" onclick="this.classList.toggle(\'on\');this.querySelector(\'.toggle-thumb\').style.left=this.classList.contains(\'on\')?\'23px\':\'3px\'"><div class="toggle-thumb" style="left:'+(on?'23':'3')+'px;"></div></div></div>';
        };
        var html=
            '<div class="modal-overlay" id="op_settings_modal" style="z-index:100000;">'+
            '<div class="modal modal-sm" style="background:#0f172a;border:1px solid #1e293b;max-height:90vh;overflow-y:auto;">'+
            '<div class="modal-header"><div class="modal-title">⚙️ Настройки «Заказы PRO»</div>'+
            '<button class="modal-close" onclick="document.getElementById(\'op_settings_modal\').remove()">✕</button></div>'+
            '<div style="padding:14px;">'+

            '<div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px;">Основные</div>'+
            tgl('op_set_enabled',     s.enabled,        'Модуль включён',                 'При выключении вкладка скрыта')+
            tgl('op_set_urgent',      s.notify_urgent,  'Уведомления о срочных заказах',   'Красная панель в канбане')+

            '<div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin:12px 0 8px;">Уведомления</div>'+
            tgl('op_set_notify_vk',   s.notify_vk,      'Уведомлять клиентов ВКонтакте',   'Через /bot/vk.php')+
            tgl('op_set_notify_art',  s.notify_artemiy, 'Уведомлять клиентов в Артемий',   'Через /bot/artemiy.php')+
            tgl('op_set_notify_crm',  s.notify_crm,     'Уведомления директору в МАКс',    'Через /bot/crm_notify.php')+

            '<div class="form-group" style="margin-top:10px;"><label class="form-label">Порог срочности (часов)</label>'+
            '<input class="form-input" type="number" id="op_set_urgent_h" value="'+(s.urgent_hours||4)+'" min="1" max="72" style="max-width:90px;"></div>'+
            '<div class="form-group"><label class="form-label">Менеджер по умолчанию</label>'+
            '<input class="form-input" id="op_set_manager" value="'+(s.default_manager||'')+'"></div>'+

            '<hr style="border:none;border-top:1px solid var(--border);margin:14px 0;">'+
            '<div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:8px;">'+self._svg('print',11)+' Товарный чек — реклама</div>'+
            '<div class="form-group"><label class="form-label">Слоган компании (в шапке чека)</label>'+
            '<input class="form-input" id="op_set_slogan" placeholder="Печатаем быстро и качественно!" value="'+_esc(s.company_slogan||'')+'"></div>'+
            '<div class="form-group"><label class="form-label">Рекламный блок 1 (в подвале чека)</label>'+
            '<textarea class="form-textarea" id="op_set_ad1" rows="2" placeholder="Например: Скидка 10% на следующий заказ при предъявлении этого чека!">'+_esc(s.receipt_ad||'')+'</textarea></div>'+
            '<div class="form-group"><label class="form-label">Рекламный блок 2 (доп. акция)</label>'+
            '<textarea class="form-textarea" id="op_set_ad2" rows="2" placeholder="Например: Фотокнига на заказ — от 990 ₽. Подробнее на сайте.">'+_esc(s.receipt_ad2||'')+'</textarea></div>'+

            '<div style="padding:8px 10px;background:rgba(6,182,212,0.06);border:1px solid rgba(6,182,212,0.15);border-radius:8px;font-size:0.72rem;color:var(--text-muted);line-height:1.6;margin-top:8px;">'+
            self._svg('label',11)+' <b>Этикетка</b> печатается в формате 2×4 дюйма для принтера этикеток.<br>'+
            '🖨️ Оплата через кассу, здесь только внутренний учёт статуса.'+
            '</div>'+
            '</div>'+
            '<div class="modal-footer">'+
            '<button class="btn btn-secondary" onclick="document.getElementById(\'op_settings_modal\').remove()">Отмена</button>'+
            '<button class="btn btn-primary" onclick="_op(\'_saveSettings\')">Сохранить</button>'+
            '</div></div></div>';
        document.body.insertAdjacentHTML('beforeend',html);
        document.getElementById('op_settings_modal').classList.add('open');
        document.getElementById('op_settings_modal').addEventListener('click',function(e){
            if(e.target.id==='op_settings_modal') document.getElementById('op_settings_modal').remove();
        });
    },

    _saveSettings: function(){
        var self=this;
        var get=function(id){return document.getElementById(id)?.classList.contains('on');};
        var s={
            enabled:         get('op_set_enabled'),
            notify_urgent:   get('op_set_urgent'),
            notify_vk:       get('op_set_notify_vk'),
            notify_artemiy:  get('op_set_notify_art'),
            notify_crm:      get('op_set_notify_crm'),
            urgent_hours:    parseInt(document.getElementById('op_set_urgent_h')?.value)||4,
            default_manager: document.getElementById('op_set_manager')?.value||'',
            company_slogan:  document.getElementById('op_set_slogan')?.value||'',
            receipt_ad:      document.getElementById('op_set_ad1')?.value||'',
            receipt_ad2:     document.getElementById('op_set_ad2')?.value||'',
        };
        CRM.api('order_pro','save_settings',s).then(function(res){
            if(res&&res.ok){
                self._settings=s;
                document.getElementById('op_settings_modal')?.remove();
                notify('Настройки сохранены','success');
            } else {
                notify('Ошибка сохранения','error');
            }
        });
    },

    // ── УТИЛИТЫ ───────────────────────────────────────────────
    _money: function(val,cur){
        cur=cur||CRM.getSettings().currency||'₽';
        return (parseFloat(val)||0).toLocaleString('ru-RU')+' '+cur;
    },
    _dateShort: function(iso){
        if(!iso) return '—';
        try { return new Date(iso).toLocaleString('ru-RU',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}); }
        catch(e){ return iso; }
    },
    _nowDT: function(){
        var n=new Date();
        var p=function(v){return String(v).padStart(2,'0');};
        return n.getFullYear()+'-'+p(n.getMonth()+1)+'-'+p(n.getDate())+'T'+p(n.getHours())+':'+p(n.getMinutes());
    },
    _deadlineColor: function(dl){
        if(!dl) return 'var(--text-muted)';
        var d=new Date(dl)-new Date();
        if(d<0) return '#f87171';
        if(d<86400000) return '#fbbf24';
        return '#34d399';
    },
    _formatSize: function(b){
        if(!b) return '0 Б';
        if(b<1024) return b+' Б';
        if(b<1048576) return (b/1024).toFixed(1)+' КБ';
        return (b/1048576).toFixed(1)+' МБ';
    },
    _buildDesc: function(order){
        var parts=[];var ex=order.extraFields||{};
        if(order.service==='photo'&&ex.photo_size) parts.push(ex.photo_size);
        if(order.service==='copy'&&ex.copy_size)   parts.push(ex.copy_size);
        if(order.service==='banner'&&ex.ban_w)     parts.push(ex.ban_w+'×'+ex.ban_h+'м');
        if(order.service==='wide'&&ex.wide_w)      parts.push(ex.wide_w+'×'+ex.wide_h+'см');
        if(order.comment) parts.push(order.comment.substring(0,50));
        return parts.join(' · ')||'—';
    },
    _infoBox: function(label,value,color) {
        return '<div style="text-align:center;padding:8px;background:var(--bg-dark);border-radius:8px;"><div style="font-size:0.6rem;color:var(--text-muted);">'+label+'</div><div style="font-weight:800;'+(color?'color:'+color+';':'')+'">' +value+'</div></div>';
    },

}); // END registerModule

// Закрываем статус-меню при клике снаружи
document.addEventListener('click', function() {
    document.querySelectorAll('.kb-status-menu.open').forEach(function(m){
        m.classList.remove('open');
    });
});
</script>