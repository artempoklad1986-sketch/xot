<?php
/**
 * Forum Frontend - Интегрирован в основной сайт
 * Версия 4.0 - с единой шапкой и навигацией
 */

// Устанавливаем кодировку UTF-8
header('Content-Type: text/html; charset=utf-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once 'Database.php';

try {
    $db = new Database();

    // Загружаем настройки сайта
    $settings = $db->get('settings');
    if (!$settings) $settings = array();

    $defaults_settings = array(
        'site_title' => 'Хотошо - Питомник',
        'header_image' => '',
        'welcome_title' => 'Добро пожаловать!',
        'welcome_text' => 'Российский Союз Владельцев Хотошо',
        'vk' => '',
        'instagram' => '',
        'facebook' => '',
        'footer_about' => 'О нас',
        'phone' => '',
        'email' => '',
        'address' => ''
    );
    $settings = array_merge($defaults_settings, $settings);

    // Загружаем данные форума
    $stats = $db->getWpForoImportStats();
    if (!$stats['is_imported']) {
        die('Форум пока не доступен. Данные не импортированы.');
    }

    $page = isset($_GET['page']) ? $_GET['page'] : 'index';
    $forumId = isset($_GET['forum']) ? intval($_GET['forum']) : 0;
    $topicId = isset($_GET['topic']) ? intval($_GET['topic']) : 0;

    $forums = $db->get('wpforo_forums');
    $topics = $db->get('wpforo_topics');
    $posts = $db->get('wpforo_posts');
    $users = $db->get('wpforo_users');

} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}

// Функции-помощники
function getUserName($userId, $users) {
    if (!$users || !isset($users['users'])) return 'Гость';
    foreach ($users['users'] as $user) {
        if ($user['ID'] == $userId) {
            return $user['display_name'] ?: $user['user_login'];
        }
    }
    return 'Гость';
}

function getForumById($forumId, $forums) {
    if (!$forums || !isset($forums['forums'])) return null;
    foreach ($forums['forums'] as $forum) {
        if ($forum['forumid'] == $forumId) return $forum;
    }
    return null;
}

function getTopicById($topicId, $topics) {
    if (!$topics || !isset($topics['topics'])) return null;
    foreach ($topics['topics'] as $topic) {
        if ($topic['topicid'] == $topicId) return $topic;
    }
    return null;
}

function getForumTopics($forumId, $topics) {
    if (!$topics || !isset($topics['topics'])) return array();
    $result = array();
    foreach ($topics['topics'] as $topic) {
        if ($topic['forumid'] == $forumId) $result[] = $topic;
    }
    usort($result, function($a, $b) {
        return strtotime($b['created']) - strtotime($a['created']);
    });
    return $result;
}

function getTopicPosts($topicId, $posts) {
    if (!$posts || !isset($posts['posts'])) return array();
    $result = array();
    foreach ($posts['posts'] as $post) {
        if ($post['topicid'] == $topicId) $result[] = $post;
    }
    usort($result, function($a, $b) {
        return strtotime($a['created']) - strtotime($b['created']);
    });
    return $result;
}

function getLastPost($forumId, $topics, $posts, $users) {
    if (!$posts || !isset($posts['posts'])) return null;

    $forumPosts = array();
    foreach ($posts['posts'] as $post) {
        $topic = getTopicById($post['topicid'], $topics);
        if ($topic && $topic['forumid'] == $forumId) {
            $forumPosts[] = $post;
        }
    }

    if (empty($forumPosts)) return null;

    usort($forumPosts, function($a, $b) {
        return strtotime($b['created']) - strtotime($a['created']);
    });

    $lastPost = $forumPosts[0];
    $topic = getTopicById($lastPost['topicid'], $topics);

    return array(
        'post' => $lastPost,
        'topic' => $topic,
        'author' => getUserName($lastPost['userid'], $users)
    );
}

function formatDate($datetime) {
    $timestamp = strtotime($datetime);
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' мин. назад';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ч. назад';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' дн. назад';
    } else {
        return date('d.m.Y', $timestamp);
    }
}

