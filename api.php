<?php
// ============================================
// PrintCRM API v2.2 — исправленный роутинг модулей
// ============================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============ НАСТРОЙКИ ============
define('API_SECRET',  '12345');
define('DB_FILE',     __DIR__ . '/../data/printcrm_data.json');
define('LOG_FILE',    __DIR__ . '/printcrm_log.txt');
define('CRM_API_KEY', 'printss_crm_key_2024');

// ============ АВТОРИЗАЦИЯ ============
function checkAuth() {
    $key = '';
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) === 'x-api-key') { $key = $value; break; }
        }
    }
    if (!$key) $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!$key) $key = $_GET['key'] ?? '';
    if (trim($key) !== API_SECRET) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
}

// ============ БД ============
function readDB() {
    if (!file_exists(DB_FILE)) {
        $e = initEmptyDB();
        writeDB($e);
        return $e;
    }
    $raw  = file_get_contents(DB_FILE);
    $data = json_decode($raw, true);
    if (!$data) {
        $e = initEmptyDB();
        writeDB($e);
        return $e;
    }
    return mergeWithDefaults($data);
}

function mergeWithDefaults($data) {
    $defaults = initEmptyDB();
    foreach ($defaults as $key => $val) {
        if (!isset($data[$key])) $data[$key] = $val;
    }
    $salDefaults = ['records' => [], 'employees' => [], 'shifts' => []];
    foreach ($salDefaults as $k => $v) {
        if (!isset($data['salary'][$k])) $data['salary'][$k] = $v;
    }
    $setDefaults = $defaults['settings'];
    foreach ($setDefaults as $k => $v) {
        if (!isset($data['settings'][$k])) $data['settings'][$k] = $v;
    }
  // Мержим настройки bank_account — подтягиваем новые поля если их нет в БД
    if (!isset($data['bank_account']['settings'])) {
        $data['bank_account']['settings'] = [];
    }
    $bankDefaults = $defaults['bank_account']['settings'];
    foreach ($bankDefaults as $k => $v) {
        if (!isset($data['bank_account']['settings'][$k])) {
            $data['bank_account']['settings'][$k] = $v;
        }
    }
    // Добавляем payments если отсутствует
    if (!isset($data['bank_account']['payments'])) {
        $data['bank_account']['payments'] = [];
    }

    return $data;
}

function writeDB($data) {
    $dir = dirname(DB_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // Защита от пустых данных
    if (empty($data) || !is_array($data)) {
        writeLog('WRITEDB_BLOCKED', 'Попытка записи пустых данных');
        return false;
    }

    $data['_lastUpdated'] = date('Y-m-d H:i:s');
    $data['_updatedBy']   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // Защита от битого JSON
    if (!$json || json_last_error() !== JSON_ERROR_NONE) {
        writeLog('WRITEDB_BLOCKED', 'Битый JSON: ' . json_last_error_msg());
        return false;
    }

    // Защита от подозрительно малого файла
    if (strlen($json) < 500) {
        writeLog('WRITEDB_BLOCKED', 'Малый объём: ' . strlen($json) . ' байт');
        return false;
    }

    // Автобэкап раз в час
    if (file_exists(DB_FILE)) {
        $mtime = filemtime(DB_FILE);
        if ((time() - $mtime) > 3600) {
            $backupDir = $dir . '/backups/';
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
            $backupFile = $backupDir . 'printcrm_' . date('Y-m-d_H') . '.json';
            if (!file_exists($backupFile)) {
                copy(DB_FILE, $backupFile);
                foreach (glob($backupDir . '*.json') as $f) {
                    if (filemtime($f) < time() - 7 * 86400) unlink($f);
                }
            }
        }
    }

    // Атомарная запись
    $tmp = DB_FILE . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        writeLog('WRITEDB_ERROR', 'Не удалось записать .tmp');
        return false;
    }
    if (!rename($tmp, DB_FILE)) {
        writeLog('WRITEDB_ERROR', 'Не удалось переименовать .tmp');
        @unlink($tmp);
        return false;
    }

    return true;
}

