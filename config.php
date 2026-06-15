<?php
// config.php - Основная конфигурация
// Версия: 2.1 - Добавлена интеграция платёжных систем

// ===== НАСТРОЙКИ ОКРУЖЕНИЯ =====
if (!defined('PRODUCTION')) {
    define('PRODUCTION', false);
}

if (PRODUCTION) {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 1);
}

// ===== БАЗОВЫЕ НАСТРОЙКИ =====
define('APP_NAME', "Sasha's Sushi");
define('APP_VERSION', '1.0.0');
define('SITE_NAME', "Sasha's Sushi");

// ===== URL НАСТРОЙКИ =====
// ИСПРАВЛЕНО: упрощена логика определения BASE_URL
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Убрали SCRIPT_NAME чтобы избежать дублирования путей
    $base_url = $protocol . '://' . $host;
    define('BASE_URL', rtrim($base_url, '/'));
}

define('ADMIN_URL', BASE_URL . '/admin');
define('API_URL', BASE_URL . '/api');
define('SITE_URL', BASE_URL);
define('ADMIN_EMAIL', 'admin@sashas-sushi.ru');

// ===== ПУТИ К ДИРЕКТОРИЯМ =====
define('ROOT_PATH', __DIR__);
define('DATA_PATH', ROOT_PATH . '/data');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('LOG_PATH', ROOT_PATH . '/logs');
define('UPLOADS_PATH', UPLOAD_PATH); // Совместимость
define('LOGS_PATH', LOG_PATH); // Совместимость

// ===== 1С ИНТЕГРАЦИЯ =====
define('EXCHANGE_1C_PATH', ROOT_PATH . '/1c_exchange');
define('EXCHANGE_1C_IMPORT_PATH', EXCHANGE_1C_PATH . '/import');
define('EXCHANGE_1C_EXPORT_PATH', EXCHANGE_1C_PATH . '/export');

// ===== ПЛАТЁЖНЫЕ СИСТЕМЫ =====
// ЮKassa (рекомендуется - надёжная российская система)
define('YOOKASSA_SHOP_ID', ''); // Ваш Shop ID из личного кабинета ЮKassa
define('YOOKASSA_SECRET_KEY', ''); // Ваш секретный ключ из личного кабинета

// Robokassa (альтернатива)
define('ROBOKASSA_LOGIN', ''); // Ваш логин
define('ROBOKASSA_PASSWORD1', ''); // Пароль #1
define('ROBOKASSA_PASSWORD2', ''); // Пароль #2

// CloudPayments
define('CLOUDPAYMENTS_PUBLIC_ID', ''); // Public ID
define('CLOUDPAYMENTS_API_SECRET', ''); // API Secret

// Т-Банк (Tinkoff)
define('TINKOFF_TERMINAL_KEY', ''); // Terminal Key
define('TINKOFF_PASSWORD', ''); // Пароль для терминала

// Настройки платежей
define('PAYMENT_CURRENCY', 'RUB');
define('PAYMENT_SUCCESS_URL', BASE_URL . '/pages/payment-success.php');
define('PAYMENT_FAIL_URL', BASE_URL . '/pages/payment-fail.php');
define('PAYMENT_WEBHOOK_URL', BASE_URL . '/api/payment-webhook.php');

