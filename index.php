<?php
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

    $settings = $db->get('settings');
    $slider = $db->get('slider');
    $about = $db->get('about');

    // Значения по умолчанию
    if (!$settings) $settings = array();
    if (!$slider) $slider = array('slides' => array());
    if (!$about) $about = array();

    // Дефолты для settings
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

    // Дефолты для about
    $defaults_about = array(
        'title' => 'О породе Хотошо',
        'image' => '',
        'content' => 'Информация о породе',
        'author' => ''
    );
    $about = array_merge($defaults_about, $about);

} catch (Exception $e) {
    die("Ошибка БД: " . htmlspecialchars($e->getMessage()));
}

// Функция для правильного пути
function getImagePath($path) {
    if (empty($path)) return '';
    if (strpos($path, '/') === 0) return $path;
    return '/' . $path;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['site_title'], ENT_QUOTES, 'UTF-8'); ?></title>
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

        /* ПРИВЕТСТВИЕ */
        .welcome-section {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 60px 20px;
            text-align: center;
        }

        .welcome-section h1 {
            color: #4A2C2A;
            font-size: 42px;
            margin-bottom: 20px;
        }

        .welcome-section p {
            font-size: 16px;
            max-width: 900px;
            margin: 0 auto;
            line-height: 1.8;
        }

        /* СЛАЙДЕР */
        .slider-section {
            padding: 60px 20px;
            background: #fffcf8;
        }

        .slider-container {
            max-width: 1200px;
            margin: 0 auto;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        }

        .slider-wrapper {
            position: relative;
            height: 600px;
            background: #ddd;
        }

        .slide {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1s;
        }

        .slide.active {
            opacity: 1;
            z-index: 1;
        }

        .slide img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .slider-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(74, 44, 42, 0.7);
            color: white;
            border: none;
            padding: 15px 20px;
            font-size: 28px;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s;
        }

        .slider-nav:hover {
            background: rgba(74, 44, 42, 0.95);
        }

        .slider-nav.prev { left: 20px; }
        .slider-nav.next { right: 20px; }

        .slider-dots {
            text-align: center;
            padding: 20px;
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10;
        }

        .dot {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            margin: 0 6px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .dot.active {
            background: #4A2C2A;
            transform: scale(1.3);
        }

        /* ЗАГОЛОВОК */
        .section-title {
            text-align: center;
            font-size: 48px;
            margin: 60px 0 40px;
            color: #4A2C2A;
        }

        /* О ПОРОДЕ */
        .about-section {
            padding: 0 20px 60px;
        }

        .about-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            gap: 40px;
        }

        .about-image {
            flex: 0 0 350px;
        }

        .about-image img {
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        .about-text {
            flex: 1;
        }

        .about-text p {
            margin-bottom: 15px;
            font-size: 16px;
            line-height: 1.8;
            text-align: justify;
        }

        .author {
            text-align: right;
            font-style: italic;
            margin-top: 30px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }

        /* ФУТЕР */
        .footer {
            background: #4A2C2A;
            color: white;
            padding: 50px 20px 30px;
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

        /* АДАПТИВ */
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

            .welcome-section h1 { 
                font-size: 28px; 
            }

            .slider-wrapper { 
                height: 300px; 
            }

            .about-content { 
                flex-direction: column; 
            }

            .about-image { 
                flex: 1; 
            }

            .footer-content { 
                grid-template-columns: 1fr; 
            }

            .section-title { 
                font-size: 32px; 
            }
        }
    </style>
</head>
<body>

    <!-- ШАПКА (загружается из админки) -->
    <?php 
    $headerPath = getImagePath($settings['header_image']);
    if (!empty($headerPath)): 
    ?>
    <img src="<?php echo htmlspecialchars($headerPath, ENT_QUOTES, 'UTF-8'); ?>" alt="Шапка сайта" class="header-image">
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
                <li><a href="index.php" class="active">Главная</a></li>
                <li><a href="blog.php">Блог</a></li>
                <li><a href="forum.php">Форум</a></li>
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

    <!-- ПРИВЕТСТВИЕ -->
    <section class="welcome-section">
        <h1><?php echo htmlspecialchars($settings['welcome_title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p><?php echo nl2br(htmlspecialchars($settings['welcome_text'], ENT_QUOTES, 'UTF-8')); ?></p>
    </section>

    <!-- СЛАЙДЕР -->
    <?php if (!empty($slider['slides']) && is_array($slider['slides']) && count($slider['slides']) > 0): ?>
    <section class="slider-section">
        <div class="slider-container">
            <div class="slider-wrapper">
                <?php foreach ($slider['slides'] as $index => $slide): ?>
                    <?php if (!empty($slide['image'])): ?>
                    <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
                        <img src="<?php echo htmlspecialchars(getImagePath($slide['image']), ENT_QUOTES, 'UTF-8'); ?>" alt="Слайд <?php echo $index + 1; ?>">
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (count($slider['slides']) > 1): ?>
                <button class="slider-nav prev" onclick="changeSlide(-1)">❮</button>
                <button class="slider-nav next" onclick="changeSlide(1)">❯</button>

                <div class="slider-dots">
                    <?php foreach ($slider['slides'] as $index => $slide): ?>
                    <span class="dot <?php echo $index === 0 ? 'active' : ''; ?>" onclick="goToSlide(<?php echo $index; ?>)"></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- О ПОРОДЕ -->
    <h2 class="section-title"><?php echo htmlspecialchars($about['title'], ENT_QUOTES, 'UTF-8'); ?></h2>

    <section class="about-section">
        <div class="about-content">
            <?php if (!empty($about['image'])): ?>
            <div class="about-image">
                <img src="<?php echo htmlspecialchars(getImagePath($about['image']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($about['title'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <?php endif; ?>

            <div class="about-text">
                <?php 
                $paragraphs = explode("\n\n", $about['content']);
                foreach ($paragraphs as $paragraph) {
                    $paragraph = trim($paragraph);
                    if (!empty($paragraph)) {
                        echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')) . '</p>';
                    }
                }
                ?>
                <?php if (!empty($about['author'])): ?>
                <p class="author">Автор: <?php echo htmlspecialchars($about['author'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ФУТЕР -->
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

        // СЛАЙДЕР
        var currentSlide = 0;
        var slides = document.querySelectorAll('.slide');
        var dots = document.querySelectorAll('.dot');
        var totalSlides = slides.length;

        function showSlide(n) {
            if (totalSlides === 0) return;

            if (n >= totalSlides) currentSlide = 0;
            if (n < 0) currentSlide = totalSlides - 1;

            for (var i = 0; i < slides.length; i++) {
                slides[i].classList.remove('active');
            }
            for (var i = 0; i < dots.length; i++) {
                dots[i].classList.remove('active');
            }

            slides[currentSlide].classList.add('active');
            if (dots[currentSlide]) {
                dots[currentSlide].classList.add('active');
            }
        }

        function changeSlide(n) {
            currentSlide += n;
            showSlide(currentSlide);
        }

        function goToSlide(n) {
            currentSlide = n;
            showSlide(currentSlide);
        }

        if (totalSlides > 1) {
            setInterval(function() {
                currentSlide++;
                showSlide(currentSlide);
            }, 5000);
        }
    </script>

</body>
</html>
