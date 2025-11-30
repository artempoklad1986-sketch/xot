<?php
/**
 * Forum v5.0 - Production Ready with Full Console Logging
 * ✅ Полная интеграция с Database.php
 * ✅ Правильная маршрутизация страниц
 * ✅ Дни рождения с круглыми аватарами
 * ✅ BBCode редактор
 * ✅ Полное логирование в консоль браузера
 */

// ========================================
// БАЗОВАЯ КОНФИГУРАЦИЯ
// ========================================

error_log("=== FORUM.PHP STARTED ===");
console_log("🚀 Запуск форума - начало инициализации");

header('Content-Type: text/html; charset=utf-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
    console_log("✅ UTF-8 кодировка установлена");
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
console_log("✅ Настройки отображения ошибок установлены");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    console_log("✅ Сессия запущена: " . session_id());
} else {
    console_log("ℹ️ Сессия уже активна: " . session_id());
}

// ========================================
// ФУНКЦИЯ ЛОГИРОВАНИЯ В КОНСОЛЬ
// ========================================

function console_log($message, $data = null) {
    $output = is_string($message) ? $message : json_encode($message);

    if ($data !== null) {
        $output .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    $output = addslashes($output);

    if (!isset($GLOBALS['console_logs'])) {
        $GLOBALS['console_logs'] = [];
    }

    $GLOBALS['console_logs'][] = $output;
    error_log("CONSOLE: " . $output);
}

// ========================================
// CSRF ЗАЩИТА
// ========================================

console_log("🔐 Проверка CSRF токена...");

if (!isset($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        console_log("✅ CSRF токен создан: " . substr($_SESSION['csrf_token'], 0, 10) . "...");
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = md5(uniqid(mt_rand(), true));
        console_log("⚠️ CSRF токен создан через fallback: " . substr($_SESSION['csrf_token'], 0, 10) . "...");
    }
} else {
    console_log("ℹ️ CSRF токен уже существует: " . substr($_SESSION['csrf_token'], 0, 10) . "...");
}

// ========================================
// ПОДКЛЮЧЕНИЕ БАЗЫ ДАННЫХ
// ========================================

console_log("💾 Подключение к базе данных...");

try {
    $dbPath = __DIR__ . '/Database.php';
    console_log("📁 Путь к Database.php: " . $dbPath);

    if (!file_exists($dbPath)) {
        console_log("❌ Database.php не найден!");
        throw new Exception('Database.php not found');
    }

    console_log("✅ Database.php существует");

    require_once $dbPath;
    console_log("✅ Database.php подключен");

    $db = new Database();
    console_log("✅ Объект Database создан успешно");

} catch (Exception $e) {
    console_log("❌ КРИТИЧЕСКАЯ ОШИБКА: " . $e->getMessage());
    die('<h1 style="text-align:center;margin-top:100px;">Ошибка подключения к базе данных</h1>');
}

// ========================================
// ЗАГРУЗКА НАСТРОЕК
// ========================================

console_log("⚙️ Загрузка настроек сайта...");

try {
    $settings = $db->get('settings');
    console_log("📥 Настройки загружены из БД", $settings ? "Данные получены" : "Данные пусты");

    if (!is_array($settings)) {
        console_log("⚠️ Настройки не массив, инициализация дефолтных значений");
        $settings = [];
    }

    $defaults_settings = [
        'site_title' => 'Хотошо - Питомник',
        'header_image' => '',
        'forum_enabled' => true,
        'forum_allow_guest_view' => true,
        'phone' => '',
        'email' => '',
        'vk' => '',
        'footer_about' => 'О питомнике'
    ];

    $settings = array_merge($defaults_settings, $settings);
    console_log("✅ Настройки объединены с дефолтными значениями");
    console_log("📋 Название сайта: " . $settings['site_title']);
    console_log("📋 Форум включен: " . ($settings['forum_enabled'] ? 'ДА' : 'НЕТ'));
    console_log("📋 Гости могут просматривать: " . ($settings['forum_allow_guest_view'] ? 'ДА' : 'НЕТ'));

    if (empty($settings['forum_enabled'])) {
        console_log("❌ Форум отключен в настройках!");
        die('<h1 style="text-align:center;margin-top:100px;">Форум временно недоступен</h1>');
    }

} catch (Exception $e) {
    console_log("⚠️ Ошибка загрузки настроек: " . $e->getMessage());
    $settings = $defaults_settings;
}

// ========================================
// АВТОРИЗАЦИЯ И РОЛИ
// ========================================

console_log("👤 Проверка авторизации пользователя...");

$currentUser = null;
$isGuest = true;
$isModerator = false;
$isAdmin = false;

try {
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        console_log("🔍 User ID в сессии: " . $_SESSION['user_id']);

        $currentUser = $db->getUserById($_SESSION['user_id']);
        console_log("📥 Данные пользователя загружены", $currentUser ? "успешно" : "пусто");

        if ($currentUser && is_array($currentUser)) {
            $isGuest = false;
            $userRole = isset($currentUser['role']) ? strtolower($currentUser['role']) : 'user';
            $isModerator = in_array($userRole, ['moderator', 'admin']);
            $isAdmin = ($userRole === 'admin');

            console_log("✅ Пользователь авторизован: " . ($currentUser['username'] ?? 'unknown'));
            console_log("📋 Роль: " . $userRole);
            console_log("📋 Модератор: " . ($isModerator ? 'ДА' : 'НЕТ'));
            console_log("📋 Админ: " . ($isAdmin ? 'ДА' : 'НЕТ'));
        } else {
            console_log("⚠️ Пользователь не найден в БД, очистка сессии");
            unset($_SESSION['user_id']);
            unset($_SESSION['username']);
        }
    } else {
        console_log("ℹ️ Пользователь не авторизован (гость)");
    }
} catch (Exception $e) {
    console_log("❌ Ошибка проверки авторизации: " . $e->getMessage());
    $isGuest = true;
}

// ========================================
// ПРОВЕРКА ДОСТУПА (БЕЗ ЦИКЛОВ!)
// ========================================

console_log("🔒 Проверка прав доступа к форуму...");

