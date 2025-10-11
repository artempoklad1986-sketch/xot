<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Проверка авторизации (потом добавишь)
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header('Location: login.php');
//     exit;
// }

require_once 'Database.php';
$db = new Database();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Сохранение настроек
    if (isset($_POST['save_settings'])) {
        $settings = $db->get('settings');

        if (isset($_POST['site_title'])) $settings['site_title'] = $_POST['site_title'];
        if (isset($_POST['welcome_title'])) $settings['welcome_title'] = $_POST['welcome_title'];
        if (isset($_POST['welcome_text'])) $settings['welcome_text'] = $_POST['welcome_text'];
        if (isset($_POST['phone'])) $settings['phone'] = $_POST['phone'];
        if (isset($_POST['email'])) $settings['email'] = $_POST['email'];
        if (isset($_POST['address'])) $settings['address'] = $_POST['address'];
        if (isset($_POST['vk'])) $settings['vk'] = $_POST['vk'];
        if (isset($_POST['instagram'])) $settings['instagram'] = $_POST['instagram'];
        if (isset($_POST['facebook'])) $settings['facebook'] = $_POST['facebook'];
        if (isset($_POST['footer_about'])) $settings['footer_about'] = $_POST['footer_about'];

        // Загрузка шапки
        if (isset($_FILES['header_image']) && $_FILES['header_image']['error'] === UPLOAD_ERR_OK) {
            $result = $db->uploadFile('header_image', 'header');
            if ($result['success']) {
                if (!empty($settings['header_image'])) {
                    $oldFile = basename($settings['header_image']);
                    $db->deleteFile($oldFile);
                }
                $settings['header_image'] = $result['path'];
            }
        }

        $db->save('settings', $settings);
        $message = 'Настройки сохранены!';
        $messageType = 'success';
    }

    // Добавление слайда
    if (isset($_POST['add_slide'])) {
        if (isset($_FILES['slide_image']) && $_FILES['slide_image']['error'] === UPLOAD_ERR_OK) {
            $slider = $db->get('slider');
            if (count($slider['slides']) < 5) {
                $result = $db->uploadFile('slide_image', 'slide');
                if ($result['success']) {
                    $newSlide = array('image' => $result['path']);
                    $slider['slides'][] = $newSlide;
                    $db->save('slider', $slider);
                    $message = 'Слайд добавлен!';
                    $messageType = 'success';
                } else {
                    $message = $result['error'];
                    $messageType = 'error';
                }
            } else {
                $message = 'Максимум 5 слайдов!';
                $messageType = 'error';
            }
        } else {
            $message = 'Выберите изображение';
            $messageType = 'error';
        }
    }

    // Удаление слайда
    if (isset($_POST['delete_slide'])) {
        $slideIndex = intval($_POST['slide_index']);
        $slider = $db->get('slider');
        if (isset($slider['slides'][$slideIndex])) {
            $slideImage = basename($slider['slides'][$slideIndex]['image']);
            $db->deleteFile($slideImage);
            array_splice($slider['slides'], $slideIndex, 1);
            $db->save('slider', $slider);
            $message = 'Слайд удалён!';
            $messageType = 'success';
        }
    }

    // Сохранение "О породе"
    if (isset($_POST['save_about'])) {
        $about = $db->get('about');

        if (isset($_POST['about_title'])) $about['title'] = $_POST['about_title'];
        if (isset($_POST['about_content'])) $about['content'] = $_POST['about_content'];
        if (isset($_POST['about_author'])) $about['author'] = $_POST['about_author'];

        // Загрузка фото
        if (isset($_FILES['about_image']) && $_FILES['about_image']['error'] === UPLOAD_ERR_OK) {
            $result = $db->uploadFile('about_image', 'about');
            if ($result['success']) {
                if (!empty($about['image'])) {
                    $oldFile = basename($about['image']);
                    $db->deleteFile($oldFile);
                }
                $about['image'] = $result['path'];
            }
        }

        $db->save('about', $about);
        $message = 'Раздел "О породе" сохранён!';
        $messageType = 'success';
    }

    // Добавление щенка
    if (isset($_POST['add_puppy'])) {
        if (isset($_FILES['puppy_image']) && $_FILES['puppy_image']['error'] === UPLOAD_ERR_OK) {
            $result = $db->uploadFile('puppy_image', 'puppy');
            if ($result['success']) {
                $puppies = $db->get('puppies');
                $newPuppy = array(
                    'id' => uniqid(),
                    'image' => $result['path'],
                    'name' => isset($_POST['puppy_name']) ? $_POST['puppy_name'] : '',
                    'age' => isset($_POST['puppy_age']) ? $_POST['puppy_age'] : '',
                    'price' => isset($_POST['puppy_price']) ? $_POST['puppy_price'] : '',
                    'description' => isset($_POST['puppy_description']) ? $_POST['puppy_description'] : '',
                    'date_added' => date('Y-m-d H:i:s')
                );
                $puppies['items'][] = $newPuppy;
                $db->save('puppies', $puppies);
                $message = 'Щенок добавлен!';
                $messageType = 'success';
            }
        } else {
            $message = 'Выберите фото щенка';
            $messageType = 'error';
        }
    }

    // Удаление щенка
    if (isset($_POST['delete_puppy'])) {
        $puppyId = $_POST['puppy_id'];
        $puppies = $db->get('puppies');
        foreach ($puppies['items'] as $index => $puppy) {
            if ($puppy['id'] === $puppyId) {
                $puppyImage = basename($puppy['image']);
                $db->deleteFile($puppyImage);
                array_splice($puppies['items'], $index, 1);
                $db->save('puppies', $puppies);
                $message = 'Щенок удалён!';
                $messageType = 'success';
                break;
            }
        }
    }

    // ========== wpForo: Удаление форума ==========
    if (isset($_POST['delete_forum'])) {
        $forumId = intval($_POST['forum_id']);
        $forums = $db->get('wpforo_forums');

        if (isset($forums['forums'])) {
            foreach ($forums['forums'] as $index => $forum) {
                if ($forum['forumid'] == $forumId) {
                    array_splice($forums['forums'], $index, 1);
                    $db->save('wpforo_forums', $forums);
                    $message = 'Форум удалён!';
                    $messageType = 'success';
                    break;
                }
            }
        }
    }

    // ========== wpForo: Удаление темы ==========
    if (isset($_POST['delete_topic'])) {
        $topicId = intval($_POST['topic_id']);
        $topics = $db->get('wpforo_topics');

        if (isset($topics['topics'])) {
            foreach ($topics['topics'] as $index => $topic) {
                if ($topic['topicid'] == $topicId) {
                    array_splice($topics['topics'], $index, 1);
                    $db->save('wpforo_topics', $topics);

                    // Удаляем все посты этой темы
                    $posts = $db->get('wpforo_posts');
                    if (isset($posts['posts'])) {
                        $posts['posts'] = array_filter($posts['posts'], function($post) use ($topicId) {
                            return $post['topicid'] != $topicId;
                        });
                        $posts['posts'] = array_values($posts['posts']);
                        $db->save('wpforo_posts', $posts);
                    }

                    $message = 'Тема удалена!';
                    $messageType = 'success';
                    break;
                }
            }
        }
    }

    // ========== wpForo: Удаление поста ==========
    if (isset($_POST['delete_post'])) {
        $postId = intval($_POST['post_id']);
        $posts = $db->get('wpforo_posts');

        if (isset($posts['posts'])) {
            foreach ($posts['posts'] as $index => $post) {
                if ($post['postid'] == $postId) {
                    array_splice($posts['posts'], $index, 1);
                    $db->save('wpforo_posts', $posts);
                    $message = 'Пост удалён!';
                    $messageType = 'success';
                    break;
                }
            }
        }
    }

    // ========== wpForo: Редактирование форума ==========
    if (isset($_POST['edit_forum'])) {
        $forumId = intval($_POST['forum_id']);
        $forums = $db->get('wpforo_forums');

        if (isset($forums['forums'])) {
            foreach ($forums['forums'] as $index => $forum) {
                if ($forum['forumid'] == $forumId) {
                    $forums['forums'][$index]['title'] = $_POST['forum_title'];
                    $forums['forums'][$index]['description'] = $_POST['forum_description'];
                    $db->save('wpforo_forums', $forums);
                    $message = 'Форум обновлён!';
                    $messageType = 'success';
                    break;
                }
            }
        }
    }
}

