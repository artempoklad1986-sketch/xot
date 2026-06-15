<?php
/**
 * database.php - Файловая база данных в PHP
 * Версия: 6.2.0 MEGA ULTIMATE EDITION - ИСПРАВЛЕНИЕ ОСТАТКОВ И КАТЕГОРИЙ
 *
 * 🔥 ЧТО ИСПРАВЛЕНО В v6.2.0:
 * ========================================
 * ✅ КРИТИЧНО: Остаток = 0 из 1С = БЕСКОНЕЧНЫЙ СКЛАД
 *    - Добавлено поле unlimited_stock (boolean)
 *    - Добавлено поле stock_quantity (int) - реальный остаток
 *    - stock = 0 → unlimited_stock = true, stock_quantity = 0
 *    - stock > 0 → unlimited_stock = false, stock_quantity = stock
 *    - Автоматический пересчет при импорте из 1С
 *
 * ✅ КРИТИЧНО: Парсинг категорий из ValueTable XML
 *    - Исправлен парсинг поля "Родитель" (CatalogRef.Номенклатура)
 *    - Извлечение текстового названия категории из XML
 *    - Автосоздание категорий с правильными именами
 *    - Сопоставление по id_Родителя
 *
 * 🎯 ВСЕ ИЗ v6.0.0 СОХРАНЕНО:
 * ========================================
 * ✅ Система акций с автоподарками
 * ✅ Ограничения на отмену/редактирование заказов
 * ✅ Полная интеграция сертификатов
 * ✅ Система адресов клиентов
 * ✅ Подарки от администратора
 * ✅ Система аккаунтов клиентов
 * ✅ Система слотов с секциями
 * ✅ Smart Merge, Multi-ID, Diff Tracking
 * ✅ Интеграция с 1С v17.2
 *
 * @version 6.2.0 MEGA ULTIMATE
 * @date 2025-10-08
 * @author Sasha's Sushi Development Team
 * @api_compatibility 1C v17.2+
 */

class Database {
   public $dataPath;
    private $cache = [];
    private $schemas = [];
    private $relations = [];

    // Кэши
    private $processedOrderIds = [];
    private $orderStructureHashes = [];
    private $slotsCache = [];
    private $zoneSlots = [];
    private $activePromotions = [];

    // Системы отслеживания
    private $diffTracking = [];
    private $fileTracking = [];
    private $batchQueue = [];
    private $rollbackPoints = [];

    // 🎯 НОВОЕ v6.0.0: Движок акций
    private $promotionsEngine = null;

    // ⚙️ РАСШИРЕННАЯ КОНФИГУРАЦИЯ v6.2.0
    private $config = [
        // === БАЗОВЫЕ НАСТРОЙКИ ===
        'enable_smart_merge' => true,
        'enable_file_tracking' => true,
        'enable_diff_tracking' => true,
        'enable_structure_hash' => true,
        'batch_size' => 50,
        'conflict_resolution' => 'newer',
        'merge_strategy' => 'smart',
        'skip_duplicate_orders' => true,
        'strict_order_validation' => true,

        // === XML ЭКСПОРТ ===
        'use_single_xml_export' => true,
        'single_xml_filename' => 'orders_export.xml',
        'include_processed_in_export' => false,
        'xml_export_format' => 'commerceml',
        'auto_apply_1c_updates' => true,

        // === СИСТЕМА СЛОТОВ ===
        'slots_enabled' => true,
        'auto_book_slots' => true,
        'auto_release_on_cancel' => true,
        'auto_release_on_delivery' => true,
        'default_slot_sections' => 10,
        'min_slot_sections' => 1,
        'max_slot_sections' => 50,
        'slot_booking_timeout' => 300,
        'allow_overbooking' => false,
        'slots_cache_ttl' => 60,

        // 🎯 НОВЫЕ НАСТРОЙКИ v6.0.0: АКЦИИ
        'promotions_enabled' => true,
        'auto_apply_promotions' => true,
        'promotions_animation' => true,
        'check_promotions_on_cart_change' => true,
        'allow_multiple_promotions' => true,
        'promo_priority' => 'highest_discount', // highest_discount | first_match | all

        // 🎯 НОВЫЕ НАСТРОЙКИ v6.0.0: ОГРАНИЧЕНИЯ ЗАКАЗОВ
        'order_cancel_hours_limit' => 2, // Клиент может отменить за 2 часа
        'order_edit_hours_limit' => 1,   // Клиент может редактировать за 1 час
        'admin_can_edit_delivered' => false, // Админ не может редактировать доставленные

        // 🎯 НОВЫЕ НАСТРОЙКИ v6.0.0: СЕРТИФИКАТЫ
        'certificates_enabled' => true,
        'certificate_partial_payment' => true,
        'certificate_auto_apply' => true,

        // 🎯 НОВЫЕ НАСТРОЙКИ v6.0.0: АДРЕСА
        'multiple_addresses_enabled' => true,
        'address_coordinates_enabled' => true,
        'address_auto_detect_zone' => true,

        // 🎯 НОВЫЕ НАСТРОЙКИ v6.0.0: ВАЛИДАЦИЯ
        'validate_sticks_count' => true, // Палочек <= позиций
        'auto_correct_sticks_count' => true,
        'strict_zone_validation' => true,

        // 🎯 НОВЫЕ НАСТРОЙКИ v6.0.0: КЛИЕНТЫ
        'customer_passwords_enabled' => true,
        'password_min_length' => 6,
        'require_email_verification' => false,
        'allow_multiple_phones' => true,

        // 🎯 НОВЫЕ НАСТРОЙКИ v6.0.0: UI
        'jobs_banner_manageable' => true,
        'show_delivery_toggle_on_main' => false, // По ТЗ убрать с главной

        // 🔥 НОВЫЕ НАСТРОЙКИ v6.2.0: ОСТАТКИ
        'stock_zero_means_unlimited' => true, // 0 из 1С = бесконечный склад
        'auto_calculate_stock' => true, // Автопересчет unlimited_stock
        'hide_out_of_stock' => false, // Скрывать товары с 0 остатком (если не unlimited)
    ];

    public function __construct($dataPath = null) {
        if ($dataPath === null) {
            $dataPath = __DIR__ . '/data/';
        }

        $dataPath = rtrim($dataPath, '/') . '/';
        $this->dataPath = $dataPath;

        $this->initDirectories();
        $this->initSchemas();
        $this->initRelations();
        $this->loadConfig();
        $this->loadProcessedOrdersCache();
        $this->loadSlotsCache();
        $this->loadActivePromotions();

        $this->log("🚀 Database initialized v6.2.0 MEGA ULTIMATE EDITION - FIXED STOCK & CATEGORIES", 'info');
    }

    // ============= 🎯 НОВОЕ v6.0.0: ИНИЦИАЛИЗАЦИЯ =============

    /**
     * 🎯 НОВОЕ v6.0.0: Загрузка активных акций в кэш
     */
    private function loadActivePromotions() {
        if (!$this->config['promotions_enabled']) {
            return;
        }

        try {
            $promotions = $this->findAll('promotions', ['active' => true]);
            $this->activePromotions = [];

            foreach ($promotions as $promo) {
                $this->activePromotions[$promo['id']] = $promo;
            }

            $this->log("Promotions cache loaded v6.2.0: " . count($this->activePromotions) . " active promotions", 'info');
        } catch (Exception $e) {
            $this->log("Failed to load promotions cache: " . $e->getMessage(), 'warning');
            $this->activePromotions = [];
        }
    }

    /**
     * 🎯 РАСШИРЕНО v6.0.0: Директории с новыми папками
     */
    private function initDirectories() {
        $dirs = [
            'products',
            'orders',
            'customers',
            'categories',
            '1c_exchange',
            '1c_exchange/orders_import',
            '1c_exchange/xml_single',
            '1c_exchange/slots',
            'settings',
            'logs',
            'users',
            'cart',
            'delivery_zones',
            'delivery_slots',
            'payment_transactions',
            'payment_settings',
            'bonus_history',
            'notifications',
            'promocodes',
            'reviews',
            'wishlists',
            'exports/1c/orders',
            'exports/1c/customers',
            'exports/1c/products',
            'exports/1c/slots',
            'locks',
            'config',
            'diff_tracking',
            'rollback',
            'batch_queue',
            'conflicts',
            'temp',
            'file_tracking',
            'slots_bookings',
            'slots_history',

            // 🎯 НОВЫЕ ДИРЕКТОРИИ v6.0.0
            'promotions',              // Акции
            'customer_addresses',      // Адреса клиентов
            'customer_phones',         // Телефоны клиентов
            'admin_gifts',             // Подарки от админа
            'certificates',            // Сертификаты
            'content_pages',           // Контентные страницы
            'email_queue',             // Очередь email
            'password_resets',         // Сброс паролей
            'sessions',                // Сессии клиентов
        ];

        foreach ($dirs as $dir) {
            $fullPath = $this->dataPath . $dir;
            if (!file_exists($fullPath)) {
                if (!mkdir($fullPath, 0777, true)) {
                    throw new Exception("Failed to create directory: {$fullPath}");
                }
            }

            if (!is_writable($fullPath)) {
                @chmod($fullPath, 0777);
                if (!is_writable($fullPath)) {
                    throw new Exception("Directory not writable: {$fullPath}");
                }
            }
        }
    }