if ($isGuest && empty($settings['forum_allow_guest_view'])) {
    console_log("❌ Гостям доступ запрещен!");

    if (!isset($_GET['access_denied'])) {
        console_log("🔄 Редирект на страницу входа");
        header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']) . '&access_denied=1');
        exit;
    } else {
        console_log("📄 Показ страницы отказа в доступе");
        die('
            <div style="max-width:600px;margin:100px auto;padding:40px;background:#fff;border:3px solid #3E3936;border-radius:15px;text-align:center;font-family:Tahoma,Arial,sans-serif;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
                <div style="font-size:64px;margin-bottom:20px;">🔒</div>
                <h1 style="color:#3E3936;margin-bottom:15px;font-size:24px;">Доступ ограничен</h1>
                <p style="font-size:14px;color:#666;margin-bottom:30px;line-height:1.6;">Для просмотра форума необходимо авторизоваться</p>
                <div style="margin-bottom:20px;">
                    <a href="login.php?redirect=forum.php" style="display:inline-block;background:#3E3936;color:white;padding:14px 35px;text-decoration:none;border-radius:8px;font-size:14px;margin:5px;transition:0.3s;">Войти</a>
                    <a href="register.php" style="display:inline-block;background:#2C5F8D;color:white;padding:14px 35px;text-decoration:none;border-radius:8px;font-size:14px;margin:5px;transition:0.3s;">Регистрация</a>
                </div>
                <a href="index.php" style="color:#999;font-size:13px;text-decoration:none;">← Вернуться на главную</a>
            </div>
        ');
    }
} else {
    console_log("✅ Доступ к форуму разрешен");
}

// ========================================
// ОБРАБОТКА POST ЗАПРОСОВ
// ========================================

console_log("📨 Проверка POST запросов...");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    console_log("📮 Получен POST запрос");
    console_log("📋 POST данные:", $_POST);

    // Создание темы
    if (isset($_POST['action']) && $_POST['action'] === 'create_topic' && !$isGuest) {
        console_log("📝 Обработка создания новой темы");

        $categoryId = intval($_POST['category_id']);
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);

        console_log("📋 Category ID: " . $categoryId);
        console_log("📋 Title: " . $title);
        console_log("📋 Content length: " . strlen($content));

        if (!empty($title) && !empty($content) && $categoryId > 0) {
            try {
                console_log("💾 Создание темы в БД...");
                $newTopicId = $db->createForumTopic($categoryId, $currentUser['id'], $title);
                console_log("✅ Тема создана, ID: " . $newTopicId);

                if ($newTopicId) {
                    console_log("💾 Создание первого поста в теме...");
                    $postCreated = $db->createForumPost($newTopicId, $currentUser['id'], $content);
                    console_log("✅ Пост создан, ID: " . $postCreated);

                    console_log("🔄 Редирект на новую тему: forum_topic.php?id=" . $newTopicId);
                    header("Location: forum_topic.php?id={$newTopicId}");
                    exit;
                } else {
                    console_log("❌ Не удалось создать тему");
                }
            } catch (Exception $e) {
                console_log("❌ Ошибка создания темы: " . $e->getMessage());
            }
        } else {
            console_log("⚠️ Валидация не прошла: пустые поля или неверный ID категории");
        }
    }

    // Создание поста
    if (isset($_POST['action']) && $_POST['action'] === 'create_post' && !$isGuest) {
        console_log("📝 Обработка создания нового поста");

        $topicId = intval($_POST['topic_id']);
        $content = trim($_POST['content']);

        console_log("📋 Topic ID: " . $topicId);
        console_log("📋 Content length: " . strlen($content));

        if (!empty($content) && $topicId > 0) {
            try {
                console_log("🔍 Проверка существования темы...");
                $topic = $db->getForumTopicById($topicId);

                if ($topic) {
                    console_log("✅ Тема найдена: " . $topic['title']);
                    console_log("🔍 Проверка блокировки темы...");

                    if (!$db->isTopicLocked($topicId)) {
                        console_log("✅ Тема не заблокирована");
                        console_log("💾 Создание поста...");

                        $postCreated = $db->createForumPost($topicId, $currentUser['id'], $content);
                        console_log("✅ Пост создан, ID: " . $postCreated);

                        console_log("🔄 Редирект на тему: forum_topic.php?id=" . $topicId);
                        header("Location: forum_topic.php?id={$topicId}");
                        exit;
                    } else {
                        console_log("⚠️ Тема заблокирована, пост не создан");
                    }
                } else {
                    console_log("❌ Тема не найдена");
                }
            } catch (Exception $e) {
                console_log("❌ Ошибка создания поста: " . $e->getMessage());
            }
        } else {
            console_log("⚠️ Валидация не прошла: пустой контент или неверный ID темы");
        }
    }
} else {
    console_log("ℹ️ POST запросов нет, метод: " . $_SERVER['REQUEST_METHOD']);
}

// ========================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
// ========================================

console_log("🔧 Инициализация вспомогательных функций");