// ===== СОЗДАНИЕ ДИРЕКТОРИЙ =====
$directories = [
    DATA_PATH,
    DATA_PATH . '/products',
    DATA_PATH . '/orders', 
    DATA_PATH . '/customers',
    DATA_PATH . '/categories',
    DATA_PATH . '/settings',
    DATA_PATH . '/contacts',
    DATA_PATH . '/delivery_zones',
    DATA_PATH . '/delivery_slots',
    DATA_PATH . '/payments', // Новая директория для платежей
    UPLOAD_PATH,
    UPLOAD_PATH . '/products',
    LOG_PATH,
    LOG_PATH . '/payments', // Логи платежей
    EXCHANGE_1C_PATH,
    EXCHANGE_1C_IMPORT_PATH,
    EXCHANGE_1C_EXPORT_PATH
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// ===== БЕЗОПАСНОСТЬ =====
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', password_hash('admin123', PASSWORD_DEFAULT));

// ===== НАСТРОЙКИ СЕССИИ =====
@ini_set('session.cookie_httponly', 1);
@ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
@ini_set('session.use_strict_mode', 1);
@ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// ===== TIMEZONE =====
date_default_timezone_set('Europe/Moscow');

// ===== ПОДКЛЮЧЕНИЕ БАЗЫ ДАННЫХ =====
require_once ROOT_PATH . '/database.php';

// ===== КЛАСС НАСТРОЕК =====
class Settings {
    private $db;
    private static $instance = null;
    private $cache = null;

    private function __construct($database = null) {
        global $db;
        $this->db = $database ?: $db;
    }

    public static function getInstance($database = null) {
        if (self::$instance === null) {
            self::$instance = new self($database);
        }
        return self::$instance;
    }

    public function get($key, $default = null) {
        try {
            if ($this->cache === null) {
                $this->cache = $this->db->find('settings', 'main') ?: [];
            }
            return $this->cache[$key] ?? $default;
        } catch (Exception $e) {
            error_log("Settings get error: " . $e->getMessage());
            return $default;
        }
    }

    public function set($key, $value) {
        try {
            if ($this->cache === null) {
                $this->cache = $this->db->find('settings', 'main') ?: [];
            }
            $this->cache[$key] = $value;
            $this->cache['updated_at'] = date('Y-m-d H:i:s');
            return $this->db->save('settings', $this->cache, 'main');
        } catch (Exception $e) {
            error_log("Settings set error: " . $e->getMessage());
            return false;
        }
    }

    public function getAll() {
        try {
            if ($this->cache === null) {
                $this->cache = $this->db->find('settings', 'main') ?: [];
            }
            return $this->cache;
        } catch (Exception $e) {
            error_log("Settings getAll error: " . $e->getMessage());
            return [];
        }
    }
}

// ===== ЗОНЫ ДОСТАВКИ =====
class DeliveryZones {
    private $db;
    private static $instance = null;
    private $cache = null;

    private function __construct($database = null) {
        global $db;
        $this->db = $database ?: $db;
        $this->initDefaultZones();
    }

    public static function getInstance($database = null) {
        if (self::$instance === null) {
            self::$instance = new self($database);
        }
        return self::$instance;
    }

    private function initDefaultZones() {
        try {
            $zones = $this->getAll();
            if (empty($zones)) {
                $defaultZones = [
                    [
                        'name' => 'Зона 1 (Центр)',
                        'min_order_amount' => 800,
                        'delivery_cost' => 0,
                        'delivery_time_min' => 40,
                        'delivery_time_max' => 60,
                        'color' => '#10b981',
                        'streets' => ['Центральная', 'Ленина', 'Советская'],
                        'coordinates' => []
                    ],
                    [
                        'name' => 'Зона 2 (Районы)',
                        'min_order_amount' => 1200,
                        'delivery_cost' => 150,
                        'delivery_time_min' => 60,
                        'delivery_time_max' => 90,
                        'color' => '#f59e0b',
                        'streets' => ['Пушкина', 'Гагарина', 'Мира'],
                        'coordinates' => []
                    ],
                    [
                        'name' => 'Зона 3 (Отдалённые)',
                        'min_order_amount' => 1500,
                        'delivery_cost' => 300,
                        'delivery_time_min' => 90,
                        'delivery_time_max' => 120,
                        'color' => '#ef4444',
                        'streets' => ['Загородная', 'Дачная'],
                        'coordinates' => []
                    ]
                ];

                foreach ($defaultZones as $zone) {
                    $this->db->save('delivery_zones', $zone);
                }
                $this->cache = null;
            }
        } catch (Exception $e) {
            error_log("DeliveryZones init error: " . $e->getMessage());
        }
    }

    public function getAll() {
        try {
            if ($this->cache === null) {
                $this->cache = $this->db->findAll('delivery_zones') ?: [];
            }
            return $this->cache;
        } catch (Exception $e) {
            error_log("DeliveryZones getAll error: " . $e->getMessage());
            return [];
        }
    }

    public function getById($id) {
        try {
            return $this->db->find('delivery_zones', $id);
        } catch (Exception $e) {
            return null;
        }
    }

    public function detectZoneByAddress($street, $house = null) {
        $zones = $this->getAll();

        foreach ($zones as $zone) {
            if (!empty($zone['streets'])) {
                foreach ($zone['streets'] as $zoneStreet) {
                    if (mb_stripos($street, $zoneStreet) !== false) {
                        return $zone;
                    }
                }
            }
        }

        return $zones[1] ?? $zones[0] ?? null;
    }

    public function detectZoneByCoordinates($lat, $lng) {
        $zones = $this->getAll();

        foreach ($zones as $zone) {
            if (!empty($zone['coordinates']) && $this->isPointInPolygon($lat, $lng, $zone['coordinates'])) {
                return $zone;
            }
        }

        return null;
    }

    private function isPointInPolygon($lat, $lng, $polygon) {
        $inside = false;
        $count = count($polygon);

        for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
            $xi = $polygon[$i]['lat'];
            $yi = $polygon[$i]['lng'];
            $xj = $polygon[$j]['lat'];
            $yj = $polygon[$j]['lng'];

            $intersect = (($yi > $lng) != ($yj > $lng))
                && ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi) + $xi);

            if ($intersect) $inside = !$inside;
        }

        return $inside;
    }
}