$settings = $db->get('settings');
$slider = $db->get('slider');
$about = $db->get('about');
$puppies = $db->get('puppies');

// Данные wpForo
$stats = $db->getWpForoImportStats();
$wpforoForums = $db->get('wpforo_forums');
$wpforoTopics = $db->get('wpforo_topics');
$wpforoPosts = $db->get('wpforo_posts');
$wpforoUsers = $db->get('wpforo_users');

// Функция получения пользователя
function getWpForoUserName($userId, $users) {
    if (!$users || !isset($users['users'])) return 'Гость';
    foreach ($users['users'] as $user) {
        if ($user['ID'] == $userId) {
            return $user['display_name'] ?: $user['user_login'];
        }
    }
    return 'Гость';
}

// Функция получения форума
function getWpForoForumName($forumId, $forums) {
    if (!$forums || !isset($forums['forums'])) return 'Неизвестно';
    foreach ($forums['forums'] as $forum) {
        if ($forum['forumid'] == $forumId) {
            return $forum['title'];
        }
    }
    return 'Неизвестно';
}

// Функция получения темы
function getWpForoTopicName($topicId, $topics) {
    if (!$topics || !isset($topics['topics'])) return 'Неизвестно';
    foreach ($topics['topics'] as $topic) {
        if ($topic['topicid'] == $topicId) {
            return $topic['title'];
        }
    }
    return 'Неизвестно';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fffcf8;
            padding: 20px;
            line-height: 1.6;
        }

        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            color: #4A2C2A;
            margin-bottom: 30px;
            font-size: 36px;
        }

        .top-nav {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .top-nav a {
            color: #4A2C2A;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            padding: 10px 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .top-nav a:hover {
            background: #4A2C2A;
            color: white;
            transform: translateY(-2px);
        }

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
        }

        .section {
            background: white;
            padding: 30px;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section h2 {
            color: #4A2C2A;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #4A2C2A;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group input[type="url"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .form-group input[type="file"] {
            padding: 10px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #4A2C2A;
            color: white;
        }

        .btn-primary:hover {
            background: #5D3735;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #d68910;
        }

        .btn-info {
            background: #3498db;
            color: white;
        }

        .btn-info:hover {
            background: #2980b9;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 14px;
        }

        .preview-image {
            max-width: 100%;
            max-height: 300px;
            margin-top: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .item-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .item-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .item-card h3 {
            margin-bottom: 10px;
            color: #4A2C2A;
        }

        .item-card p {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }

        .slide-item {
            background: #f9f9f9;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .slide-item img {
            width: 200px;
            height: 120px;
            object-fit: cover;
            border-radius: 5px;
        }

        .slide-info {
            flex: 1;
        }

        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table th {
            background: #4A2C2A;
            color: white;
            font-weight: bold;
        }

        table tr:hover {
            background: #f9f9f9;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-pin {
            background: #f39c12;
            color: white;
        }

        .badge-lock {
            background: #e74c3c;
            color: white;
        }

        .actions-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #4A2C2A;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }

        @media (max-width: 768px) {
            .two-columns {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>

    <div class="admin-container">
        <h1>🎛️ Админ-панель Хотошо</h1>

        <div class="top-nav">
            <a href="index.php" target="_blank">🏠 Главная сайта</a>
            <a href="forum.php" target="_blank">💬 Форум</a>
            <a href="blog.php" target="_blank">📝 Блог</a>
            <a href="puppies.php" target="_blank">🐕 Щенки</a>
            <a href="gallery.php" target="_blank">🖼️ Галерея</a>
        </div>

        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- НАСТРОЙКИ САЙТА -->
        <div class="section">
            <h2>⚙️ Настройки сайта</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Название сайта:</label>
                    <input type="text" name="site_title" value="<?php echo htmlspecialchars($settings['site_title']); ?>" required>
                </div>

                <div class="form-group">
                    <label>🖼️ Шапка сайта (отображается на главной и на форуме):</label>
                    <input type="file" name="header_image" accept="image/*">
                    <small style="color: #666;">Рекомендуемый размер: 1920x200-300 пикселей. Эта картинка будет на ВСЕХ страницах сайта!</small>
                    <?php if (!empty($settings['header_image'])): ?>
                    <br><img src="<?php echo htmlspecialchars($settings['header_image']); ?>" alt="Шапка" class="preview-image">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Заголовок приветствия:</label>
                    <input type="text" name="welcome_title" value="<?php echo htmlspecialchars($settings['welcome_title']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Текст приветствия:</label>
                    <textarea name="welcome_text" required><?php echo htmlspecialchars($settings['welcome_text']); ?></textarea>
                </div>

                <h3 style="margin: 30px 0 15px; color: #4A2C2A;">Контакты</h3>

                <div class="two-columns">
                    <div class="form-group">
                        <label>Телефон:</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($settings['phone']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($settings['email']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Адрес:</label>
                    <input type="text" name="address" value="<?php echo htmlspecialchars($settings['address']); ?>">
                </div>

                <h3 style="margin: 30px 0 15px; color: #4A2C2A;">Социальные сети</h3>

                <div class="form-group">
                    <label>ВКонтакте (ссылка):</label>
                    <input type="url" name="vk" value="<?php echo htmlspecialchars($settings['vk']); ?>" placeholder="https://vk.com/...">
                </div>

                <div class="form-group">
                    <label>Instagram (ссылка):</label>
                    <input type="url" name="instagram" value="<?php echo htmlspecialchars($settings['instagram']); ?>" placeholder="https://instagram.com/...">
                </div>

                <div class="form-group">
                    <label>Facebook (ссылка):</label>
                    <input type="url" name="facebook" value="<?php echo htmlspecialchars($settings['facebook']); ?>" placeholder="https://facebook.com/...">
                </div>

                <div class="form-group">
                    <label>Текст "О нас" в подвале:</label>
                    <textarea name="footer_about"><?php echo htmlspecialchars($settings['footer_about']); ?></textarea>
                </div>

                <button type="submit" name="save_settings" class="btn btn-success">💾 Сохранить настройки</button>
            </form>
        </div>

        <!-- СЛАЙДЕР -->
        <div class="section">
            <h2>🖼️ Слайдер (макс. 5 картинок)</h2>

            <?php if (count($slider['slides']) < 5): ?>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Изображение слайда:</label>
                    <input type="file" name="slide_image" accept="image/*" required>
                    <small style="color: #666;">Рекомендуемый размер: 1200x600 пикселей</small>
                </div>

                <button type="submit" name="add_slide" class="btn btn-primary">➕ Добавить слайд</button>
            </form>
            <?php else: ?>
            <p style="color: #e74c3c; font-weight: bold;">Достигнуто максимальное количество слайдов (5)</p>
            <?php endif; ?>

            <div style="margin-top: 30px;">
                <h3>Текущие слайды (<?php echo count($slider['slides']); ?>):</h3>
                <?php if (empty($slider['slides'])): ?>
                <p>Слайдов пока нет</p>
                <?php else: ?>
                <?php foreach ($slider['slides'] as $index => $slide): ?>
                <div class="slide-item">
                    <img src="<?php echo htmlspecialchars($slide['image']); ?>" alt="Слайд <?php echo $index + 1; ?>">
                    <div class="slide-info">
                        <h4>Слайд <?php echo $index + 1; ?></h4>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="slide_index" value="<?php echo $index; ?>">
                        <button type="submit" name="delete_slide" class="btn btn-danger" onclick="return confirm('Удалить этот слайд?')">🗑️ Удалить</button>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- О ПОРОДЕ -->
        <div class="section">
            <h2>📝 Раздел "О породе"</h2>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Заголовок:</label>
                    <input type="text" name="about_title" value="<?php echo htmlspecialchars($about['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Фото слева:</label>
                    <input type="file" name="about_image" accept="image/*">
                    <small style="color: #666;">Рекомендуемый размер: 400x600 пикселей</small>
                    <?php if (!empty($about['image'])): ?>
                    <br><img src="<?php echo htmlspecialchars($about['image']); ?>" alt="Фото" class="preview-image">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Текст:</label>
                    <textarea name="about_content" required style="min-height: 300px;"><?php echo htmlspecialchars($about['content']); ?></textarea>
                    <small style="color: #666;">Разделяйте абзацы двойным переносом строки</small>
                </div>

                <div class="form-group">
                    <label>Автор:</label>
                    <input type="text" name="about_author" value="<?php echo htmlspecialchars($about['author']); ?>">
                </div>

                <button type="submit" name="save_about" class="btn btn-success">💾 Сохранить</button>
            </form>
        </div>

        <!-- ЩЕНКИ НА ПРОДАЖУ -->
        <div class="section">
            <h2>🐕 Щенки на продажу</h2>

            <form method="POST" enctype="multipart/form-data">
                <div class="two-columns">
                    <div class="form-group">
                        <label>Фото щенка:</label>
                        <input type="file" name="puppy_image" accept="image/*" required>
                    </div>

                    <div class="form-group">
                        <label>Кличка:</label>
                        <input type="text" name="puppy_name" required>
                    </div>
                </div>

                <div class="two-columns">
                    <div class="form-group">
                        <label>Возраст:</label>
                        <input type="text" name="puppy_age" placeholder="Например: 2 месяца" required>
                    </div>

                    <div class="form-group">
                        <label>Цена (руб):</label>
                        <input type="number" name="puppy_price" placeholder="30000" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Описание:</label>
                    <textarea name="puppy_description" placeholder="Характер, особенности, здоровье..."></textarea>
                </div>

                <button type="submit" name="add_puppy" class="btn btn-primary">➕ Добавить щенка</button>
            </form>

            <div class="items-grid">
                <?php if (empty($puppies['items'])): ?>
                <p>Щенков пока нет</p>
                <?php else: ?>
                <?php foreach ($puppies['items'] as $puppy): ?>
                <div class="item-card">
                    <img src="<?php echo htmlspecialchars($puppy['image']); ?>" alt="<?php echo htmlspecialchars($puppy['name']); ?>">
                    <h3><?php echo htmlspecialchars($puppy['name']); ?></h3>
                    <p><strong>Возраст:</strong> <?php echo htmlspecialchars($puppy['age']); ?></p>
                    <p><strong>Цена:</strong> <?php echo htmlspecialchars($puppy['price']); ?> ₽</p>
                    <p><?php echo htmlspecialchars($puppy['description']); ?></p>
                    <form method="POST">
                        <input type="hidden" name="puppy_id" value="<?php echo $puppy['id']; ?>">
                        <button type="submit" name="delete_puppy" class="btn btn-danger" onclick="return confirm('Удалить щенка?')">🗑️ Удалить</button>
                    </form>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========== wpForo ФОРУМ ========== -->

        <div class="section">
            <h2>💬 Форум wpForo - Статистика</h2>

            <?php if ($stats['is_imported']): ?>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['forums']; ?></div>
                        <div class="label">Разделов</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['topics']; ?></div>
                        <div class="label">Тем</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['posts']; ?></div>
                        <div class="label">Сообщений</div>
                    </div>
                    <div class="stat-card">
                        <div class="number"><?php echo $stats['users']; ?></div>
                        <div class="label">Пользователей</div>
                    </div>
                </div>

                <p style="color: #27ae60; font-weight: bold; margin-top: 20px;">✅ Данные wpForo успешно импортированы!</p>
            <?php else: ?>
                <p style="color: #e74c3c; font-weight: bold;">❌ Данные wpForo не импортированы. Запустите импорт.</p>
            <?php endif; ?>
        </div>

        <!-- wpForo: РАЗДЕЛЫ ФОРУМА -->
        <?php if ($stats['is_imported'] && isset($wpforoForums['forums']) && !empty($wpforoForums['forums'])): ?>
        <div class="section">
            <h2>📁 Разделы форума</h2>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название</th>
                        <th>Описание</th>
                        <th>Тем</th>
                        <th>Сообщений</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $regularForums = array_filter($wpforoForums['forums'], function($f) {
                        return $f['is_cat'] != 1;
                    });

                    foreach ($regularForums as $forum): 
                    ?>
                    <tr>
                        <td><?php echo $forum['forumid']; ?></td>
                        <td><strong><?php echo htmlspecialchars($forum['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars(mb_substr(strip_tags($forum['description']), 0, 100)); ?></td>
                        <td><?php echo $forum['topics']; ?></td>
                        <td><?php echo $forum['posts']; ?></td>
                        <td>
                            <form method="POST" style="display: inline-block;">
                                <input type="hidden" name="forum_id" value="<?php echo $forum['forumid']; ?>">
                                <button type="submit" name="delete_forum" class="btn btn-danger btn-sm" onclick="return confirm('Удалить раздел? Темы останутся!')">🗑️ Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- wpForo: ТЕМЫ -->
        <?php if ($stats['is_imported'] && isset($wpforoTopics['topics']) && !empty($wpforoTopics['topics'])): ?>
        <div class="section">
            <h2>📋 Темы форума (последние 50)</h2>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Название темы</th>
                        <th>Раздел</th>
                        <th>Автор</th>
                        <th>Ответов</th>
                        <th>Просмотры</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $latestTopics = array_slice(array_reverse($wpforoTopics['topics']), 0, 50);

                    foreach ($latestTopics as $topic): 
                        $forumName = getWpForoForumName($topic['forumid'], $wpforoForums);
                        $authorName = getWpForoUserName($topic['userid'], $wpforoUsers);
                    ?>
                    <tr>
                        <td><?php echo $topic['topicid']; ?></td>
                        <td><strong><?php echo htmlspecialchars($topic['title']); ?></strong></td>
                        <td><?php echo htmlspecialchars($forumName); ?></td>
                        <td><?php echo htmlspecialchars($authorName); ?></td>
                        <td><?php echo $topic['posts']; ?></td>
                        <td><?php echo $topic['views']; ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($topic['created'])); ?></td>
                        <td>
                            <div class="actions-group">
                                <a href="forum.php?page=topic&topic=<?php echo $topic['topicid']; ?>" class="btn btn-info btn-sm" target="_blank">👁️ Посмотреть</a>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="topic_id" value="<?php echo $topic['topicid']; ?>">
                                    <button type="submit" name="delete_topic" class="btn btn-danger btn-sm" onclick="return confirm('Удалить тему? Все посты будут удалены!')">🗑️ Удалить</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- wpForo: ПОСТЫ -->
        <?php if ($stats['is_imported'] && isset($wpforoPosts['posts']) && !empty($wpforoPosts['posts'])): ?>
        <div class="section">
            <h2>💬 Последние сообщения (50 шт.)</h2>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Тема</th>
                        <th>Автор</th>
                        <th>Сообщение</th>
                        <th>Дата</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $latestPosts = array_slice(array_reverse($wpforoPosts['posts']), 0, 50);

                    foreach ($latestPosts as $post): 
                        $topicName = getWpForoTopicName($post['topicid'], $wpforoTopics);
                        $authorName = getWpForoUserName($post['userid'], $wpforoUsers);
                    ?>
                    <tr>
                        <td><?php echo $post['postid']; ?></td>
                        <td><?php echo htmlspecialchars($topicName); ?></td>
                        <td><?php echo htmlspecialchars($authorName); ?></td>
                        <td><?php echo mb_substr(strip_tags($post['body']), 0, 100) . '...'; ?></td>
                        <td><?php echo date('d.m.Y H:i', strtotime($post['created'])); ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="post_id" value="<?php echo $post['postid']; ?>">
                                <button type="submit" name="delete_post" class="btn btn-danger btn-sm" onclick="return confirm('Удалить сообщение?')">🗑️ Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>

</body>
</html>