    /**
     * 🔥 РАСШИРЕНО v6.2.0: Схемы с ИСПРАВЛЕННЫМИ полями остатков
     */
    private function initSchemas() {
        $this->schemas = [
            // === ТОВАРЫ === (🔥 ИСПРАВЛЕНО v6.2.0)
            'products' => [
                'name' => ['type' => 'string', 'required' => true, 'max' => 255],
                'description' => ['type' => 'string'],
                'price' => ['type' => 'float', 'required' => true, 'min' => 0],
                'old_price' => ['type' => 'float', 'min' => 0],
                'category_id' => ['type' => 'int', 'foreign_key' => 'categories'],
                'sku' => ['type' => 'string', 'max' => 100],
                'external_id' => ['type' => 'string', 'max' => 100],

                // 🔥 ИСПРАВЛЕНО v6.2.0: Остатки
                'stock' => ['type' => 'int', 'min' => 0, 'default' => 0], // Оригинальное значение из 1С
                'unlimited_stock' => ['type' => 'bool', 'default' => true], // TRUE если stock=0
                'stock_quantity' => ['type' => 'int', 'min' => 0, 'default' => 0], // Реальный остаток (если не unlimited)

                'weight' => ['type' => 'float', 'min' => 0],
                'status' => ['type' => 'enum', 'values' => ['active', 'inactive', 'draft'], 'default' => 'active'],
                'is_new' => ['type' => 'bool', 'default' => false],
                'is_popular' => ['type' => 'bool', 'default' => false],
                'is_light' => ['type' => 'bool', 'default' => false],
                'is_spicy' => ['type' => 'bool', 'default' => false],
                'is_vegetarian' => ['type' => 'bool', 'default' => false],
                'weight_info' => ['type' => 'string'],
                'composition' => ['type' => 'string'],
                'calories' => ['type' => 'float', 'min' => 0],
                'proteins' => ['type' => 'float', 'min' => 0],
                'fats' => ['type' => 'float', 'min' => 0],
                'carbs' => ['type' => 'float', 'min' => 0],
                'image' => ['type' => 'string'],

                // 🎯 НОВОЕ v6.0.0
                'gallery' => ['type' => 'array', 'default' => []], // Галерея изображений
                'is_certificate' => ['type' => 'bool', 'default' => false], // Является ли сертификатом
                'certificate_nominal' => ['type' => 'float', 'min' => 0], // Номинал сертификата

                // 🔥 НОВОЕ v6.1.0
                'parent_name' => ['type' => 'string', 'max' => 255], // Название родителя из 1С
                'is_closed' => ['type' => 'bool', 'default' => false], // ЗапретитьКЗаказу
                'is_hot_roll' => ['type' => 'bool', 'default' => false], // Запеченный ролл
            ],

            // === КАТЕГОРИИ === (сохранено)
            'categories' => [
                'name' => ['type' => 'string', 'required' => true],
                'slug' => ['type' => 'string', 'required' => true, 'unique' => true],
                'description' => ['type' => 'string'],
                'external_id' => ['type' => 'string'],
                'sort_order' => ['type' => 'int', 'min' => 0, 'default' => 0],
                'status' => ['type' => 'enum', 'values' => ['active', 'inactive'], 'default' => 'active'],
                'is_special' => ['type' => 'bool', 'default' => false],
                'attribute_filter' => ['type' => 'string'],
                'icon' => ['type' => 'string'],
                'order' => ['type' => 'int', 'min' => 0, 'default' => 999],
                'product_count' => ['type' => 'int', 'min' => 0, 'default' => 0],
                     'is_visible' => ['type' => 'bool', 'default' => null], // null=авто, 0=скрыто, 1=показано
                'created_from_1c' => ['type' => 'bool', 'default' => false],
            ],

            // === ЗАКАЗЫ === (расширено v6.0.0)
            'orders' => [
                'order_number' => ['type' => 'string', 'required' => true],
                'customer_id' => ['type' => 'int'],
                'customer_name' => ['type' => 'string', 'required' => true],
                'customer_phone' => ['type' => 'string', 'required' => true],
                'customer_email' => ['type' => 'string'],
                'total' => ['type' => 'float', 'required' => true, 'min' => 0],
                'subtotal' => ['type' => 'float', 'min' => 0],
                'delivery_cost' => ['type' => 'float', 'min' => 0],
                'discount' => ['type' => 'float', 'min' => 0],
                'payment_method' => ['type' => 'enum', 'values' => ['cash', 'card', 'online', 'sbp', 'cashless'], 'default' => 'cash'],
                'payment_status' => ['type' => 'enum', 'values' => ['pending', 'paid', 'failed', 'refunded', 'partial'], 'default' => 'pending'],
                'status' => ['type' => 'enum', 'values' => ['new', 'processing', 'confirmed', 'preparing', 'ready', 'delivery', 'delivered', 'completed', 'cancelled'], 'default' => 'new'],
                'delivery_type' => ['type' => 'enum', 'values' => ['delivery', 'pickup'], 'default' => 'delivery'],
                'delivery_address' => ['type' => 'string'],
                'delivery_date' => ['type' => 'string'],
                'delivery_time' => ['type' => 'string'],
                'delivery_zone_id' => ['type' => 'int'],
                'comment' => ['type' => 'string'],

                // Поля 1С
                'is_paid' => ['type' => 'bool', 'default' => false],
                'incoming_doc_number' => ['type' => 'string'],
                'export_id' => ['type' => 'string'],
                'is_exported_1c' => ['type' => 'bool', 'default' => false],
                'manual_promotions' => ['type' => 'bool', 'default' => false],
                'site_status' => ['type' => 'string'],
                'certificate_str' => ['type' => 'string'],

                // Поля v5.x
                'merge_count' => ['type' => 'int', 'min' => 0, 'default' => 0],
                'last_merge_from_1c' => ['type' => 'string'],
                'structure_hash' => ['type' => 'string'],
                'conflict_history' => ['type' => 'array', 'default' => []],
                'exported_in_batch' => ['type' => 'bool', 'default' => false],
                'batch_export_id' => ['type' => 'string'],
                'last_xml_export_at' => ['type' => 'string'],

                // Слоты
                'delivery_slot_id' => ['type' => 'int'],
                'slot_section_number' => ['type' => 'int'],
                'slot_booked_at' => ['type' => 'string'],
                'slot_released_at' => ['type' => 'string'],

                // 🎯 НОВЫЕ ПОЛЯ v6.0.0
                'people_count' => ['type' => 'int', 'min' => 0, 'default' => 1], // Количество палочек
                'applied_promotions' => ['type' => 'array', 'default' => []], // Примененные акции
                'gift_items_count' => ['type' => 'int', 'min' => 0, 'default' => 0], // Количество подарков
                'certificate_applied' => ['type' => 'string'], // Код примененного сертификата
                'certificate_amount' => ['type' => 'float', 'min' => 0, 'default' => 0], // Сумма по сертификату
                'customer_address_id' => ['type' => 'int'], // ID адреса из справочника
                'can_cancel' => ['type' => 'bool', 'default' => true], // Может ли клиент отменить
                'can_edit' => ['type' => 'bool', 'default' => true], // Может ли клиент редактировать
                'cancel_deadline' => ['type' => 'string'], // Крайний срок отмены
                'edit_deadline' => ['type' => 'string'], // Крайний срок редактирования
                'cancelled_at' => ['type' => 'string'],
                'cancelled_by' => ['type' => 'string'], // customer | admin
                'cancel_reason' => ['type' => 'string'],
                'status_history' => ['type' => 'array', 'default' => []], // История смены статусов
            ],

            // === КЛИЕНТЫ === (расширено v6.0.0)
            'customers' => [
                'name' => ['type' => 'string', 'required' => true],
                'phone' => ['type' => 'string', 'required' => true, 'unique' => true],
                'email' => ['type' => 'string'],
                'bonus_balance' => ['type' => 'float', 'min' => 0, 'default' => 0],
                'external_id' => ['type' => 'string'],
                'status' => ['type' => 'enum', 'values' => ['active', 'blocked'], 'default' => 'active'],

                // 🎯 НОВЫЕ ПОЛЯ v6.0.0
                'password_hash' => ['type' => 'string'], // bcrypt хеш пароля
                'email_verified' => ['type' => 'bool', 'default' => false],
                'email_verification_token' => ['type' => 'string'],
                'pending_gifts' => ['type' => 'array', 'default' => []], // Подарки от админа
                'last_login_at' => ['type' => 'string'],
                'login_count' => ['type' => 'int', 'min' => 0, 'default' => 0],
                'orders_count' => ['type' => 'int', 'min' => 0, 'default' => 0],
                'total_spent' => ['type' => 'float', 'min' => 0, 'default' => 0],
            ],

            // 🎯 НОВАЯ ТАБЛИЦА v6.0.0: Телефоны клиентов
            'customer_phones' => [
                'customer_id' => ['type' => 'int', 'required' => true, 'foreign_key' => 'customers'],
                'phone' => ['type' => 'string', 'required' => true],
                'is_primary' => ['type' => 'bool', 'default' => false],
                'verified' => ['type' => 'bool', 'default' => false],
                'label' => ['type' => 'string'], // Мобильный, Рабочий и т.д.
            ],

            // 🎯 НОВАЯ ТАБЛИЦА v6.0.0: Адреса клиентов
            'customer_addresses' => [
                'customer_id' => ['type' => 'int', 'required' => true, 'foreign_key' => 'customers'],
                'label' => ['type' => 'enum', 'values' => ['home', 'work', 'other'], 'default' => 'home'],
                'street' => ['type' => 'string', 'required' => true],
                'house' => ['type' => 'string', 'required' => true],
                'apartment' => ['type' => 'string'],
                'entrance' => ['type' => 'string'],
                'floor' => ['type' => 'string'],
                'intercom' => ['type' => 'string'],
                'coordinates' => ['type' => 'string'], // "lat,lng"
                'is_default' => ['type' => 'bool', 'default' => false],
                'zone_id' => ['type' => 'int', 'foreign_key' => 'delivery_zones'],
                'full_address' => ['type' => 'string'], // Полный адрес строкой
                'notes' => ['type' => 'string'], // Комментарий к адресу
            ],

            // 🎯 НОВАЯ ТАБЛИЦА v6.0.0: Система акций
            'promotions' => [
                'name' => ['type' => 'string', 'required' => true],
                'description' => ['type' => 'string'],
                'type' => ['type' => 'enum', 'values' => ['gift', 'discount', 'bonus'], 'default' => 'gift'],
                'gift_product_id' => ['type' => 'int'], // ID подарочного товара
                'discount_type' => ['type' => 'enum', 'values' => ['percent', 'fixed'], 'default' => 'percent'],
                'discount_value' => ['type' => 'float', 'min' => 0],

                // Условия применения
                'min_sum' => ['type' => 'float', 'min' => 0, 'default' => 0],
                'days_of_week' => ['type' => 'array', 'default' => []], // [1,2,3] - пн,вт,ср
                'time_from' => ['type' => 'string'], // "10:00"
                'time_to' => ['type' => 'string'], // "14:00"
                'holidays_only' => ['type' => 'bool', 'default' => false],
                'specific_dates' => ['type' => 'array', 'default' => []], // Конкретные даты

                // Статус и приоритет
                'active' => ['type' => 'bool', 'default' => true],
                'priority' => ['type' => 'int', 'min' => 0, 'default' => 10], // Чем выше, тем важнее
                'start_date' => ['type' => 'string'],
                'end_date' => ['type' => 'string'],

                // Ограничения
                'max_uses' => ['type' => 'int', 'min' => 0], // Максимум применений
                'current_uses' => ['type' => 'int', 'min' => 0, 'default' => 0],
                'max_uses_per_customer' => ['type' => 'int', 'min' => 0],

                // Визуал
                'image' => ['type' => 'string'],
                'badge_text' => ['type' => 'string'], // Текст бейджа "АКЦИЯ"
                'badge_color' => ['type' => 'string', 'default' => '#FF4046'],
            ],

            // 🎯 НОВАЯ ТАБЛИЦА v6.0.0: Применение акций
            'promotion_usages' => [
                'promotion_id' => ['type' => 'int', 'required' => true, 'foreign_key' => 'promotions'],
                'order_id' => ['type' => 'int', 'required' => true, 'foreign_key' => 'orders'],
                'customer_id' => ['type' => 'int', 'foreign_key' => 'customers'],
                'gift_product_id' => ['type' => 'int'],
                'discount_amount' => ['type' => 'float', 'min' => 0, 'default' => 0],
                'applied_at' => ['type' => 'string'],
            ],

            // 🎯 НОВАЯ ТАБЛИЦА v6.0.0: Подарки от админа
            'admin_gifts' => [
                'customer_id' => ['type' => 'int', 'required' => true, 'foreign_key' => 'customers'],
                'product_id' => ['type' => 'int', 'required' => true, 'foreign_key' => 'products'],
                'admin_id' => ['type' => 'int', 'required' => true],
                'admin_name' => ['type' => 'string'],
                'reason' => ['type' => 'string'],
                'status' => ['type' => 'enum', 'values' => ['pending', 'applied', 'expired'], 'default' => 'pending'],
                'applied_order_id' => ['type' => 'int'],
                'expires_at' => ['type' => 'string'], // Срок действия подарка
            ],

            // === ЗОНЫ ДОСТАВКИ === (сохранено)
            'delivery_zones' => [
                'name' => ['type' => 'string', 'required' => true],
                'delivery_cost' => ['type' => 'float', 'min' => 0, 'default' => 0],
                'min_order' => ['type' => 'float', 'min' => 0, 'default' => 0],
                'delivery_time' => ['type' => 'string'],
                'external_id' => ['type' => 'string'],
                'status' => ['type' => 'enum', 'values' => ['active', 'inactive'], 'default' => 'active'],

                // 🎯 НОВОЕ v6.0.0
                'streets' => ['type' => 'array', 'default' => []], // Список улиц
                'coordinates_polygon' => ['type' => 'array', 'default' => []], // Полигон зоны
                'color' => ['type' => 'string', 'default' => '#10B981'], // Цвет на карте
            ],

            // === СЛОТЫ ДОСТАВКИ === (сохранено из v5.2.0)
'delivery_slots' => [
    'zone_id' => ['type' => 'int', 'required' => false, 'foreign_key' => 'delivery_zones'],
    'date' => ['type' => 'string', 'required' => true],
    'time' => ['type' => 'string', 'required' => true],
    'type' => ['type' => 'enum', 'values' => ['delivery', 'pickup'], 'default' => 'delivery'],
    'total_sections' => ['type' => 'int', 'min' => 1, 'default' => 10],
    'available_sections' => ['type' => 'int', 'min' => 0, 'default' => 10],
    'booked_sections' => ['type' => 'array', 'default' => []],
    'batch_number' => ['type' => 'string', 'required' => false, 'nullable' => true],
    'batch_capacity' => ['type' => 'int', 'min' => 0, 'required' => false],
    'status' => ['type' => 'enum', 'values' => ['active', 'full', 'blocked', 'archived'], 'default' => 'active'],
    'external_id' => ['type' => 'string', 'required' => false, 'nullable' => true],
    'created_from_1c' => ['type' => 'bool', 'default' => false],
    'last_sync_1c' => ['type' => 'string', 'required' => false, 'nullable' => true],
    'temporary_locks' => ['type' => 'array', 'default' => []],
],

            // === КОРЗИНА === (расширено v6.0.0)
            'cart' => [
                'session_id' => ['type' => 'string', 'required' => true],
                'customer_id' => ['type' => 'int'],
                'items' => ['type' => 'array', 'default' => []],
                'subtotal' => ['type' => 'float', 'min' => 0, 'default' => 0],
                'total' => ['type' => 'float', 'min' => 0, 'default' => 0],
                'delivery_cost' => ['type' => 'float', 'min' => 0, 'default' => 0],
                'status' => ['type' => 'enum', 'values' => ['active', 'completed', 'abandoned'], 'default' => 'active'],
                'temp_slot_id' => ['type' => 'int'],
                'temp_slot_expires_at' => ['type' => 'string'],

                // 🎯 НОВЫЕ ПОЛЯ v6.0.0
                'applied_promotions' => ['type' => 'array', 'default' => []], // Примененные акции
                'certificate_code' => ['type' => 'string'], // Примененный сертификат
                'certificate_amount' => ['type' => 'float', 'min' => 0, 'default' => 0],
                'discount' => ['type' => 'float', 'min' => 0, 'default' => 0],
                'gift_items' => ['type' => 'array', 'default' => []], // Подарочные товары
            ],

            // 🎯 НОВАЯ ТАБЛИЦА v6.0.0: Сертификаты (расширено)
            'certificates' => [
                'code' => ['type' => 'string', 'required' => true, 'unique' => true],
                'value' => ['type' => 'float', 'required' => true, 'min' => 0],
                'balance' => ['type' => 'float', 'required' => true, 'min' => 0],
                'type' => ['type' => 'enum', 'values' => ['monetary', 'product', 'discount'], 'default' => 'monetary'],
                'status' => ['type' => 'enum', 'values' => ['active', 'used', 'expired', 'blocked'], 'default' => 'active'],
                'issued_at' => ['type' => 'string'],
                'expires_at' => ['type' => 'string'],
                'used_at' => ['type' => 'string'],
                'synced_from_1c' => ['type' => 'bool', 'default' => false],
                'external_id' => ['type' => 'string'],

                // 🎯 НОВОЕ v6.0.0
                'usage_history' => ['type' => 'array', 'default' => []], // История использования
                'linked_customer_id' => ['type' => 'int'], // Привязка к клиенту
                'issued_by' => ['type' => 'string'], // Кто выдал
            ],

            // 🎯 НОВАЯ ТАБЛИЦА v6.0.0: История использования сертификатов
            'certificate_usages' => [
                'certificate_id' => ['type' => 'int', 'required' => true, 'foreign_key' => 'certificates'],
                'order_id' => ['type' => 'int', 'required' => true, 'foreign_key' => 'orders'],
                'amount_used' => ['type' => 'float', 'required' => true, 'min' => 0],
                'balance_before' => ['type' => 'float', 'min' => 0],
                'balance_after' => ['type' => 'float', 'min' => 0],
                'used_at' => ['type' => 'string'],
            ],

            // 🎯 НОВАЯ ТАБЛИЦА v6.0.0: Контентные страницы
            'content_pages' => [
                'slug' => ['type' => 'string', 'required' => true, 'unique' => true],
                'title' => ['type' => 'string', 'required' => true],
                'content' => ['type' => 'string', 'required' => true],
                'meta_description' => ['type' => 'string'],
                'meta_keywords' => ['type' => 'string'],
                'status' => ['type' => 'enum', 'values' => ['draft', 'published'], 'default' => 'published'],
                'show_in_footer' => ['type' => 'bool', 'default' => true],
                'order' => ['type' => 'int', 'min' => 0, 'default' => 999],
            ],

            // 🎯 НОВАЯ ТАБЛИЦА v6.0.0: Очередь email
            'email_queue' => [
                'to_email' => ['type' => 'string', 'required' => true],
                'to_name' => ['type' => 'string'],
                'subject' => ['type' => 'string', 'required' => true],
                'body' => ['type' => 'string', 'required' => true],
                'template' => ['type' => 'string'],
                'template_vars' => ['type' => 'array', 'default' => []],
                'status' => ['type' => 'enum', 'values' => ['pending', 'sent', 'failed'], 'default' => 'pending'],
                'attempts' => ['type' => 'int', 'min' => 0, 'default' => 0],
                'last_attempt_at' => ['type' => 'string'],
                'sent_at' => ['type' => 'string'],
                'error' => ['type' => 'string'],
            ],

            // === СОХРАНЕННЫЕ ТАБЛИЦЫ ИЗ v5.x ===

            'file_tracking' => [
                'file_hash' => ['type' => 'string', 'required' => true],
                'filename' => ['type' => 'string', 'required' => true],
                'filepath' => ['type' => 'string'],
                'status' => ['type' => 'enum', 'values' => ['processing', 'completed', 'failed'], 'default' => 'processing'],
                'processed_at' => ['type' => 'string'],
                'file_size' => ['type' => 'int', 'min' => 0],
                'results' => ['type' => 'array', 'default' => []],
                'error' => ['type' => 'string']
            ],

            'diff_tracking' => [
                'order_id' => ['type' => 'int', 'required' => true],
                'changes' => ['type' => 'array', 'default' => []],
                'has_conflicts' => ['type' => 'array', 'default' => []],
                'timestamp' => ['type' => 'string'],
                'source' => ['type' => 'string']
            ],

            'xml_exports' => [
                'export_id' => ['type' => 'string', 'required' => true],
                'filename' => ['type' => 'string', 'required' => true],
                'orders_count' => ['type' => 'int', 'min' => 0],
                'file_size' => ['type' => 'int', 'min' => 0],
                'status' => ['type' => 'enum', 'values' => ['pending', 'completed', 'failed'], 'default' => 'pending'],
                'created_at' => ['type' => 'string'],
                'processed_by_1c_at' => ['type' => 'string']
            ],

            'slots_history' => [
                'slot_id' => ['type' => 'int', 'required' => true],
                'order_id' => ['type' => 'int'],
                'action' => ['type' => 'enum', 'values' => ['book', 'release', 'add_section', 'remove_section', 'block', 'unblock'], 'required' => true],
                'section_number' => ['type' => 'int'],
                'sections_before' => ['type' => 'int'],
                'sections_after' => ['type' => 'int'],
                'user_id' => ['type' => 'int'],
                'comment' => ['type' => 'string'],
                'timestamp' => ['type' => 'string'],
            ],

            'slot_bookings' => [
                'slot_id' => ['type' => 'int', 'required' => true],
                'order_id' => ['type' => 'int', 'required' => true],
                'section_number' => ['type' => 'int', 'required' => true],
                'booked_at' => ['type' => 'string', 'required' => true],
                'released_at' => ['type' => 'string'],
                'status' => ['type' => 'enum', 'values' => ['active', 'released', 'expired'], 'default' => 'active'],
                'booking_type' => ['type' => 'enum', 'values' => ['order', 'temporary'], 'default' => 'order'],
                'session_id' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * 🎯 РАСШИРЕНО v6.0.0: Связи между таблицами
     */
    private function initRelations() {
        $this->relations = [
            'products' => [
                'category' => ['table' => 'categories', 'foreign_key' => 'category_id', 'type' => 'belongsTo']
            ],
            'orders' => [
                'customer' => ['table' => 'customers', 'foreign_key' => 'customer_id', 'type' => 'belongsTo'],
                'delivery_slot' => ['table' => 'delivery_slots', 'foreign_key' => 'delivery_slot_id', 'type' => 'belongsTo'],
                'customer_address' => ['table' => 'customer_addresses', 'foreign_key' => 'customer_address_id', 'type' => 'belongsTo'],
            ],
            'customers' => [
                'orders' => ['table' => 'orders', 'foreign_key' => 'customer_id', 'type' => 'hasMany'],
                'addresses' => ['table' => 'customer_addresses', 'foreign_key' => 'customer_id', 'type' => 'hasMany'],
                'phones' => ['table' => 'customer_phones', 'foreign_key' => 'customer_id', 'type' => 'hasMany'],
                'pending_gifts' => ['table' => 'admin_gifts', 'foreign_key' => 'customer_id', 'type' => 'hasMany'],
            ],
            'categories' => [
                'products' => ['table' => 'products', 'foreign_key' => 'category_id', 'type' => 'hasMany']
            ],
            'cart' => [
                'customer' => ['table' => 'customers', 'foreign_key' => 'customer_id', 'type' => 'belongsTo']
            ],
            'delivery_slots' => [
                'zone' => ['table' => 'delivery_zones', 'foreign_key' => 'zone_id', 'type' => 'belongsTo'],
                'orders' => ['table' => 'orders', 'foreign_key' => 'delivery_slot_id', 'type' => 'hasMany']
            ],
            'delivery_zones' => [
                'slots' => ['table' => 'delivery_slots', 'foreign_key' => 'zone_id', 'type' => 'hasMany']
            ],
            'promotions' => [
                'usages' => ['table' => 'promotion_usages', 'foreign_key' => 'promotion_id', 'type' => 'hasMany']
            ],
        ];
    }

    // ============= 🔥 КРИТИЧНО v6.2.0: АВТОМАТИЧЕСКИЙ ПЕРЕСЧЕТ ОСТАТКОВ =============

    /**
     * 🔥 НОВОЕ v6.2.0: Автоматический пересчёт остатков при сохранении
     * 
     * ЛОГИКА:
     * - stock = 0 ИЛИ stock = null → unlimited_stock = true, stock_quantity = 0, status = active
     * - stock > 0 И stock < 9999 → unlimited_stock = false, stock_quantity = stock
     * - stock >= 9999 → unlimited_stock = true, stock_quantity = 0 (1С обозначает 9999 как бесконечность)
     * 
     * Вызывается ПЕРЕД каждым сохранением товара
     */
    private function autoCalculateStock(&$data) {
        // Проверяем что это товар
        if (!isset($data['name']) && !isset($data['price'])) {
            return; // Это не товар
        }

        if (!$this->config['auto_calculate_stock']) {
            return; // Автопересчет отключен
        }

        // Если есть поле stock - пересчитываем
        if (isset($data['stock']) || array_key_exists('stock', $data)) {
            $stock = $data['stock'];

            // Приводим к числу
            if ($stock === null || $stock === '' || $stock === false) {
                $stock = null;
            } else {
                $stock = intval($stock);
            }

            // 🔥 ПРАВИЛЬНАЯ ЛОГИКА (НЕИЗМЕНЯЕМАЯ!)
            if ($stock === null || $stock === 0) {
                // 🔥 0 из 1С = БЕСКОНЕЧНЫЙ СКЛАД
                $data['unlimited_stock'] = true;
                $data['stock_quantity'] = 0;
                if (!isset($data['status']) || $data['status'] === 'inactive') {
                    $data['status'] = 'active';
                }

                $this->log("🔥 Stock=0 detected → UNLIMITED for product: " . ($data['name'] ?? 'N/A'), 'info');

            } elseif ($stock >= 9999) {
                // 1С обозначает 9999 как бесконечность
                $data['unlimited_stock'] = true;
                $data['stock_quantity'] = 0;
                if (!isset($data['status']) || $data['status'] === 'inactive') {
                    $data['status'] = 'active';
                }

                $this->log("🔥 Stock>=9999 detected → UNLIMITED for product: " . ($data['name'] ?? 'N/A'), 'info');

            } else {
                // Ограниченный остаток
                $data['unlimited_stock'] = false;
                $data['stock_quantity'] = $stock;

                if (!isset($data['status'])) {
                    $data['status'] = ($stock > 0) ? 'active' : 'inactive';
                }

                $this->log("Stock={$stock} detected → LIMITED for product: " . ($data['name'] ?? 'N/A'), 'debug');
            }
        }

        // Если установлены unlimited_stock/stock_quantity напрямую - синхронизируем
        elseif (isset($data['unlimited_stock']) || isset($data['stock_quantity'])) {
            $unlimited = $data['unlimited_stock'] ?? true;
            $quantity = $data['stock_quantity'] ?? 0;

            if ($unlimited) {
                $data['stock'] = 0; // Бесконечный = 0 в старом поле
                $data['stock_quantity'] = 0;
                $data['status'] = 'active';
            } else {
                $data['stock'] = $quantity;
                $data['stock_quantity'] = $quantity;
                $data['status'] = ($quantity > 0) ? 'active' : 'inactive';
            }
        }
    }

    // ============= 🎯 НОВОЕ v6.0.0: СИСТЕМА АКЦИЙ =============

    /**
     * 🎯 НОВОЕ v6.0.0: Проверка и применение акций к корзине
     * 
     * @param array $cart Корзина
     * @param string $deliveryDate Дата доставки (Y-m-d)
     * @param string $deliveryTime Время доставки (H:i)
     * @return array Обновленная корзина с подарками
     */
    public function checkAndApplyPromotions($cart, $deliveryDate = null, $deliveryTime = null) {
        if (!$this->config['promotions_enabled'] || !$this->config['auto_apply_promotions']) {
            return $cart;
        }

        $this->log("🎁 Checking promotions for cart", 'info');

        // Если даты нет - используем текущую
        if (!$deliveryDate) {
            $deliveryDate = date('Y-m-d');
        }
        if (!$deliveryTime) {
            $deliveryTime = date('H:i');
        }

        // 1. Определяем параметры для проверки
        $dayOfWeek = date('N', strtotime($deliveryDate)); // 1-7 (пн-вс)
        $cartTotal = floatval($cart['subtotal'] ?? 0);
        $isHoliday = $this->isHolidayDate($deliveryDate);

        $this->log("Promo params: day={$dayOfWeek}, time={$deliveryTime}, sum={$cartTotal}, holiday={$isHoliday}", 'debug');

        // 2. Получаем подходящие акции
        $suitablePromotions = $this->getSuitablePromotions([
            'day' => $dayOfWeek,
            'time' => $deliveryTime,
            'sum' => $cartTotal,
            'is_holiday' => $isHoliday,
            'date' => $deliveryDate
        ]);

        if (empty($suitablePromotions)) {
            $this->log("No suitable promotions found", 'debug');
            return $cart;
        }

        $this->log("Found " . count($suitablePromotions) . " suitable promotions", 'info');

        // 3. Применяем акции
        if (!isset($cart['applied_promotions'])) {
            $cart['applied_promotions'] = [];
        }
        if (!isset($cart['gift_items'])) {
            $cart['gift_items'] = [];
        }

        $appliedPromotionIds = array_column($cart['applied_promotions'], 'id');

        foreach ($suitablePromotions as $promo) {
            // Пропускаем уже примененные
            if (in_array($promo['id'], $appliedPromotionIds)) {
                continue;
            }

            // Проверяем ограничения
            if (!$this->canApplyPromotion($promo, $cart)) {
                continue;
            }

            // Применяем акцию
            $result = $this->applyPromotion($promo, $cart);

            if ($result['applied']) {
                $cart = $result['cart'];
                $this->log("✅ Promotion applied: {$promo['name']}", 'info');
            }

            // Если не разрешено несколько акций - выходим
            if (!$this->config['allow_multiple_promotions']) {
                break;
            }
        }

        return $cart;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Получение подходящих акций
     */
    private function getSuitablePromotions($params) {
        $dayOfWeek = $params['day'];
        $time = $params['time'];
        $sum = $params['sum'];
        $isHoliday = $params['is_holiday'];
        $date = $params['date'];

        $suitable = [];

        foreach ($this->activePromotions as $promo) {
            // Проверка суммы
            if (!empty($promo['min_sum']) && $sum < $promo['min_sum']) {
                continue;
            }

            // Проверка дня недели
            if (!empty($promo['days_of_week']) && !in_array($dayOfWeek, $promo['days_of_week'])) {
                continue;
            }

            // Проверка времени
            if (!empty($promo['time_from']) && !empty($promo['time_to'])) {
                if ($time < $promo['time_from'] || $time > $promo['time_to']) {
                    continue;
                }
            }

            // Проверка праздников
            if ($promo['holidays_only'] && !$isHoliday) {
                continue;
            }

            // Проверка конкретных дат
            if (!empty($promo['specific_dates']) && !in_array($date, $promo['specific_dates'])) {
                continue;
            }

            // Проверка периода действия
            if (!empty($promo['start_date']) && $date < $promo['start_date']) {
                continue;
            }
            if (!empty($promo['end_date']) && $date > $promo['end_date']) {
                continue;
            }

            // Проверка лимита использований
            if (!empty($promo['max_uses']) && $promo['current_uses'] >= $promo['max_uses']) {
                continue;
            }

            $suitable[] = $promo;
        }

        // Сортируем по приоритету
        usort($suitable, function($a, $b) {
            return ($b['priority'] ?? 10) - ($a['priority'] ?? 10);
        });

        return $suitable;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Проверка возможности применения акции
     */
    private function canApplyPromotion($promo, $cart) {
        // Проверка лимита на клиента
        if (!empty($promo['max_uses_per_customer']) && !empty($cart['customer_id'])) {
            $usages = $this->findAll('promotion_usages', [
                'promotion_id' => $promo['id'],
                'customer_id' => $cart['customer_id']
            ]);

            if (count($usages) >= $promo['max_uses_per_customer']) {
                return false;
            }
        }

        return true;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Применение акции
     */
    private function applyPromotion($promo, $cart) {
        $applied = false;

        switch ($promo['type']) {
            case 'gift':
                if (!empty($promo['gift_product_id'])) {
                    $product = $this->find('products', $promo['gift_product_id']);

                    if ($product) {
                        // Добавляем подарок
                        $cart['gift_items'][] = [
                            'product_id' => $product['id'],
                            'name' => $product['name'],
                            'image' => $product['image'] ?? '',
                            'quantity' => 1,
                            'price' => 0,
                            'subtotal' => 0,
                            'is_gift' => true,
                            'promotion_id' => $promo['id'],
                            'promotion_name' => $promo['name']
                        ];

                        /// НЕ добавляем в items — подарки показываются только через gift_items
// Физическое добавление в корзину делает cart.php v3.0 через validatePromotions()

                        $applied = true;
                    }
                }
                break;

            case 'discount':
                $discountAmount = 0;

                if ($promo['discount_type'] === 'percent') {
                    $discountAmount = ($cart['subtotal'] * $promo['discount_value']) / 100;
                } else {
                    $discountAmount = $promo['discount_value'];
                }

                $cart['discount'] = ($cart['discount'] ?? 0) + $discountAmount;
                $cart['total'] = $cart['subtotal'] + $cart['delivery_cost'] - $cart['discount'];

                $applied = true;
                break;

            case 'bonus':
                // Логика бонусов (если нужна)
                $applied = true;
                break;
        }

        if ($applied) {
            // Записываем применение акции
            $cart['applied_promotions'][] = [
                'id' => $promo['id'],
                'name' => $promo['name'],
                'type' => $promo['type'],
                'applied_at' => date('Y-m-d H:i:s')
            ];
        }

        return ['applied' => $applied, 'cart' => $cart];
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Проверка является ли дата праздником
     */
    private function isHolidayDate($date) {
        // Получаем список праздников из настроек
        $settings = $this->find('settings', 'main');
        $holidays = $settings['holidays'] ?? [];

        // Дефолтные праздники РФ
        $defaultHolidays = [
            '01-01', '01-02', '01-03', '01-04', '01-05', '01-06', '01-07', '01-08', // Новый год
            '02-23', // День защитника отечества
            '03-08', // 8 марта
            '05-01', '05-09', // Праздник весны и труда, День победы
            '06-12', // День России
            '11-04', // День народного единства
        ];

        $holidays = array_merge($defaultHolidays, $holidays);

        $monthDay = date('m-d', strtotime($date));

        return in_array($monthDay, $holidays) || in_array($date, $holidays);
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Удаление акций из корзины (при изменении условий)
     */
    public function removePromotionsFromCart($cart) {
        if (empty($cart['applied_promotions'])) {
            return $cart;
        }

        // Удаляем подарочные товары
        $cart['items'] = array_filter($cart['items'], function($item) {
            return !($item['is_gift'] ?? false);
        });

        // Сбрасываем скидку от акций
        $cart['discount'] = 0;
        $cart['applied_promotions'] = [];
        $cart['gift_items'] = [];

        // Пересчитываем
        $cart = $this->recalculateCart($cart);

        return $cart;
    }

    // ============= 🎯 НОВОЕ v6.0.0: ОГРАНИЧЕНИЯ НА ЗАКАЗЫ =============

    /**
     * 🎯 НОВОЕ v6.0.0: Проверка возможности отмены заказа
     * 
     * @param int $orderId ID заказа
     * @param bool $isAdmin Является ли пользователь администратором
     * @return array ['can_cancel' => bool, 'reason' => string, 'deadline' => string]
     */
    public function canCancelOrder($orderId, $isAdmin = false) {
        $order = $this->find('orders', $orderId);

        if (!$order) {
            return ['can_cancel' => false, 'reason' => 'Заказ не найден'];
        }

        // Если заказ уже отменен или завершен
        if (in_array($order['status'], ['cancelled', 'completed', 'delivered'])) {
            return ['can_cancel' => false, 'reason' => 'Заказ уже завершен или отменен'];
        }

        // Админ может отменить любой не доставленный заказ
        if ($isAdmin) {
            if (in_array($order['status'], ['delivered', 'completed'])) {
                return ['can_cancel' => false, 'reason' => 'Нельзя отменить доставленный заказ'];
            }
            return ['can_cancel' => true, 'reason' => '', 'deadline' => null];
        }

        // Для клиента проверяем время до доставки
        if (empty($order['delivery_date']) || empty($order['delivery_time'])) {
            return ['can_cancel' => true, 'reason' => '', 'deadline' => null];
        }

        $deliveryDateTime = strtotime($order['delivery_date'] . ' ' . $order['delivery_time']);
        $now = time();
        $hoursUntilDelivery = ($deliveryDateTime - $now) / 3600;

        $cancelLimit = $this->config['order_cancel_hours_limit'];

        if ($hoursUntilDelivery < $cancelLimit) {
            $deadline = date('Y-m-d H:i', $deliveryDateTime - ($cancelLimit * 3600));
            return [
                'can_cancel' => false, 
                'reason' => "Отменить заказ можно не позднее чем за {$cancelLimit} часа до доставки",
                'deadline' => $deadline
            ];
        }

        $deadline = date('Y-m-d H:i', $deliveryDateTime - ($cancelLimit * 3600));

        return [
            'can_cancel' => true, 
            'reason' => '',
            'deadline' => $deadline,
            'hours_left' => round($hoursUntilDelivery, 1)
        ];
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Проверка возможности редактирования заказа
     * 
     * @param int $orderId ID заказа
     * @param bool $isAdmin Является ли пользователь администратором
     * @return array ['can_edit' => bool, 'reason' => string, 'deadline' => string]
     */
    public function canEditOrder($orderId, $isAdmin = false) {
        $order = $this->find('orders', $orderId);

        if (!$order) {
            return ['can_edit' => false, 'reason' => 'Заказ не найден'];
        }

        // Если заказ отменен или завершен
        if (in_array($order['status'], ['cancelled', 'completed', 'delivered'])) {
            return ['can_edit' => false, 'reason' => 'Заказ уже завершен или отменен'];
        }

        // Админ может редактировать любой не доставленный заказ
        if ($isAdmin) {
            if ($this->config['admin_can_edit_delivered'] === false && 
                in_array($order['status'], ['delivered', 'completed'])) {
                return ['can_edit' => false, 'reason' => 'Нельзя редактировать доставленный заказ'];
            }
            return ['can_edit' => true, 'reason' => '', 'deadline' => null];
        }

        // Для клиента проверяем время до доставки и статус
        if ($order['status'] !== 'new') {
            return ['can_edit' => false, 'reason' => 'Заказ уже принят в работу'];
        }

        if (empty($order['delivery_date']) || empty($order['delivery_time'])) {
            return ['can_edit' => true, 'reason' => '', 'deadline' => null];
        }

        $deliveryDateTime = strtotime($order['delivery_date'] . ' ' . $order['delivery_time']);
        $now = time();
        $hoursUntilDelivery = ($deliveryDateTime - $now) / 3600;

        $editLimit = $this->config['order_edit_hours_limit'];

        if ($hoursUntilDelivery < $editLimit) {
            $deadline = date('Y-m-d H:i', $deliveryDateTime - ($editLimit * 3600));
            return [
                'can_edit' => false, 
                'reason' => "Редактировать заказ можно не позднее чем за {$editLimit} час до доставки",
                'deadline' => $deadline
            ];
        }

        $deadline = date('Y-m-d H:i', $deliveryDateTime - ($editLimit * 3600));

        return [
            'can_edit' => true, 
            'reason' => '',
            'deadline' => $deadline,
            'hours_left' => round($hoursUntilDelivery, 1)
        ];
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Отмена заказа с проверкой ограничений
     */
    public function cancelOrder($orderId, $reason = null, $isAdmin = false, $userId = null) {
        // Проверяем возможность отмены
        $canCancel = $this->canCancelOrder($orderId, $isAdmin);

        if (!$canCancel['can_cancel']) {
            throw new Exception($canCancel['reason']);
        }

        $order = $this->find('orders', $orderId);

        if (!$order) {
            throw new Exception("Order not found: {$orderId}");
        }

        $this->log("Cancelling order v6.2.0: order={$orderId}, admin={$isAdmin}", 'info');

        // Освобождаем слот если включено
        if ($this->config['auto_release_on_cancel'] && !empty($order['delivery_slot_id'])) {
            try {
                $this->releaseSlotSection($order['delivery_slot_id'], $orderId);
                $order['slot_released_at'] = date('Y-m-d H:i:s');
            } catch (Exception $e) {
                $this->log("Failed to release slot for cancelled order {$orderId}: " . $e->getMessage(), 'error');
            }
        }

        // Обновляем заказ
        $order['status'] = 'cancelled';
        $order['cancelled_at'] = date('Y-m-d H:i:s');
        $order['cancelled_by'] = $isAdmin ? 'admin' : 'customer';
        $order['cancel_reason'] = $reason;
        $order['updated_at'] = date('Y-m-d H:i:s');

        if (!isset($order['status_history'])) {
            $order['status_history'] = [];
        }

        $order['status_history'][] = [
            'status' => 'cancelled',
            'date' => date('Y-m-d H:i:s'),
            'reason' => $reason,
            'by' => $isAdmin ? 'admin' : 'customer',
            'user_id' => $userId
        ];

        $this->saveWithoutValidation('orders', $order, $orderId);

        $this->log("Order cancelled v6.2.0: order={$orderId}, by=" . ($isAdmin ? 'admin' : 'customer'), 'info');

        return true;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Обновление полей can_cancel/can_edit при сохранении заказа
     */
    private function updateOrderPermissions(&$order) {
        if (empty($order['delivery_date']) || empty($order['delivery_time'])) {
            $order['can_cancel'] = true;
            $order['can_edit'] = true;
            $order['cancel_deadline'] = null;
            $order['edit_deadline'] = null;
            return;
        }

        $deliveryDateTime = strtotime($order['delivery_date'] . ' ' . $order['delivery_time']);

        $cancelLimit = $this->config['order_cancel_hours_limit'];
        $editLimit = $this->config['order_edit_hours_limit'];

        $cancelDeadline = $deliveryDateTime - ($cancelLimit * 3600);
        $editDeadline = $deliveryDateTime - ($editLimit * 3600);

        $order['cancel_deadline'] = date('Y-m-d H:i:s', $cancelDeadline);
        $order['edit_deadline'] = date('Y-m-d H:i:s', $editDeadline);

        $now = time();

        $order['can_cancel'] = ($now < $cancelDeadline) && !in_array($order['status'], ['cancelled', 'completed', 'delivered']);
        $order['can_edit'] = ($now < $editDeadline) && $order['status'] === 'new';
    }

    // ============= 🎯 НОВОЕ v6.0.0: ИНТЕГРАЦИЯ СЕРТИФИКАТОВ =============

    /**
     * 🎯 НОВОЕ v6.0.0: Применение сертификата к корзине
     * 
     * @param array $cart Корзина
     * @param string $certificateCode Код сертификата
     * @return array Обновленная корзина
     */
    public function applyCertificateToCart($cart, $certificateCode) {
        if (!$this->config['certificates_enabled']) {
            throw new Exception('Система сертификатов отключена');
        }

        $this->log("Applying certificate to cart v6.2.0: code={$certificateCode}", 'info');

        // Валидация сертификата через интеграцию с 1С
        $validation = $this->validateCertificate($certificateCode);

        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }

        $certificate = $validation['certificate'];
        $balance = floatval($certificate['balance']);
        $cartTotal = floatval($cart['total'] ?? 0);

        if ($balance <= 0) {
            throw new Exception('На сертификате закончились средства');
        }

        // Применяем сертификат
        if ($balance >= $cartTotal) {
            // Полная оплата сертификатом
            $cart['certificate_amount'] = $cartTotal;
            $cart['total'] = 0;
        } else {
            // Частичная оплата
            if (!$this->config['certificate_partial_payment']) {
                throw new Exception('Частичная оплата сертификатом не поддерживается');
            }

            $cart['certificate_amount'] = $balance;
            $cart['total'] -= $balance;
        }

        $cart['certificate_code'] = $certificateCode;

        $this->log("Certificate applied to cart: amount={$cart['certificate_amount']}, new_total={$cart['total']}", 'info');

        return $cart;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Списание с сертификата при оформлении заказа
     */
    public function applyCertificateToOrder($orderId, $certificateCode) {
        $order = $this->find('orders', $orderId);
        if (!$order) throw new Exception('Order not found');

        $validation = $this->validateCertificate($certificateCode);
        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }

        $certificate = $validation['certificate'];
        $balance = floatval($certificate['balance']);
        $orderTotal = floatval($order['total']);

        // Определяем сумму к списанию
        $amountToApply = min($balance, $orderTotal);

        // Списываем с сертификата
        $certificate['balance'] -= $amountToApply;
        $certificate['updated_at'] = date('Y-m-d H:i:s');

        if ($certificate['balance'] <= 0) {
            $certificate['status'] = 'used';
            $certificate['used_at'] = date('Y-m-d H:i:s');
        }

        // Добавляем в историю использования
        if (!isset($certificate['usage_history'])) {
            $certificate['usage_history'] = [];
        }

        $certificate['usage_history'][] = [
            'order_id' => $orderId,
            'amount' => $amountToApply,
            'balance_before' => $balance,
            'balance_after' => $certificate['balance'],
            'used_at' => date('Y-m-d H:i:s')
        ];

        $this->saveWithoutValidation('certificates', $certificate, $certificate['id']);

        // Создаем запись о использовании
        $usage = [
            'certificate_id' => $certificate['id'],
            'order_id' => $orderId,
            'amount_used' => $amountToApply,
            'balance_before' => $balance,
            'balance_after' => $certificate['balance'],
            'used_at' => date('Y-m-d H:i:s')
        ];
        $this->saveWithoutValidation('certificate_usages', $usage);

        // Обновляем заказ
        $order['certificate_applied'] = $certificateCode;
        $order['certificate_amount'] = $amountToApply;
        $order['total'] -= $amountToApply;

        if ($order['total'] <= 0) {
            $order['total'] = 0;
            $order['payment_status'] = 'paid';
            $order['is_paid'] = true;
        }

        $this->saveWithoutValidation('orders', $order, $orderId);

        $this->log("Certificate applied to order v6.2.0: order={$orderId}, code={$certificateCode}, amount={$amountToApply}", 'info');

        return [
            'success' => true,
            'applied_amount' => $amountToApply,
            'new_order_total' => $order['total'],
            'certificate_balance' => $certificate['balance']
        ];
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Валидация сертификата
     */
    public function validateCertificate($code) {
        $code = strtoupper(trim($code));

        $certificate = $this->findOne('certificates', ['code' => $code]);

        if (!$certificate) {
            return ['valid' => false, 'message' => 'Сертификат не найден'];
        }

        if ($certificate['status'] !== 'active') {
            return ['valid' => false, 'message' => 'Сертификат не активен'];
        }

        if (!empty($certificate['expires_at']) && strtotime($certificate['expires_at']) < time()) {
            return ['valid' => false, 'message' => 'Срок действия сертификата истек'];
        }

        $balance = floatval($certificate['balance']);
        if ($balance <= 0) {
            return ['valid' => false, 'message' => 'На сертификате закончились средства'];
        }

        return [
            'valid' => true,
            'certificate' => $certificate,
            'balance' => $balance,
            'message' => 'Сертификат действителен'
        ];
    }

    // ============= 🎯 НОВОЕ v6.0.0: АДРЕСА КЛИЕНТОВ =============

    /**
     * 🎯 НОВОЕ v6.0.0: Добавление адреса клиенту
     */
    public function addCustomerAddress($customerId, $addressData) {
        $addressData['customer_id'] = $customerId;

        // Формируем полный адрес строкой
        $fullAddress = implode(', ', array_filter([
            $addressData['street'] ?? '',
            'д. ' . ($addressData['house'] ?? ''),
            !empty($addressData['apartment']) ? 'кв. ' . $addressData['apartment'] : '',
        ]));

        $addressData['full_address'] = $fullAddress;

        // Определяем зону доставки если включено
        if ($this->config['address_auto_detect_zone']) {
            $zone = $this->detectDeliveryZone($fullAddress);
            if ($zone) {
                $addressData['zone_id'] = $zone['id'];
            }
        }

        $addressId = $this->save('customer_addresses', $addressData);

        // Если это первый адрес - делаем его дефолтным
        $addresses = $this->findAll('customer_addresses', ['customer_id' => $customerId]);
        if (count($addresses) === 1) {
            $this->setDefaultAddress($addressId, $customerId);
        }

        $this->log("Customer address added v6.2.0: customer={$customerId}, address_id={$addressId}", 'info');

        return $addressId;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Получение адресов клиента
     */
    public function getCustomerAddresses($customerId) {
        $addresses = $this->findAll('customer_addresses', ['customer_id' => $customerId]);

        // Сортируем: сначала default
        usort($addresses, function($a, $b) {
            if (($a['is_default'] ?? false) === ($b['is_default'] ?? false)) {
                return 0;
            }
            return ($a['is_default'] ?? false) ? -1 : 1;
        });

        return $addresses;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Установка адреса по умолчанию
     */
    public function setDefaultAddress($addressId, $customerId) {
        // Сбрасываем все is_default для клиента
        $addresses = $this->findAll('customer_addresses', ['customer_id' => $customerId]);

        foreach ($addresses as $addr) {
            $addr['is_default'] = false;
            $this->saveWithoutValidation('customer_addresses', $addr, $addr['id']);
        }

        // Устанавливаем новый default
        $address = $this->find('customer_addresses', $addressId);
        if ($address && $address['customer_id'] == $customerId) {
            $address['is_default'] = true;
            $this->saveWithoutValidation('customer_addresses', $address, $addressId);

            $this->log("Default address updated v6.2.0: customer={$customerId}, address={$addressId}", 'info');
            return true;
        }

        return false;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Удаление адреса
     */
    public function deleteCustomerAddress($addressId, $customerId) {
        $address = $this->find('customer_addresses', $addressId);

        if (!$address || $address['customer_id'] != $customerId) {
            throw new Exception('Address not found or access denied');
        }

        $wasDefault = $address['is_default'] ?? false;

        $this->delete('customer_addresses', $addressId);

        // Если был дефолтным - выбираем другой
        if ($wasDefault) {
            $addresses = $this->findAll('customer_addresses', ['customer_id' => $customerId]);
            if (!empty($addresses)) {
                $this->setDefaultAddress($addresses[0]['id'], $customerId);
            }
        }

        $this->log("Customer address deleted v6.2.0: address={$addressId}", 'info');

        return true;
    }

    // ============= 🎯 НОВОЕ v6.0.0: ПОДАРКИ ОТ АДМИНА =============

    /**
     * 🎯 НОВОЕ v6.0.0: Добавление подарка клиенту от админа
     */
    public function addAdminGiftToCustomer($customerId, $productId, $adminId, $adminName, $reason = null, $expiresAt = null) {
        $customer = $this->find('customers', $customerId);
        if (!$customer) {
            throw new Exception('Customer not found');
        }

        $product = $this->find('products', $productId);
        if (!$product) {
            throw new Exception('Product not found');
        }

        // Если срок не указан - ставим 30 дней
        if (!$expiresAt) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        }

        $gift = [
            'customer_id' => $customerId,
            'product_id' => $productId,
            'admin_id' => $adminId,
            'admin_name' => $adminName,
            'reason' => $reason,
            'status' => 'pending',
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $giftId = $this->save('admin_gifts', $gift);

        // Также добавляем в pending_gifts клиента для совместимости
        $pendingGifts = $customer['pending_gifts'] ?? [];
        $pendingGifts[] = [
            'gift_id' => $giftId,
            'product_id' => $productId,
            'product_name' => $product['name'],
            'added_by' => $adminName,
            'added_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expiresAt
        ];

        $customer['pending_gifts'] = $pendingGifts;
        $this->saveWithoutValidation('customers', $customer, $customerId);

        $this->log("Admin gift added v6.2.0: customer={$customerId}, product={$productId}, admin={$adminName}", 'info');

        return $giftId;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Получение активных подарков клиента
     */
    public function getPendingGiftsForCustomer($customerId) {
        $gifts = $this->findAll('admin_gifts', [
            'customer_id' => $customerId,
            'status' => 'pending'
        ]);

        // Фильтруем просроченные
        $activeGifts = [];
        $now = time();

        foreach ($gifts as $gift) {
            if (!empty($gift['expires_at']) && strtotime($gift['expires_at']) < $now) {
                // Помечаем как истекший
                $gift['status'] = 'expired';
                $this->saveWithoutValidation('admin_gifts', $gift, $gift['id']);
                continue;
            }

            $activeGifts[] = $gift;
        }

        return $activeGifts;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Применение подарков к заказу
     */
    public function applyPendingGiftsToOrder($customerId, $orderId) {
        $gifts = $this->getPendingGiftsForCustomer($customerId);

        if (empty($gifts)) {
            return 0;
        }

        $order = $this->find('orders', $orderId);
        if (!$order) {
            throw new Exception('Order not found');
        }

        $appliedCount = 0;

        foreach ($gifts as $gift) {
            $product = $this->find('products', $gift['product_id']);
            if (!$product) continue;

            // Добавляем подарок в items заказа
            $order['items'][] = [
                'id' => $product['id'],
                'product_id' => $product['id'],
                'name' => $product['name'],
                'image' => $product['image'] ?? '',
                'quantity' => 1,
                'price' => 0,
                'subtotal' => 0,
                'is_gift' => true,
                'promotion_name' => 'Подарок от администратора',
                'admin_gift_id' => $gift['id']
            ];

            // Обновляем статус подарка
            $gift['status'] = 'applied';
            $gift['applied_order_id'] = $orderId;
            $gift['applied_at'] = date('Y-m-d H:i:s');
            $this->saveWithoutValidation('admin_gifts', $gift, $gift['id']);

            $appliedCount++;
        }

        if ($appliedCount > 0) {
            // Обновляем счетчик подарков в заказе
            $order['gift_items_count'] = ($order['gift_items_count'] ?? 0) + $appliedCount;
            $this->saveWithoutValidation('orders', $order, $orderId);

            // Очищаем pending_gifts у клиента
            $customer = $this->find('customers', $customerId);
            $customer['pending_gifts'] = [];
            $this->saveWithoutValidation('customers', $customer, $customerId);

            $this->log("Applied {$appliedCount} admin gifts to order v6.2.0: order={$orderId}", 'info');
        }

        return $appliedCount;
    }

    // ============= 🎯 НОВОЕ v6.0.0: СИСТЕМА АККАУНТОВ =============

    /**
     * 🎯 НОВОЕ v6.0.0: Регистрация клиента с паролем
     */
    public function registerCustomer($data) {
        if (!$this->config['customer_passwords_enabled']) {
            throw new Exception('Регистрация с паролем отключена');
        }

        // Проверяем обязательные поля
        if (empty($data['phone'])) {
            throw new Exception('Телефон обязателен');
        }

        if (empty($data['password'])) {
            throw new Exception('Пароль обязателен');
        }

        $minLength = $this->config['password_min_length'];
        if (strlen($data['password']) < $minLength) {
            throw new Exception("Пароль должен быть не менее {$minLength} символов");
        }

        // Проверяем существование телефона
        $existing = $this->findOne('customers', ['phone' => $data['phone']]);
        if ($existing) {
            throw new Exception('Клиент с таким телефоном уже существует');
        }

        // Создаем клиента
        $customer = [
            'name' => $data['name'] ?? 'Клиент',
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'email_verified' => false,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        // Email verification token
        if (!empty($customer['email']) && $this->config['require_email_verification']) {
            $customer['email_verification_token'] = bin2hex(random_bytes(32));
        }

        $customerId = $this->save('customers', $customer);

        $this->log("Customer registered v6.2.0: id={$customerId}, phone={$data['phone']}", 'info');

        // Отправляем email если нужно
        if (!empty($customer['email']) && $this->config['require_email_verification']) {
            $this->queueVerificationEmail($customerId, $customer['email'], $customer['email_verification_token']);
        }

        return $customerId;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Авторизация клиента
     */
    public function loginCustomer($phone, $password) {
        $customer = $this->findOne('customers', ['phone' => $phone]);

        if (!$customer) {
            throw new Exception('Неверный телефон или пароль');
        }

        if (empty($customer['password_hash'])) {
            throw new Exception('Для этого аккаунта не установлен пароль');
        }

        if (!password_verify($password, $customer['password_hash'])) {
            throw new Exception('Неверный телефон или пароль');
        }

        if ($customer['status'] !== 'active') {
            throw new Exception('Аккаунт заблокирован');
        }

        // Обновляем данные входа
        $customer['last_login_at'] = date('Y-m-d H:i:s');
        $customer['login_count'] = ($customer['login_count'] ?? 0) + 1;
        $this->saveWithoutValidation('customers', $customer, $customer['id']);

        $this->log("Customer logged in v6.2.0: id={$customer['id']}, phone={$phone}", 'info');

        return $customer;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Смена пароля
     */
    public function changeCustomerPassword($customerId, $oldPassword, $newPassword) {
        $customer = $this->find('customers', $customerId);

        if (!$customer) {
            throw new Exception('Customer not found');
        }

        if (!password_verify($oldPassword, $customer['password_hash'])) {
            throw new Exception('Неверный старый пароль');
        }

        $minLength = $this->config['password_min_length'];
        if (strlen($newPassword) < $minLength) {
            throw new Exception("Пароль должен быть не менее {$minLength} символов");
        }

        $customer['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT);
        $customer['updated_at'] = date('Y-m-d H:i:s');

        $this->saveWithoutValidation('customers', $customer, $customerId);

        $this->log("Customer password changed v6.2.0: id={$customerId}", 'info');

        return true;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Добавление телефона клиенту
     */
    public function addCustomerPhone($customerId, $phone, $label = null, $isPrimary = false) {
        if (!$this->config['allow_multiple_phones']) {
            throw new Exception('Множественные телефоны отключены');
        }

        $phoneData = [
            'customer_id' => $customerId,
            'phone' => $phone,
            'is_primary' => $isPrimary,
            'verified' => false,
            'label' => $label,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $phoneId = $this->save('customer_phones', $phoneData);

        // Если это основной телефон - сбрасываем у остальных
        if ($isPrimary) {
            $phones = $this->findAll('customer_phones', ['customer_id' => $customerId]);
            foreach ($phones as $p) {
                if ($p['id'] != $phoneId) {
                    $p['is_primary'] = false;
                    $this->saveWithoutValidation('customer_phones', $p, $p['id']);
                }
            }
        }

        return $phoneId;
    }

    // ============= 🎯 НОВОЕ v6.0.0: EMAIL ОЧЕРЕДЬ =============

    /**
     * 🎯 НОВОЕ v6.0.0: Добавление email в очередь
     */
    public function queueEmail($toEmail, $toName, $subject, $body, $template = null, $templateVars = []) {
        $emailData = [
            'to_email' => $toEmail,
            'to_name' => $toName,
            'subject' => $subject,
            'body' => $body,
            'template' => $template,
            'template_vars' => $templateVars,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $emailId = $this->save('email_queue', $emailData);

        $this->log("Email queued v6.2.0: to={$toEmail}, subject={$subject}", 'info');

        return $emailId;
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Email подтверждения регистрации
     */
    private function queueVerificationEmail($customerId, $email, $token) {
        $verificationLink = "https://yoursite.ru/verify-email?token={$token}";

        $body = "
            <h2>Подтверждение email</h2>
            <p>Для завершения регистрации перейдите по ссылке:</p>
            <p><a href='{$verificationLink}'>{$verificationLink}</a></p>
        ";

        return $this->queueEmail(
            $email,
            'Новый клиент',
            'Подтверждение email - Sasha\'s Sushi',
            $body,
            'email_verification',
            ['link' => $verificationLink]
        );
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Email уведомление о заказе
     */
    public function queueOrderNotificationEmail($orderId) {
        $order = $this->find('orders', $orderId);

        if (!$order || empty($order['customer_email'])) {
            return false;
        }

        $subject = "Заказ #{$order['order_number']} принят - Sasha's Sushi";

        $body = "
            <h2>Ваш заказ принят!</h2>
            <p><strong>Номер заказа:</strong> #{$order['order_number']}</p>
            <p><strong>Дата доставки:</strong> {$order['delivery_date']} {$order['delivery_time']}</p>
            <p><strong>Адрес:</strong> {$order['delivery_address']}</p>
            <p><strong>Сумма:</strong> {$order['total']} ₽</p>
            <p>Спасибо за заказ!</p>
        ";

        return $this->queueEmail(
            $order['customer_email'],
            $order['customer_name'],
            $subject,
            $body,
            'order_confirmation',
            ['order' => $order]
        );
    }

    // ============= 🎯 НОВОЕ v6.0.0: КОНТЕНТНЫЕ СТРАНИЦЫ =============

    /**
     * 🎯 НОВОЕ v6.0.0: Создание/обновление контентной страницы
     */
    public function saveContentPage($slug, $title, $content, $options = []) {
        $existing = $this->findOne('content_pages', ['slug' => $slug]);

        $pageData = [
            'slug' => $slug,
            'title' => $title,
            'content' => $content,
            'meta_description' => $options['meta_description'] ?? '',
            'meta_keywords' => $options['meta_keywords'] ?? '',
            'status' => $options['status'] ?? 'published',
            'show_in_footer' => $options['show_in_footer'] ?? true,
            'order' => $options['order'] ?? 999,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($existing) {
            $pageData['id'] = $existing['id'];
            $pageData['created_at'] = $existing['created_at'];
            $this->saveWithoutValidation('content_pages', $pageData, $existing['id']);
            return $existing['id'];
        } else {
            $pageData['created_at'] = date('Y-m-d H:i:s');
            return $this->saveWithoutValidation('content_pages', $pageData);
        }
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Получение контентной страницы
     */
    public function getContentPage($slug) {
        return $this->findOne('content_pages', ['slug' => $slug, 'status' => 'published']);
    }

    // ============= 🎯 РАСШИРЕНО v6.0.0: КОРЗИНА =============

    /**
     * 🎯 РАСШИРЕНО v6.0.0: Получение корзины с применением акций
     */
    public function getCart($sessionId, $customerId = null, $autoApplyPromotions = true) {
        $cart = $this->findBy('cart', 'session_id', $sessionId);

        if (!$cart || $cart['status'] !== 'active') {
            $cart = [
                'session_id' => $sessionId,
                'customer_id' => $customerId,
                'items' => [],
                'subtotal' => 0,
                'delivery_cost' => 0,
                'total' => 0,
                'discount' => 0,
                'status' => 'active',
                'temp_slot_id' => null,
                'temp_slot_expires_at' => null,
                'applied_promotions' => [],
                'gift_items' => [],
                'certificate_code' => null,
                'certificate_amount' => 0,
            ];
            $cartId = $this->saveWithoutValidation('cart', $cart);
            $cart['id'] = $cartId;
        }

        // Проверяем истечение временного слота
        if (!empty($cart['temp_slot_id']) && !empty($cart['temp_slot_expires_at'])) {
            if (strtotime($cart['temp_slot_expires_at']) <= time()) {
                $this->releaseTemporaryLock($cart['temp_slot_id'], $sessionId);
                $cart['temp_slot_id'] = null;
                $cart['temp_slot_expires_at'] = null;
                $this->saveWithoutValidation('cart', $cart, $cart['id']);
            }
        }

        // 🎯 НОВОЕ v6.0.0: Автоприменение акций если включено
        if ($autoApplyPromotions && $this->config['check_promotions_on_cart_change']) {
            $cart = $this->checkAndApplyPromotions($cart);
            $this->saveWithoutValidation('cart', $cart, $cart['id']);
        }

        return $cart;
    }

    /**
     * 🎯 РАСШИРЕНО v6.0.0: Добавление товара в корзину с автопроверкой акций
     */
    public function addToCart($sessionId, $productId, $quantity = 1, $customerId = null) {
        $cart = $this->getCart($sessionId, $customerId, false); // Без автоакций пока

        $product = $this->find('products', $productId);

        if (!$product) {
            throw new Exception("Product not found: {$productId}");
        }

        if ($product['status'] !== 'active') {
            throw new Exception("Product is not available: {$productId}");
        }

        $items = $cart['items'] ?? [];
        $found = false;

        foreach ($items as &$item) {
            if ($item['id'] == $productId && !($item['is_gift'] ?? false)) {
                $item['quantity'] += $quantity;
                $item['subtotal'] = $item['quantity'] * $item['price'];
                $found = true;
                break;
            }
        }

        if (!$found) {
            $items[] = [
                'id' => $product['id'],
                'product_id' => $product['id'],
                'name' => $product['name'],
                'price' => floatval($product['price']),
                'image' => $product['image'] ?? '',
                'quantity' => intval($quantity),
                'subtotal' => floatval($product['price']) * intval($quantity),
                'is_gift' => false
            ];
        }

        $cart['items'] = $items;

        // Пересчитываем
        $cart = $this->recalculateCart($cart);

        // 🎯 НОВОЕ v6.0.0: Применяем акции
        $cart = $this->checkAndApplyPromotions($cart);

        $this->saveWithoutValidation('cart', $cart, $cart['id']);

        $this->log("Added to cart v6.2.0: product={$productId}, quantity={$quantity}, cart_id={$cart['id']}", 'info');

        return $cart;
    }

    /**
     * 🎯 РАСШИРЕНО v6.0.0: Пересчет корзины с учетом сертификатов
     */
    private function recalculateCart($cart) {
        $settings = $this->find('settings', 'main');
        $freeDeliveryFrom = $settings['free_delivery_from'] ?? 999;
        $deliveryCost = $settings['delivery_cost'] ?? 200;

        $subtotal = 0;

        // Считаем только не-подарочные товары
        foreach ($cart['items'] as $item) {
            if (!($item['is_gift'] ?? false)) {
                $subtotal += $item['subtotal'];
            }
        }

        $cart['subtotal'] = $subtotal;

        // Доставка
        if ($subtotal >= $freeDeliveryFrom) {
            $cart['delivery_cost'] = 0;
        } else {
            $cart['delivery_cost'] = $deliveryCost;
        }

        // Учитываем скидки
        $discount = $cart['discount'] ?? 0;

        // Учитываем сертификат
        $certificateAmount = $cart['certificate_amount'] ?? 0;

        $cart['total'] = $subtotal + $cart['delivery_cost'] - $discount - $certificateAmount;
        $cart['total'] = max(0, $cart['total']); // Не может быть отрицательной

        $cart['updated_at'] = date('Y-m-d H:i:s');

        return $cart;
    }

    /**
     * 🎯 РАСШИРЕНО v6.2.0: Оформление заказа с полной интеграцией
     */
    public function cartToOrder($sessionId, $orderData) {
        $cart = $this->getCart($sessionId);

        if (empty($cart['items'])) {
            throw new Exception("Cart is empty");
        }

        $this->log("Converting cart to order v6.2.0: cart_id={$cart['id']}", 'info');

        $customerId = $cart['customer_id'] ?? null;

        // 🎯 НОВОЕ v6.0.0: Валидация количества палочек
        $itemsCount = count($cart['items']);
        $peopleCount = intval($orderData['people_count'] ?? $itemsCount);

        if ($this->config['validate_sticks_count'] && $peopleCount > $itemsCount) {
            if ($this->config['auto_correct_sticks_count']) {
                $peopleCount = $itemsCount;
                $this->log("Auto-corrected people_count: {$peopleCount}", 'info');
            } else {
                throw new Exception("Количество палочек ({$peopleCount}) не может быть больше количества позиций ({$itemsCount})");
            }
        }

        $order = [
            'order_number' => $this->generateOrderNumber(),
            'customer_id' => $customerId,
            'customer_name' => $orderData['customer_name'] ?? '',
            'customer_phone' => $orderData['customer_phone'] ?? '',
            'customer_email' => $orderData['customer_email'] ?? '',

            'items' => $cart['items'],

            'subtotal' => $cart['subtotal'],
            'delivery_cost' => $cart['delivery_cost'],
            'discount' => $cart['discount'] ?? 0,
            'total' => $cart['total'],

            'delivery_type' => $orderData['delivery_type'] ?? 'delivery',
            'delivery_address' => $orderData['delivery_address'] ?? '',
            'delivery_date' => $orderData['delivery_date'] ?? '',
            'delivery_time' => $orderData['delivery_time'] ?? '',
            'delivery_zone_id' => $orderData['delivery_zone_id'] ?? null,
            'delivery_slot_id' => $orderData['delivery_slot_id'] ?? null,

            'payment_method' => $orderData['payment_method'] ?? 'cash',
            'payment_status' => 'pending',

            'status' => 'new',
            'comment' => $orderData['comment'] ?? '',

            // Поля из v5.x
            'is_paid' => false,
            'incoming_doc_number' => null,
            'export_id' => null,
            'is_exported_1c' => false,
            'manual_promotions' => false,
            'site_status' => 'processing',
            'certificate_str' => null,
            'merge_count' => 0,
            'structure_hash' => null,
            'conflict_history' => [],
            'exported_in_batch' => false,
            'batch_export_id' => null,
            'last_xml_export_at' => null,
            'slot_section_number' => null,
            'slot_booked_at' => null,
            'slot_released_at' => null,

            // 🎯 НОВЫЕ ПОЛЯ v6.0.0
            'people_count' => $peopleCount,
            'applied_promotions' => $cart['applied_promotions'] ?? [],
            'gift_items_count' => count($cart['gift_items'] ?? []),
            'certificate_applied' => $cart['certificate_code'] ?? null,
            'certificate_amount' => $cart['certificate_amount'] ?? 0,
            'customer_address_id' => $orderData['customer_address_id'] ?? null,

            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'status_history' => [[
                'status' => 'new',
                'date' => date('Y-m-d H:i:s'),
                'source' => 'website'
            ]]
        ];

        // Генерируем structure hash
        if ($this->config['enable_structure_hash']) {
            $order['structure_hash'] = $this->generateOrderStructureHash($order);
        }

        // Обновляем дедлайны отмены/редактирования
        $this->updateOrderPermissions($order);

        // Автоподбор слота если не указан
        if ($this->config['slots_enabled'] && $this->config['auto_book_slots']) {
            if (empty($order['delivery_slot_id']) && 
                !empty($order['delivery_date']) && 
                !empty($order['delivery_time']) && 
                !empty($order['delivery_zone_id'])) {

                $availableSlots = $this->getAvailableSlots(
                    $order['delivery_zone_id'],
                    $order['delivery_date'],
                    $order['delivery_type']
                );

                $matchedSlot = null;
                foreach ($availableSlots as $slot) {
                    if ($slot['time'] === $order['delivery_time']) {
                        $matchedSlot = $slot;
                        break;
                    }
                }

                if ($matchedSlot) {
                    $order['delivery_slot_id'] = $matchedSlot['id'];
                }
            }
        }

        // Сохраняем заказ БЕЗ бронирования слота (временно)
        $orderId = $this->save('orders', $order);

        // Бронируем секцию в слоте ПОСЛЕ создания заказа
        if (!empty($order['delivery_slot_id'])) {
            try {
                if (!empty($cart['temp_slot_id'])) {
                    $this->releaseTemporaryLock($cart['temp_slot_id'], $sessionId);
                }

                $bookingResult = $this->bookSlotSection($order['delivery_slot_id'], $orderId, $sessionId);

                if ($bookingResult['success']) {
                    $order['id'] = $orderId;
                    $order['slot_section_number'] = $bookingResult['section_number'];
                    $order['slot_booked_at'] = date('Y-m-d H:i:s');
                    $this->saveWithoutValidation('orders', $order, $orderId);
                }
            } catch (Exception $e) {
                $this->log("Slot booking failed for order {$orderId}: " . $e->getMessage(), 'warning');
            }
        }

        // 🎯 НОВОЕ v6.0.0: Применяем сертификат если есть
        if (!empty($cart['certificate_code'])) {
            try {
                $this->applyCertificateToOrder($orderId, $cart['certificate_code']);
            } catch (Exception $e) {
                $this->log("Certificate application failed: " . $e->getMessage(), 'error');
            }
        }

        // 🎯 НОВОЕ v6.0.0: Применяем подарки от админа
        if ($customerId) {
            try {
                $giftsApplied = $this->applyPendingGiftsToOrder($customerId, $orderId);
                if ($giftsApplied > 0) {
                    $order = $this->find('orders', $orderId);
                }
            } catch (Exception $e) {
                $this->log("Admin gifts application failed: " . $e->getMessage(), 'error');
            }
        }

        // 🎯 НОВОЕ v6.0.0: Записываем использование акций
        foreach ($order['applied_promotions'] as $promo) {
            $usage = [
                'promotion_id' => $promo['id'],
                'order_id' => $orderId,
                'customer_id' => $customerId,
                'applied_at' => date('Y-m-d H:i:s')
            ];
            $this->saveWithoutValidation('promotion_usages', $usage);

            // Увеличиваем счетчик использований акции
            $promotion = $this->find('promotions', $promo['id']);
            if ($promotion) {
                $promotion['current_uses'] = ($promotion['current_uses'] ?? 0) + 1;
                $this->saveWithoutValidation('promotions', $promotion, $promo['id']);
            }
        }

        // Обновляем кэш заказов
        if (!empty($order['order_number'])) {
            $this->processedOrderIds['num_' . $order['order_number']] = $orderId;
        }
        if (!empty($order['structure_hash'])) {
            $this->processedOrderIds['hash_' . $order['structure_hash']] = $orderId;
            $this->orderStructureHashes[$order['structure_hash']] = $orderId;
        }

        // Обновляем статистику клиента
        if ($customerId) {
            $customer = $this->find('customers', $customerId);
            if ($customer) {
                $customer['orders_count'] = ($customer['orders_count'] ?? 0) + 1;
                $customer['total_spent'] = ($customer['total_spent'] ?? 0) + $order['total'];
                $this->saveWithoutValidation('customers', $customer, $customerId);
            }
        }

        // Завершаем корзину
        $cart['status'] = 'completed';
        $cart['order_id'] = $orderId;
        $cart['temp_slot_id'] = null;
        $cart['temp_slot_expires_at'] = null;
        $this->saveWithoutValidation('cart', $cart, $cart['id']);

        // 🎯 НОВОЕ v6.0.0: Отправляем email если указан
        if (!empty($order['customer_email'])) {
            $this->queueOrderNotificationEmail($orderId);
        }

        $this->log("Cart converted to order v6.2.0: cart_id={$cart['id']}, order_id={$orderId}, order_number={$order['order_number']}", 'info');

        return $orderId;
    }

    // ============= СОХРАНЕННЫЕ МЕТОДЫ ИЗ v5.2.0: СЛОТЫ =============

    /**
     * ✅ СОХРАНЕНО v5.2.0: Получение доступных слотов для зоны на дату
     */
    public function getAvailableSlots($zoneId, $date, $type = 'delivery') {
        if (!$this->config['slots_enabled']) {
            return [];
        }

        $this->log("Getting available slots v6.2.0: zone={$zoneId}, date={$date}, type={$type}", 'info');

        // Проверяем кэш
        $cacheKey = "slots_{$zoneId}_{$date}_{$type}";
        $cacheFile = $this->dataPath . 'temp/' . $cacheKey . '.json';
        $cacheTTL = $this->config['slots_cache_ttl'];

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached) {
                $this->log("Returning cached slots: " . count($cached), 'info');
                return $cached;
            }
        }

        // Загружаем слоты из БД
        $slots = $this->findAll('delivery_slots', [
            'zone_id' => $zoneId,
            'date' => $date,
            'type' => $type
        ]);

        $availableSlots = [];
        $now = time();

        foreach ($slots as $slot) {
            $slotStatus = $slot['status'] ?? 'active';

            if ($slotStatus === 'blocked' || $slotStatus === 'archived') {
                continue;
            }

            $totalSections = intval($slot['total_sections'] ?? 10);
            $availableSections = intval($slot['available_sections'] ?? $totalSections);

            // Учитываем временные блокировки
            $tempLocks = $slot['temporary_locks'] ?? [];
            $activeTempLocks = 0;

            foreach ($tempLocks as $sessionId => $expiresAt) {
                if (strtotime($expiresAt) > $now) {
                    $activeTempLocks++;
                }
            }

            $realAvailable = $availableSections - $activeTempLocks;

            // Автоматически обновляем статус если заполнен
            if ($realAvailable <= 0 && $slotStatus !== 'full') {
                $slot['status'] = 'full';
                $slot['available_sections'] = 0;
                $this->saveWithoutValidation('delivery_slots', $slot, $slot['id']);
                continue;
            }

            $slotData = [
                'id' => $slot['id'],
                'time' => $slot['time'],
                'date' => $slot['date'],
                'zone_id' => $slot['zone_id'],
                'type' => $slot['type'],
                'total_sections' => $totalSections,
                'available_sections' => $realAvailable,
                'status' => $realAvailable > 0 ? 'available' : 'full',
                'batch_number' => $slot['batch_number'] ?? null,
                'external_id' => $slot['external_id'] ?? null,
            ];

            $availableSlots[] = $slotData;
        }

        usort($availableSlots, function($a, $b) {
            return strcmp($a['time'], $b['time']);
        });

        @file_put_contents($cacheFile, json_encode($availableSlots, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->log("Found " . count($availableSlots) . " available slots", 'info');

        return $availableSlots;
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Бронирование секции в слоте
     */
    public function bookSlotSection($slotId, $orderId, $sessionId = null) {
        if (!$this->config['slots_enabled']) {
            return ['success' => false, 'error' => 'Slots system disabled'];
        }

        $this->log("Booking slot section v6.2.0: slot={$slotId}, order={$orderId}", 'info');

        return $this->atomicOperation('delivery_slots', $slotId, function($slot) use ($orderId, $sessionId) {
            $totalSections = intval($slot['total_sections'] ?? 10);
            $availableSections = intval($slot['available_sections'] ?? $totalSections);
            $bookedSections = $slot['booked_sections'] ?? [];

            if ($availableSections <= 0) {
                if (!$this->config['allow_overbooking']) {
                    throw new Exception("Slot is full: no available sections");
                }
            }

            $this->cleanExpiredTemporaryLocks($slot);

            $usedSections = array_values($bookedSections);
            $sectionNumber = 1;

            for ($i = 1; $i <= $totalSections + 1; $i++) {
                if (!in_array($i, $usedSections)) {
                    $sectionNumber = $i;
                    break;
                }
            }

            $bookedSections[$orderId] = $sectionNumber;
            $slot['booked_sections'] = $bookedSections;
            $slot['available_sections'] = $availableSections - 1;

            if ($slot['available_sections'] <= 0) {
                $slot['status'] = 'full';
            }

            $slot['updated_at'] = date('Y-m-d H:i:s');

            $this->saveSlotHistory($slotId, $orderId, 'book', $sectionNumber, $availableSections, $slot['available_sections']);
            $this->createSlotBooking($slotId, $orderId, $sectionNumber, $sessionId);

            $this->log("Slot section booked: slot={$slotId}, order={$orderId}, section={$sectionNumber}", 'info');

            return [
                'success' => true,
                'slot_id' => $slotId,
                'section_number' => $sectionNumber,
                'available_sections' => $slot['available_sections'],
                'status' => $slot['status']
            ];
        });
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Освобождение секции при отмене/доставке
     */
    public function releaseSlotSection($slotId, $orderId) {
        if (!$this->config['slots_enabled']) {
            return ['success' => false, 'error' => 'Slots system disabled'];
        }

        $this->log("Releasing slot section v6.2.0: slot={$slotId}, order={$orderId}", 'info');

        return $this->atomicOperation('delivery_slots', $slotId, function($slot) use ($orderId) {
            $bookedSections = $slot['booked_sections'] ?? [];

            if (!isset($bookedSections[$orderId])) {
                throw new Exception("Order {$orderId} does not have a booked section in slot {$slot['id']}");
            }

            $sectionNumber = $bookedSections[$orderId];
            $availableBefore = intval($slot['available_sections'] ?? 0);

            unset($bookedSections[$orderId]);
            $slot['booked_sections'] = $bookedSections;
            $slot['available_sections'] = $availableBefore + 1;

            if ($slot['status'] === 'full' && $slot['available_sections'] > 0) {
                $slot['status'] = 'active';
            }

            $slot['updated_at'] = date('Y-m-d H:i:s');

            $this->saveSlotHistory($slot['id'], $orderId, 'release', $sectionNumber, $availableBefore, $slot['available_sections']);
            $this->releaseSlotBooking($slot['id'], $orderId);

            $this->log("Slot section released: slot={$slot['id']}, order={$orderId}, section={$sectionNumber}", 'info');

            return [
                'success' => true,
                'slot_id' => $slot['id'],
                'section_number' => $sectionNumber,
                'available_sections' => $slot['available_sections'],
                'status' => $slot['status']
            ];
        });
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Добавление секций в слот
     */
    public function addSlotSections($slotId, $count = 1) {
        if (!$this->config['slots_enabled']) {
            return ['success' => false, 'error' => 'Slots system disabled'];
        }

        $this->log("Adding sections to slot v6.2.0: slot={$slotId}, count={$count}", 'info');

        return $this->atomicOperation('delivery_slots', $slotId, function($slot) use ($count) {
            $totalBefore = intval($slot['total_sections'] ?? 10);
            $availableBefore = intval($slot['available_sections'] ?? $totalBefore);

            $newTotal = $totalBefore + $count;
            if ($newTotal > $this->config['max_slot_sections']) {
                throw new Exception("Cannot exceed max sections limit: " . $this->config['max_slot_sections']);
            }

            $slot['total_sections'] = $newTotal;
            $slot['available_sections'] = $availableBefore + $count;

            if ($slot['status'] === 'full' && $slot['available_sections'] > 0) {
                $slot['status'] = 'active';
            }

            $slot['updated_at'] = date('Y-m-d H:i:s');

            $this->saveSlotHistory($slotId, null, 'add_section', null, $totalBefore, $slot['total_sections'], "Added {$count} sections");

            $this->log("Added {$count} sections to slot {$slotId}: {$totalBefore} → {$newTotal}", 'info');

            return [
                'success' => true,
                'slot_id' => $slotId,
                'total_sections' => $slot['total_sections'],
                'available_sections' => $slot['available_sections'],
                'status' => $slot['status']
            ];
        });
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Удаление секций из слота
     */
    public function removeSlotSections($slotId, $count = 1) {
        if (!$this->config['slots_enabled']) {
            return ['success' => false, 'error' => 'Slots system disabled'];
        }

        $this->log("Removing sections from slot v6.2.0: slot={$slotId}, count={$count}", 'info');

        return $this->atomicOperation('delivery_slots', $slotId, function($slot) use ($count) {
            $totalBefore = intval($slot['total_sections'] ?? 10);
            $availableBefore = intval($slot['available_sections'] ?? $totalBefore);
            $bookedCount = count($slot['booked_sections'] ?? []);

            $newTotal = $totalBefore - $count;
            if ($newTotal < $bookedCount) {
                throw new Exception("Cannot remove sections: {$bookedCount} already booked");
            }

            if ($newTotal < $this->config['min_slot_sections']) {
                throw new Exception("Cannot go below min sections limit: " . $this->config['min_slot_sections']);
            }

            $slot['total_sections'] = $newTotal;
            $slot['available_sections'] = max(0, $availableBefore - $count);

            if ($slot['available_sections'] <= 0) {
                $slot['status'] = 'full';
            }

            $slot['updated_at'] = date('Y-m-d H:i:s');

            $this->saveSlotHistory($slotId, null, 'remove_section', null, $totalBefore, $slot['total_sections'], "Removed {$count} sections");

            $this->log("Removed {$count} sections from slot {$slotId}: {$totalBefore} → {$newTotal}", 'info');

            return [
                'success' => true,
                'slot_id' => $slotId,
                'total_sections' => $slot['total_sections'],
                'available_sections' => $slot['available_sections'],
                'status' => $slot['status']
            ];
        });
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Временная блокировка слота
     */
    public function temporaryLockSlot($slotId, $sessionId) {
        if (!$this->config['slots_enabled']) {
            return ['success' => false, 'error' => 'Slots system disabled'];
        }

        $timeout = $this->config['slot_booking_timeout'];
        $expiresAt = date('Y-m-d H:i:s', time() + $timeout);

        $this->log("Temporary locking slot v6.2.0: slot={$slotId}, session={$sessionId}, expires={$expiresAt}", 'info');

        return $this->atomicOperation('delivery_slots', $slotId, function($slot) use ($sessionId, $expiresAt) {
            $tempLocks = $slot['temporary_locks'] ?? [];

            $now = time();
            foreach ($tempLocks as $sid => $expires) {
                if (strtotime($expires) <= $now) {
                    unset($tempLocks[$sid]);
                }
            }

            $tempLocks[$sessionId] = $expiresAt;
            $slot['temporary_locks'] = $tempLocks;
            $slot['updated_at'] = date('Y-m-d H:i:s');

            return [
                'success' => true,
                'slot_id' => $slot['id'],
                'expires_at' => $expiresAt
            ];
        });
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Снятие временной блокировки
     */
    public function releaseTemporaryLock($slotId, $sessionId) {
        if (!$this->config['slots_enabled']) {
            return ['success' => false, 'error' => 'Slots system disabled'];
        }

        return $this->atomicOperation('delivery_slots', $slotId, function($slot) use ($sessionId) {
            $tempLocks = $slot['temporary_locks'] ?? [];

            if (isset($tempLocks[$sessionId])) {
                unset($tempLocks[$sessionId]);
                $slot['temporary_locks'] = $tempLocks;
                $slot['updated_at'] = date('Y-m-d H:i:s');
            }

            return ['success' => true];
        });
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Очистка истекших временных блокировок
     */
    private function cleanExpiredTemporaryLocks(&$slot) {
        $tempLocks = $slot['temporary_locks'] ?? [];
        $now = time();
        $cleaned = false;

        foreach ($tempLocks as $sessionId => $expiresAt) {
            if (strtotime($expiresAt) <= $now) {
                unset($tempLocks[$sessionId]);
                $cleaned = true;
            }
        }

        if ($cleaned) {
            $slot['temporary_locks'] = $tempLocks;
        }
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Проверка доступности слота
     */
    public function checkSlotAvailability($slotId) {
        $slot = $this->find('delivery_slots', $slotId);

        if (!$slot) {
            return [
                'available' => false,
                'error' => 'Slot not found'
            ];
        }

        $totalSections = intval($slot['total_sections'] ?? 10);
        $availableSections = intval($slot['available_sections'] ?? $totalSections);
        $bookedSections = $slot['booked_sections'] ?? [];
        $tempLocks = $slot['temporary_locks'] ?? [];

        $activeTempLocks = 0;
        $now = time();

        foreach ($tempLocks as $expiresAt) {
            if (strtotime($expiresAt) > $now) {
                $activeTempLocks++;
            }
        }

        $realAvailable = $availableSections - $activeTempLocks;

        return [
            'available' => $realAvailable > 0,
            'slot_id' => $slotId,
            'total_sections' => $totalSections,
            'booked_sections' => count($bookedSections),
            'available_sections' => $availableSections,
            'temporary_locks' => $activeTempLocks,
            'real_available' => $realAvailable,
            'status' => $slot['status'],
            'date' => $slot['date'],
            'time' => $slot['time'],
            'zone_id' => $slot['zone_id'],
        ];
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Импорт слотов из 1С
     */
    public function import1CSlots($slotsData) {
        $this->log("Importing slots from 1C v6.2.0: " . count($slotsData) . " slots", 'info');

        $results = [
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        foreach ($slotsData as $slotData) {
            try {
                $externalId = $slotData['external_id'] ?? null;
                $zoneId = $slotData['zone_id'] ?? null;
                $date = $slotData['date'] ?? null;
                $time = $slotData['time'] ?? null;

                if (!$externalId || !$zoneId || !$date || !$time) {
                    $results['skipped']++;
                    $results['errors'][] = "Missing required fields in slot data";
                    continue;
                }

                $existingSlot = $this->findOne('delivery_slots', ['external_id' => $externalId]);

                $slotRecord = [
                    'zone_id' => $zoneId,
                    'date' => $date,
                    'time' => $time,
                    'type' => $slotData['type'] ?? 'delivery',
                    'total_sections' => intval($slotData['total_sections'] ?? $this->config['default_slot_sections']),
                    'batch_number' => $slotData['batch_number'] ?? null,
                    'batch_capacity' => intval($slotData['batch_capacity'] ?? 0),
                    'external_id' => $externalId,
                    'created_from_1c' => true,
                    'last_sync_1c' => date('Y-m-d H:i:s'),
                    'status' => $slotData['status'] ?? 'active',
                ];

                if ($existingSlot) {
                    $slotRecord['id'] = $existingSlot['id'];
                    $slotRecord['available_sections'] = $existingSlot['available_sections'];
                    $slotRecord['booked_sections'] = $existingSlot['booked_sections'] ?? [];
                    $slotRecord['temporary_locks'] = $existingSlot['temporary_locks'] ?? [];

                    $this->saveWithoutValidation('delivery_slots', $slotRecord, $existingSlot['id']);
                    $results['updated']++;
                } else {
                    $slotRecord['available_sections'] = $slotRecord['total_sections'];
                    $slotRecord['booked_sections'] = [];
                    $slotRecord['temporary_locks'] = [];

                    $this->saveWithoutValidation('delivery_slots', $slotRecord);
                    $results['imported']++;
                }

            } catch (Exception $e) {
                $results['errors'][] = "Error importing slot: " . $e->getMessage();
            }
        }

        $this->reloadSlotsCache();

        $this->log("Slots import completed v6.2.0: imported={$results['imported']}, updated={$results['updated']}, errors=" . count($results['errors']), 'info');

        return $results;
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Экспорт слотов для 1С
     */
    public function export1CSlots($date = null) {
        $filter = ['created_from_1c' => false];

        if ($date) {
            $filter['date'] = $date;
        }

        $slots = $this->findAll('delivery_slots', $filter);
        $exportData = [];

        foreach ($slots as $slot) {
            $exportData[] = [
                'id' => $slot['id'],
                'external_id' => $slot['external_id'] ?? null,
                'zone_id' => $slot['zone_id'],
                'date' => $slot['date'],
                'time' => $slot['time'],
                'type' => $slot['type'],
                'total_sections' => $slot['total_sections'],
                'available_sections' => $slot['available_sections'],
                'booked_count' => count($slot['booked_sections'] ?? []),
                'batch_number' => $slot['batch_number'] ?? null,
                'status' => $slot['status'],
            ];
        }

        $this->log("Exported " . count($exportData) . " slots for 1C v6.2.0", 'info');

        return $exportData;
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Сохранение истории изменений слота
     */
    private function saveSlotHistory($slotId, $orderId, $action, $sectionNumber = null, $sectionsBefore = null, $sectionsAfter = null, $comment = null) {
        $history = [
            'slot_id' => $slotId,
            'order_id' => $orderId,
            'action' => $action,
            'section_number' => $sectionNumber,
            'sections_before' => $sectionsBefore,
            'sections_after' => $sectionsAfter,
            'comment' => $comment,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        $this->saveWithoutValidation('slots_history', $history);
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Создание записи бронирования
     */
    private function createSlotBooking($slotId, $orderId, $sectionNumber, $sessionId = null) {
        $booking = [
            'slot_id' => $slotId,
            'order_id' => $orderId,
            'section_number' => $sectionNumber,
            'booked_at' => date('Y-m-d H:i:s'),
            'status' => 'active',
            'booking_type' => $sessionId ? 'temporary' : 'order',
            'session_id' => $sessionId,
        ];

        $this->saveWithoutValidation('slot_bookings', $booking);
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Освобождение записи бронирования
     */
    private function releaseSlotBooking($slotId, $orderId) {
        $bookings = $this->findAll('slot_bookings', [
            'slot_id' => $slotId,
            'order_id' => $orderId,
            'status' => 'active'
        ]);

        foreach ($bookings as $booking) {
            $booking['status'] = 'released';
            $booking['released_at'] = date('Y-m-d H:i:s');
            $this->saveWithoutValidation('slot_bookings', $booking, $booking['id']);
        }
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Получение истории слота
     */
    public function getSlotHistory($slotId, $limit = 50) {
        $history = $this->findAll('slots_history', ['slot_id' => $slotId]);

        usort($history, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($history, 0, $limit);
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Получение всех бронирований слота
     */
    public function getSlotBookings($slotId) {
        return $this->findAll('slot_bookings', ['slot_id' => $slotId]);
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Автоматическая очистка истекших временных блокировок
     */
    public function cleanupExpiredSlotLocks() {
        $slots = $this->findAll('delivery_slots');
        $cleaned = 0;

        foreach ($slots as $slot) {
            $tempLocks = $slot['temporary_locks'] ?? [];
            $originalCount = count($tempLocks);
            $now = time();

            foreach ($tempLocks as $sessionId => $expiresAt) {
                if (strtotime($expiresAt) <= $now) {
                    unset($tempLocks[$sessionId]);
                }
            }

            if (count($tempLocks) < $originalCount) {
                $slot['temporary_locks'] = $tempLocks;
                $this->saveWithoutValidation('delivery_slots', $slot, $slot['id']);
                $cleaned++;
            }
        }

        $this->log("Cleaned expired slot locks v6.2.0: {$cleaned} slots updated", 'info');

        return $cleaned;
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Перезагрузка кэша слотов
     */
    public function reloadSlotsCache() {
        $this->slotsCache = [];
        $this->zoneSlots = [];
        $this->loadSlotsCache();
        return count($this->slotsCache);
    }

    /**
     * ✅ СОХРАНЕНО v5.2.0: Загрузка кэша слотов
     */
    private function loadSlotsCache() {
        if (!$this->config['slots_enabled']) {
            return;
        }

        try {
            $slots = $this->findAll('delivery_slots', ['status' => 'active']);
            $this->slotsCache = [];
            $this->zoneSlots = [];

            foreach ($slots as $slot) {
                $slotId = $slot['id'];
                $zoneId = $slot['zone_id'] ?? null;
                $date = $slot['date'] ?? null;

                $this->slotsCache[$slotId] = $slot;

                if ($zoneId && $date) {
                    $key = $zoneId . '_' . $date;
                    if (!isset($this->zoneSlots[$key])) {
                        $this->zoneSlots[$key] = [];
                    }
                    $this->zoneSlots[$key][] = $slotId;
                }
            }

            $this->log("Slots cache loaded v6.2.0: " . count($this->slotsCache) . " active slots", 'info');
        } catch (Exception $e) {
            $this->log("Failed to load slots cache: " . $e->getMessage(), 'warning');
            $this->slotsCache = [];
            $this->zoneSlots = [];
        }
    }

    // ============= СОХРАНЕННЫЕ МЕТОДЫ ИЗ v5.x: ОБРАБОТКА ЗАКАЗОВ =============

    /**
     * ✅ РАСШИРЕНО v6.2.0: Завершение заказа с освобождением слота
     */
    public function completeOrder($orderId) {
        $order = $this->find('orders', $orderId);

        if (!$order) {
            throw new Exception("Order not found: {$orderId}");
        }

        $this->log("Completing order v6.2.0: order={$orderId}", 'info');

        // Освобождаем слот если включено
        if ($this->config['auto_release_on_delivery'] && !empty($order['delivery_slot_id'])) {
            try {
                $this->releaseSlotSection($order['delivery_slot_id'], $orderId);
                $order['slot_released_at'] = date('Y-m-d H:i:s');
            } catch (Exception $e) {
                $this->log("Failed to release slot for completed order {$orderId}: " . $e->getMessage(), 'error');
            }
        }

        $order['status'] = 'completed';
        $order['completed_at'] = date('Y-m-d H:i:s');
        $order['updated_at'] = date('Y-m-d H:i:s');

        if (!isset($order['status_history'])) {
            $order['status_history'] = [];
        }

        $order['status_history'][] = [
            'status' => 'completed',
            'date' => date('Y-m-d H:i:s'),
            'source' => 'manual'
        ];

        $this->saveWithoutValidation('orders', $order, $orderId);

        $this->log("Order completed v6.2.0: order={$orderId}", 'info');

        return true;
    }

    /**
     * ✅ СОХРАНЕНО: Обновление товара из корзины
     */
    public function updateCartItem($sessionId, $productId, $quantity) {
        $cart = $this->getCart($sessionId, null, false);
        $items = $cart['items'] ?? [];
        $found = false;

        foreach ($items as $index => &$item) {
            if ($item['id'] == $productId && !($item['is_gift'] ?? false)) {
                if ($quantity <= 0) {
                    unset($items[$index]);
                } else {
                    $item['quantity'] = intval($quantity);
                    $item['subtotal'] = $item['quantity'] * $item['price'];
                }
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new Exception("Product not found in cart: {$productId}");
        }

        $cart['items'] = array_values($items);
        $cart = $this->recalculateCart($cart);

        // Пересчитываем акции
        $cart = $this->checkAndApplyPromotions($cart);

        $this->saveWithoutValidation('cart', $cart, $cart['id']);

        $this->log("Updated cart item v6.2.0: product={$productId}, quantity={$quantity}, cart_id={$cart['id']}", 'info');

        return $cart;
    }

    /**
     * ✅ СОХРАНЕНО: Удаление товара из корзины
     */
    public function removeFromCart($sessionId, $productId) {
        return $this->updateCartItem($sessionId, $productId, 0);
    }

    /**
     * ✅ РАСШИРЕНО v6.2.0: Очистка корзины с освобождением слота
     */
    public function clearCart($sessionId) {
        $cart = $this->getCart($sessionId, null, false);

        // Освобождаем временный слот
        if (!empty($cart['temp_slot_id'])) {
            $this->releaseTemporaryLock($cart['temp_slot_id'], $sessionId);
        }

        $cart['items'] = [];
        $cart['subtotal'] = 0;
        $cart['delivery_cost'] = 0;
        $cart['total'] = 0;
        $cart['discount'] = 0;
        $cart['temp_slot_id'] = null;
        $cart['temp_slot_expires_at'] = null;
        $cart['applied_promotions'] = [];
        $cart['gift_items'] = [];
        $cart['certificate_code'] = null;
        $cart['certificate_amount'] = 0;

        $this->saveWithoutValidation('cart', $cart, $cart['id']);

        $this->log("Cart cleared v6.2.0: cart_id={$cart['id']}", 'info');

        return $cart;
    }

    /**
     * ✅ СОХРАНЕНО: Получение брошенных корзин
     */
    public function getAbandonedCarts($hours = 24) {
        $carts = $this->findAll('cart', ['status' => 'active']);
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));

        $abandoned = [];

        foreach ($carts as $cart) {
            if (isset($cart['updated_at']) && $cart['updated_at'] < $cutoff) {
                $abandoned[] = $cart;
            }
        }

        return $abandoned;
    }

    /**
     * ✅ РАСШИРЕНО v6.2.0: Очистка брошенных корзин с освобождением слотов
     */
    public function cleanAbandonedCarts($hours = 24) {
        $abandoned = $this->getAbandonedCarts($hours);
        $count = 0;

        foreach ($abandoned as $cart) {
            // Освобождаем временные слоты
            if (!empty($cart['temp_slot_id'])) {
                try {
                    $this->releaseTemporaryLock($cart['temp_slot_id'], $cart['session_id']);
                } catch (Exception $e) {
                    $this->log("Failed to release temp slot for abandoned cart {$cart['id']}: " . $e->getMessage(), 'warning');
                }
            }

            $cart['status'] = 'abandoned';
            $cart['temp_slot_id'] = null;
            $cart['temp_slot_expires_at'] = null;
            $this->saveWithoutValidation('cart', $cart, $cart['id']);
            $count++;
        }

        $this->log("Cleaned {$count} abandoned carts v6.2.0 (older than {$hours} hours)", 'info');

        return $count;
    }

    /**
     * ✅ СОХРАНЕНО: Генерация номера заказа
     */
    private function generateOrderNumber() {
        $prefix = 'ORD';
        $date = date('ymd');
        $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        return $prefix . '-' . $date . '-' . $random;
    }

    // ============= 🎯 НОВОЕ v6.2.0: ВАЛИДАЦИЯ ЗОНЫ ДОСТАВКИ =============

    /**
     * 🎯 РАСШИРЕНО v6.2.0: Определение зоны доставки с улучшенной логикой
     */
    private function detectDeliveryZone($address) {
        $zones = $this->findAll('delivery_zones', ['status' => 'active']);

        foreach ($zones as $zone) {
            $streets = $zone['streets'] ?? [];

            foreach ($streets as $street) {
                if (mb_stripos($address, $street) !== false) {
                    return $zone;
                }
            }

            $zoneName = mb_strtolower($zone['name'] ?? '');
            $addressLower = mb_strtolower($address);

            if (mb_stripos($addressLower, $zoneName) !== false) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * 🎯 НОВОЕ v6.2.0: Проверка возможности доставки в зону
     * 
     * @param string $address Адрес доставки
     * @param float $orderSum Сумма заказа
     * @param string $deliveryDate Дата доставки
     * @param string $deliveryTime Время доставки
     * @return array ['can_deliver' => bool, 'zone' => array|null, 'reason' => string]
     */
    public function validateDeliveryZone($address, $orderSum, $deliveryDate = null, $deliveryTime = null) {
        if (!$this->config['strict_zone_validation']) {
            return ['can_deliver' => true, 'zone' => null, 'reason' => 'Validation disabled'];
        }

        // Определяем зону
        $zone = $this->detectDeliveryZone($address);

        if (!$zone) {
            return [
                'can_deliver' => false,
                'zone' => null,
                'reason' => 'Адрес находится вне зоны доставки'
            ];
        }

        // Проверяем минимальную сумму заказа
        $minOrder = floatval($zone['min_order'] ?? 0);
        if ($orderSum < $minOrder) {
            return [
                'can_deliver' => false,
                'zone' => $zone,
                'reason' => "Минимальная сумма заказа для этой зоны: {$minOrder} ₽"
            ];
        }

        // Проверяем доступность по времени (если нужна расширенная логика)
        // TODO: Добавить проверку рабочих часов зоны

        return [
            'can_deliver' => true,
            'zone' => $zone,
            'reason' => '',
            'delivery_cost' => $zone['delivery_cost'] ?? 0
        ];
    }

    // ============= СОХРАНЕННЫЕ МЕТОДЫ: КЭШИРОВАНИЕ И КОНФИГУРАЦИЯ =============

    /**
     * ✅ СОХРАНЕНО: Загрузка конфигурации
     */
    private function loadConfig() {
        $configFile = $this->dataPath . 'config/database_config.json';

        if (file_exists($configFile)) {
            $saved = json_decode(file_get_contents($configFile), true);
            if ($saved) {
                $this->config = array_merge($this->config, $saved);
            }
        }
    }

    /**
     * ✅ СОХРАНЕНО: Сохранение конфигурации
     */
    public function saveConfig() {
        $configDir = $this->dataPath . 'config/';
        if (!is_dir($configDir)) {
            @mkdir($configDir, 0777, true);
        }

        $configFile = $configDir . 'database_config.json';
        file_put_contents($configFile, json_encode($this->config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * ✅ СОХРАНЕНО: Получение конфигурации
     */
    public function getConfig() {
        return $this->config;
    }

    /**
     * ✅ СОХРАНЕНО: Обновление конфигурации
     */
    public function updateConfig($updates) {
        $this->config = array_merge($this->config, $updates);
        $this->saveConfig();
        return true;
    }

    /**
     * ✅ СОХРАНЕНО: Загрузка кэша заказов с Multi-ID
     */
    private function loadProcessedOrdersCache() {
        try {
            $orders = $this->findAll('orders');
            $this->processedOrderIds = [];
            $this->orderStructureHashes = [];

            foreach ($orders as $order) {
                $orderId = $order['id'];
                $externalId = $order['external_id'] ?? null;
                $orderNumber = $order['order_number'] ?? null;

                if (!empty($externalId)) {
                    $this->processedOrderIds[$externalId] = $orderId;
                }

                if (!empty($orderNumber)) {
                    $this->processedOrderIds['num_' . $orderNumber] = $orderId;
                }

                if ($this->config['enable_structure_hash']) {
                    $structureHash = $this->generateOrderStructureHash($order);
                    if ($structureHash) {
                        $this->orderStructureHashes[$structureHash] = $orderId;
                        $this->processedOrderIds['hash_' . $structureHash] = $orderId;
                    }
                }
            }

            $this->log("Orders cache loaded v6.2.0: " . count($this->processedOrderIds) . " identifiers (Multi-ID)", 'info');
        } catch (Exception $e) {
            $this->log("Failed to load orders cache: " . $e->getMessage(), 'warning');
            $this->processedOrderIds = [];
            $this->orderStructureHashes = [];
        }
    }

    /**
     * ✅ СОХРАНЕНО: Генерация хэша структуры заказа
     */
    private function generateOrderStructureHash($order) {
        $parts = [
            $order['customer_phone'] ?? '',
            $order['delivery_date'] ?? '',
            $order['delivery_time'] ?? '',
            $order['total'] ?? '',
            date('Y-m-d', strtotime($order['created_at'] ?? 'now'))
        ];

        $cleanParts = array_filter($parts, function($p) {
            return !empty($p);
        });

        if (count($cleanParts) < 3) {
            return null;
        }

        return md5(implode('|', $cleanParts));
    }

    /**
     * ✅ СОХРАНЕНО: Очистка и перезагрузка кэша заказов
     */
    public function reloadOrdersCache() {
        $this->processedOrderIds = [];
        $this->orderStructureHashes = [];
        $this->loadProcessedOrdersCache();
        return count($this->processedOrderIds);
    }

    // ============= ВАЛИДАЦИЯ (СОХРАНЕНО + РАСШИРЕНО v6.2.0) =============

    /**
     * ✅ СОХРАНЕНО: Валидация данных по схеме
     */
    private function validate($table, $data, $isUpdate = false) {
        if (!isset($this->schemas[$table])) {
            return ['valid' => true];
        }

        $schema = $this->schemas[$table];
        $errors = [];

        foreach ($schema as $field => $rules) {
            $value = $data[$field] ?? null;

            if (!$isUpdate && isset($rules['required']) && $rules['required'] && empty($value) && $value !== 0 && $value !== '0') {
                $errors[$field] = "Field '{$field}' is required";
                continue;
            }

            if ($value === null || $value === '') {
                continue;
            }

            if (isset($rules['type'])) {
                switch ($rules['type']) {
                  case 'string':
                        if (!is_string($value) && !is_null($value)) {
                            $value = (string)$value;
                            $data[$field] = $value;
                        }
                        if (!is_string($value) && !is_null($value)) {
                            $errors[$field] = "Field '{$field}' must be a string";
                        } elseif (is_string($value) && isset($rules['max']) && mb_strlen($value) > $rules['max']) {
                            $errors[$field] = "Field '{$field}' must be less than {$rules['max']} characters";
                        }
                        break;

                    case 'int':
                        if (!is_numeric($value)) {
                            $errors[$field] = "Field '{$field}' must be an integer";
                        } elseif (isset($rules['min']) && $value < $rules['min']) {
                            $errors[$field] = "Field '{$field}' must be at least {$rules['min']}";
                        }
                        break;

                    case 'float':
                        if (!is_numeric($value)) {
                            $errors[$field] = "Field '{$field}' must be a number";
                        } elseif (isset($rules['min']) && $value < $rules['min']) {
                            $errors[$field] = "Field '{$field}' must be at least {$rules['min']}";
                        }
                        break;

                    case 'bool':
                        if (!is_bool($value) && $value !== 0 && $value !== 1 && $value !== '0' && $value !== '1') {
                            $errors[$field] = "Field '{$field}' must be boolean";
                        }
                        break;

                    case 'enum':
                        if (isset($rules['values']) && !in_array($value, $rules['values'])) {
                            $errors[$field] = "Field '{$field}' must be one of: " . implode(', ', $rules['values']);
                        }
                        break;

                    case 'array':
                        if (!is_array($value)) {
                            $errors[$field] = "Field '{$field}' must be an array";
                        }
                        break;
                }
            }

            if (isset($rules['email']) && $rules['email'] && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = "Field '{$field}' must be a valid email";
            }

            if (isset($rules['foreign_key']) && $value) {
                $relatedTable = $rules['foreign_key'];
                if (!$this->exists($relatedTable, $value)) {
                    $errors[$field] = "Related record in '{$relatedTable}' with ID {$value} not found";
                }
            }

            if (isset($rules['unique']) && $rules['unique']) {
                $existing = $this->findBy($table, $field, $value);
                if ($existing && (!$isUpdate || $existing['id'] !== $data['id'])) {
                    $errors[$field] = "Field '{$field}' must be unique. Value '{$value}' already exists";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * ✅ СОХРАНЕНО: Применение дефолтных значений
     */
    private function applyDefaults($table, $data) {
        if (!isset($this->schemas[$table])) {
            return $data;
        }

        $schema = $this->schemas[$table];

        foreach ($schema as $field => $rules) {
            if (!isset($data[$field]) && isset($rules['default'])) {
                $data[$field] = $rules['default'];
            }
        }

        return $data;
    }

    // ============= СВЯЗИ (RELATIONS) - СОХРАНЕНО =============

    /**
     * ✅ СОХРАНЕНО: Получение данных со связями
     */
    public function findWithRelations($table, $id, $relations = []) {
        $data = $this->find($table, $id);

        if (!$data || empty($relations)) {
            return $data;
        }

        $availableRelations = $this->relations[$table] ?? [];

        foreach ($relations as $relationName) {
            if (!isset($availableRelations[$relationName])) {
                continue;
            }

            $relation = $availableRelations[$relationName];

            if ($relation['type'] === 'belongsTo') {
                $foreignKey = $relation['foreign_key'];
                if (isset($data[$foreignKey]) && $data[$foreignKey]) {
                    $data[$relationName] = $this->find($relation['table'], $data[$foreignKey]);
                }
            } elseif ($relation['type'] === 'hasMany') {
                $foreignKey = $relation['foreign_key'];
                $data[$relationName] = $this->findAll($relation['table'], [$foreignKey => $data['id']]);
            }
        }

        return $data;
    }

    /**
     * ✅ СОХРАНЕНО: Получение всех данных со связями
     */
    public function findAllWithRelations($table, $relations = [], $filter = [], $limit = null) {
        $items = $this->findAll($table, $filter, $limit);

        if (empty($relations)) {
            return $items;
        }

        foreach ($items as &$item) {
            $item = $this->loadRelationsForItem($table, $item, $relations);
        }

        return $items;
    }

    /**
     * ✅ СОХРАНЕНО: Загрузка связей для элемента
     */
    private function loadRelationsForItem($table, $item, $relations) {
        $availableRelations = $this->relations[$table] ?? [];

        foreach ($relations as $relationName) {
            if (!isset($availableRelations[$relationName])) {
                continue;
            }

            $relation = $availableRelations[$relationName];

            if ($relation['type'] === 'belongsTo') {
                $foreignKey = $relation['foreign_key'];
                if (isset($item[$foreignKey]) && $item[$foreignKey]) {
                    $item[$relationName] = $this->find($relation['table'], $item[$foreignKey]);
                }
            } elseif ($relation['type'] === 'hasMany') {
                $foreignKey = $relation['foreign_key'];
                $item[$relationName] = $this->findAll($relation['table'], [$foreignKey => $item['id']]);
            }
        }

        return $item;
    }

    // ============= АТОМАРНЫЕ ОПЕРАЦИИ (СОХРАНЕНО) =============

    /**
     * ✅ СОХРАНЕНО: Инкремент значения
     */
    public function increment($table, $id, $field, $value = 1) {
        return $this->atomicOperation($table, $id, function($data) use ($field, $value) {
            $data[$field] = ($data[$field] ?? 0) + $value;
            return $data;
        });
    }

    /**
     * ✅ СОХРАНЕНО: Декремент значения
     */
    public function decrement($table, $id, $field, $value = 1) {
        return $this->atomicOperation($table, $id, function($data) use ($field, $value) {
            $data[$field] = ($data[$field] ?? 0) - $value;
            return $data;
        });
    }

    /**
     * ✅ СОХРАНЕНО: Атомарная операция с блокировкой
     */
    public function atomicOperation($table, $id, $callback) {
        $lockFile = $this->dataPath . 'locks/' . $table . '_' . $id . '.lock';

        $fp = @fopen($lockFile, 'c');
        if (!$fp) {
            throw new Exception("Cannot create lock file: {$lockFile}");
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            throw new Exception("Cannot acquire lock for {$table}:{$id}");
        }

        try {
            $data = $this->find($table, $id);

            if (!$data) {
                throw new Exception("Record not found: {$table}:{$id}");
            }

            $result = $callback($data);

            // Если callback вернул результат операции (не данные)
            if (isset($result['success'])) {
                flock($fp, LOCK_UN);
                fclose($fp);
                @unlink($lockFile);
                return $result;
            }

            // Иначе это обновленные данные
            $data = $result;
            $this->save($table, $data, $id);

            flock($fp, LOCK_UN);
            fclose($fp);
            @unlink($lockFile);

            return $data;
        } catch (Exception $e) {
            flock($fp, LOCK_UN);
            fclose($fp);
            @unlink($lockFile);
            throw $e;
        }
    }

    // ============= BULK ОПЕРАЦИИ (СОХРАНЕНО) =============

    /**
     * ✅ СОХРАНЕНО: Массовая вставка
     */
    public function bulkInsert($table, $items) {
        $inserted = [];
        $errors = [];

        foreach ($items as $index => $item) {
            try {
                $id = $this->save($table, $item);
                $inserted[] = $id;
            } catch (Exception $e) {
                $errors[$index] = $e->getMessage();
            }
        }

        return [
            'inserted' => $inserted,
            'errors' => $errors,
            'total' => count($inserted),
            'failed' => count($errors)
        ];
    }

    /**
     * ✅ СОХРАНЕНО: Массовое обновление
     */
    public function bulkUpdate($table, $filter, $updates) {
        $items = $this->findAll($table, $filter);
        $updated = 0;

        foreach ($items as $item) {
            $item = array_merge($item, $updates);
            $this->save($table, $item, $item['id']);
            $updated++;
        }

        return $updated;
    }

    // ============= 🔥 КРИТИЧНО v6.2.0: СОХРАНЕНИЕ С АВТОПЕРЕСЧЕТОМ ОСТАТКОВ =============

    /**
     * ✅ СОХРАНЕНО: Вспомогательные методы преобразования типов
     */
    private function toInt($value, $default = 0) {
        if (is_numeric($value)) {
            return intval($value);
        }
        return $default;
    }

    private function toFloat($value, $default = 0.0) {
        if (is_numeric($value)) {
            return floatval($value);
        }
        return $default;
    }

    /**
     * 🔥 РАСШИРЕНО v6.2.0: Сохранение с автопересчетом остатков
     */
    public function save($table, $data, $id = null, $bypassValidation = false) {
        $isUpdate = ($id !== null) || isset($data['id']);

        $data = $this->applyDefaults($table, $data);

        // 🔥 КРИТИЧНО v6.2.0: Автопересчет остатков для товаров
        if ($table === 'products') {
            $this->autoCalculateStock($data);
        }

        // 🎯 НОВОЕ v6.0.0: Автообновление полей для заказов
        if ($table === 'orders') {
            $this->updateOrderPermissions($data);
        }

        if (!$bypassValidation) {
            $validation = $this->validate($table, $data, $isUpdate);
            if (!$validation['valid']) {
                $errorMsg = "Validation failed for {$table}: " . json_encode($validation['errors']);
                $this->log($errorMsg, 'error');
                throw new Exception($errorMsg);
            }
        }

        if ($id === null && !isset($data['id'])) {
            $id = $this->generateId($table);
            $data['id'] = $id;
            $isNew = true;
        } elseif ($id === null && isset($data['id'])) {
            $id = $data['id'];
            $filePath = $this->dataPath . $table . '/' . $id . '.json';
            $isNew = !file_exists($filePath);
        } else {
            $data['id'] = $id;
            $filePath = $this->dataPath . $table . '/' . $id . '.json';
            $isNew = !file_exists($filePath);
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($isNew) {
            $data['created_at'] = date('Y-m-d H:i:s');
        } else {
            if (!isset($data['created_at'])) {
                $existing = $this->find($table, $id);
                if ($existing && isset($existing['created_at'])) {
                    $data['created_at'] = $existing['created_at'];
                } else {
                    $data['created_at'] = date('Y-m-d H:i:s');
                }
            }
        }

        $dir = $this->dataPath . $table;

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                throw new Exception("Failed to create directory: {$dir}");
            }
        }

        $filePath = $dir . '/' . $id . '.json';

        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($jsonData === false) {
            throw new Exception("Failed to encode JSON: " . json_last_error_msg());
        }

        $attempts = 3;
        $result = false;

        for ($i = 0; $i < $attempts; $i++) {
            $result = @file_put_contents($filePath, $jsonData, LOCK_EX);
            if ($result !== false) {
                break;
            }
            usleep(100000);
        }

        if ($result === false) {
            $error = error_get_last();
            throw new Exception("Failed to write file: {$filePath}. Error: " . ($error['message'] ?? 'Unknown'));
        }

        clearstatcache(true, $filePath);

        if (!file_exists($filePath)) {
            throw new Exception("File was not created: {$filePath}");
        }

        $filesize = filesize($filePath);
        if ($filesize === false || $filesize === 0) {
            throw new Exception("File is empty: {$filePath}");
        }

        $verification = @file_get_contents($filePath);
        if ($verification === false || empty($verification)) {
            throw new Exception("File verification failed (cannot read): {$filePath}");
        }

        $verifyData = json_decode($verification, true);
        if ($verifyData === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("File verification failed (invalid JSON): {$filePath}");
        }

        $this->updateIndex($table, $id, $data);

        // 🎯 НОВОЕ v6.0.0: Обновляем кэши
        if ($table === 'orders' && $isNew) {
            if (!empty($data['external_id'])) {
                $this->processedOrderIds[$data['external_id']] = $id;
            }
            if (!empty($data['order_number'])) {
                $this->processedOrderIds['num_' . $data['order_number']] = $id;
            }
            if (!empty($data['structure_hash']) && $this->config['enable_structure_hash']) {
                $this->processedOrderIds['hash_' . $data['structure_hash']] = $id;
                $this->orderStructureHashes[$data['structure_hash']] = $id;
            }
        }

        if ($table === 'delivery_slots') {
            $this->slotsCache[$id] = $data;

            $zoneId = $data['zone_id'] ?? null;
            $date = $data['date'] ?? null;

            if ($zoneId && $date) {
                $key = $zoneId . '_' . $date;
                if (!isset($this->zoneSlots[$key])) {
                    $this->zoneSlots[$key] = [];
                }
                if (!in_array($id, $this->zoneSlots[$key])) {
                    $this->zoneSlots[$key][] = $id;
                }
            }

            // Инвалидируем кэш доступных слотов
            if ($zoneId && $date) {
                $type = $data['type'] ?? 'delivery';
                $cacheKey = "slots_{$zoneId}_{$date}_{$type}";
                $cacheFile = $this->dataPath . 'temp/' . $cacheKey . '.json';
                @unlink($cacheFile);
            }
        }

        if ($table === 'promotions') {
            $this->activePromotions[$id] = $data;
        }

        unset($this->cache[$table . '_all']);

        $action = $isNew ? 'CREATED' : 'UPDATED';
        $this->log("{$table} {$action} v6.2.0: ID={$id}, name=" . ($data['name'] ?? 'N/A') . ", size={$filesize} bytes", 'info');

        return $id;
    }

    /**
     * ✅ СОХРАНЕНО: Сохранение без валидации
     */
    public function saveWithoutValidation($table, $data, $id = null) {
        return $this->save($table, $data, $id, true);
    }

    // ============= ПОИСК И ЧТЕНИЕ (СОХРАНЕНО) =============

    /**
     * ✅ СОХРАНЕНО: Поиск по ID
     */
    public function find($table, $id) {
        $filePath = $this->dataPath . $table . '/' . $id . '.json';

        if (!file_exists($filePath)) {
            return null;
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            $this->log("Failed to read file: {$filePath}", 'warning');
            return null;
        }

        $data = json_decode($content, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->log("Failed to decode JSON in file: {$filePath}", 'error');
            return null;
        }

        return $data;
    }

    /**
     * ✅ СОХРАНЕНО: Алиас для find
     */
    public function findById($table, $id) {
        return $this->find($table, $id);
    }

    /**
     * ✅ СОХРАНЕНО: Поиск всех записей
     */
    public function findAll($table, $filter = [], $limit = null, $offset = 0) {
        $cacheKey = $table . '_all';

        if (empty($filter) && isset($this->cache[$cacheKey])) {
            $results = $this->cache[$cacheKey];
        } else {
            $results = [];
            $dir = $this->dataPath . $table . '/';

            if (!is_dir($dir)) {
                return [];
            }

            $files = glob($dir . '*.json');

            if ($files === false) {
                return [];
            }

            foreach ($files as $file) {
                if (basename($file) === 'index.json') {
                    continue;
                }

                $content = @file_get_contents($file);
                if ($content === false) {
                    continue;
                }

                $data = json_decode($content, true);

                if ($data === null || !is_array($data)) {
                    continue;
                }

                if (empty($filter) || $this->matchesFilter($data, $filter)) {
                    $results[] = $data;
                }
            }

            usort($results, function($a, $b) {
                $aId = $this->toInt($a['id'] ?? 0);
                $bId = $this->toInt($b['id'] ?? 0);
                return $aId - $bId;
            });

            if (empty($filter)) {
                $this->cache[$cacheKey] = $results;
            }
        }

        if ($limit !== null) {
            $results = array_slice($results, $offset, $limit);
        } elseif ($offset > 0) {
            $results = array_slice($results, $offset);
        }

        return $results;
    }

    /**
     * ✅ СОХРАНЕНО: Поиск одной записи
     */
    public function findOne($table, $filter) {
        $results = $this->findAll($table, $filter, 1);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * ✅ СОХРАНЕНО: Поиск по полю
     */
    public function findBy($table, $field, $value) {
        return $this->findOne($table, [$field => $value]);
    }

    /**
     * ✅ СОХРАНЕНО: Создание записи
     */
    public function create($table, $data) {
        return $this->save($table, $data);
    }

    /**
     * ✅ СОХРАНЕНО: Обновление записи
     */
    public function update($table, $id, $data) {
        return $this->save($table, $data, $id);
    }

    /**
     * ✅ РАСШИРЕНО v6.2.0: Удаление с освобождением ресурсов
     */
    public function delete($table, $id) {
        $filePath = $this->dataPath . $table . '/' . $id . '.json';

        if (!file_exists($filePath)) {
            return false;
        }

        // 🎯 НОВОЕ v6.0.0: Особая обработка удаления заказов
        if ($table === 'orders') {
            $order = $this->find($table, $id);
            if ($order) {
                // Освобождаем слот если был
                if (!empty($order['delivery_slot_id'])) {
                    try {
                        $this->releaseSlotSection($order['delivery_slot_id'], $id);
                    } catch (Exception $e) {
                        $this->log("Failed to release slot when deleting order {$id}: " . $e->getMessage(), 'warning');
                    }
                }

                // Удаляем из кэша
                if (!empty($order['external_id'])) {
                    unset($this->processedOrderIds[$order['external_id']]);
                }
                if (!empty($order['order_number'])) {
                    unset($this->processedOrderIds['num_' . $order['order_number']]);
                }
                if (!empty($order['structure_hash'])) {
                    unset($this->processedOrderIds['hash_' . $order['structure_hash']]);
                    unset($this->orderStructureHashes[$order['structure_hash']]);
                }
            }
        }

        if ($table === 'delivery_slots') {
            $slot = $this->find($table, $id);
            if ($slot) {
                unset($this->slotsCache[$id]);

                $zoneId = $slot['zone_id'] ?? null;
                $date = $slot['date'] ?? null;

                if ($zoneId && $date) {
                    $key = $zoneId . '_' . $date;
                    if (isset($this->zoneSlots[$key])) {
                        $this->zoneSlots[$key] = array_diff($this->zoneSlots[$key], [$id]);
                    }
                }
            }
        }

        if ($table === 'promotions') {
            unset($this->activePromotions[$id]);
        }

        if (@unlink($filePath)) {
            $this->removeFromIndex($table, $id);
            unset($this->cache[$table . '_all']);

            $this->log("Deleted from {$table} v6.2.0: ID={$id}", 'info');
            return true;
        }

        return false;
    }

    /**
     * ✅ СОХРАНЕНО: Удаление по фильтру
     */
    public function deleteWhere($table, $filters) {
        $items = $this->findAll($table, $filters);
        $deleted = 0;

        foreach ($items as $item) {
            if (isset($item['id']) && $this->delete($table, $item['id'])) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * ✅ СОХРАНЕНО: Генерация ID
     */
    private function generateId($table) {
        $dir = $this->dataPath . $table . '/';

        if (!is_dir($dir)) {
            return 1;
        }

        $files = glob($dir . '*.json');

        if ($files === false || empty($files)) {
            return 1;
        }

        $maxId = 0;

        foreach ($files as $file) {
            if (basename($file) === 'index.json') {
                continue;
            }

            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);

            if ($data && isset($data['id'])) {
                $currentId = $this->toInt($data['id']);
                if ($currentId > $maxId) {
                    $maxId = $currentId;
                }
            }
        }

        return $maxId + 1;
    }

    // ============= ИНДЕКСАЦИЯ (РАСШИРЕНА v6.2.0) =============

    /**
     * 🔥 РАСШИРЕНО v6.2.0: Обновление индекса с полями остатков
     */
    private function updateIndex($table, $id, $data) {
        $indexPath = $this->dataPath . $table . '/index.json';
        $index = $this->getIndex($table);

        $indexEntry = [
            'id' => $id,
            'name' => $data['name'] ?? $data['title'] ?? '',
            'status' => $data['status'] ?? 'active',
            'created_at' => $data['created_at'],
            'updated_at' => $data['updated_at']
        ];

        if ($table === 'products') {
            $indexEntry['price'] = $this->toFloat($data['price'] ?? 0);
            $indexEntry['category_id'] = $data['category_id'] ?? null;
            $indexEntry['external_id'] = $data['external_id'] ?? '';
            $indexEntry['sku'] = $data['sku'] ?? '';

            // 🔥 НОВОЕ v6.2.0: Индексируем остатки правильно
            $indexEntry['stock'] = $this->toInt($data['stock'] ?? 0);
            $indexEntry['unlimited_stock'] = $data['unlimited_stock'] ?? true;
            $indexEntry['stock_quantity'] = $this->toInt($data['stock_quantity'] ?? 0);

            $indexEntry['is_new'] = $data['is_new'] ?? false;
            $indexEntry['is_popular'] = $data['is_popular'] ?? false;
            $indexEntry['is_light'] = $data['is_light'] ?? false;
            $indexEntry['is_spicy'] = $data['is_spicy'] ?? false;
            $indexEntry['is_vegetarian'] = $data['is_vegetarian'] ?? false;
            $indexEntry['parent_name'] = $data['parent_name'] ?? '';
            $indexEntry['is_closed'] = $data['is_closed'] ?? false;
        }

        if ($table === 'categories') {
            $indexEntry['slug'] = $data['slug'] ?? '';
            $indexEntry['external_id'] = $data['external_id'] ?? '';
            $indexEntry['is_special'] = $data['is_special'] ?? false;
            $indexEntry['attribute_filter'] = $data['attribute_filter'] ?? null;
            $indexEntry['order'] = $this->toInt($data['order'] ?? 999);
            $indexEntry['product_count'] = $this->toInt($data['product_count'] ?? 0);
            $indexEntry['is_visible'] = $data['is_visible'] ?? null;
            $indexEntry['created_from_1c'] = $data['created_from_1c'] ?? false;
        }

        if ($table === 'orders') {
            $indexEntry['order_number'] = $data['order_number'] ?? '';
            $indexEntry['customer_id'] = $data['customer_id'] ?? null;
            $indexEntry['total'] = $this->toFloat($data['total'] ?? 0);
            $indexEntry['payment_status'] = $data['payment_status'] ?? 'pending';
            $indexEntry['payment_method'] = $data['payment_method'] ?? 'cash';
            $indexEntry['delivery_type'] = $data['delivery_type'] ?? 'delivery';
            $indexEntry['external_id'] = $data['external_id'] ?? '';
            $indexEntry['is_exported_1c'] = $data['is_exported_1c'] ?? false;
            $indexEntry['structure_hash'] = $data['structure_hash'] ?? null;
            $indexEntry['delivery_slot_id'] = $data['delivery_slot_id'] ?? null;

            // 🎯 НОВЫЕ ПОЛЯ v6.0.0
            $indexEntry['people_count'] = $data['people_count'] ?? 1;
            $indexEntry['gift_items_count'] = $data['gift_items_count'] ?? 0;
            $indexEntry['certificate_applied'] = $data['certificate_applied'] ?? null;
            $indexEntry['can_cancel'] = $data['can_cancel'] ?? true;
            $indexEntry['can_edit'] = $data['can_edit'] ?? true;
        }

        if ($table === 'customers') {
            $indexEntry['email'] = $data['email'] ?? '';
            $indexEntry['phone'] = $data['phone'] ?? '';
            $indexEntry['external_id'] = $data['external_id'] ?? '';
            $indexEntry['bonus_balance'] = $this->toFloat($data['bonus_balance'] ?? 0);

            // 🎯 НОВЫЕ ПОЛЯ v6.0.0
            $indexEntry['email_verified'] = $data['email_verified'] ?? false;
            $indexEntry['orders_count'] = $data['orders_count'] ?? 0;
            $indexEntry['total_spent'] = $data['total_spent'] ?? 0;
        }

        if ($table === 'delivery_zones') {
            $indexEntry['delivery_cost'] = $this->toFloat($data['delivery_cost'] ?? 0);
            $indexEntry['min_order'] = $this->toFloat($data['min_order'] ?? 0);
            $indexEntry['delivery_time'] = $data['delivery_time'] ?? '';
            $indexEntry['external_id'] = $data['external_id'] ?? '';
        }

        if ($table === 'delivery_slots') {
            $indexEntry['zone_id'] = $data['zone_id'] ?? null;
            $indexEntry['date'] = $data['date'] ?? '';
            $indexEntry['time'] = $data['time'] ?? '';
            $indexEntry['type'] = $data['type'] ?? 'delivery';
            $indexEntry['total_sections'] = $this->toInt($data['total_sections'] ?? 10);
            $indexEntry['available_sections'] = $this->toInt($data['available_sections'] ?? 10);
            $indexEntry['booked_count'] = count($data['booked_sections'] ?? []);
            $indexEntry['batch_number'] = $data['batch_number'] ?? '';
            $indexEntry['external_id'] = $data['external_id'] ?? '';
        }

        if ($table === 'cart') {
            $indexEntry['session_id'] = $data['session_id'] ?? '';
            $indexEntry['customer_id'] = $data['customer_id'] ?? null;
            $indexEntry['total'] = $this->toFloat($data['total'] ?? 0);
            $indexEntry['items_count'] = count($data['items'] ?? []);
            $indexEntry['temp_slot_id'] = $data['temp_slot_id'] ?? null;

            // 🎯 НОВЫЕ ПОЛЯ v6.0.0
            $indexEntry['certificate_code'] = $data['certificate_code'] ?? null;
            $indexEntry['discount'] = $data['discount'] ?? 0;
        }

        // 🎯 НОВЫЕ ТАБЛИЦЫ v6.0.0

        if ($table === 'promotions') {
            $indexEntry['type'] = $data['type'] ?? 'gift';
            $indexEntry['active'] = $data['active'] ?? true;
            $indexEntry['min_sum'] = $data['min_sum'] ?? 0;
            $indexEntry['priority'] = $data['priority'] ?? 10;
            $indexEntry['current_uses'] = $data['current_uses'] ?? 0;
        }

        if ($table === 'customer_addresses') {
            $indexEntry['customer_id'] = $data['customer_id'] ?? null;
            $indexEntry['label'] = $data['label'] ?? 'home';
            $indexEntry['is_default'] = $data['is_default'] ?? false;
            $indexEntry['zone_id'] = $data['zone_id'] ?? null;
        }

        if ($table === 'certificates') {
            $indexEntry['code'] = $data['code'] ?? '';
            $indexEntry['value'] = $data['value'] ?? 0;
            $indexEntry['balance'] = $data['balance'] ?? 0;
            $indexEntry['type'] = $data['type'] ?? 'monetary';
        }

        if ($table === 'admin_gifts') {
            $indexEntry['customer_id'] = $data['customer_id'] ?? null;
            $indexEntry['product_id'] = $data['product_id'] ?? null;
            $indexEntry['status'] = $data['status'] ?? 'pending';
        }

        if ($table === 'content_pages') {
            $indexEntry['slug'] = $data['slug'] ?? '';
            $indexEntry['title'] = $data['title'] ?? '';
            $indexEntry['show_in_footer'] = $data['show_in_footer'] ?? true;
        }

        $index[$id] = $indexEntry;

        @file_put_contents($indexPath, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * ✅ СОХРАНЕНО: Получение индекса
     */
       public function getIndex($table) {
        $indexPath = $this->dataPath . $table . '/index.json';

        if (!file_exists($indexPath)) {
            return [];
        }

        $content = @file_get_contents($indexPath);
        if ($content === false) {
            return [];
        }

        $index = json_decode($content, true);

        return is_array($index) ? $index : [];
    }

    /**
     * ✅ СОХРАНЕНО: Удаление из индекса
     */
    private function removeFromIndex($table, $id) {
        $indexPath = $this->dataPath . $table . '/index.json';
        $index = $this->getIndex($table);
        unset($index[$id]);
        @file_put_contents($indexPath, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * ✅ СОХРАНЕНО: Проверка соответствия фильтру
     */
    private function matchesFilter($item, $filter) {
        foreach ($filter as $key => $value) {
            if (!isset($item[$key]) || $item[$key] != $value) {
                return false;
            }
        }
        return true;
    }

    /**
     * ✅ СОХРАНЕНО: Пересоздание индекса
     */
    public function rebuildIndex($table) {
        $this->log("Rebuilding index v6.2.0 for table: {$table}", 'info');

        $dir = $this->dataPath . $table . '/';

        if (!is_dir($dir)) {
            $this->log("Directory does not exist: {$dir}", 'warning');
            return false;
        }

        $files = glob($dir . '*.json');

        if ($files === false) {
            $this->log("Failed to read directory: {$dir}", 'error');
            return false;
        }

        $indexPath = $dir . 'index.json';
        @file_put_contents($indexPath, json_encode([], JSON_PRETTY_PRINT), LOCK_EX);

        $count = 0;
        foreach ($files as $file) {
            if (basename($file) === 'index.json') {
                continue;
            }

            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);

            if ($data && isset($data['id'])) {
                $this->updateIndex($table, $data['id'], $data);
                $count++;
            }
        }

        unset($this->cache[$table . '_all']);

        if ($table === 'orders') {
            $this->reloadOrdersCache();
        }

        if ($table === 'delivery_slots') {
            $this->reloadSlotsCache();
        }

        if ($table === 'promotions') {
            $this->loadActivePromotions();
        }

        $this->log("Index rebuilt v6.2.0 for table: {$table}, entries: {$count}", 'info');

        return true;
    }

    // ============= ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ (РАСШИРЕНО v6.2.0) =============

    /**
     * 🔥 РАСШИРЕНО v6.2.0: Статистика таблицы
     */
    public function getTableStats($table) {
        $dir = $this->dataPath . $table . '/';
        $stats = [
            'total_files' => 0,
            'total_items' => 0,
            'index_items' => 0,
            'index_exists' => false,
            'directory_writable' => false,
            'directory_exists' => false,
            'version' => '6.2.0'
        ];

        if (!is_dir($dir)) {
            return $stats;
        }

        $stats['directory_exists'] = true;
        $stats['directory_writable'] = is_writable($dir);

        $files = glob($dir . '*.json');

        if ($files === false) {
            return $stats;
        }

        $stats['total_files'] = count($files);

        foreach ($files as $file) {
            if (basename($file) === 'index.json') {
                $stats['index_exists'] = true;
                $index = json_decode(@file_get_contents($file), true);
                $stats['index_items'] = count($index ?: []);
            } else {
                $stats['total_items']++;
            }
        }

        return $stats;
    }

    /**
     * ✅ СОХРАНЕНО: Очистка таблицы
     */
    public function truncate($table) {
        $dir = $this->dataPath . $table . '/';

        if (!is_dir($dir)) {
            return true;
        }

        $files = glob($dir . '*.json');

        if ($files !== false) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }

        unset($this->cache[$table . '_all']);

        if ($table === 'orders') {
            $this->processedOrderIds = [];
            $this->orderStructureHashes = [];
        }

        if ($table === 'delivery_slots') {
            $this->slotsCache = [];
            $this->zoneSlots = [];
        }

        if ($table === 'promotions') {
            $this->activePromotions = [];
        }

        $this->log("Table {$table} truncated v6.2.0", 'info');

        return true;
    }

    /**
     * ✅ СОХРАНЕНО: Подсчет записей
     */
    public function count($table, $filter = []) {
        return count($this->findAll($table, $filter));
    }

    /**
     * ✅ СОХРАНЕНО: Проверка существования
     */
    public function exists($table, $id = null) {
        if ($id === null) {
            return is_dir($this->dataPath . $table);
        }

        $filePath = $this->dataPath . $table . '/' . $id . '.json';
        return file_exists($filePath);
    }

    /**
     * ✅ СОХРАНЕНО: Получение первой записи
     */
    public function first($table, $filters = []) {
        return $this->findOne($table, $filters);
    }

    /**
     * ✅ СОХРАНЕНО: Получение последней записи
     */
    public function last($table, $filters = []) {
        $results = $this->findAll($table, $filters);
        return !empty($results) ? end($results) : null;
    }

    /**
     * ✅ СОХРАНЕНО: Пагинация
     */
    public function paginate($table, $page = 1, $perPage = 20, $filters = []) {
        $allData = $this->findAll($table, $filters);
        $total = count($allData);
        $totalPages = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $data = array_slice($allData, $offset, $perPage);

        return [
            'data' => $data,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }

    /**
     * ✅ СОХРАНЕНО: Сортировка
     */
    public function orderBy($table, $field, $direction = 'ASC', $filters = []) {
        $data = $this->findAll($table, $filters);

        usort($data, function($a, $b) use ($field, $direction) {
            $aVal = $a[$field] ?? null;
            $bVal = $b[$field] ?? null;

            if (is_numeric($aVal) && is_numeric($bVal)) {
                $aVal = $this->toFloat($aVal);
                $bVal = $this->toFloat($bVal);
            }

            if ($aVal === $bVal) return 0;

            $result = $aVal < $bVal ? -1 : 1;

            return strtoupper($direction) === 'DESC' ? -$result : $result;
        });

        return $data;
    }

    /**
     * ✅ СОХРАНЕНО: Поиск
     */
    public function search($table, $query, $fields = []) {
        $data = $this->findAll($table);
        $query = mb_strtolower($query, 'UTF-8');

        return array_filter($data, function($item) use ($query, $fields) {
            foreach ($fields as $field) {
                if (isset($item[$field])) {
                    $value = mb_strtolower($item[$field], 'UTF-8');
                    if (strpos($value, $query) !== false) {
                        return true;
                    }
                }
            }
            return false;
        });
    }

    /**
     * ✅ СОХРАНЕНО: Извлечение значений поля
     */
    public function pluck($table, $field, $filters = []) {
        $data = $this->findAll($table, $filters);
        $values = array_column($data, $field);
        return array_unique($values);
    }

    /**
     * ✅ СОХРАНЕНО: Группировка
     */
    public function groupBy($table, $field, $filters = []) {
        $data = $this->findAll($table, $filters);
        $grouped = [];

        foreach ($data as $item) {
            $key = $item[$field] ?? 'null';
            if (!isset($grouped[$key])) {
                $grouped[$key] = 0;
            }
            $grouped[$key]++;
        }

        return $grouped;
    }

    /**
     * ✅ СОХРАНЕНО: Бэкап таблицы
     */
    public function backup($table) {
        $sourceDir = $this->dataPath . $table . '/';

        if (!is_dir($sourceDir)) {
            return false;
        }

        $backupDir = $this->dataPath . 'backups/' . $table . '/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        $backupPath = $backupDir . date('Y-m-d_H-i-s') . '/';
        mkdir($backupPath, 0777, true);

        $files = glob($sourceDir . '*.json');
        $copied = 0;

        foreach ($files as $file) {
            if (copy($file, $backupPath . basename($file))) {
                $copied++;
            }
        }

        $this->log("Backed up {$table} v6.2.0: {$copied} files", 'info');

        return $backupPath;
    }

    /**
     * ✅ СОХРАНЕНО: Восстановление из бэкапа
     */
    public function restore($table, $backupPath) {
        if (!is_dir($backupPath)) {
            $this->log("Backup path not found: {$backupPath}", 'error');
            return false;
        }

        $targetDir = $this->dataPath . $table . '/';

        $this->truncate($table);

        $files = glob($backupPath . '*.json');
        $restored = 0;

        foreach ($files as $file) {
            if (copy($file, $targetDir . basename($file))) {
                $restored++;
            }
        }

        unset($this->cache[$table . '_all']);

        if ($table === 'orders') {
            $this->reloadOrdersCache();
        }

        if ($table === 'delivery_slots') {
            $this->reloadSlotsCache();
        }

        if ($table === 'promotions') {
            $this->loadActivePromotions();
        }

        $this->log("Restored {$table} v6.2.0: {$restored} files from {$backupPath}", 'info');

        return $restored;
    }

    /**
     * ✅ СОХРАНЕНО: Получение списка таблиц
     */
    public function getTables() {
        $dirs = glob($this->dataPath . '*', GLOB_ONLYDIR);
        $tables = [];

        $excludeDirs = [
            'logs', 'backups', '1c_exchange', 'exports', 'locks', 'config', 
            'diff_tracking', 'rollback', 'batch_queue', 'conflicts', 'temp', 
            'file_tracking', 'slots_bookings', 'slots_history', 'sessions',
            'email_queue', 'password_resets', 'content_pages', 'admin_gifts'
        ];

        foreach ($dirs as $dir) {
            $name = basename($dir);
            if (!in_array($name, $excludeDirs)) {
                $tables[] = $name;
            }
        }

        return $tables;
    }

    /**
     * ✅ СОХРАНЕНО: Информация о таблице
     */
    public function getTableInfo($table) {
        $dir = $this->dataPath . $table . '/';

        if (!is_dir($dir)) {
            return null;
        }

        $files = glob($dir . '*.json');
        $count = 0;
        $size = 0;

        foreach ($files as $file) {
            if (basename($file) !== 'index.json') {
                $count++;
                $size += filesize($file);
            }
        }

        return [
            'name' => $table,
            'path' => $dir,
            'records' => $count,
            'size' => $size,
            'size_formatted' => $this->formatBytes($size),
            'modified' => is_file($dir . 'index.json') ? date('Y-m-d H:i:s', filemtime($dir . 'index.json')) : null
        ];
    }

    /**
     * ✅ СОХРАНЕНО: Форматирование размера
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * 🔥 РАСШИРЕНО v6.2.0: Общая статистика системы
     */
    public function getStats() {
        $stats = [
            'tables' => [],
            'total_records' => 0,
            'total_size' => 0,
            'orders_cache_size' => count($this->processedOrderIds),
            'structure_hashes' => count($this->orderStructureHashes),
            'slots_cache_size' => count($this->slotsCache),
            'zone_slots_count' => count($this->zoneSlots),
            'active_promotions' => count($this->activePromotions),
            'version' => '6.2.0 MEGA ULTIMATE - FIXED STOCK & CATEGORIES',
            'integration_version' => '17.2',
            'features' => [
                'promotions_system' => $this->config['promotions_enabled'],
                'certificates' => $this->config['certificates_enabled'],
                'slots_system' => $this->config['slots_enabled'],
                'customer_passwords' => $this->config['customer_passwords_enabled'],
                'multiple_addresses' => $this->config['multiple_addresses_enabled'],
                'smart_merge' => $this->config['enable_smart_merge'],
                'diff_tracking' => $this->config['enable_diff_tracking'],
                'stock_zero_means_unlimited' => $this->config['stock_zero_means_unlimited'],
                'auto_calculate_stock' => $this->config['auto_calculate_stock'],
            ]
        ];

        foreach ($this->getTables() as $table) {
            $info = $this->getTableInfo($table);
            if ($info) {
                $stats['tables'][$table] = $info;
                $stats['total_records'] += $info['records'];
                $stats['total_size'] += $info['size'];
            }
        }

        $stats['total_size_formatted'] = $this->formatBytes($stats['total_size']);

        return $stats;
    }

    /**
     * ✅ СОХРАНЕНО: Логирование
     */
    public function log($message, $type = 'info') {
        $logPath = $this->dataPath . 'logs/' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;

        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        @file_put_contents($logPath, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * ✅ СОХРАНЕНО: Получение логов
     */
    public function getLogs($date = null) {
        $date = $date ?? date('Y-m-d');
        $logPath = $this->dataPath . 'logs/' . $date . '.log';

        if (file_exists($logPath)) {
            return file($logPath, FILE_IGNORE_NEW_LINES);
        }

        return [];
    }

    /**
     * ✅ СОХРАНЕНО: Очистка старых логов
     */
    public function cleanLogs($days = 7) {
        $logDir = $this->dataPath . 'logs/';

        if (!is_dir($logDir)) {
            return 0;
        }

        $files = glob($logDir . '*.log');
        $deleted = 0;
        $cutoff = time() - ($days * 86400);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    // ============= 🎯 НОВОЕ v6.0.0: ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ДЛЯ КЛИЕНТА =============

    /**
     * 🎯 НОВОЕ v6.0.0: Получение заказов клиента с пагинацией
     */
    public function getCustomerOrders($customerId, $page = 1, $perPage = 10) {
        return $this->paginate('orders', $page, $perPage, ['customer_id' => $customerId]);
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Получение активных подарков клиента
     */
    public function getCustomerGifts($customerId) {
        return $this->getPendingGiftsForCustomer($customerId);
    }

    /**
     * 🎯 НОВОЕ v6.0.0: Получение полного профиля клиента
     */
    public function getCustomerFullProfile($customerId) {
        $customer = $this->find('customers', $customerId);

        if (!$customer) {
            return null;
        }

        $customer['addresses'] = $this->getCustomerAddresses($customerId);
        $customer['phones'] = $this->findAll('customer_phones', ['customer_id' => $customerId]);
        $customer['orders'] = $this->findAll('orders', ['customer_id' => $customerId]);
        $customer['pending_gifts'] = $this->getPendingGiftsForCustomer($customerId);

        return $customer;
    }

    // ============= 🔥 МЕТОДЫ v6.1.0 ДЛЯ СОВМЕСТИМОСТИ (ДОБАВЛЕНЫ В v6.2.0) =============

    /**
     * 🔥 НОВОЕ v6.1.0: Проверка вместимости слота по количеству запеченных роллов
     */
    public function checkSlotCapacity($slotId, $hotRollsCount) {
        $slot = $this->find('delivery_slots', $slotId);

        if (!$slot) {
            return false;
        }

        $maxCapacity = intval($slot['max_hot_rolls'] ?? 25);
        $currentLoad = intval($slot['current_hot_rolls'] ?? 0);

        return ($currentLoad + $hotRollsCount) <= $maxCapacity;
    }

    /**
     * 🔥 НОВОЕ v6.1.0: Автоматическое создание категории из родителя товара
     */
    public function autoCreateCategoryFromParent($parentName, $externalId = null) {
        // Проверяем существование
        $existing = $this->findOne('categories', ['name' => $parentName]);

        if ($existing) {
            return $existing['id'];
        }

        // Создаем новую категорию
        $slug = $this->generateSlug($parentName);

       $category = [
            'name' => $parentName,
            'slug' => $slug,
            'description' => '',
            'status' => 'active',
            'is_special' => false,
            'external_id' => $externalId ?? $slug,
            'created_from_1c' => true,
            'order' => 999,
            'product_count' => 0,
            'is_visible' => null, // ✅ null = авто (управляется по наличию товаров)
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $categoryId = $this->save('categories', $category);

        $this->log("🔥 Auto-created category v6.2.0: {$parentName} (id: {$categoryId})", 'info');

        return $categoryId;
    }

    /**
     * 🔥 НОВОЕ v6.1.0: Генерация slug из названия
     */
    public function generateSlug($name) {
        $translit = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya'
        ];

        $slug = mb_strtolower($name, 'UTF-8');
        $slug = strtr($slug, $translit);
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }

    /**
     * 🔥 НОВОЕ v6.1.0: Обновление счетчика товаров в категории
     */
    public function updateCategoryProductCount($categoryId) {
        $productsCount = $this->count('products', [
            'category_id' => $categoryId,
            'is_closed' => false,
            'status' => 'active'
        ]);

        $category = $this->find('categories', $categoryId);
        if ($category) {
            $category['product_count'] = $productsCount;

            // ✅ Не перезаписываем is_visible если оно задано вручную (не null)
            // null = авто-управление, 0 = скрыто вручную, 1 = показано вручную
            if (!isset($category['is_visible']) || $category['is_visible'] === null) {
                $category['is_visible'] = ($productsCount > 0) ? true : null;
            }

            $this->saveWithoutValidation('categories', $category, $categoryId);
        }
    }

    /**
     * 🔥 НОВОЕ v6.1.0: Подсчет запеченных роллов в заказе
     */
    public function calculateOrderHotRolls($order) {
        $hotRollsCount = 0;

        if (!isset($order['items']) || !is_array($order['items'])) {
            return 0;
        }

        foreach ($order['items'] as $item) {
            $productId = $item['product_id'] ?? $item['id'] ?? 0;
            $product = $this->find('products', $productId);

            if ($product && ($product['is_hot_roll'] ?? false)) {
                $hotRollsCount += intval($item['quantity'] ?? 1);
            }
        }

        return $hotRollsCount;
    }

    /**
     * 🔥 НОВОЕ v6.2.0: Получение товаров с правильной фильтрацией по остаткам
     * 
     * @param array $filters Фильтры
     * @param bool $hideOutOfStock Скрывать товары с 0 остатком (не unlimited)
     * @return array Список товаров
     */
    public function getProductsWithStockFilter($filters = [], $hideOutOfStock = null) {
        if ($hideOutOfStock === null) {
            $hideOutOfStock = $this->config['hide_out_of_stock'] ?? false;
        }

        $products = $this->findAll('products', $filters);

        if (!$hideOutOfStock) {
            return $products;
        }

        // Фильтруем: показываем только товары с unlimited_stock ИЛИ stock_quantity > 0
        return array_filter($products, function($product) {
            $unlimited = $product['unlimited_stock'] ?? true;
            $quantity = $product['stock_quantity'] ?? 0;

            return $unlimited || $quantity > 0;
        });
    }

} // ← ЗАКРЫВАЮЩАЯ СКОБКА КЛАССА Database

// ============= 🎯 ИНИЦИАЛИЗАЦИЯ БАЗЫ ДАННЫХ v6.2.0 =============

$db = new Database();

/**
 * 🔥 РАСШИРЕНО v6.2.0: Инициализация с новыми данными
 */
function initializeDatabase($db) {
    // Специальные категории
    $specialCategories = [
        [
            'name' => '🔥 Популярное',
            'slug' => 'popular',
            'description' => 'Самые популярные блюда',
            'status' => 'active',
            'is_special' => true,
            'attribute_filter' => 'is_popular',
            'icon' => '🔥',
            'order' => 1,
            'external_id' => 'special_popular'
        ],
        [
            'name' => '✨ Новинки',
            'slug' => 'new',
            'description' => 'Новые позиции в меню',
            'status' => 'active',
            'is_special' => true,
            'attribute_filter' => 'is_new',
            'icon' => '✨',
            'order' => 2,
            'external_id' => 'special_new'
        ],
        [
    'name'             => '🌶️ Острое',
    'slug'             => 'spicy',
    'description'      => 'Острые блюда',
    'status'           => 'active',
    'is_special'       => true,
    'attribute_filter' => 'is_spicy',
    'icon'             => '🌶️',
    'order'            => 5,
    'external_id'      => 'special_spicy'
],
        [
            'name' => '🍃 Лёгкие',
            'slug' => 'light',
            'description' => 'Лёгкие блюда',
            'status' => 'active',
            'is_special' => true,
            'attribute_filter' => 'is_light',
            'icon' => '🍃',
            'order' => 3,
            'external_id' => 'special_light'
        ]
    ];

    foreach ($specialCategories as $category) {
        $existing = $db->findBy('categories', 'slug', $category['slug']);
        if (!$existing) {
            $db->saveWithoutValidation('categories', $category);
            $db->log("Special category created v6.2.0: {$category['name']}", 'info');
        }
    }

    // Обычные категории
    $categories = [
        ['name' => 'Роллы', 'slug' => 'rolls', 'description' => 'Японские роллы', 'status' => 'active', 'order' => 10],
        ['name' => 'Суши', 'slug' => 'sushi', 'description' => 'Классические суши', 'status' => 'active', 'order' => 20],
        ['name' => 'Сашими', 'slug' => 'sashimi', 'description' => 'Свежая рыба без риса', 'status' => 'active', 'order' => 30],
        ['name' => 'Горячие роллы', 'slug' => 'hot-rolls', 'description' => 'Запеченные роллы', 'status' => 'active', 'order' => 40],
        ['name' => 'Поке', 'slug' => 'poke', 'description' => 'Гавайские боулы', 'status' => 'active', 'order' => 50],
        ['name' => 'Сеты', 'slug' => 'sets', 'description' => 'Готовые наборы', 'status' => 'active', 'order' => 60],
        ['name' => 'Напитки', 'slug' => 'drinks', 'description' => 'Безалкогольные напитки', 'status' => 'active', 'order' => 70]
    ];

    foreach ($categories as $category) {
        $existing = $db->findBy('categories', 'slug', $category['slug']);
        if (!$existing) {
            $db->saveWithoutValidation('categories', $category);
        }
    }

    // 🎯 НОВОЕ v6.0.0: Создаем дефолтные контентные страницы
    $contentPages = [
        [
            'slug' => 'promotions',
            'title' => 'Акции',
            'content' => '<h1>Акции и спецпредложения</h1><p>Здесь будут отображаться актуальные акции.</p>',
            'status' => 'published',
            'show_in_footer' => true,
            'order' => 1
        ],
        [
            'slug' => 'payment',
            'title' => 'Оплата на сайте',
            'content' => '<h1>Способы оплаты</h1><p>Мы принимаем оплату наличными, картой, онлайн и через СБП.</p>',
            'status' => 'published',
            'show_in_footer' => true,
            'order' => 2
        ],
        [
            'slug' => 'privacy',
            'title' => 'Политика конфиденциальности',
            'content' => '<h1>Политика конфиденциальности</h1><p>Мы защищаем ваши персональные данные.</p>',
            'status' => 'published',
            'show_in_footer' => true,
            'order' => 3
        ]
    ];

    foreach ($contentPages as $page) {
        $existing = $db->findBy('content_pages', 'slug', $page['slug']);
        if (!$existing) {
            $db->saveWithoutValidation('content_pages', $page);
            $db->log("Content page created v6.2.0: {$page['title']}", 'info');
        }
    }

    // Основные настройки сайта
    $mainSettings = $db->find('settings', 'main');

    if (!$mainSettings) {
        $mainSettings = [
            'id' => 'main',
            'site_name' => "Sasha's Sushi",
            'site_description' => 'Лучшие суши и роллы в городе с доставкой',
            'site_logo' => '',
            'hero_image' => '',
            'phones' => ['+7 999 123-45-67'],
            'work_hours' => ['start' => '10:00', 'end' => '23:00'],
            'vk_link' => 'https://vk.com/sasha_s_sushi',
            'telegram_link' => '',
            'email' => '',
            'delivery_cost' => 200,
            'free_delivery_from' => 999,
            'min_order_amount' => 800,

            // 🎯 НОВОЕ v6.0.0: Настройка баннера вакансий
            'jobs_banner' => [
                'enabled' => true,
                'title' => 'Требуются работники',
                'description' => 'Официальное оформление. Стабильная зарплата!',
                'link' => 'https://forms.yandex.ru/cloud/65d07d1ac09c024b01bf6adb/',
                'button_text' => 'Заполнить анкету'
            ],

            // 🎯 НОВОЕ v6.0.0: Праздничные дни
            'holidays' => [
                '01-01', '01-02', '01-03', '01-04', '01-05', '01-06', '01-07', '01-08',
                '02-23', '03-08', '05-01', '05-09', '06-12', '11-04'
            ],

            '1c_integration' => [
                'enabled' => true,
                'api_endpoint' => 'api/1c-integration.php',
                'api_key' => '',
                'auto_sync' => true,
                'sync_interval' => 300,
                'export_orders' => true,
                'import_products' => true,
                'import_customers' => true,
                'import_orders' => true,
                'direct_orders' => true,
                'export_mode' => 'xml',
                'auto_create_categories' => true,
                'hide_empty_categories' => true,
                'export_full_1c_fields' => true,
                'parse_multiple_items' => true,
                'max_items_per_order' => 19,
                'strict_order_validation' => true,
                'skip_duplicate_orders' => true,
                'enable_smart_merge' => true,
                'enable_file_tracking' => true,
                'enable_diff_tracking' => true,
                'enable_structure_hash' => true,
                'batch_size' => 50,
                'conflict_resolution' => 'newer',
                'merge_strategy' => 'smart',
                'use_single_xml_export' => true,
                'single_xml_filename' => 'orders_export.xml',
                'include_processed_in_export' => false,
                'xml_export_format' => 'commerceml',
                'auto_apply_1c_updates' => true,
                'slots_enabled' => true,
                'auto_book_slots' => true,
                'auto_release_on_cancel' => true,
                'auto_release_on_delivery' => true,
                'import_slots_from_1c' => true,
                'export_slots_to_1c' => true,

                // 🔥 НОВОЕ v6.2.0
                'stock_zero_means_unlimited' => true,
                'auto_calculate_stock' => true,

                'version' => '17.2',
                'db_version' => '6.2.0',
                'last_sync' => null
            ]
        ];
        $db->saveWithoutValidation('settings', $mainSettings, 'main');
    }

    $db->log('🔥 Database initialized v6.2.0 MEGA ULTIMATE EDITION - FIXED STOCK & CATEGORIES ✅', 'info');
}

// Инициализация только если БД полностью пуста
$_allCats = $db->findAll('categories') ?: [];
if (empty($_allCats)) {
    initializeDatabase($db);
}
unset($_allCats);

$db->log('🎉 Database v6.2.0 MEGA ULTIMATE EDITION ready! STOCK=0 → UNLIMITED ✅', 'info');

// ✅ Пересборка индекса категорий если поле is_visible отсутствует в индексе
$_catIndex = $db->getIndex('categories');
$_needRebuild = false;
foreach ($_catIndex as $_entry) {
    if (!array_key_exists('is_visible', $_entry)) {
        $_needRebuild = true;
        break;
    }
}
if ($_needRebuild) {
    $db->rebuildIndex('categories');
    $db->log('✅ Categories index rebuilt — is_visible field added', 'info');
}
unset($_catIndex, $_needRebuild, $_entry);