// ===== СЛОТЫ ДОСТАВКИ =====
class DeliverySlots {
    private $db;
    private static $instance = null;

    private function __construct($database = null) {
        global $db;
        $this->db = $database ?: $db;
    }

    public static function getInstance($database = null) {
        if (self::$instance === null) {
            self::$instance = new self($database);
        }
        return self::$instance;
    }

    public function getAvailableSlots($date, $type = 'delivery') {
        try {
            $slots = $this->db->findAll('delivery_slots', [
                'date' => $date,
                'type' => $type,
                'status' => 'available'
            ]);

            if (!empty($slots)) {
                usort($slots, function($a, $b) {
                    return strcmp($a['time'] ?? '', $b['time'] ?? '');
                });
                return $slots;
            }

            return $this->getDefaultSlots($date, $type);
        } catch (Exception $e) {
            error_log("DeliverySlots getAvailableSlots error: " . $e->getMessage());
            return $this->getDefaultSlots($date, $type);
        }
    }

    private function getDefaultSlots($date, $type) {
        $slots = [];
        $start = $type === 'pickup' ? 10 : 11;
        $end = 22;

        for ($hour = $start; $hour < $end; $hour++) {
            $slots[] = [
                'id' => "{$date}_{$hour}:00",
                'date' => $date,
                'time' => sprintf('%02d:00', $hour),
                'time_end' => sprintf('%02d:30', $hour),
                'type' => $type,
                'status' => 'available',
                'capacity' => 10
            ];

            $slots[] = [
                'id' => "{$date}_{$hour}:30",
                'date' => $date,
                'time' => sprintf('%02d:30', $hour),
                'time_end' => sprintf('%02d:00', $hour + 1),
                'type' => $type,
                'status' => 'available',
                'capacity' => 10
            ];
        }

        return $slots;
    }

    public function saveSlots($slots) {
        foreach ($slots as $slot) {
            try {
                $this->db->save('delivery_slots', $slot, $slot['id'] ?? null);
            } catch (Exception $e) {
                error_log("DeliverySlots saveSlots error: " . $e->getMessage());
            }
        }
    }

    public function bookSlot($slotId) {
        try {
            $slot = $this->db->find('delivery_slots', $slotId);

            if ($slot) {
                $slot['capacity'] = max(0, ($slot['capacity'] ?? 10) - 1);
                if ($slot['capacity'] === 0) {
                    $slot['status'] = 'full';
                }
                $this->db->save('delivery_slots', $slot, $slotId);
                return true;
            }
        } catch (Exception $e) {
            error_log("DeliverySlots bookSlot error: " . $e->getMessage());
        }

        return false;
    }
}

// ===== КЛИЕНТЫ =====
class Customers {
    private $db;
    private static $instance = null;

    private function __construct($database = null) {
        global $db;
        $this->db = $database ?: $db;
    }

    public static function getInstance($database = null) {
        if (self::$instance === null) {
            self::$instance = new self($database);
        }
        return self::$instance;
    }

    public function findByPhone($phone) {
        try {
            $cleanPhone = preg_replace('/\D/', '', $phone);
            $customers = $this->db->findAll('customers');

            foreach ($customers as $customer) {
                $customerPhone = preg_replace('/\D/', '', $customer['phone'] ?? '');
                if ($customerPhone === $cleanPhone) {
                    return $customer;
                }
            }
        } catch (Exception $e) {
            error_log("Customers findByPhone error: " . $e->getMessage());
        }

        return null;
    }