function initEmptyDB() {
    return [
        'orders'       => [],
        'finance'      => [],
        'clients'      => [],
        'notes'        => [],
        'warehouse'    => [],
        'calEvents'    => [],
        'chatHistory'  => [],
        'orderCounter' => 1,
        'debts'        => [],
        'salary'       => [
            'records'   => [],
            'employees' => [],
            'shifts'    => [],
        ],
      'bank_account' => [
    'balance'        => 0,
    'operations'     => [],
    'reconciliation' => [],
    'payments'       => [],
    'settings'       => [
        'enabled'               => false,
        'account_number'        => '',
        'bank_name'             => 'Озон Банк',
        'bik'                   => '044525634',
        'last_sync'             => null,
        'acquiring_enabled'     => false,
        'acquiring_access_key'  => '',
        'acquiring_secret_key'  => '',
        'acquiring_api_url'     => 'https://payapi.ozon.ru',
        'acquiring_success_url' => '',
        'acquiring_fail_url'    => '',
        'acquiring_notify_url'  => '',
    ],
],
        'shifts'       => [
            'current' => null,
            'history' => [],
        ],
        'settings'     => [
            'company'        => '',
            'inn'            => '',
            'ogrn'           => '',
            'address'        => '',
            'phone'          => '',
            'email'          => '',
            'website'        => '',
            'bankAcc'        => '',
            'bik'            => '',
            'bankName'       => '',
            'korAcc'         => '',
            'kpp'            => '',
            'receiptHeader'  => 'Спасибо за заказ! Ждём вас снова.',
            'receiptFooter'  => 'Сохраняйте чек при получении заказа.',
            'signatory'      => '',
            'signatoryTitle' => 'Менеджер',
            'vat'            => '0',
            'currency'       => '₽',
            'apiKey'         => '',
            'apiModel'       => 'deepseek-chat',
            'modules'        => [],
            'tgToken'        => '',
            'tgBossId'       => '',
        ],
        '_lastUpdated' => date('Y-m-d H:i:s'),
        '_updatedBy'   => 'system',
    ];
}