function safe_htmlspecialchars($string) {
    if (!is_string($string)) {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function getImagePath($path) {
    if (empty($path) || !is_string($path)) {
        return '';
    }

    if (strpos($path, 'http') === 0) {
        return $path;
    }

    if (strpos($path, '/') === 0) {
        return $path;
    }

    return '/' . $path;
}

function formatDate($datetime) {
    if (empty($datetime)) {
        return '';
    }

    try {
        $timestamp = strtotime($datetime);

        if ($timestamp === false) {
            return $datetime;
        }

        $now = time();
        $diff = $now - $timestamp;

        if ($diff < 60) {
            return 'только что';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' мин. назад';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' ч. назад';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' дн. назад';
        } else {
            return date('d.m.Y в H:i', $timestamp);
        }

    } catch (Exception $e) {
        return $datetime;
    }
}

function getUserName($userId, $db) {
    try {
        $user = $db->getUserById($userId);

        if (!$user || !is_array($user)) {
            return 'Гость';
        }

        if (!empty($user['display_name'])) {
            return $user['display_name'];
        }

        if (!empty($user['username'])) {
            return $user['username'];
        }

        return 'Гость';

    } catch (Exception $e) {
        return 'Гость';
    }
}

function getUserAvatar($userId, $db) {
    try {
        $user = $db->getUserById($userId);

        if ($user && is_array($user) && isset($user['avatar']) && !empty($user['avatar'])) {
            return getImagePath($user['avatar']);
        }

        return null;

    } catch (Exception $e) {
        return null;
    }
}

function getUserInitials($userId, $db) {
    try {
        $name = getUserName($userId, $db);

        if (empty($name) || $name === 'Гость') {
            return '?';
        }

        $words = explode(' ', $name);

        if (count($words) >= 2) {
            $first = mb_substr($words[0], 0, 1, 'UTF-8');
            $second = mb_substr($words[1], 0, 1, 'UTF-8');
            return mb_strtoupper($first . $second, 'UTF-8');
        }

        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');

    } catch (Exception $e) {
        return '?';
    }
}

function getUserCity($userId, $db) {
    try {
        $user = $db->getUserById($userId);
        if ($user && is_array($user) && isset($user['city']) && !empty($user['city'])) {
            return $user['city'];
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

function getUserPostsCount($userId, $db) {
    try {
        $user = $db->getUserById($userId);
        if ($user && is_array($user) && isset($user['posts_count'])) {
            return intval($user['posts_count']);
        }
        return 0;
    } catch (Exception $e) {
        return 0;
    }
}

function getUserAge($birthday) {
    if (empty($birthday)) {
        return null;
    }

    try {
        $birthDate = new DateTime($birthday);
        $today = new DateTime('today');
        $age = $birthDate->diff($today)->y;
        return $age;
    } catch (Exception $e) {
        return null;
    }
}

console_log("✅ Вспомогательные функции инициализированы");

// ========================================
// ЗАГРУЗКА СТРУКТУРЫ ФОРУМА
// ========================================

console_log("🗂️ Загрузка структуры форума...");

$forumStructure = [];
try {
    $forumCategoriesData = $db->get('forum_categories');
    console_log("📥 Данные forum_categories загружены");

    if (is_array($forumCategoriesData)) {
        $forumStructure = $forumCategoriesData;
        console_log("✅ Структура форума загружена");
    } else {
        console_log("⚠️ forum_categories не является массивом");
    }
} catch (Exception $e) {
    console_log("❌ Ошибка загрузки структуры форума: " . $e->getMessage());
    $forumStructure = [];
}

$categories = isset($forumStructure['categories']) ? $forumStructure['categories'] : [];
$forums = isset($forumStructure['forums']) ? $forumStructure['forums'] : [];
$categoryGroups = isset($forumStructure['category_groups']) ? $forumStructure['category_groups'] : [];

console_log("📊 Категорий: " . count($categories));
console_log("📊 Форумов: " . count($forums));
console_log("📊 Групп категорий: " . count($categoryGroups));

// Темы
console_log("📚 Загрузка тем форума...");
$topics = [];
try {
    $topicsData = $db->get('forum_topics');
    if (is_array($topicsData) && isset($topicsData['topics'])) {
        $topics = $topicsData['topics'];
        console_log("✅ Загружено тем: " . count($topics));
    } else {
        console_log("⚠️ Темы не найдены или не массив");
    }
} catch (Exception $e) {
    console_log("❌ Ошибка загрузки тем: " . $e->getMessage());
    $topics = [];
}

// Сообщения
console_log("💬 Загрузка сообщений форума...");
$posts = [];
try {
    $postsData = $db->get('forum_posts');
    if (is_array($postsData) && isset($postsData['posts'])) {
        $posts = $postsData['posts'];
        console_log("✅ Загружено сообщений: " . count($posts));
    } else {
        console_log("⚠️ Сообщения не найдены или не массив");
    }
} catch (Exception $e) {
    console_log("❌ Ошибка загрузки сообщений: " . $e->getMessage());
    $posts = [];
}

// ========================================
// ОНЛАЙН ПОЛЬЗОВАТЕЛИ
// ========================================

console_log("🌐 Обработка онлайн пользователей...");

$onlineUsers = 0;
$onlineGuests = 0;
$onlineUsernames = [];

try {
    $onlineData = $db->get('forum_online');
    console_log("📥 Данные онлайн загружены");

    if (!is_array($onlineData)) {
        console_log("⚠️ Создание новой структуры онлайн данных");
        $onlineData = ['users' => []];
    }

    if (!isset($onlineData['users']) || !is_array($onlineData['users'])) {
        console_log("⚠️ Инициализация массива пользователей онлайн");
        $onlineData['users'] = [];
    }

    $currentTime = time();
    $timeout = 15 * 60;
    console_log("⏰ Текущее время: " . $currentTime);
    console_log("⏰ Таймаут: " . $timeout . " секунд");

    $beforeCount = count($onlineData['users']);
    console_log("📊 Пользователей онлайн до очистки: " . $beforeCount);

    $onlineData['users'] = array_filter($onlineData['users'], function($user) use ($currentTime, $timeout) {
        return isset($user['last_seen']) && ($currentTime - $user['last_seen']) <= $timeout;
    });

    $afterCount = count($onlineData['users']);
    console_log("📊 Пользователей онлайн после очистки: " . $afterCount);
    console_log("🗑️ Удалено неактивных: " . ($beforeCount - $afterCount));

    $sessionId = session_id();
    $found = false;
    console_log("🔍 Поиск текущей сессии: " . $sessionId);

    foreach ($onlineData['users'] as $key => $user) {
        if (isset($user['session_id']) && $user['session_id'] === $sessionId) {
            $onlineData['users'][$key]['last_seen'] = $currentTime;
            $found = true;
            console_log("✅ Сессия найдена, обновлено время");
            break;
        }
    }

    if (!$found) {
        console_log("➕ Добавление новой сессии в онлайн");
        $onlineData['users'][] = [
            'session_id' => $sessionId,
            'user_id' => $isGuest ? null : $_SESSION['user_id'],
            'username' => $isGuest ? null : (isset($_SESSION['username']) ? $_SESSION['username'] : 'User'),
            'last_seen' => $currentTime,
            'page' => 'forum'
        ];
        console_log("✅ Новая сессия добавлена");
    }

    console_log("💾 Сохранение данных онлайн...");
    $db->save('forum_online', $onlineData);
    console_log("✅ Данные онлайн сохранены");

    console_log("📊 Подсчет онлайн пользователей...");
    foreach ($onlineData['users'] as $user) {
        if (isset($user['user_id']) && !empty($user['user_id'])) {
            $onlineUsers++;
            if (isset($user['username'])) {
                $onlineUsernames[] = $user['username'];
            }
        } else {
            $onlineGuests++;
        }
    }

    console_log("👥 Зарегистрированных онлайн: " . $onlineUsers);
    console_log("👁️ Гостей онлайн: " . $onlineGuests);
    console_log("📋 Имена пользователей онлайн:", $onlineUsernames);

} catch (Exception $e) {
    console_log("❌ Ошибка обработки онлайн: " . $e->getMessage());
}

// ========================================
// СТАТИСТИКА
// ========================================

console_log("📊 Загрузка статистики форума...");

$stats = $db->getForumStats();
console_log("📥 Статистика получена из БД", $stats);

$totalTopics = isset($stats['topics']) ? $stats['topics'] : count($topics);
$totalPosts = isset($stats['posts']) ? $stats['posts'] : count($posts);
$totalUsers = isset($stats['users']) ? $stats['users'] : 0;

console_log("📈 Всего тем: " . $totalTopics);
console_log("📈 Всего сообщений: " . $totalPosts);
console_log("📈 Всего пользователей: " . $totalUsers);

console_log("🔍 Поиск последнего сообщения...");
$lastPost = null;
$lastTopic = null;
$lastUser = null;

if (!empty($posts)) {
    $postsCopy = $posts;
    usort($postsCopy, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $lastPost = $postsCopy[0];
    console_log("✅ Последнее сообщение найдено, ID: " . $lastPost['id']);
} else {
    console_log("ℹ️ Сообщений нет");
}

console_log("🔍 Поиск последней темы...");
if (!empty($topics)) {
    $topicsCopy = $topics;
    usort($topicsCopy, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $lastTopic = $topicsCopy[0];
    console_log("✅ Последняя тема найдена, ID: " . $lastTopic['id'] . ", Название: " . $lastTopic['title']);
} else {
    console_log("ℹ️ Тем нет");
}

console_log("🔍 Поиск последнего зарегистрированного пользователя...");
try {
    $usersData = $db->get('users');
    if (is_array($usersData) && isset($usersData['users']) && !empty($usersData['users'])) {
        $users = $usersData['users'];
        usort($users, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        $lastUser = $users[0];
        console_log("✅ Последний пользователь: " . ($lastUser['username'] ?? 'unknown'));
    } else {
        console_log("ℹ️ Пользователи не найдены");
    }
} catch (Exception $e) {
    console_log("❌ Ошибка загрузки пользователей: " . $e->getMessage());
    $lastUser = null;
}

// ========================================
// УВЕДОМЛЕНИЯ
// ========================================

console_log("🔔 Загрузка уведомлений...");

$unreadNotifications = 0;

if (!$isGuest) {
    console_log("🔍 Получение непрочитанных уведомлений для пользователя ID: " . $_SESSION['user_id']);
    $unreadNotifications = $db->getUnreadNotificationsCount($_SESSION['user_id']);
    console_log("✅ Непрочитанных уведомлений: " . $unreadNotifications);
} else {
    console_log("ℹ️ Пользователь гость, уведомления пропущены");
}

// ========================================
// ГРУППИРОВКА ФОРУМОВ ПО КАТЕГОРИЯМ
// ========================================

console_log("📂 Группировка форумов по категориям...");

$parentCategories = [];
$childForums = [];

foreach ($forums as $forum) {
    if (isset($forum['is_cat']) && $forum['is_cat'] == '1') {
        $parentCategories[$forum['forumid']] = $forum;
        console_log("📁 Родительская категория найдена: " . $forum['title'] . " (ID: " . $forum['forumid'] . ")");
    } else {
        $parentId = isset($forum['parentid']) ? $forum['parentid'] : '0';
        if (!isset($childForums[$parentId])) {
            $childForums[$parentId] = [];
        }
        $childForums[$parentId][] = $forum;
        console_log("📄 Дочерний форум: " . $forum['title'] . " (Parent ID: " . $parentId . ")");
    }
}

console_log("📊 Родительских категорий: " . count($parentCategories));
console_log("📊 Групп дочерних форумов: " . count($childForums));

// ========================================
// 🎂 ДНИ РОЖДЕНИЯ СЕГОДНЯ
// ========================================

console_log("🎂 Загрузка дней рождения...");

$birthdayUsers = [];
try {
    $usersData = $db->get('users');
    console_log("📥 Данные пользователей для дней рождения загружены");

    if (is_array($usersData) && isset($usersData['users'])) {
        $today = date('m-d');
        console_log("📅 Сегодняшняя дата (m-d): " . $today);

        foreach ($usersData['users'] as $user) {
            if (isset($user['birthday']) && !empty($user['birthday'])) {
                $userBirthday = date('m-d', strtotime($user['birthday']));

                if ($userBirthday === $today) {
                    $birthdayUsers[] = $user;
                    console_log("🎉 День рождения у: " . ($user['username'] ?? 'unknown'));
                }
            }
        }

        console_log("🎂 Всего именинников сегодня: " . count($birthdayUsers));
    } else {
        console_log("⚠️ Пользователи не найдены");
    }
} catch (Exception $e) {
    console_log("❌ Ошибка загрузки дней рождения: " . $e->getMessage());
    $birthdayUsers = [];
}

console_log("🎨 Генерация HTML страницы...");

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="Форум питомника <?php echo safe_htmlspecialchars($settings['site_title']); ?>">
    <title>Форум - <?php echo safe_htmlspecialchars($settings['site_title']); ?></title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            background: #e8e8e8;
        }

        body {
            font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #000000;
            background: #FFFCF8;
            max-width: 1200px;
            margin: 0 auto;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            overflow-x: hidden;
        }

        .header-image {
            width: 100%;
            display: block;
            height: 400px;
            object-fit: cover;
            object-position: center;
        }

        .nav {
            background: #3E3936;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .burger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 10px;
            z-index: 1001;
            position: absolute;
            left: 20px;
        }

        .burger span {
            width: 25px;
            height: 3px;
            background: #FFFFFF;
            margin: 3px 0;
            transition: 0.3s;
            border-radius: 3px;
        }

        .burger.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px);
        }
        .burger.active span:nth-child(2) {
            opacity: 0;
        }
        .burger.active span:nth-child(3) {
            transform: rotate(-45deg) translate(7px, -7px);
        }

        nav ul {
            list-style: none;
            display: flex;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        nav ul li a,
        nav ul li .user-info {
            display: flex;
            align-items: center;
            color: #FFFFFF;
            text-decoration: none;
            padding: 12px 16px;
            font-size: 11px;
            font-weight: normal;
            transition: 0.3s;
            border-right: 1px solid #2D2825;
        }

        nav ul li:last-child a,
        nav ul li:last-child .user-info {
            border-right: none;
        }

        nav ul li a:hover,
        nav ul li a.active {
            background: rgba(0,0,0,0.2);
        }

        .user-info {
            gap: 8px;
            cursor: pointer;
        }

        .user-info .avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 13px;
        }

        .nav-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.6);
            z-index: 999;
        }

        .nav-overlay.active {
            display: block;
        }

        main.container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .mini-nav {
            background: #EFEFEF;
            padding: 15px 30px;
            border-bottom: 1px solid #E5DDD7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            border-radius: 8px 8px 0 0;
        }

        .mini-nav-left, .mini-nav-right {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .mini-nav a {
            color: #2C5F8D;
            text-decoration: none;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            padding: 5px 10px;
            border-radius: 6px;
            white-space: nowrap;
        }

        .mini-nav a:hover {
            background: #DDDDDD;
            color: #1A4A6D;
        }

        .notification-badge {
            background: #3E3936;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 9px;
            font-weight: bold;
            margin-left: 4px;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: white;
            border: 1px solid #E5DDD7;
            border-radius: 20px;
            padding: 5px 15px;
            min-width: 200px;
            width: 100%;
            max-width: 300px;
        }

        .search-bar input {
            border: none;
            outline: none;
            background: transparent;
            padding: 5px;
            flex: 1;
            color: #333333;
            font-size: 11px;
            font-family: Tahoma, Verdana, Arial, Helvetica, sans-serif;
            width: 100%;
        }

        .search-bar input::placeholder {
            color: #999999;
        }

        .category-section {
            background: #FFFFFF;
            border: 1px solid #CCCCCC;
            border-radius: 0;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .category-header {
            background: #E8E3DC;
            border-bottom: 1px solid #CCCCCC;
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }

        .category-header:hover {
            background: #DED9D2;
        }

        .category-title {
            display: flex;
            flex-direction: column;
            gap: 3px;
            flex: 1;
            min-width: 0;
        }

        .category-title strong {
            font-size: 13px;
            font-weight: bold;
            color: #000000;
            word-wrap: break-word;
        }

        .category-description {
            font-size: 11px;
            color: #666666;
            font-weight: normal;
            line-height: 1.4;
            word-wrap: break-word;
        }

        .category-header-right {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-shrink: 0;
        }

        .forum-count {
            font-size: 11px;
            color: #666666;
            font-weight: normal;
            white-space: nowrap;
        }

        .toggle-icon {
            font-size: 1em;
            transition: transform 0.3s ease;
            color: #666666;
            font-weight: normal;
        }

        .toggle-icon.collapsed {
            transform: rotate(-90deg);
        }

        .subforum-list {
            padding: 0;
            display: block;
            max-height: 3000px;
            overflow: hidden;
            transition: max-height 0.4s ease;
        }

        .subforum-list.collapsed {
            max-height: 0;
        }

        .subforum-item {
            display: grid;
            grid-template-columns: 50px 1fr 150px 250px;
            border-bottom: 1px solid #E5DDD7;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #FFFFFF;
        }

        .subforum-item:hover {
            background: #F5F5F5;
        }

        .subforum-item:last-child {
            border-bottom: none;
        }

        .subforum-item > * {
            padding: 12px 10px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-width: 0;
        }

        .subforum-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .subforum-icon::before {
            content: '';
            width: 30px;
            height: 22px;
            background: linear-gradient(135deg, #F5F1E8 0%, #E8DDD0 100%);
            border: 1.5px solid #C4A777;
            border-radius: 3px;
            position: relative;
            display: block;
            box-shadow:
                0 1px 3px rgba(196, 167, 119, 0.3),
                inset 0 1px 0 rgba(255,255,255,0.5);
        }

        .subforum-icon::after {
            content: '';
            width: 10px;
            height: 3px;
            background: linear-gradient(135deg, #F5F1E8 0%, #E8DDD0 100%);
            border: 1.5px solid #C4A777;
            border-bottom: none;
            border-radius: 2px 2px 0 0;
            position: absolute;
            top: 9px;
            left: 13px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.3);
        }

        .subforum-item.has-new .subforum-icon::before {
            background: linear-gradient(135deg, #FFF8E7 0%, #F0E5D0 100%);
            border-color: #D4B068;
            box-shadow:
                0 1px 4px rgba(212, 176, 104, 0.4),
                inset 0 1px 0 rgba(255,255,255,0.7),
                0 0 8px rgba(212, 176, 104, 0.2);
        }

        .subforum-item.has-new .subforum-icon::after {
            background: linear-gradient(135deg, #FFF8E7 0%, #F0E5D0 100%);
            border-color: #D4B068;
        }

        .subforum-info {
            min-width: 0;
            overflow: hidden;
        }

        .subforum-name {
            font-weight: bold;
            color: #003366;
            font-size: 11px;
            margin-bottom: 3px;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .subforum-description {
            font-size: 11px;
            color: #666666;
            line-height: 1.3;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .subforum-stats {
            text-align: center;
            font-size: 11px;
            color: #000000;
            font-weight: normal;
        }

        .subforum-last-activity {
            font-size: 11px;
            color: #000000;
            display: flex;
            flex-direction: column;
            gap: 3px;
            line-height: 1.4;
            min-width: 0;
            overflow: hidden;
        }

        .subforum-last-activity a {
            color: #003366;
            text-decoration: none;
            font-weight: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .subforum-last-activity a:hover {
            text-decoration: underline;
        }

        .subforum-last-activity .meta {
            color: #666666;
            font-size: 11px;
            word-wrap: break-word;
        }

        .info-center {
            background: #FFFFFF;
            border: 1px solid #CCCCCC;
            border-radius: 0;
            margin: 30px 0;
            overflow: hidden;
        }

        .info-center-header {
            background: #E8E3DC;
            border-bottom: 1px solid #CCCCCC;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: bold;
            color: #000000;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stats-row {
            background: #F9F9F9;
            border-bottom: 1px solid #E5DDD7;
            padding: 20px 25px;
            display: flex;
            justify-content: space-around;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .stat-box {
            text-align: center;
            flex: 1;
            min-width: 120px;
        }

        .stat-box .icon {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .stat-box .number {
            font-size: 18px;
            font-weight: bold;
            color: #003366;
            margin-bottom: 4px;
            word-wrap: break-word;
        }

        .stat-box .label {
            font-size: 11px;
            color: #666666;
        }

        .info-blocks {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1px solid #E5DDD7;
        }

        .info-block {
            padding: 15px 20px;
            border-right: 1px solid #E5DDD7;
            background: #FFFFFF;
        }

        .info-block:last-child {
            border-right: none;
        }

        .info-block-title {
            font-size: 11px;
            font-weight: bold;
            color: #000000;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-block-content {
            font-size: 11px;
            color: #000000;
            line-height: 1.5;
            word-wrap: break-word;
        }

        .info-block-content a {
            color: #003366;
            font-weight: normal;
            text-decoration: none;
            word-wrap: break-word;
        }

        .info-block-content a:hover {
            text-decoration: underline;
        }

        .info-block-meta {
            font-size: 11px;
            color: #666666;
            margin-top: 5px;
        }

        .online-info {
            padding: 15px 20px;
            background: #FFFFFF;
        }

        .online-title {
            font-size: 11px;
            font-weight: bold;
            color: #000000;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .online-stats {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #E5DDD7;
            flex-wrap: wrap;
        }

        .online-stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: #000000;
        }

        .online-stat-item .icon {
            font-size: 16px;
        }

        .online-stat-item .count {
            font-weight: bold;
            color: #003366;
            font-size: 11px;
        }

        .online-users-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .online-user-tag {
            background: #3E3936;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: normal;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .online-user-tag .status-dot {
            width: 6px;
            height: 6px;
            background: #4CAF50;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .detailed-stats {
            background: #F9F9F9;
            padding: 12px 20px;
            text-align: center;
        }

        .detailed-stats a {
            color: #003366;
            text-decoration: none;
            font-size: 11px;
            font-weight: normal;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .detailed-stats a:hover {
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666666;
            background: #FFFFFF;
            border: 1px solid #E5DDD7;
            border-radius: 10px;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }

        /* ========================================
           🎂 ДНИ РОЖДЕНИЯ - КАК В VK
           ======================================== */

        .birthdays-section {
            background: linear-gradient(135deg, #FF6B9D 0%, #C44569 100%);
            border-radius: 12px;
            padding: 30px 25px;
            margin: 25px 0;
            color: white;
            box-shadow: 0 8px 25px rgba(196, 69, 105, 0.4);
            position: relative;
            overflow: hidden;
        }

        .birthdays-section::before {
            content: '🎉';
            position: absolute;
            top: -20px;
            right: -20px;
            font-size: 120px;
            opacity: 0.1;
            transform: rotate(25deg);
        }

        .birthdays-section::after {
            content: '🎈';
            position: absolute;
            bottom: -30px;
            left: -30px;
            font-size: 150px;
            opacity: 0.1;
            transform: rotate(-15deg);
        }

        .birthdays-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            font-size: 18px;
            font-weight: bold;
            position: relative;
            z-index: 1;
        }

        .birthdays-header-icon {
            font-size: 36px;
            animation: rotate-cake 4s infinite ease-in-out;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
        }

        @keyframes rotate-cake {
            0%, 100% { transform: rotate(0deg) scale(1); }
            10% { transform: rotate(-15deg) scale(1.1); }
            20% { transform: rotate(15deg) scale(1.15); }
            30% { transform: rotate(-10deg) scale(1.1); }
            40% { transform: rotate(10deg) scale(1.05); }
            50% { transform: rotate(0deg) scale(1); }
        }

        .birthdays-list {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
            justify-content: flex-start;
            position: relative;
            z-index: 1;
        }

        .birthday-user {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            position: relative;
            transition: transform 0.3s ease;
        }

        .birthday-user:hover {
            transform: translateY(-8px);
        }

        .birthday-avatar-wrapper {
            position: relative;
            width: 90px;
            height: 90px;
        }

        .birthday-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            border: 4px solid #FFD700;
            background: linear-gradient(135deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0.1) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
            color: white;
            overflow: hidden;
            box-shadow: 
                0 8px 20px rgba(0,0,0,0.3),
                0 0 0 2px rgba(255,215,0,0.3),
                inset 0 2px 4px rgba(255,255,255,0.3);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .birthday-avatar:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 
                0 12px 30px rgba(0,0,0,0.4),
                0 0 0 3px rgba(255,215,0,0.5),
                inset 0 2px 6px rgba(255,255,255,0.4);
        }

        .birthday-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .birthday-cake {
            position: absolute;
            top: -8px;
            right: -8px;
            font-size: 28px;
            animation: bounce-cake 1.5s infinite ease-in-out;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
            z-index: 2;
        }

        @keyframes bounce-cake {
            0%, 100% { 
                transform: translateY(0) scale(1) rotate(0deg); 
            }
            25% { 
                transform: translateY(-10px) scale(1.15) rotate(-10deg); 
            }
            50% { 
                transform: translateY(-5px) scale(1.1) rotate(5deg); 
            }
            75% { 
                transform: translateY(-8px) scale(1.12) rotate(-5deg); 
            }
        }

        .birthday-name {
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            color: white;
            text-shadow: 
                0 2px 4px rgba(0,0,0,0.3),
                0 1px 2px rgba(0,0,0,0.2);
            max-width: 100px;
            word-wrap: break-word;
            line-height: 1.3;
        }

        .birthday-name a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s ease;
        }

        .birthday-name a:hover {
            opacity: 0.85;
            text-decoration: underline;
        }

        .birthday-info {
            font-size: 11px;
            color: rgba(255,255,255,0.95);
            text-align: center;
            display: flex;
            align-items: center;
            gap: 4px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }

        .birthday-age {
            background: rgba(255,255,255,0.2);
            padding: 3px 8px;
            border-radius: 10px;
            font-weight: bold;
            font-size: 11px;
            backdrop-filter: blur(5px);
        }

        .no-birthdays {
            text-align: center;
            color: rgba(255,255,255,0.9);
            font-size: 14px;
            padding: 20px;
            font-style: italic;
        }

        .footer {
            background: #3E3936;
            color: white;
            padding: 50px 20px 30px;
            margin-top: 80px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 40px;
            margin-bottom: 30px;
        }

        .footer-column h3 {
            color: #DED5CF;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .footer-column a {
            color: white;
            text-decoration: none;
            display: block;
            margin: 10px 0;
            transition: 0.3s;
            font-size: 11px;
        }

        .footer-column a:hover {
            color: #DED5CF;
            padding-left: 10px;
        }

        .footer-column p {
            color: rgba(255,255,255,0.9);
            line-height: 1.7;
            font-size: 11px;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 25px;
            border-top: 1px solid rgba(255,255,255,0.2);
            color: rgba(255,255,255,0.8);
            font-size: 11px;
        }

        @media (max-width: 1024px) {
            .header-image {
                height: 300px;
            }
        }

        @media (max-width: 768px) {
            body {
                font-size: 12px;
                box-shadow: none;
            }

            .header-image {
                height: 200px;
            }

            .burger {
                display: flex;
                left: 15px;
                padding: 15px 10px;
            }

            nav ul {
                position: fixed;
                top: 0;
                right: -100%;
                width: 85%;
                max-width: 300px;
                height: 100vh;
                background: #3E3936;
                flex-direction: column;
                padding: 70px 0 20px;
                transition: right 0.3s ease;
                box-shadow: -5px 0 20px rgba(0,0,0,0.3);
                z-index: 1000;
                overflow-y: auto;
                align-items: stretch;
            }

            nav ul.active {
                right: 0;
            }

            nav ul li {
                width: 100%;
                border-bottom: 1px solid #2D2825;
            }

            nav ul li a,
            nav ul li .user-info {
                padding: 18px 20px;
                width: 100%;
                border-right: none;
                font-size: 13px;
            }

            main.container {
                margin: 10px auto;
                padding: 0 10px;
            }

            .mini-nav {
                padding: 12px 15px;
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                margin-top: 10px;
                border-radius: 8px 8px 0 0;
            }

            .mini-nav-left,
            .mini-nav-right {
                width: 100%;
                flex-direction: column;
                gap: 10px;
            }

            .mini-nav a {
                padding: 12px 15px;
                font-size: 12px;
                width: 100%;
                justify-content: center;
                background: white;
                border-radius: 6px;
            }

            .search-bar {
                width: 100%;
                max-width: 100%;
                min-width: 0;
                padding: 10px 15px;
            }

            .category-header {
                padding: 12px 15px;
                flex-wrap: wrap;
                gap: 8px;
            }

            .category-header-right {
                width: 100%;
                flex-direction: row;
                justify-content: space-between;
                gap: 10px;
            }

            .subforum-item {
                grid-template-columns: 1fr;
                gap: 0;
                padding: 0;
            }

            .subforum-icon {
                display: none;
            }

            .subforum-item > * {
                padding: 14px 15px;
                border-right: none;
                border-bottom: 1px solid rgba(229, 221, 215, 0.3);
            }

            .subforum-item > *:last-child {
                border-bottom: none;
            }

            .info-blocks {
                grid-template-columns: 1fr;
            }

            .info-block {
                padding: 12px 15px;
                border-right: none;
                border-bottom: 1px solid #E5DDD7;
            }

            .info-block:last-child {
                border-bottom: none;
            }

            .birthdays-section {
                padding: 25px 15px;
                border-radius: 10px;
            }

            .birthdays-header {
                font-size: 16px;
                margin-bottom: 20px;
            }

            .birthdays-header-icon {
                font-size: 32px;
            }

            .birthdays-list {
                justify-content: center;
                gap: 20px;
            }

            .birthday-avatar-wrapper,
            .birthday-avatar {
                width: 75px;
                height: 75px;
            }

            .birthday-avatar {
                font-size: 30px;
            }

            .birthday-cake {
                font-size: 24px;
                top: -6px;
                right: -6px;
            }

            .birthday-name {
                font-size: 12px;
                max-width: 85px;
            }

            .footer {
                padding: 30px 15px 20px;
                margin-top: 40px;
            }

            .footer-content {
                grid-template-columns: 1fr;
                gap: 25px;
            }
        }

        @media (max-width: 480px) {
            body {
                font-size: 11px;
            }

            .header-image {
                height: 120px;
            }

            .birthdays-section {
                padding: 20px 12px;
            }

            .birthdays-header {
                font-size: 14px;
            }

            .birthday-avatar-wrapper,
            .birthday-avatar {
                width: 65px;
                height: 65px;
            }

            .birthday-avatar {
                font-size: 26px;
                border-width: 3px;
            }

            .birthday-cake {
                font-size: 20px;
            }

            .birthday-name {
                font-size: 11px;
                max-width: 75px;
            }

            .birthday-info {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>

    <?php
    console_log("🎨 Рендеринг шапки сайта...");
    $headerPath = getImagePath($settings['header_image']);
    if (!empty($headerPath)):
        console_log("🖼️ Путь к изображению шапки: " . $headerPath);
    ?>
    <img src="<?php echo safe_htmlspecialchars($headerPath); ?>" alt="Шапка сайта" class="header-image">
    <?php else: 
        console_log("ℹ️ Изображение шапки не установлено");
    endif; ?>

    <?php console_log("🧭 Рендеринг навигации..."); ?>
    <nav class="nav" id="mainNav">
        <div class="nav-container">
            <div class="burger" id="burger">
                <span></span>
                <span></span>
                <span></span>
            </div>

            <ul id="navMenu">
                <li><a href="index.php">Главная</a></li>
                <li><a href="blog.php">Блог</a></li>
                <li><a href="forum.php" class="active">Форум</a></li>
                <li><a href="puppies.php">Щенки</a></li>
                <li><a href="gallery.php">Галерея</a></li>
                <li><a href="dogs_online.php">Каталог собак</a></li>
                <li><a href="feedback.php">Связь</a></li>

                <?php if ($isAdmin): 
                    console_log("👑 Админ ссылка добавлена");
                ?>
                <li><a href="admin.php">Админка</a></li>
                <?php endif; ?>

                <?php if ($isModerator): 
                    console_log("🛡️ Модераторская ссылка добавлена");
                ?>
                <li><a href="forum_moderate.php">Модерация</a></li>
                <?php endif; ?>

                <?php if (isset($_SESSION['user_id'])): 
                    console_log("👤 Меню пользователя показано");
                ?>
                <li class="user-menu">
                    <div class="user-info">
                        <div class="avatar">
                            <?php echo isset($_SESSION['username']) ? mb_substr($_SESSION['username'], 0, 1, 'UTF-8') : '?'; ?>
                        </div>
                        <span><?php echo isset($_SESSION['username']) ? safe_htmlspecialchars($_SESSION['username']) : 'User'; ?></span>
                    </div>
                </li>
                <li><a href="logout.php">Выйти</a></li>
                <?php else: 
                    console_log("🔓 Кнопка входа показана");
                ?>
                <li class="user-menu"><a href="login.php">Войти</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="nav-overlay" id="navOverlay"></div>
    </nav>

    <?php console_log("📄 Рендеринг основного контента..."); ?>
    <main class="container">

        <?php console_log("🧭 Рендеринг мини-навигации..."); ?>
        <div class="mini-nav">
            <div class="mini-nav-left">
                <a href="forum.php">
                    🏠 Главная
                </a>
                <a href="forum.php?view=new">
                    ✨ Новое
                </a>
                <?php if (!$isGuest): 
                    console_log("🔔 Ссылка на уведомления добавлена");
                ?>
                <a href="forum_notifications.php">
                    🔔 Уведомления
                    <?php if ($unreadNotifications > 0): 
                        console_log("🔔 Непрочитанных уведомлений: " . $unreadNotifications);
                    ?>
                    <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
            </div>
            <div class="mini-nav-right">
                <form method="GET" action="forum_search.php" class="search-bar">
                    🔍
                    <input type="text" name="q" placeholder="Поиск..." minlength="2" required>
                </form>
            </div>
        </div>

        <?php 
        console_log("🎂 Рендеринг виджета дней рождения...");
        if (!empty($birthdayUsers)): 
            console_log("🎉 Именинников найдено: " . count($birthdayUsers));
        ?>
        <div class="birthdays-section">
            <div class="birthdays-header">
                <span class="birthdays-header-icon">🎂</span>
                <span>Сегодня день рождения отмечают!</span>
            </div>

            <div class="birthdays-list">
                <?php 
                foreach ($birthdayUsers as $user): 
                    console_log("🎂 Рендеринг именинника: " . ($user['username'] ?? 'unknown'));

                    $avatar = getUserAvatar($user['id'], $db);
                    $userName = getUserName($user['id'], $db);
                    $userCity = getUserCity($user['id'], $db);
                    $userPostsCount = getUserPostsCount($user['id'], $db);
                    $userAge = getUserAge($user['birthday']);
                    $userInitials = '';

                    if (!$avatar) {
                        $words = explode(' ', $userName);
                        if (count($words) >= 2) {
                            $userInitials = mb_substr($words[0], 0, 1, 'UTF-8') . mb_substr($words[1], 0, 1, 'UTF-8');
                        } else {
                            $userInitials = mb_substr($userName, 0, 2, 'UTF-8');
                        }
                        $userInitials = mb_strtoupper($userInitials, 'UTF-8');
                    }
                ?>
                <div class="birthday-user">
                    <div class="birthday-avatar-wrapper">
                        <a href="forum_user_profile.php?id=<?php echo $user['id']; ?>" class="birthday-avatar">
                            <?php if ($avatar): 
                                console_log("🖼️ Аватар найден для: " . $userName);
                            ?>
                            <img src="<?php echo safe_htmlspecialchars($avatar); ?>" alt="<?php echo safe_htmlspecialchars($userName); ?>">
                            <?php else: 
                                console_log("🔤 Используются инициалы для: " . $userName . " (" . $userInitials . ")");
                            ?>
                            <?php echo safe_htmlspecialchars($userInitials); ?>
                            <?php endif; ?>
                        </a>
                        <span class="birthday-cake">🎂</span>
                    </div>
                    <div class="birthday-name">
                        <a href="forum_user_profile.php?id=<?php echo $user['id']; ?>">
                            <?php echo safe_htmlspecialchars($userName); ?>
                        </a>
                    </div>
                    <?php if ($userAge): 
                        console_log("🎂 Возраст: " . $userAge . " лет");
                    ?>
                    <div class="birthday-info">
                        🎉 <span class="birthday-age"><?php echo $userAge; ?> лет</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($userCity): 
                        console_log("📍 Город: " . $userCity);
                    ?>
                    <div class="birthday-info">
                        📍 <?php echo safe_htmlspecialchars($userCity); ?>
                    </div>
                    <?php endif; ?>
                    <div class="birthday-info">
                        💬 <?php echo $userPostsCount; ?> сообщений
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: 
            console_log("ℹ️ Именинников сегодня нет");
        endif; ?>

        <?php 
        console_log("📂 Рендеринг категорий форума...");
        if (!empty($parentCategories)): 
            console_log("📊 Родительских категорий для рендера: " . count($parentCategories));

            uasort($parentCategories, function($a, $b) {
                $orderA = isset($a['order']) ? intval($a['order']) : 999;
                $orderB = isset($b['order']) ? intval($b['order']) : 999;
                return $orderA - $orderB;
            });
            console_log("✅ Категории отсортированы");

            foreach ($parentCategories as $parentId => $parentCat):
                console_log("📁 Рендеринг категории: " . $parentCat['title'] . " (ID: " . $parentId . ")");

                $children = isset($childForums[$parentId]) ? $childForums[$parentId] : [];
                if (empty($children)) {
                    console_log("⚠️ У категории нет дочерних форумов, пропуск");
                    continue;
                }

                console_log("📊 Дочерних форумов: " . count($children));
            ?>
                <section class="category-section">
                    <header class="category-header" onclick="toggleCategory(this)">
                        <div class="category-title">
                            <strong><?php echo safe_htmlspecialchars($parentCat['title']); ?></strong>
                            <?php if (!empty($parentCat['description'])): ?>
                                <span class="category-description"><?php echo strip_tags($parentCat['description']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="category-header-right">
                            <span class="forum-count">Разделов: <?php echo count($children); ?></span>
                            <span class="toggle-icon">▼</span>
                        </div>
                    </header>
                    <div class="subforum-list">
                        <?php 
                        foreach ($children as $forum):
                            $forumId = $forum['forumid'];
                            console_log("📄 Рендеринг форума: " . $forum['title'] . " (ID: " . $forumId . ")");

                            console_log("🔍 Подсчет тем для форума ID: " . $forumId);
                            $forumTopics = array_filter($topics, function($topic) use ($forumId) {
                                return isset($topic['category_id']) && $topic['category_id'] == $forumId;
                            });
                            $topicsCount = count($forumTopics);
                            console_log("📊 Тем в форуме: " . $topicsCount);

                            console_log("🔍 Подсчет постов для форума ID: " . $forumId);
                            $postsCount = 0;
                            foreach ($forumTopics as $topic) {
                                $topicPosts = array_filter($posts, function($post) use ($topic) {
                                    return isset($post['topic_id']) && $post['topic_id'] == $topic['id'];
                                });
                                $postsCount += count($topicPosts);
                            }
                            console_log("📊 Постов в форуме: " . $postsCount);

                            console_log("🔍 Поиск последнего поста...");
                            $lastPost = null;
                            $lastPostTopic = null;

                            foreach ($forumTopics as $topic) {
                                $topicPosts = array_filter($posts, function($post) use ($topic) {
                                    return isset($post['topic_id']) && $post['topic_id'] == $topic['id'];
                                });

                                if (!empty($topicPosts)) {
                                    usort($topicPosts, function($a, $b) {
                                        return strtotime($b['created_at']) - strtotime($a['created_at']);
                                    });

                                    $tempPost = $topicPosts[0];
                                    if (!$lastPost || strtotime($tempPost['created_at']) > strtotime($lastPost['created_at'])) {
                                        $lastPost = $tempPost;
                                        $lastPostTopic = $topic;
                                    }
                                }
                            }

                            if ($lastPost) {
                                console_log("✅ Последний пост найден, ID: " . $lastPost['id']);
                            } else {
                                console_log("ℹ️ Постов в форуме нет");
                            }

                            $hasNew = false;
                            if ($lastPost && strtotime($lastPost['created_at']) > strtotime('-24 hours')) {
                                $hasNew = true;
                                console_log("🆕 Форум имеет новые сообщения");
                            }
                        ?>
                            <article class="subforum-item <?php echo $hasNew ? 'has-new' : ''; ?>" onclick="window.location.href='forum_category.php?id=<?php echo $forumId; ?>'">
                                <div class="subforum-icon"></div>

                                <div class="subforum-info">
                                    <h3 class="subforum-name"><?php echo safe_htmlspecialchars($forum['title']); ?></h3>
                                    <?php if (!empty($forum['description'])): ?>
                                        <div class="subforum-description"><?php echo strip_tags($forum['description']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="subforum-stats">
                                    <div><?php echo $postsCount; ?> Сообщений</div>
                                    <div><?php echo $topicsCount; ?> Тем</div>
                                </div>

                                <div class="subforum-last-activity">
                                    <?php if ($lastPost): ?>
                                        <div>
                                            Последний ответ от
                                            <a href="forum_user_profile.php?id=<?php echo $lastPost['user_id']; ?>" onclick="event.stopPropagation()">
                                                <?php echo safe_htmlspecialchars(getUserName($lastPost['user_id'], $db)); ?>
                                            </a>
                                        </div>
                                        <?php if ($lastPostTopic): ?>
                                        <div>
                                            <a href="forum_topic.php?id=<?php echo $lastPostTopic['id']; ?>" onclick="event.stopPropagation()" title="<?php echo safe_htmlspecialchars($lastPostTopic['title']); ?>">
                                                <?php
                                                $topicTitle = $lastPostTopic['title'];
                                                if (mb_strlen($topicTitle, 'UTF-8') > 30) {
                                                    $topicTitle = mb_substr($topicTitle, 0, 30, 'UTF-8') . '...';
                                                }
                                                echo safe_htmlspecialchars($topicTitle);
                                                ?>
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                        <div class="meta">
                                            <?php echo formatDate($lastPost['created_at']); ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="color: #999999; text-align: center;">Нет сообщений</div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php else: 
            console_log("⚠️ Категории форума пусты");
        ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <div>Форум пока пуст</div>
                <?php if ($isAdmin): ?>
                <div style="margin-top: 15px;">
                    <a href="admin.php?page=forum_settings" style="color: #2C5F8D; text-decoration: none;">
                        → Настроить форум в админке
                    </a>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php console_log("📊 Рендеринг информационного блока..."); ?>
        <section class="info-center">
            <header class="info-center-header">
                📊 Информ-блок
            </header>

            <div class="stats-row">
                <article class="stat-box">
                    <div class="icon">💬</div>
                    <div class="number"><?php echo $totalTopics; ?></div>
                    <div class="label">Тем</div>
                </article>
                <article class="stat-box">
                    <div class="icon">📝</div>
                    <div class="number"><?php echo $totalPosts; ?></div>
                    <div class="label">Сообщений</div>
                </article>
                <article class="stat-box">
                    <div class="icon">👥</div>
                    <div class="number"><?php echo $totalUsers; ?></div>
                    <div class="label">Участников</div>
                </article>
                <article class="stat-box">
                    <div class="icon">🆕</div>
                    <div class="number"><?php echo $lastUser ? safe_htmlspecialchars(getUserName($lastUser['id'], $db)) : 'Нет'; ?></div>
                    <div class="label">Последний участник</div>
                </article>
            </div>

            <div class="info-blocks">
                <article class="info-block">
                    <h4 class="info-block-title">
                        📌 Последнее сообщение
                    </h4>
                    <div class="info-block-content">
                        <?php if ($lastPost): 
                            console_log("📌 Рендеринг последнего сообщения");

                            $postContentText = strip_tags($lastPost['content']);
                            if (mb_strlen($postContentText, 'UTF-8') > 100) {
                                $postContentText = mb_substr($postContentText, 0, 100, 'UTF-8') . '...';
                            }

                            $postTopic = null;
                            foreach ($topics as $topic) {
                                if ($topic['id'] == $lastPost['topic_id']) {
                                    $postTopic = $topic;
                                    break;
                                }
                            }
                        ?>
                            <?php if ($postTopic): ?>
                            <div style="margin-bottom: 6px;">
                                В теме: <a href="forum_topic.php?id=<?php echo $postTopic['id']; ?>">
                                    <?php echo safe_htmlspecialchars($postTopic['title']); ?>
                                </a>
                            </div>
                            <?php endif; ?>
                            <div style="color: #666666; font-size: 11px; line-height: 1.4;">
                                <?php echo safe_htmlspecialchars($postContentText); ?>
                            </div>
                            <div class="info-block-meta">
                                Автор: <strong><?php echo safe_htmlspecialchars(getUserName($lastPost['user_id'], $db)); ?></strong>
                                • <?php echo formatDate($lastPost['created_at']); ?>
                            </div>
                        <?php else: 
                            console_log("ℹ️ Последних сообщений нет");
                        ?>
                            <div style="color: #666666;">Сообщений пока нет</div>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="info-block">
                    <h4 class="info-block-title">
                        🔥 Последняя тема
                    </h4>
                    <div class="info-block-content">
                        <?php if ($lastTopic): 
                            console_log("🔥 Рендеринг последней темы");
                        ?>
                            <div style="margin-bottom: 6px;">
                                <a href="forum_topic.php?id=<?php echo $lastTopic['id']; ?>">
                                    <?php echo safe_htmlspecialchars($lastTopic['title']); ?>
                                </a>
                            </div>
                            <div class="info-block-meta">
                                Автор: <strong><?php echo safe_htmlspecialchars(getUserName($lastTopic['user_id'], $db)); ?></strong>
                                • <?php echo formatDate($lastTopic['created_at']); ?>
                            </div>
                        <?php else: 
                            console_log("ℹ️ Последних тем нет");
                        ?>
                            <div style="color: #666666;">Тем пока нет</div>
                        <?php endif; ?>
                    </div>
                </article>
            </div>

            <?php console_log("🌐 Рендеринг блока онлайн пользователей..."); ?>
            <div class="online-info">
                <h4 class="online-title">
                    🌐 Пользователи Online
                </h4>
                <div class="online-stats">
                    <div class="online-stat-item">
                        <span class="icon">👤</span>
                        <span>Участников: <span class="count"><?php echo $onlineUsers; ?></span></span>
                    </div>
                    <div class="online-stat-item">
                        <span class="icon">👁️</span>
                        <span>Гостей: <span class="count"><?php echo $onlineGuests; ?></span></span>
                    </div>
                    <div class="online-stat-item">
                        <span class="icon">🌐</span>
                        <span>Всего онлайн: <span class="count"><?php echo $onlineUsers + $onlineGuests; ?></span></span>
                    </div>
                </div>
                <?php if (!empty($onlineUsernames)): 
                    console_log("👥 Рендеринг списка пользователей онлайн");
                ?>
                <div class="online-users-list">
                    <?php foreach ($onlineUsernames as $username): ?>
                    <div class="online-user-tag">
                        <span class="status-dot"></span>
                        <?php echo safe_htmlspecialchars($username); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: 
                    console_log("ℹ️ Зарегистрированных пользователей онлайн нет");
                ?>
                <div style="text-align: center; color: #666666; font-size: 11px; padding: 10px;">
                    Зарегистрированных пользователей онлайн нет
                </div>
                <?php endif; ?>
            </div>

            <div class="detailed-stats">
                <a href="forum_stats.php">
                    📈 Подробная статистика форума →
                </a>
            </div>
        </section>

    </main>

    <?php 
    console_log("🦶 Рендеринг футера...");
    if (file_exists(__DIR__ . '/includes/footer.php')): 
        console_log("📄 Подключение footer.php из includes");
        include __DIR__ . '/includes/footer.php'; 
    else: 
        console_log("⚠️ footer.php не найден, используется дефолтный футер");
    ?>
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-column">
                <h3>О питомнике</h3>
                <p><?php echo safe_htmlspecialchars($settings['footer_about']); ?></p>
            </div>
            <div class="footer-column">
                <h3>Навигация</h3>
                <a href="index.php">Главная</a>
                <a href="blog.php">Блог</a>
                <a href="forum.php">Форум</a>
                <a href="puppies.php">Щенки</a>
                <a href="gallery.php">Галерея</a>
            </div>
            <div class="footer-column">
                <h3>Контакты</h3>
                <?php if (!empty($settings['phone'])): ?>
                <p>📞 <?php echo safe_htmlspecialchars($settings['phone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($settings['email'])): ?>
                <p>📧 <?php echo safe_htmlspecialchars($settings['email']); ?></p>
                <?php endif; ?>
                <?php if (!empty($settings['vk'])): ?>
                <a href="<?php echo safe_htmlspecialchars($settings['vk']); ?>" target="_blank">VK</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?php echo date('Y'); ?> <?php echo safe_htmlspecialchars($settings['site_title']); ?>. Все права защищены.
        </div>
    </footer>
    <?php endif; ?>

    <?php console_log("🎭 Инициализация JavaScript..."); ?>
    <script>
        console.log('🚀 JavaScript инициализирован');

        // Вывод всех PHP логов в консоль браузера
        <?php if (isset($GLOBALS['console_logs']) && !empty($GLOBALS['console_logs'])): ?>
        console.log('📋 === PHP ЛОГИ ===');
        <?php foreach ($GLOBALS['console_logs'] as $log): ?>
        console.log('<?php echo $log; ?>');
        <?php endforeach; ?>
        console.log('📋 === КОНЕЦ PHP ЛОГОВ ===');
        <?php endif; ?>

        console.log('🎭 Настройка бургер-меню...');
        const burgerBtn = document.getElementById('burger');
        const navMenuList = document.getElementById('navMenu');
        const navOverlayDiv = document.getElementById('navOverlay');

        if (burgerBtn && navMenuList && navOverlayDiv) {
            console.log('✅ Элементы бургер-меню найдены');

            burgerBtn.addEventListener('click', function(e) {
                console.log('🍔 Клик по бургеру');
                e.stopPropagation();
                burgerBtn.classList.toggle('active');
                navMenuList.classList.toggle('active');
                navOverlayDiv.classList.toggle('active');

                if (burgerBtn.classList.contains('active')) {
                    console.log('📖 Меню открыто');
                } else {
                    console.log('📕 Меню закрыто');
                }
            });

            navOverlayDiv.addEventListener('click', function() {
                console.log('🔘 Клик по оверлею, закрытие меню');
                burgerBtn.classList.remove('active');
                navMenuList.classList.remove('active');
                navOverlayDiv.classList.remove('active');
            });

            console.log('✅ События бургер-меню установлены');
        } else {
            console.error('❌ Не все элементы бургер-меню найдены!');
        }

        console.log('🎭 Настройка сворачивания категорий...');
        function toggleCategory(header) {
            console.log('📁 toggleCategory вызвана');
            const icon = header.querySelector('.toggle-icon');
            const list = header.nextElementSibling;

            if (icon && list) {
                icon.classList.toggle('collapsed');
                list.classList.toggle('collapsed');

                if (list.classList.contains('collapsed')) {
                    console.log('➖ Категория свернута');
                } else {
                    console.log('➕ Категория развернута');
                }
            } else {
                console.error('❌ Не найдены элементы категории');
            }
        }

        console.log('✅ Функция toggleCategory установлена');
        console.log('🎉 Инициализация JavaScript завершена');
    </script>

</body>
</html>
<?php 
console_log("✅ HTML полностью отрендерен");
console_log("🏁 FORUM.PHP ЗАВЕРШЕН");
error_log("=== FORUM.PHP ENDED ===");
?>