    public function saveCustomer($data) {
        try {
            $phone = preg_replace('/\D/', '', $data['phone']);
            $existing = $this->findByPhone($phone);

            if ($existing) {
                $customerId = $existing['id'];
                $data['id'] = $customerId;
                $data['total_orders'] = ($existing['total_orders'] ?? 0) + 1;
                $data['total_spent'] = ($existing['total_spent'] ?? 0) + ($data['order_amount'] ?? 0);
                $data['last_order_date'] = date('Y-m-d H:i:s');

                if (!empty($data['address'])) {
                    $addresses = $existing['addresses'] ?? [];
                    $newAddress = [
                        'label' => $data['address_label'] ?? 'Адрес ' . (count($addresses) + 1),
                        'street' => $data['street'] ?? '',
                        'house' => $data['house'] ?? '',
                        'entrance' => $data['entrance'] ?? '',
                        'floor' => $data['floor'] ?? '',
                        'apartment' => $data['apartment'] ?? '',
                        'full_address' => $data['address'],
                        'coordinates' => $data['coordinates'] ?? null
                    ];

                    $isDuplicate = false;
                    foreach ($addresses as $addr) {
                        if (($addr['full_address'] ?? '') === $newAddress['full_address']) {
                            $isDuplicate = true;
                            break;
                        }
                    }

                    if (!$isDuplicate) {
                        $addresses[] = $newAddress;
                        $data['addresses'] = $addresses;
                    } else {
                        $data['addresses'] = $addresses;
                    }
                }
            } else {
                $data['total_orders'] = 1;
                $data['total_spent'] = $data['order_amount'] ?? 0;
                $data['first_order_date'] = date('Y-m-d H:i:s');
                $data['last_order_date'] = date('Y-m-d H:i:s');
                $data['bonus_balance'] = 0;
                $data['certificates'] = [];
                $data['addresses'] = [];

                if (!empty($data['address'])) {
                    $data['addresses'][] = [
                        'label' => 'Адрес 1',
                        'street' => $data['street'] ?? '',
                        'house' => $data['house'] ?? '',
                        'entrance' => $data['entrance'] ?? '',
                        'floor' => $data['floor'] ?? '',
                        'apartment' => $data['apartment'] ?? '',
                        'full_address' => $data['address'],
                        'coordinates' => $data['coordinates'] ?? null
                    ];
                }

                $customerId = null;
            }

            return $this->db->save('customers', $data, $customerId);
        } catch (Exception $e) {
            error_log("Customers saveCustomer error: " . $e->getMessage());
            return false;
        }
    }

    public function addBonus($customerId, $amount, $description) {
        try {
            $customer = $this->db->find('customers', $customerId);

            if ($customer) {
                $customer['bonus_balance'] = ($customer['bonus_balance'] ?? 0) + $amount;
                $customer['bonus_history'] = $customer['bonus_history'] ?? [];
                $customer['bonus_history'][] = [
                    'date' => date('Y-m-d H:i:s'),
                    'amount' => $amount,
                    'description' => $description
                ];

                $this->db->save('customers', $customer, $customerId);
                return true;
            }
        } catch (Exception $e) {
            error_log("Customers addBonus error: " . $e->getMessage());
        }

        return false;
    }

    public function addCertificate($customerId, $certificate) {
        try {
            $customer = $this->db->find('customers', $customerId);

            if ($customer) {
                $customer['certificates'] = $customer['certificates'] ?? [];
                $customer['certificates'][] = [
                    'code' => $certificate['code'],
                    'value' => $certificate['value'],
                    'type' => $certificate['type'],
                    'product_id' => $certificate['product_id'] ?? null,
                    'expires_at' => $certificate['expires_at'] ?? null,
                    'status' => 'active',
                    'added_at' => date('Y-m-d H:i:s'),
                    'from_1c' => true
                ];

                $this->db->save('customers', $customer, $customerId);
                return true;
            }
        } catch (Exception $e) {
            error_log("Customers addCertificate error: " . $e->getMessage());
        }

        return false;
    }
}

// ===== ФУНКЦИИ-ПОМОЩНИКИ =====

function formatPrice($price) {
    return number_format(floatval($price), 0, '', ' ') . ' ₽';
}

function formatDate($date) {
    if (empty($date)) return '';
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    return $timestamp ? date('d.m.Y H:i', $timestamp) : '';
}

