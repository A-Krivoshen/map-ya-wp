<?php
/*
Plugin Name: Map Activation Plugin
Description: Вставка карт через шорткод с активацией по клику и адаптивным масштабированием.
Version: 1.3
Author: Aleskey Krivoshein
*/

if (!defined('ABSPATH')) {
    exit; // Защита от прямого доступа
}

// Включаем поддержку широких блоков для темы
function map_activation_theme_support() {
    add_theme_support('align-wide');
}
add_action('after_setup_theme', 'map_activation_theme_support');

// Добавляем страницу настроек в админку
add_action('admin_menu', 'map_activation_plugin_menu');

function map_activation_plugin_menu() {
    add_options_page(
        'Настройки Map Activation',
        'Map Activation',
        'manage_options',
        'map-activation-plugin',
        'map_activation_plugin_options'
    );
}

// Функция для отображения страницы настроек
function map_activation_plugin_options() {
    if (!current_user_can('manage_options')) {
        wp_die(__('У вас нет прав доступа к этой странице.'));
    }

    // Обработка сохранения настроек
    if (isset($_POST['map_activation_plugin_text']) && isset($_POST['map_activation_plugin_default_src'])) {
        update_option('map_activation_plugin_text', sanitize_text_field($_POST['map_activation_plugin_text']));
        update_option('map_activation_plugin_default_src', esc_url_raw($_POST['map_activation_plugin_default_src']));
        echo '<div class="updated"><p>Настройки сохранены.</p></div>';
    }

    // Получаем текущие значения настроек
    $activation_text = get_option('map_activation_plugin_text', 'Для активации карты нажмите по ней');
    $default_src = get_option('map_activation_plugin_default_src', '');

    ?>

    <div class="wrap">
        <h1>Настройки Map Activation</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Фраза для активации карты</th>
                    <td>
                        <input type="text" name="map_activation_plugin_text" value="<?php echo esc_attr($activation_text); ?>" class="regular-text" />
                        <p class="description">Введите фразу, которая будет отображаться перед активацией карты. По умолчанию: "Для активации карты нажмите по ней".</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Ссылка для карты по умолчанию (src)</th>
                    <td>
                        <input type="text" name="map_activation_plugin_default_src" value="<?php echo esc_attr($default_src); ?>" class="regular-text" />
                        <p class="description">Введите ссылку `src` для карты по умолчанию, если в шорткоде не указано значение. Например: https://yandex.ru/map-widget/v1/...</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>

    <?php
}

// Регистрируем шорткод
add_shortcode('map_activation', 'map_activation_plugin_shortcode');

// Функция шорткода для вставки карты
function map_activation_plugin_shortcode($atts, $content = null) {
    $atts = shortcode_atts(
        array(
            'iframe' => '',   
            'width'  => '100%', 
            'height' => '400',  
        ),
        $atts,
        'map_activation'
    );

    // Получаем `src` и текст из настроек
    $default_src = get_option('map_activation_plugin_default_src', '');
    $activation_text = get_option('map_activation_plugin_text', 'Для активации карты нажмите по ней');

    // Если `iframe` не задан, используем значение по умолчанию
    $iframe_code = $atts['iframe'] ?: '<iframe src="' . esc_url($default_src) . '" width="' . esc_attr($atts['width']) . '" height="' . esc_attr($atts['height']) . '" frameborder="0" style="pointer-events: none;"></iframe>';

    // Уникальный идентификатор для каждой карты
    $unique_id = uniqid('wrapMap_');

    ob_start();
    ?>

    <div id="<?php echo esc_attr($unique_id); ?>" class="wrapMap" style="width: 100%; height: <?php echo esc_attr($atts['height']); ?>px;">
        <?php echo $iframe_code; ?>
    </div>

    <style>
        #<?php echo esc_attr($unique_id); ?> {
            position: relative;
            cursor: help;
            overflow: hidden;
            border: 1px solid #ccc;
        }
        #<?php echo esc_attr($unique_id); ?> .mapTitle {
            position: absolute;
            z-index: 1000;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.25);
            display: none;
            padding: 5px 20px;
            border-radius: 5px;
            background: #fff;
            border: 1px solid #ccc;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var wrapMap = document.getElementById('<?php echo esc_js($unique_id); ?>');
            var iframe = wrapMap.querySelector('iframe');

            // Функция для адаптивного изменения высоты iframe
            function resizeIframe() {
                iframe.style.height = wrapMap.offsetHeight + 'px';
            }

            resizeIframe(); // Устанавливаем начальную высоту
            window.addEventListener('resize', resizeIframe); // Обновляем высоту при изменении размера окна

            // Создаем элемент подсказки
            var mapTitle = document.createElement('div');
            mapTitle.className = 'mapTitle';
            mapTitle.textContent = '<?php echo esc_js($activation_text); ?>';
            wrapMap.appendChild(mapTitle);

            // Обработчик клика для активации карты
            wrapMap.addEventListener('click', function() {
                iframe.style.pointerEvents = 'auto'; // Включаем взаимодействие
                mapTitle.remove(); // Удаляем подсказку
            });

            // Обработчики движения мыши для отображения подсказки
            wrapMap.addEventListener('mousemove', function(event) {
                mapTitle.style.display = 'block';
                mapTitle.style.top = event.offsetY + 20 + 'px';
                mapTitle.style.left = event.offsetX + 20 + 'px';
            });

            wrapMap.addEventListener('mouseleave', function() {
                mapTitle.style.display = 'none';
            });
        });
    </script>

    <?php
    return ob_get_clean();
}

// Регистрируем скрипт для использования в шорткоде (опционально)
function map_activation_enqueue_scripts() {
    wp_enqueue_script('map-activation-script', plugins_url('map-activation.js', __FILE__), array('jquery'), null, true);
}
add_action('wp_enqueue_scripts', 'map_activation_enqueue_scripts');
?>