function writeLog($action, $details = '') {
    $line = date('Y-m-d H:i:s')
        . ' | ' . $action
        . ' | ' . $details
        . ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// ============ ПАРСИМ ЗАПРОС ============
$method       = $_SERVER['REQUEST_METHOD'];
$rawInput     = file_get_contents('php://input');
$body         = json_decode($rawInput, true) ?? [];
$moduleParam  = trim($_GET['module'] ?? $body['module'] ?? '', '/');
$actionParam  = trim($_GET['action'] ?? $body['action'] ?? '', '/');

// Определяем путь роутера
$path = $moduleParam ? 'module' : $actionParam;

// Авторизация (кроме ping)
if ($path !== 'ping') checkAuth();

// ============ РОУТЕР ============
switch ($path) {

    // ---- PING ----
    case 'ping':
        echo json_encode([
            'status'  => 'ok',
            'message' => 'PrintCRM API работает!',
            'time'    => date('Y-m-d H:i:s'),
            'php'     => PHP_VERSION,
        ]);
        break;

    // ---- ВСЯ БД ----
    case 'db':
        if ($method === 'GET') {
            echo json_encode(['ok' => true, 'data' => readDB()]);
        } elseif ($method === 'POST' || $method === 'PUT') {
            if (empty($body)) {
                http_response_code(400);
                echo json_encode(['error' => 'Пустое тело']);
                break;
            }
            $current = readDB();
            if (empty($body['salary']['employees']) && !empty($current['salary']['employees'])) {
                $body['salary'] = $current['salary'];
            }
            if (empty($body['salary']['shifts']) && !empty($current['salary']['shifts'])) {
                $body['salary']['shifts'] = $current['salary']['shifts'];
            }
            $merged = mergeWithDefaults($body);
            writeDB($merged);
            writeLog('SAVE_DB', 'Full DB saved');
            echo json_encode(['ok' => true, 'message' => 'База сохранена']);
        }
        break;

    // ---- ЗАКАЗЫ ----
    case 'orders':
        $db = readDB();
        if ($method === 'GET') {
            $orders = $db['orders'];
            if (!empty($_GET['status']))
                $orders = array_filter($orders, fn($o) => $o['status'] === $_GET['status']);
            if (!empty($_GET['date']))
                $orders = array_filter($orders, fn($o) => str_starts_with($o['date'] ?? '', $_GET['date']));
            echo json_encode(['ok' => true, 'data' => array_values($orders), 'total' => count($orders)]);
        } elseif ($method === 'POST') {
            if (empty($body)) { http_response_code(400); echo json_encode(['error' => 'Нет данных']); break; }
            $body['id']        = $body['id'] ?? time() . rand(100, 999);
            $body['createdAt'] = date('Y-m-d H:i:s');
            array_unshift($db['orders'], $body);
            $db['orderCounter'] = ($db['orderCounter'] ?? 1) + 1;
            writeDB($db);
            writeLog('ORDER_ADD', $body['num'] ?? 'no-num');
            echo json_encode(['ok' => true, 'data' => $body]);
        } elseif ($method === 'PUT') {
            $id = $_GET['id'] ?? $body['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Нет ID']); break; }
            $found = false;
            foreach ($db['orders'] as &$order) {
                if ((string)$order['id'] === (string)$id) {
                    $order = array_merge($order, $body);
                    $found = true;
                    break;
                }
            }
            unset($order);
            if (!$found) { http_response_code(404); echo json_encode(['error' => 'Не найден']); break; }
            writeDB($db);
            writeLog('ORDER_UPDATE', 'ID: ' . $id);
            echo json_encode(['ok' => true]);
        } elseif ($method === 'DELETE') {
            $id = $_GET['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Нет ID']); break; }
            $db['orders'] = array_values(array_filter($db['orders'], fn($o) => (string)$o['id'] !== (string)$id));
            writeDB($db);
            writeLog('ORDER_DELETE', 'ID: ' . $id);
            echo json_encode(['ok' => true]);
        }
        break;

    // ---- ФИНАНСЫ ----
    case 'finance':
        $db = readDB();
        if ($method === 'GET') {
            $items = $db['finance'];
            if (!empty($_GET['type']))
                $items = array_filter($items, fn($f) => $f['type'] === $_GET['type']);
            $month      = $_GET['month'] ?? date('Y-m');
            $monthItems = array_filter($db['finance'], fn($f) => str_starts_with($f['date'] ?? '', $month));
            $income  = array_sum(array_column(array_filter($monthItems, fn($f) => $f['type'] === 'income'),  'amount'));
            $expense = array_sum(array_column(array_filter($monthItems, fn($f) => $f['type'] === 'expense'), 'amount'));
            echo json_encode([
                'ok'      => true,
                'data'    => array_values($items),
                'summary' => [
                    'income'  => $income,
                    'expense' => $expense,
                    'profit'  => $income - $expense,
                    'month'   => $month,
                ],
            ]);
        } elseif ($method === 'POST') {
            if (empty($body)) { http_response_code(400); echo json_encode(['error' => 'Нет данных']); break; }
            $body['id']        = $body['id'] ?? time() . rand(100, 999);
            $body['createdAt'] = date('Y-m-d H:i:s');
            array_unshift($db['finance'], $body);
            writeDB($db);
            writeLog('FINANCE_ADD', ($body['type'] ?? '') . ' ' . ($body['amount'] ?? '') . '₽');
            echo json_encode(['ok' => true, 'data' => $body]);
        } elseif ($method === 'DELETE') {
            $id = $_GET['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Нет ID']); break; }
            $db['finance'] = array_values(array_filter($db['finance'], fn($f) => (string)$f['id'] !== (string)$id));
            writeDB($db);
            writeLog('FINANCE_DELETE', 'ID: ' . $id);
            echo json_encode(['ok' => true]);
        }
        break;

    // ---- КЛИЕНТЫ ----
    case 'clients':
        $db = readDB();
        if ($method === 'GET') {
            $clients = $db['clients'];
            if (!empty($_GET['search'])) {
                $s = mb_strtolower($_GET['search']);
                $clients = array_filter($clients, fn($c) =>
                    str_contains(mb_strtolower($c['name']  ?? ''), $s) ||
                    str_contains($c['phone'] ?? '', $s) ||
                    str_contains(mb_strtolower($c['email'] ?? ''), $s)
                );
            }
            echo json_encode(['ok' => true, 'data' => array_values($clients), 'total' => count($clients)]);
        } elseif ($method === 'POST') {
            $body['id']        = $body['id'] ?? time() . rand(100, 999);
            $body['createdAt'] = date('Y-m-d H:i:s');
            array_unshift($db['clients'], $body);
            writeDB($db);
            writeLog('CLIENT_ADD', $body['name'] ?? 'no-name');
            echo json_encode(['ok' => true, 'data' => $body]);
        } elseif ($method === 'DELETE') {
            $id = $_GET['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Нет ID']); break; }
            $db['clients'] = array_values(array_filter($db['clients'], fn($c) => (string)$c['id'] !== (string)$id));
            writeDB($db);
            writeLog('CLIENT_DELETE', 'ID: ' . $id);
            echo json_encode(['ok' => true]);
        }
        break;

    // ---- ЗАМЕТКИ ----
    case 'notes':
        $db = readDB();
        if ($method === 'GET') {
            echo json_encode(['ok' => true, 'data' => $db['notes'] ?? []]);
        } elseif ($method === 'POST') {
            $body['id']        = $body['id'] ?? time() . rand(100, 999);
            $body['createdAt'] = date('Y-m-d H:i:s');
            array_unshift($db['notes'], $body);
            writeDB($db);
            writeLog('NOTE_ADD', $body['title'] ?? 'no-title');
            echo json_encode(['ok' => true, 'data' => $body]);
        } elseif ($method === 'DELETE') {
            $id = $_GET['id'] ?? null;
            if (!$id) { http_response_code(400); echo json_encode(['error' => 'Нет ID']); break; }
            $db['notes'] = array_values(array_filter($db['notes'], fn($n) => (string)$n['id'] !== (string)$id));
            writeDB($db);
            echo json_encode(['ok' => true]);
        }
        break;

    // ---- НАСТРОЙКИ ----
    case 'settings':
        $db = readDB();
        if ($method === 'GET') {
            echo json_encode(['ok' => true, 'data' => $db['settings'] ?? []]);
        } elseif ($method === 'POST' || $method === 'PUT') {
            $db['settings'] = array_merge($db['settings'] ?? [], $body);
            writeDB($db);
            writeLog('SETTINGS_SAVE', 'updated');
            echo json_encode(['ok' => true, 'message' => 'Сохранено']);
        }
        break;

    // ---- ЛОГ ----
    case 'log':
        if ($method === 'GET') {
            $lines = file_exists(LOG_FILE) ? array_slice(file(LOG_FILE), -100) : [];
            echo json_encode(['ok' => true, 'data' => array_values($lines)]);
        }
        break;

    // ---- СТАТИСТИКА ----
    case 'stats':
        $db     = readDB();
        $month  = $_GET['month'] ?? date('Y-m');
        $orders = array_filter($db['orders'], fn($o) => str_starts_with($o['date'] ?? '', $month));
        $fin    = array_filter($db['finance'], fn($f) => str_starts_with($f['date'] ?? '', $month));
        $income  = array_sum(array_column(array_filter($fin, fn($f) => $f['type'] === 'income'),  'amount'));
        $expense = array_sum(array_column(array_filter($fin, fn($f) => $f['type'] === 'expense'), 'amount'));
        $byService = [];
        foreach ($orders as $o) {
            $k = $o['serviceLabel'] ?? 'Прочее';
            $byService[$k] = ($byService[$k] ?? 0) + 1;
        }
        arsort($byService);
        $ordersArr = array_values($orders);
        $avgCheck  = count($ordersArr) > 0
            ? round(array_sum(array_column($ordersArr, 'total')) / count($ordersArr))
            : 0;
        echo json_encode([
            'ok'    => true,
            'month' => $month,
            'orders' => [
                'total'  => count($db['orders']),
                'month'  => count($ordersArr),
                'active' => count(array_filter($db['orders'], fn($o) => in_array($o['status'] ?? '', ['new','work']))),
                'done'   => count(array_filter($db['orders'], fn($o) => ($o['status'] ?? '') === 'done')),
            ],
            'finance' => [
                'income'  => $income,
                'expense' => $expense,
                'profit'  => $income - $expense,
                'margin'  => $income > 0 ? round(($income - $expense) / $income * 100, 1) : 0,
            ],
            'clients'   => count($db['clients']),
            'avgCheck'  => $avgCheck,
            'byService' => $byService,
        ]);
        break;

    // ---- РЕЕСТР МОДУЛЕЙ ----
    case 'registry':
        $modules = [];
        $files   = glob(__DIR__ . '/modules/*.php') ?: [];
        foreach ($files as $file) {
            $lines = array_slice(file($file), 0, 30);
            $meta  = [
                'id'          => str_replace('.php', '', basename($file)),
                'name'        => '',
                'icon'        => '🧩',
                'description' => '',
                'version'     => '1.0',
                'sidebar'     => true,
                'color'       => '#7c3aed',
            ];
            foreach ($lines as $line) {
                if (preg_match('/@name\s+(.+)/',        $line, $m)) $meta['name']        = trim($m[1]);
                if (preg_match('/@icon\s+(.+)/',        $line, $m)) $meta['icon']        = trim($m[1]);
                if (preg_match('/@description\s+(.+)/', $line, $m)) $meta['description'] = trim($m[1]);
                if (preg_match('/@version\s+(.+)/',     $line, $m)) $meta['version']     = trim($m[1]);
                if (preg_match('/@sidebar\s+(.+)/',     $line, $m)) $meta['sidebar']     = trim($m[1]) === 'true';
                if (preg_match('/@color\s+(.+)/',       $line, $m)) $meta['color']       = trim($m[1]);
            }
            if (!$meta['name']) $meta['name'] = ucfirst($meta['id']);
            $modules[] = $meta;
        }
        echo json_encode(['ok' => true, 'modules' => $modules]);
        break;

    // ---- МОДУЛИ (внешние PHP-файлы) ----
    case 'module':
        $safeModule = preg_replace('/[^a-z0-9_]/', '', strtolower($moduleParam));
        $moduleFile = __DIR__ . '/modules/' . $safeModule . '.php';

        // Отдать JS модуля
        if ($actionParam === '__getjs__') {
            if (!$safeModule || !file_exists($moduleFile)) {
                http_response_code(404);
                echo '<!-- module not found -->';
                exit();
            }
            $content = file_get_contents($moduleFile);
            $pos     = strpos($content, '<!--MODULE_JS_START-->');
            header('Content-Type: text/html; charset=utf-8');
            echo $pos !== false ? substr($content, $pos) : '<!-- no js -->';
            exit();
        }

        // Проверяем существование файла модуля
        if (!$safeModule || !file_exists($moduleFile)) {
            http_response_code(404);
            echo json_encode([
                'ok'        => false,
                'error'     => 'Модуль не найден: ' . $safeModule,
                'available' => array_map(
                    fn($f) => str_replace('.php', '', basename($f)),
                    glob(__DIR__ . '/modules/*.php') ?: []
                ),
            ]);
            break;
        }

        // *** КЛЮЧЕВОЕ ИСПРАВЛЕНИЕ ***
        // Переменные которые ожидает модуль — объявляем ДО подключения файла
        $moduleDB     = readDB();
        $moduleAction = $actionParam;   // ← 'add_payment', 'list', 'create' и т.д.
        $moduleBody   = $body;          // ← тело POST запроса
        $moduleParams = $_GET;          // ← GET параметры

        // Убираем мусорный вывод до JSON от модуля
        ob_start();
        require $moduleFile;
        $output = ob_get_clean();

        // Если модуль содержит JS — отрезаем его, отдаём только JSON
        $jsMarker = strpos($output, '<!--MODULE_JS_START-->');
        $jsonPart = $jsMarker !== false ? substr($output, 0, $jsMarker) : $output;

        // Ищем JSON в выводе
        $jsonPart = trim($jsonPart);
        if ($jsonPart !== '') {
            echo $jsonPart;
        } else {
            echo json_encode(['ok' => true, 'data' => null]);
        }
        break;
        
   // ---- ЗАГРУЗКА ФАЙЛОВ ----
    case 'upload':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
            break;
        }
        $uploadDir = __DIR__ . '/../data/uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        if (!empty($_FILES['file'])) {
            $file     = $_FILES['file'];
            $origName = basename($file['name']);
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $allowed  = ['jpg','jpeg','png','gif','pdf','ai','cdr','eps','tif','tiff','psd','doc','docx','xlsx','zip'];
            if (!in_array($ext, $allowed)) {
                echo json_encode(['ok' => false, 'error' => 'Недопустимый тип: ' . $ext]);
                break;
            }
            if ($file['size'] > 50 * 1024 * 1024) {
                echo json_encode(['ok' => false, 'error' => 'Файл > 50MB']);
                break;
            }
            $newName  = date('Ymd_His') . '_' . uniqid() . '.' . $ext;
            $destPath = $uploadDir . $newName;
            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $fileUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http')
                         . '://' . $_SERVER['HTTP_HOST'] . '/data/uploads/' . $newName;
                writeLog('FILE_UPLOAD', $origName . ' → ' . $newName);
                echo json_encode(['ok' => true, 'filename' => $newName,
                    'origName' => $origName, 'url' => $fileUrl,
                    'size' => $file['size'], 'ext' => $ext]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'Ошибка сохранения']);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'Файл не передан']);
        }
        break;