function cleanHtml($content) {
    $content = preg_replace('/$$attach$$.*?$$\/attach$$/s', '', $content);
    $content = strip_tags($content);
    return $content;
}

$currentForum = null;
$currentTopic = null;

if ($page === 'forum' && $forumId > 0) {
    $currentForum = getForumById($forumId, $forums);
    $forumTopics = getForumTopics($forumId, $topics);
} elseif ($page === 'topic' && $topicId > 0) {
    $currentTopic = getTopicById($topicId, $topics);
    if ($currentTopic) {
        $currentForum = getForumById($currentTopic['forumid'], $forums);
        $currentPosts = getTopicPosts($topicId, $posts);
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php 
        if ($page === 'topic' && $currentTopic) {
            echo htmlspecialchars($currentTopic['title']) . ' - Форум';
        } elseif ($page === 'forum' && $currentForum) {
            echo htmlspecialchars($currentForum['title']) . ' - Форум';
        } else {
            echo 'Форум';
        }
        echo ' - ' . htmlspecialchars($settings['site_title']);
    ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #fffcf8;
        }

        /* ШАПКА */
        .header-image {
            width: 100%;
            display: block;
            max-height: 300px;
            object-fit: cover;
        }

        /* МЕНЮ */
        .nav {
            background: #4A2C2A;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            position: relative;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        .nav-logo {
            color: white;
            font-size: 20px;
            font-weight: bold;
            padding: 15px 0;
        }

        .burger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 10px;
        }

        .burger span {
            width: 25px;
            height: 3px;
            background: white;
            margin: 3px 0;
            transition: all 0.3s;
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

        .nav ul {
            list-style: none;
            display: flex;
            padding: 0;
            margin: 0;
        }

        .nav ul li a,
        .nav ul li .user-info {
            display: block;
            color: #fff;
            text-decoration: none;
            padding: 15px 20px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .nav ul li a:hover,
        .nav ul li a.active {
            background: #5D3735;
        }

        /* USER INFO */
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
        }

        .user-info .avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #5D3735;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        /* ФОРУМ */
        #prime-forum {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            padding: 0;
        }

        /* Header */
        .prime-forum-header {
            background-color: #fafafa;
            border: 1px solid #eee;
            margin: 5px auto 10px;
            padding: 10px 15px;
        }

        .prime-forum-header h1 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        /* Breadcrumbs */
        .prime-breadcrumbs {
            background-color: #fafafa;
            border: 1px solid #eee;
            padding: 10px 15px;
            margin: 5px auto 10px;
            font-size: 13px;
        }

        .prime-breadcrumbs a {
            color: #555;
            text-decoration: none;
            transition: color 0.2s;
        }

        .prime-breadcrumbs a:hover {
            color: #000;
        }

        .prime-breadcrumbs span {
            color: #777;
            margin: 0 5px;
        }

        /* Stats */
        .forum-stats-block {
            background-color: #fafafa;
            border: 1px solid #eee;
            margin: 10px auto;
            padding: 20px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }

        .forum-stats-block .stat-item {
            flex: 1;
        }

        .forum-stats-block .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #555;
            margin-bottom: 5px;
        }

        .forum-stats-block .stat-label {
            font-size: 12px;
            color: #777;
            text-transform: uppercase;
        }

        /* Content */
        .prime-forum-content {
            overflow: hidden;
        }

        /* Parent Box */
        .prime-parent-box {
            border-left: 2px solid #ececec;
            margin-bottom: 30px;
            padding-left: 10px;
        }

        .prime-item-label {
            border-bottom: 2px solid #ececec;
            margin-bottom: 10px;
        }

        .prime-item-label span {
            background: #ececec;
            color: #777;
            display: inline-block;
            line-height: 20px;
            padding: 3px 10px;
            position: relative;
            font-weight: 600;
            font-size: 14px;
        }

        .prime-item-label span::before {
            border-style: solid;
            border-color: #ececec;
            border-width: 15px 5px 13px;
            content: "";
            height: 0;
            left: -10px;
            position: absolute;
            top: 0;
            width: 0;
        }

        .prime-item-label span::after {
            border-style: solid;
            border-color: transparent transparent #ececec #ececec;
            border-width: 16px 10px 10px 16px;
            content: "";
            height: 0;
            position: absolute;
            right: -26px;
            top: 0;
            width: 0;
        }

        /* Group Box */
        .prime-group-box {
            margin: 20px 0;
        }

        /* Forum Item */
        .prime-forum-item {
            align-items: center;
            background-color: #f5f5f5;
            border: 1px solid #e5e5e5;
            box-sizing: border-box;
            display: flex;
            margin: 10px 0;
            overflow: hidden;
            padding: 5px;
            position: relative;
            width: 100%;
            transition: background-color 0.2s;
        }

        .prime-forum-item:hover {
            background-color: #fafafa;
        }

        .prime-forum-item > div {
            box-sizing: border-box;
        }

        /* Forum Icon */
        .prime-forum-icon {
            color: #d5d5d5;
            font-size: 30px;
            padding: 8px;
            flex-shrink: 0;
        }

        /* Forum Title */
        .prime-forum-title {
            flex-grow: 1;
            margin: 5px;
        }

        .prime-general-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .prime-general-title a {
            color: #333;
            text-decoration: none;
            transition: color 0.2s;
        }

        .prime-general-title a:hover {
            color: #000;
        }

        .prime-forum-description {
            color: #777;
            font-size: 12px;
            line-height: 1.4;
            margin-top: 4px;
        }

        .prime-forum-description:empty {
            display: none;
        }

        /* Forum Topics/Posts Stats */
        .prime-forum-topics {
            align-items: center;
            align-self: center;
            border-left: 1px solid #e5e5e5;
            border-right: 1px solid #e5e5e5;
            display: flex;
            flex-direction: column;
            font-size: 13px;
            padding: 5px 15px;
            text-align: center;
            min-width: 80px;
        }

        .prime-forum-topics > span {
            display: block;
            margin: 2px 0;
            color: #777;
        }

        .prime-forum-topics strong {
            color: #333;
            font-weight: 600;
        }

        /* Last Post */
        .prime-last-items {
            font-size: 12px;
            padding: 5px 10px;
            max-width: 300px;
            min-width: 200px;
            width: 30%;
        }

        .prime-last-topic-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .prime-last-topic-title a {
            color: #333;
            text-decoration: none;
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .prime-last-topic-title a:hover {
            color: #000;
        }

        .prime-last-author {
            color: #777;
            font-size: 11px;
        }

        .prime-last-author a {
            color: #555;
            text-decoration: none;
        }

        .prime-last-author a:hover {
            text-decoration: underline;
        }

        .prime-forum-time-ago {
            color: #999;
            font-size: 11px;
            margin-top: 2px;
        }

        /* Topics List */
        .prime-topics-list {
            margin: 20px 0;
        }

        .prime-topic {
            align-items: center;
            background-color: #f5f5f5;
            border: 1px solid #e5e5e5;
            display: flex;
            margin: 10px 0;
            padding: 10px;
            transition: background-color 0.2s;
        }

        .prime-topic:hover {
            background-color: #fafafa;
        }

        .prime-topic-icon {
            font-size: 8px;
            color: #d5d5d5;
            padding: 8px;
            flex-shrink: 0;
        }

        .prime-topic-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #d5d5d5;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
            margin-right: 12px;
            font-size: 16px;
        }

        .prime-forum-title.prime-topic-main {
            display: flex;
            align-items: center;
            flex-grow: 1;
        }

        .prime-topic-content {
            flex: 1;
            min-width: 0;
        }

        .prime-topic-title-text {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .prime-topic-title-text a {
            color: #333;
            text-decoration: none;
        }

        .prime-topic-title-text a:hover {
            color: #000;
        }

        .prime-topic-author {
            font-size: 12px;
            color: #999;
        }

        .prime-topic-author a {
            color: #555;
            text-decoration: none;
        }

        .prime-topic-author a:hover {
            text-decoration: underline;
        }

        .prime-topic-stats {
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-weight: 600;
            color: #666;
            min-width: 70px;
            padding: 0 10px;
        }

        .prime-topic-lastpost {
            font-size: 12px;
            padding: 5px 10px;
            min-width: 180px;
        }

        .prime-topic-lastpost-author {
            color: #555;
            text-decoration: none;
            font-weight: 600;
        }

        .prime-topic-lastpost-author:hover {
            text-decoration: underline;
        }

        .prime-topic-lastpost-date {
            color: #999;
            margin-top: 2px;
            font-size: 11px;
        }

        /* Posts */
        .prime-posts {
            overflow: hidden;
            margin: 20px 0;
        }

        .prime-post {
            background-color: #fafafa;
            border: 1px solid #e5e5e5;
            box-sizing: border-box;
            display: flex;
            margin: 10px 0;
            position: relative;
            width: 100%;
        }

        .prime-topic-left {
            align-items: center;
            border-right: 1px solid #eee;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            max-width: 130px;
            overflow: hidden;
            padding: 15px 10px;
            position: relative;
            text-align: center;
            width: 180px;
            word-wrap: break-word;
            background-color: #f9f9f9;
        }

        .prime-post-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #d5d5d5;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 32px;
            margin-bottom: 12px;
        }

        .prime-author-metabox {
            margin: 10px 0;
            width: 100%;
        }

        .prime-author-name {
            font-size: 14px;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
            word-wrap: break-word;
        }

        .prime-author-role {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            margin: 10px 0;
        }

        .prime-author-topics,
        .prime-author-posts {
            color: #777;
            font-size: 12px;
            margin: 3px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            padding: 3px 5px;
        }

        .prime-topic-right {
            display: flex;
            flex-wrap: wrap;
            width: calc(100% - 180px);
        }

        .prime-post-top {
            align-items: center;
            align-self: flex-start;
            border-bottom: 1px solid #eee;
            display: flex;
            width: 100%;
            padding: 8px 15px;
            background-color: #fff;
        }

        .prime-count {
            align-items: center;
            display: flex;
            font-size: 13px;
            color: #777;
        }

        .prime-count > span {
            padding: 0 10px 0 0;
        }

        .prime-date {
            flex-grow: 1;
            font-size: 12px;
            color: #999;
            margin-left: 10px;
        }

        .prime-post-content {
            box-sizing: border-box;
            padding: 15px 20px;
            width: 100%;
            word-wrap: break-word;
            background-color: #fff;
            line-height: 1.7;
        }

        .prime-post-content p {
            margin-bottom: 12px;
        }

        .prime-post-content img {
            margin: 10px 0;
            max-width: 100%;
        }

        /* Back Button */
        .prime-back-btn {
            display: inline-block;
            padding: 8px 16px;
            background: #f5f5f5;
            color: #333;
            text-decoration: none;
            border: 1px solid #e5e5e5;
            font-size: 13px;
            margin: 15px 0;
            transition: background 0.2s;
        }

        .prime-back-btn:hover {
            background: #ececec;
        }

        /* Empty State */
        .prime-empty {
            padding: 60px 20px;
            text-align: center;
            color: #999;
            background-color: #fafafa;
            border: 1px solid #e5e5e5;
            margin: 10px 0;
        }

        .prime-empty-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Footer */
        .prime-forum-footer {
            background-color: #fafafa;
            border: 1px solid #eee;
            margin: 10px 0;
            padding: 10px 15px;
            font-size: 12px;
            color: #777;
        }

        /* ФУТЕР САЙТА */
        .footer {
            background: #4A2C2A;
            color: white;
            padding: 50px 20px 30px;
            margin-top: 60px;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 40px;
        }

        .footer-column h3 {
            margin-bottom: 15px;
            color: #FFD700;
        }

        .footer-column a {
            color: #fff;
            text-decoration: none;
            display: block;
            margin: 8px 0;
            transition: all 0.3s;
        }

        .footer-column a:hover {
            color: #FFD700;
            padding-left: 10px;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.2);
            margin-top: 30px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .burger {
                display: flex;
            }

            .nav-logo {
                font-size: 16px;
            }

            .nav ul {
                position: fixed;
                top: 0;
                right: -100%;
                width: 80%;
                max-width: 300px;
                height: 100vh;
                background: #4A2C2A;
                flex-direction: column;
                padding-top: 60px;
                transition: right 0.3s;
                box-shadow: -5px 0 15px rgba(0,0,0,0.3);
                z-index: 999;
                overflow-y: auto;
            }

            .nav ul.active {
                right: 0;
            }

            .nav ul li {
                width: 100%;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }

            .nav ul li a,
            .nav ul li .user-info {
                padding: 20px;
                width: 100%;
            }

            .nav-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 998;
            }

            .nav-overlay.active {
                display: block;
            }

            .prime-forum-item {
                flex-wrap: wrap;
            }

            .prime-forum-icon {
                width: 45px;
            }

            .prime-forum-title {
                width: calc(100% - 60px);
            }

            .prime-forum-topics {
                border-left: none;
                width: 50%;
            }

            .prime-last-items {
                max-width: none;
                width: 50%;
            }

            .prime-topic {
                flex-wrap: wrap;
            }

            .prime-topic-stats {
                width: 50%;
                border-top: 1px solid #e5e5e5;
                padding: 10px;
            }

            .prime-topic-lastpost {
                width: 50%;
                border-top: 1px solid #e5e5e5;
                padding: 10px;
            }

            .footer-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 568px) {
            .prime-topic-left {
                width: 90px;
                max-width: 90px;
                padding: 10px 5px;
            }

            .prime-post-avatar {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }

            .prime-topic-right {
                width: calc(100% - 90px);
            }

            .prime-author-topics,
            .prime-author-posts {
                font-size: 11px;
            }
        }

        @media (max-width: 414px) {
            .prime-post {
                flex-direction: column;
                margin: 25px 0;
            }

            .prime-topic-left {
                border-bottom: 1px solid #eee;
                border-right: none;
                max-width: none;
                width: 100%;
                padding-top: 50px;
            }

            .prime-topic-right {
                width: 100%;
            }

            .prime-post-top {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
            }

            .forum-stats-block {
                flex-wrap: wrap;
            }

            .forum-stats-block .stat-item {
                width: 50%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>

    <!-- ШАПКА -->
    <?php if (!empty($settings['header_image']) && file_exists('.' . $settings['header_image'])): ?>
    <img src="<?php echo htmlspecialchars($settings['header_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Шапка" class="header-image">
    <?php endif; ?>

    <!-- МЕНЮ -->
    <nav class="nav">
        <div class="nav-container">
            <div class="nav-logo">🐕 Хотошо</div>

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
                <li><a href="catalog.php">Каталог пород</a></li>
                <li><a href="feedback.php">Обратная связь</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li>
                    <div class="user-info">
                        <div class="avatar">
                            <?php echo mb_substr($_SESSION['username'], 0, 1, 'UTF-8'); ?>
                        </div>
                        <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </div>
                </li>
                <li><a href="logout.php">Выйти</a></li>
                <?php else: ?>
                <li><a href="login.php">Войти</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="nav-overlay" id="navOverlay"></div>
    </nav>

    <!-- ФОРУМ -->
    <div id="prime-forum">

        <!-- Header -->
        <div class="prime-forum-header">
            <h1>💬 Форум Хотошо</h1>
        </div>

        <!-- Breadcrumbs -->
        <div class="prime-breadcrumbs">
            <?php if ($page === 'index'): ?>
                <span>📁 Главная форума</span>
            <?php elseif ($page === 'forum' && $currentForum): ?>
                <a href="forum.php?page=index">📁 Главная форума</a>
                <span>→</span>
                <span><?php echo htmlspecialchars($currentForum['title']); ?></span>
            <?php elseif ($page === 'topic' && $currentTopic): ?>
                <a href="forum.php?page=index">📁 Главная форума</a>
                <span>→</span>
                <a href="forum.php?page=forum&forum=<?php echo $currentTopic['forumid']; ?>">
                    <?php echo htmlspecialchars($currentForum['title']); ?>
                </a>
                <span>→</span>
                <span><?php echo htmlspecialchars($currentTopic['title']); ?></span>
            <?php endif; ?>
        </div>

        <?php if ($page === 'index'): ?>

            <!-- ГЛАВНАЯ СТРАНИЦА -->

            <!-- Stats Block -->
            <div class="forum-stats-block">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['forums']; ?></div>
                    <div class="stat-label">Разделов</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['topics']; ?></div>
                    <div class="stat-label">Тем</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['posts']; ?></div>
                    <div class="stat-label">Сообщений</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['users']; ?></div>
                    <div class="stat-label">Пользователей</div>
                </div>
            </div>

            <div class="prime-forum-content">
                <?php
                if ($forums && isset($forums['forums'])):
                    $categories = array();
                    $regularForums = array();

                    foreach ($forums['forums'] as $forum) {
                        if ($forum['is_cat'] == 1) {
                            $categories[] = $forum;
                        } else {
                            $regularForums[] = $forum;
                        }
                    }

                    foreach ($categories as $category):
                ?>
                    <div class="prime-parent-box">
                        <div class="prime-item-label">
                            <span><?php echo htmlspecialchars($category['title']); ?></span>
                        </div>

                        <div class="prime-group-box">
                            <?php
                            $subforums = array_filter($regularForums, function($f) use ($category) {
                                return $f['parentid'] == $category['forumid'];
                            });

                            foreach ($subforums as $forum):
                                $lastPost = getLastPost($forum['forumid'], $topics, $posts, $users);
                            ?>
                                <div class="prime-forum-item">
                                    <div class="prime-forum-icon">
                                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none">
                                            <rect x="3" y="6" width="18" height="12" rx="2" stroke="#d5d5d5" stroke-width="2"/>
                                            <path d="M3 10h18" stroke="#d5d5d5" stroke-width="2"/>
                                        </svg>
                                    </div>
                                    <div class="prime-forum-title">
                                        <div class="prime-general-title">
                                            <a href="forum.php?page=forum&forum=<?php echo $forum['forumid']; ?>">
                                                <?php echo htmlspecialchars($forum['title']); ?>
                                            </a>
                                        </div>
                                        <?php if (!empty($forum['description'])): ?>
                                            <div class="prime-forum-description">
                                                <?php echo htmlspecialchars(cleanHtml($forum['description'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="prime-forum-topics">
                                        <span>Тем: <strong><?php echo $forum['topics']; ?></strong></span>
                                        <span>Сообщений: <strong><?php echo $forum['posts']; ?></strong></span>
                                    </div>
                                    <div class="prime-last-items">
                                        <?php if ($lastPost): ?>
                                            <div class="prime-last-topic-title">
                                                <a href="forum.php?page=topic&topic=<?php echo $lastPost['topic']['topicid']; ?>">
                                                    <?php echo htmlspecialchars($lastPost['topic']['title']); ?>
                                                </a>
                                            </div>
                                            <div class="prime-last-author">
                                                <a href="#"><?php echo htmlspecialchars($lastPost['author']); ?></a>
                                            </div>
                                            <div class="prime-forum-time-ago">
                                                <?php echo formatDate($lastPost['post']['created']); ?>
                                            </div>
                                        <?php else: ?>
                                            <div style="color: #999; font-size: 12px;">Нет сообщений</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php
                    endforeach;

                    // Форумы без категории
                    $orphans = array_filter($regularForums, function($f) {
                        return $f['parentid'] == 0;
                    });

                    if (!empty($orphans)):
                ?>
                    <div class="prime-parent-box">
                        <div class="prime-item-label">
                            <span>Прочее</span>
                        </div>
                        <div class="prime-group-box">
                            <?php foreach ($orphans as $forum):
                                $lastPost = getLastPost($forum['forumid'], $topics, $posts, $users);
                            ?>
                                <div class="prime-forum-item">
                                    <div class="prime-forum-icon">
                                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none">
                                            <rect x="3" y="6" width="18" height="12" rx="2" stroke="#d5d5d5" stroke-width="2"/>
                                            <path d="M3 10h18" stroke="#d5d5d5" stroke-width="2"/>
                                        </svg>
                                    </div>
                                    <div class="prime-forum-title">
                                        <div class="prime-general-title">
                                            <a href="forum.php?page=forum&forum=<?php echo $forum['forumid']; ?>">
                                                <?php echo htmlspecialchars($forum['title']); ?>
                                            </a>
                                        </div>
                                        <?php if (!empty($forum['description'])): ?>
                                            <div class="prime-forum-description">
                                                <?php echo htmlspecialchars(cleanHtml($forum['description'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="prime-forum-topics">
                                        <span>Тем: <strong><?php echo $forum['topics']; ?></strong></span>
                                        <span>Сообщений: <strong><?php echo $forum['posts']; ?></strong></span>
                                    </div>
                                    <div class="prime-last-items">
                                        <?php if ($lastPost): ?>
                                            <div class="prime-last-topic-title">
                                                <a href="forum.php?page=topic&topic=<?php echo $lastPost['topic']['topicid']; ?>">
                                                    <?php echo htmlspecialchars($lastPost['topic']['title']); ?>
                                                </a>
                                            </div>
                                            <div class="prime-last-author">
                                                <a href="#"><?php echo htmlspecialchars($lastPost['author']); ?></a>
                                            </div>
                                            <div class="prime-forum-time-ago">
                                                <?php echo formatDate($lastPost['post']['created']); ?>
                                            </div>
                                        <?php else: ?>
                                            <div style="color: #999; font-size: 12px;">Нет сообщений</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php
                    endif;
                else:
                ?>
                    <div class="prime-empty">
                        <div class="prime-empty-icon">📭</div>
                        <div>Форум пуст</div>
                    </div>
                <?php endif; ?>
            </div>

        <?php elseif ($page === 'forum' && $currentForum): ?>

            <!-- СПИСОК ТЕМ -->

            <a href="forum.php?page=index" class="prime-back-btn">← Вернуться к разделам</a>

            <?php if (!empty($forumTopics)): ?>
                <div class="prime-topics-list">
                    <?php foreach ($forumTopics as $topic): 
                        $topicPosts = getTopicPosts($topic['topicid'], $posts);
                        $lastPost = !empty($topicPosts) ? end($topicPosts) : null;
                        $userName = getUserName($topic['userid'], $users);
                    ?>
                        <div class="prime-topic prime-forum-item">
                            <div class="prime-topic-icon">●</div>
                            <div class="prime-forum-title prime-topic-main">
                                <div class="prime-topic-avatar">
                                    <?php echo mb_strtoupper(mb_substr($userName, 0, 1)); ?>
                                </div>
                                <div class="prime-topic-content">
                                    <div class="prime-topic-title-text">
                                        <a href="forum.php?page=topic&topic=<?php echo $topic['topicid']; ?>">
                                            <?php echo htmlspecialchars($topic['title']); ?>
                                        </a>
                                    </div>
                                    <div class="prime-topic-author">
                                        <a href="#"><?php echo htmlspecialchars($userName); ?></a>
                                        • <?php echo formatDate($topic['created']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="prime-topic-stats">
                                <?php echo max(0, $topic['posts'] - 1); ?> ответов
                            </div>
                            <div class="prime-topic-stats">
                                <?php echo $topic['views']; ?> просмотров
                            </div>
                            <div class="prime-topic-lastpost prime-last-items">
                                <?php if ($lastPost): ?>
                                    <div>
                                        <a href="#" class="prime-topic-lastpost-author">
                                            <?php echo htmlspecialchars(getUserName($lastPost['userid'], $users)); ?>
                                        </a>
                                    </div>
                                    <div class="prime-topic-lastpost-date">
                                        <?php echo formatDate($lastPost['created']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="prime-empty">
                    <div class="prime-empty-icon">📝</div>
                    <div>В этом разделе пока нет тем</div>
                </div>
            <?php endif; ?>

        <?php elseif ($page === 'topic' && $currentTopic): ?>

            <!-- ПРОСМОТР ТЕМЫ -->

            <a href="forum.php?page=forum&forum=<?php echo $currentTopic['forumid']; ?>" class="prime-back-btn">
                ← Вернуться к темам
            </a>

            <?php if (!empty($currentPosts)): ?>
                <div class="prime-posts">
                    <?php foreach ($currentPosts as $index => $post): 
                        $userName = getUserName($post['userid'], $users);
                    ?>
                        <div class="prime-post">
                            <div class="prime-topic-left">
                                <div class="prime-post-avatar">
                                    <?php echo mb_strtoupper(mb_substr($userName, 0, 1)); ?>
                                </div>
                                <div class="prime-author-metabox">
                                    <div class="prime-author-name">
                                        <?php echo htmlspecialchars($userName); ?>
                                    </div>
                                    <div class="prime-author-role">
                                        <?php echo $index === 0 ? 'Автор темы' : 'Участник'; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="prime-topic-right">
                                <div class="prime-post-top">
                                    <div class="prime-count">
                                        <span>#<?php echo $index + 1; ?></span>
                                    </div>
                                    <div class="prime-date">
                                        <span class="post-date"><?php echo date('d.m.Y в H:i', strtotime($post['created'])); ?></span>
                                    </div>
                                </div>
                                <div class="prime-post-content">
                                    <?php echo nl2br(htmlspecialchars(cleanHtml($post['body']))); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="prime-empty">
                    <div class="prime-empty-icon">💬</div>
                    <div>В этой теме нет сообщений</div>
                </div>
            <?php endif; ?>

        <?php else: ?>

            <div class="prime-empty">
                <div class="prime-empty-icon">❌</div>
                <div>Страница не найдена</div>
                <div style="margin-top: 15px;">
                    <a href="forum.php?page=index" style="color: #555;">← На главную форума</a>
                </div>
            </div>

        <?php endif; ?>

        <!-- Footer форума -->
        <div class="prime-forum-footer">
            Форум работает на базе wpForo • Всего тем: <?php echo $stats['topics']; ?> • Сообщений: <?php echo $stats['posts']; ?>
        </div>

    </div>

    <!-- ФУТЕР САЙТА -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-column">
                <h3>Социальные сети</h3>
                <?php if (!empty($settings['vk'])): ?>
                <a href="<?php echo htmlspecialchars($settings['vk'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">ВКонтакте</a>
                <?php endif; ?>
                <?php if (!empty($settings['instagram'])): ?>
                <a href="<?php echo htmlspecialchars($settings['instagram'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Instagram</a>
                <?php endif; ?>
                <?php if (!empty($settings['facebook'])): ?>
                <a href="<?php echo htmlspecialchars($settings['facebook'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Facebook</a>
                <?php endif; ?>
            </div>

            <div class="footer-column">
                <h3>О нас</h3>
                <p><?php echo nl2br(htmlspecialchars($settings['footer_about'], ENT_QUOTES, 'UTF-8')); ?></p>
            </div>

            <div class="footer-column">
                <h3>Контакты</h3>
                <?php if (!empty($settings['phone'])): ?>
                <p>Телефон: <?php echo htmlspecialchars($settings['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <?php if (!empty($settings['email'])): ?>
                <p>Email: <?php echo htmlspecialchars($settings['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <?php if (!empty($settings['address'])): ?>
                <p>Адрес: <?php echo htmlspecialchars($settings['address'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['site_title'], ENT_QUOTES, 'UTF-8'); ?>. Все права защищены.</p>
        </div>
    </footer>

    <!-- СКРИПТЫ -->
    <script>
        // БУРГЕР-МЕНЮ
        var burger = document.getElementById('burger');
        var navMenu = document.getElementById('navMenu');
        var navOverlay = document.getElementById('navOverlay');

        burger.addEventListener('click', function() {
            burger.classList.toggle('active');
            navMenu.classList.toggle('active');
            navOverlay.classList.toggle('active');
            document.body.style.overflow = navMenu.classList.contains('active') ? 'hidden' : '';
        });

        navOverlay.addEventListener('click', function() {
            burger.classList.remove('active');
            navMenu.classList.remove('active');
            navOverlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    </script>

</body>
</html>