function generateSlug($text) {
    if (empty($text)) return '';

    $transliteration = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ь' => '', 'ы' => 'y', 'ъ' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
    ];

    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, $transliteration);
    $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function uploadFile($file, $path = 'general') {
    if (!isset($file['tmp_name']) || !$file['tmp_name'] || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = mime_content_type($file['tmp_name']);

    if (!in_array($fileType, $allowedTypes)) {
        return false;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }

    $uploadDir = UPLOAD_PATH . '/' . $path . '/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileName = time() . '_' . uniqid() . '.' . $extension;
    $filePath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return '/uploads/' . $path . '/' . $fileName;
    }

    return false;
}

function check_admin_auth() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function isAuthenticated() {
    return check_admin_auth();
}

// ИСПРАВЛЕНО: используем абсолютный путь без переменных
function requireAuth() {
    if (!check_admin_auth()) {
        if (isAjax()) {
            jsonResponse(['error' => 'Необходима авторизация'], 401);
        } else {
            header('Location: /admin/login.php', true, 302);
            exit;
        }
    }
}

function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function set_cors_headers() {
    if (!headers_sent()) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

function jsonResponse($data, $status = 200) {
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_response($data, $status_code = 200) {
    jsonResponse($data, $status_code);
}

function logger($message, $level = 'info', $file = 'app') {
    try {
        $logFile = LOG_PATH . '/' . $file . '_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        error_log("Logger error: " . $e->getMessage());
    }
}

function log_message($message, $type = 'info') {
    logger($message, $type);
}

function sanitize_input($input) {
    if (is_array($input)) {
        return array_map('sanitize_input', $input);
    }
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

function clean_phone($phone) {
    if (empty($phone)) return '';
    $phone = preg_replace('/[^\d]/', '', $phone);
    if (strlen($phone) === 11 && substr($phone, 0, 1) === '8') {
        $phone = '7' . substr($phone, 1);
    }
    return $phone;
}

function format_phone($phone) {
    $clean = clean_phone($phone);
    if (strlen($clean) === 11) {
        return '+' . substr($clean, 0, 1) . ' ' . substr($clean, 1, 3) . ' ' . 
               substr($clean, 4, 3) . '-' . substr($clean, 7, 2) . '-' . substr($clean, 9, 2);
    }
    return $phone;
}

function generate_random_string($length = 10) {
    return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/62))), 1, $length);
}

function get_post($key, $default = null) {
    return isset($_POST[$key]) ? sanitize_input($_POST[$key]) : $default;
}

function get_get($key, $default = null) {
    return isset($_GET[$key]) ? sanitize_input($_GET[$key]) : $default;
}

function debug($data, $exit = false) {
    if (!PRODUCTION) {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        if ($exit) exit;
    }
}

// ===== ВАЛИДАЦИЯ =====
class Validator {
    public static function required($value) {
        return !empty(trim($value ?? ''));
    }

    public static function email($email) {
        return filter_var($email ?? '', FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function phone($phone) {
        if (empty($phone)) return false;
        $phone = preg_replace('/[^\d]/', '', $phone);
        return strlen($phone) === 11 && in_array(substr($phone, 0, 1), ['7', '8']);
    }

    public static function price($price) {
        return is_numeric($price) && floatval($price) >= 0;
    }

    public static function length($value, $min = 0, $max = null) {
        $length = mb_strlen(trim($value ?? ''));
        if ($length < $min) return false;
        if ($max && $length > $max) return false;
        return true;
    }

    public static function url($url) {
        return filter_var($url ?? '', FILTER_VALIDATE_URL) !== false;
    }

    public static function integer($value, $min = null, $max = null) {
        if (!is_numeric($value)) return false;
        $int = intval($value);
        if ($min !== null && $int < $min) return false;
        if ($max !== null && $int > $max) return false;
        return true;
    }
}

// ===== СОЗДАЁМ ГЛОБАЛЬНЫЕ ЭКЗЕМПЛЯРЫ =====
$settings = Settings::getInstance($db);
$deliveryZones = DeliveryZones::getInstance($db);
$deliverySlots = DeliverySlots::getInstance($db);
$customers = Customers::getInstance($db);

// ===== КОНСТАНТА ЗАГРУЗКИ =====
define('CONFIG_LOADED', true);

// ===== ЛОГИРОВАНИЕ ЗАПУСКА =====
if (!PRODUCTION) {
    logger("Application started. Request: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']}", 'debug');
}