// ---- ПРЯМОЙ ВЫЗОВ МОДУЛЯ bank_account ----
    case 'create_payment':
    case 'current':
    case 'save_settings':
    case 'payments':
    case 'payment_status':
    case 'operations':
    case 'import_csv':
    case 'add_operation':
    case 'delete_operation':
    case 'webhook':
    case 'debug':
        $safeModule = 'bank_account';
        $moduleFile = __DIR__ . '/modules/bank_account.php';
        if (!file_exists($moduleFile)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'bank_account not found']);
            break;
        }
        $moduleDB     = readDB();
        $moduleAction = $path;
        $moduleBody   = $body;
        $moduleParams = $_GET;
        ob_start();
        require $moduleFile;
        $output   = ob_get_clean();
        $jsMarker = strpos($output, '<!--MODULE_JS_START-->');
        $jsonPart = trim($jsMarker !== false ? substr($output, 0, $jsMarker) : $output);
        echo $jsonPart ?: json_encode(['ok' => true, 'data' => null]);
        break;

// ---- 404 ----
    default:
        http_response_code(404);
        echo json_encode([
            'error'  => 'Неизвестный endpoint',
            'path'   => $path,
            'routes' => ['ping','db','orders','finance','clients',
                         'notes','settings','stats','log','registry','module'],
        ]);
        break;
}
?>