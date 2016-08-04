<?php
/**
 * @package RiseSiteWP
 * @version 1.0
 */
/*
Plugin Name: RiseSiteWP
Plugin URI: https://github.com/zinovchik/RiseSiteWP
Description: This is a plugin for rise post from WebArchive.
Author: Maxim Zinovchik
Version: 1.0
*/


//****************************************************************************************************************************************
// Функция добавляет пункты меню в админку
function rs_add_menu()
{
    add_menu_page('Rise Post', 'Rise Post', 8, 'rs_rise_post', 'rs_rise_post');
    add_submenu_page('rs_rise_post', 'Plugin Settings', 'Plugin Settings', 8, 'rs_settings', 'rs_settings');
}

add_action('admin_menu', 'rs_add_menu');


//****************************************************************************************************************************************
// Страница востановления постов
function rs_rise_post()
{
    $rs_domain_name = get_option('rs_domain_name');

    echo "<h2>Востановление постов сайта: <b>$rs_domain_name</b></h2>
            <style type='text/css'>
                #wpbody-content td {border: 1px solid #ccc;padding: 5px 10px;text-align: center;max-width: 1000px;}
                .rs_error {color: red;}
                .rs_ok {color: green;}
            </style>";

    if (isset($_POST['rs_scan'])) {
        //****************************************************************************************************************************************
        //АНАЛИЗ ДОСТУПНЫХ ВЕРСИЙ СТРАНИЦЫ В ВЕБ АРХИВЕ

        $list_of_pages = file_get_contents("http://web.archive.org/cdx/search/cdx?url=" . $_POST['rs_url_page'] . "&output=json&limit=500");
        $list_of_pages = json_decode($list_of_pages, true);

        if ($list_of_pages) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function () {
                    jQuery(".rs_list tr:not('.rs_list tr:first , .rs_list tr:last')").click(function () {
                        jQuery(this).find('input').prop("checked", true);
                        jQuery(".rs_list td").removeAttr('style');
                        jQuery(this).find('td').css({"backgroundColor": "#D6D4D4"});

                    });
                    <?php
                    if (get_option('rs_auto_select_version')) echo "jQuery('#rs_rise_form').click();";
                    ?>
                });
            </script>
            <form method="post">

                <h4>URL: <?= $_POST['rs_url_page'] ?></h4>
                <table class='rs_list' cellspacing='0' style="margin: 0 50px;">
                    <tr>
                        <td><b>№</b></td>
                        <td><b>Дата</b></td>
                        <td><b>Статус</b></td>
                        <td colspan="2"><b>Действия</b></td>
                    </tr>

                    <?php
                    $i = 1;
                    array_shift($list_of_pages);
                    rsort($list_of_pages);

                    $rs_checked = 0;
                    foreach ($list_of_pages as $one_page) { ?>
                        <tr>
                            <?php if (!$rs_checked && ($one_page[4] == '200')) {
                                $rs_tmp_style = ' style="background-color: #d6d4d4;"';
                            } else {
                                $rs_tmp_style = '';
                            } ?>
                            <td<?= $rs_tmp_style ?>><?php echo $i++; ?></td>
                            <td<?= $rs_tmp_style ?>><?php echo substr($one_page[1], 6, 2) . '.' . substr($one_page[1], 4, 2) . '.' . substr($one_page[1], 0, 4); ?></td>
                            <td<?= $rs_tmp_style ?>><?php echo $one_page[4]; ?></td>
                            <td<?= $rs_tmp_style ?>><a
                                    href="http://web.archive.org/web/<?php echo $one_page[1]; ?>/<?php echo $one_page[2]; ?>"
                                    target="_blank">Открыть</a></td>
                            <td<?= $rs_tmp_style ?>>
                                <label>
                                    <input type="radio" name="rs_version_date"
                                           value="<?= $one_page[1] ?>" <?php if (!$rs_checked && ($one_page[4] == '200')) {
                                        echo "checked='checked'";
                                        $rs_checked++;
                                    } ?> />
                                    <input type="hidden" name="type_file" value="<?= $one_page[3] ?>">
                                </label></td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td colspan="5">
                            <div class="submit">
                                <input name="rs_url_page" type="hidden" value="<?= $_POST['rs_url_page'] ?>"/>
                                <input name="rs_rise" id="rs_rise_form" type="submit" class="button-primary"
                                       value="<?php echo __('Read Post'); ?>"/>
                            </div>
                        </td>
                    </tr>
                </table>
            </form>
        <?php } else {
            echo "<br><span class='rs_error'>* В Веб Архиве нет данных о текущей странице!</span>";
        }


    } elseif (isset($_POST['rs_rise'])) {
        //****************************************************************************************************************************************
        //ЧТЕНИЕ ИЗ ВЕБ АРХИВА СТРАНИЦЫ И ЕЕ ПАРСИНГ

        //Если востанавливаеем картинки
        if (in_array($_POST['type_file'], array('image/jpeg', 'image/png', 'image/gif'))) {

            if (strpos($_POST['rs_url_page'], 'wp-content/uploads')) {

                //создаем масив из имен папок где лежит картинка
                $path_file = $_POST['rs_url_page'];
                $path_file = substr($path_file, strpos($path_file, 'wp-content/uploads') + 19);
                $path_file = explode('/', $path_file);
                $file_name = array_pop($path_file);

                $start_path = '../wp-content/uploads/';
                if (!file_exists($start_path)) {
                    mkdir($start_path);
                }

                //если директории нет, то создаем ее
                foreach ($path_file as $one) {
                    $start_path .= $one . '/';
                    if (!file_exists($start_path)) {
                        mkdir($start_path);
                    }
                }
                $start_path = $start_path . $file_name;
                //чтение создание и запись картинки
                $handle = fopen($start_path, "w");
                fwrite($handle, file_get_contents("http://web.archive.org/web/$_POST[rs_version_date]/$_POST[rs_url_page]"));
                fclose($handle);

                $start_path = get_site_url() . substr($start_path, 2);
                echo "<p>Изображение востановлено <br><br><img src='$start_path' /></p>";


                // файл должен находиться в директории загрузок WP.
                $upload_dir = wp_upload_dir();

                $filename = $upload_dir['basedir'] . substr($start_path, strpos($start_path, 'wp-content/uploads') + 18);

                // ID поста, к которому прикрепим вложение.
                $parent_post_id = 0;

                // Проверим тип поста, который мы будем использовать в поле 'post_mime_type'.
                $filetype = wp_check_filetype(basename($filename), null);

                // Получим путь до директории загрузок.
                $wp_upload_dir = wp_upload_dir();

                // Подготовим массив с необходимыми данными для вложения.
                $attachment = array(
                    'guid' => $filename,
                    'post_mime_type' => $filetype['type'],
                    'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
                    'post_content' => '',
                    'post_status' => 'inherit'
                );

                // Вставляем запись в базу данных.
                $attach_id = wp_insert_attachment($attachment, $filename, $parent_post_id);

                // Подключим нужный файл, если он еще не подключен
                // wp_generate_attachment_metadata() зависит от этого файла.
                require_once(ABSPATH . 'wp-admin/includes/image.php');

                // Создадим метаданные для вложения и обновим запись в базе данных.
                $attach_data = wp_generate_attachment_metadata($attach_id, $filename);
                wp_update_attachment_metadata($attach_id, $attach_data);


            } else {
                echo "<p>Ошибка! Для востановления доступны изображения загруженые пользователем (из директории ...wp-content/uploads/...)</p>";
            }


        }

        //Если востанавливаем страницу
        if ($_POST['type_file'] == 'text/html') {
            // подключение библиотеки
            include_once('simple_html_dom.php');
            global $wpdb;
            // $html - весь контент страницы узятой из веб архива
            // $rs_title - заголовок поста, потом адрес (slug) поста
            // rs_content - содержимое поста
            // rs_content_link - переменная для ссылок в посте
            // $rs_message - текст сообщения
            // $query - запрос к базе данных
            // $rs_i -  счетчик в цикле
            // $rs_tmp - временная переменная

            $rs_domain_name = get_option('rs_domain_name');
//        $rs_get_title = get_option('rs_get_title');
            $rs_title_path = get_option('rs_title_path');
            $rs_get_slug = get_option('rs_get_slug');
            $rs_slug_path = get_option('rs_slug_path');
//        $rs_get_date = get_option('rs_get_date');
            $rs_date_path = get_option('rs_date_path');
//        $rs_get_content = get_option('rs_get_content');
            $rs_content_path = get_option('rs_content_path');
//        $rs_get_category = get_option('rs_get_category');
            $rs_category_path = get_option('rs_category_path');
            $rs_tag_path = get_option('rs_tag_path');
            $rs_is_localhost = get_option('rs_is_localhost');

            $html = file_get_html("http://web.archive.org/web/$_POST[rs_version_date]/$_POST[rs_url_page]");

            echo "<form method='post'>";

            $rs_title = $html->find($rs_title_path, 0);
            echo "<label>Title <input name='rs_title' style='display: block;margin: 10px 0;width: 99%; border:1px solid #777;' value='$rs_title->plaintext' /></label>";

            $rs_slug = explode("/", $_POST['rs_url_page']);
            $rs_slug = array_diff($rs_slug, array('')); //удаляем пустые елементы масива, если url заканчивается на слеш
            $rs_slug = array_pop($rs_slug);

            //если нужно с url убрать, то что в поле $rs_slug_path например .html
            if($rs_get_slug){
                $rs_slug = str_replace($rs_slug_path, '', $rs_slug);
            }

            $query = "SELECT count(*) as c FROM `wp_posts` WHERE `post_status` = 'publish' AND `post_name` = '$rs_slug' AND `post_type` = 'post'";
            $rs_message = ($wpdb->get_var($wpdb->prepare($query))) ? "<span class='rs_error'>(Текущий адрес (slug) существует на сайте!!!)</span>" : '';

            echo "<label>Slug $rs_message<input name='rs_slug' style='display: block;margin: 10px 0;width: 99%; border:1px solid #777;' value='$rs_slug' /></label>";

            $rs_date = $html->find($rs_date_path, 0);
            $rs_date = date("Y-m-d H:i:s", strtotime($rs_date->plaintext));
            echo "<label>Date <input name='rs_date' style='display: block;margin: 10px 0;width: 99%; border:1px solid #777;' value='$rs_date' /></label>";

            foreach ($html->find($rs_category_path) as $e) {
                $e1[] = $e->plaintext;
            }

            $e = '';
            $args = array(
                'hide_empty' => 0,
                'type' => 'post'
            );
            $categories = get_categories($args);
            foreach ($categories as $category) {
                $e[$category->term_id] = $category->name;
            }


            foreach ($e1 as $value) {

                if (in_array($value, $e)) {
                    $e = array_flip($e);
                    $e2[] = $e[$value];
                    $e = array_flip($e);
                } else {
                    $my_cat = array('cat_name' => $value);
                    // Create the category
                    echo '<br><span class="rs_ok">* create new category id = ';
                    echo $e2[] = wp_insert_category($my_cat);
                    echo ', Name = ' . $value . '</span><br>';
                }
            }
            $e2 = implode(',', $e2);
            echo "<label>Category id <input name='rs_category' style='display: block;margin: 10px 0;width: 99%; border:1px solid #777;' value='$e2' /></label>";



            //**************

            foreach ($html->find($rs_tag_path) as $e) {
                $tags[] = $e->plaintext;
            }

//            $e = '';
//            $args = array(
//                'hide_empty' => 0,
//                'type' => 'post'
//            );
//            $tags = get_tags($args);
//            foreach ($tags as $tag) {
//                $e[$tag->term_id] = $tag->name;
//            }


//            foreach ($e1 as $value) {
//
//                if (in_array($value, $e)) {
//                    $e = array_flip($e);
//                    $e2[] = $e[$value];
//                    $e = array_flip($e);
//                } else {
//                    $my_tag = array('cat_name' => $value);
//                    // Create the tag
//                    echo '<br><span class="rs_ok">* create new tag id = ';
//                    echo $e2[] = wp_insert_category($my_cat);
//                    echo ', Name = ' . $value . '</span><br>';
//                }
//            }
            $e2 = implode(',', $tags);
            echo "<label>Tags <input name='rs_tag' style='display: block;margin: 10px 0;width: 99%; border:1px solid #777;' value='$e2' /></label>";

            //**************




            $rs_content = $html->find($rs_content_path, 0);

            foreach ($rs_content->find('h1.post-title') as $e3) {
                $e3->outertext = '';
                echo '<span class="rs_error">* Удален заголовок!!!</span><br>';
            }

            foreach ($rs_content->find('div.meta') as $e3) {
                $e3->outertext = '';
                echo '<span class="rs_error">* Удален верхний блок!!!</span><br>';
            }


            foreach ($rs_content->find('textarea') as $e3) {
                $e3->outertext = '';
                echo '<span class="rs_error">* Удалена textarea!!!</span><br>';
            }


            //--поиск и удаление битых ссылок (анкор остается)
            $rs_i = 0;
            foreach ($rs_content->find('a') as $rs_content_link) {
                $rs_i++;
                $rs_tmp = $rs_content_link->href;

                // убираем из ссылки код веб архива
                $rs_tmp = str_replace("/web/$_POST[rs_version_date]/", '', $rs_tmp);

                // если сайт локально востанавливаем, то добавляем localhost
                if ($rs_is_localhost) {
                    $rs_tmp = str_replace($rs_domain_name, 'localhost/' . $rs_domain_name, $rs_tmp);
                }

                //Приведем все ссылки к стандартному виду, поставив всем в начало протокол http
                if (strpos($rs_tmp, "http") === FALSE && strpos($rs_tmp, "mailto:") === FALSE) {
                    echo $rs_tmp = "http://" . $rs_tmp;
                }

                //если включена опция "При востановлении поста автоматически определять и удалять битые ссылки"
                if (get_option('rs_is_detect_broken_links')) {

                    // если ссылка не рабочая то удаляем ее и оставляем анкор
                    $rs_message = rs_open_url($rs_tmp);
                    if ($rs_message == '200' || $rs_message == 'ссылка на електронную почту') {
                        $rs_content_link->href = $rs_tmp;

                    } elseif (($rs_message == '301' && !(get_option('rs_is_301_broken_links'))) || ($rs_message == '302' && !(get_option('rs_is_302_broken_links')))) {
                        echo "<br><span class='rs_ok'> * в коде возможно не рабочая ссылка (<a href='$rs_tmp' target='_blank'>URL ссылки</a>) код ответа $rs_message</span>";
                        $rs_content_link->href = $rs_tmp;
                        //$rs_content_link->outertext = $rs_content_link->innertext;

                    } else {
                        echo "<br><span class='rs_ok'> * не рабочая ссылка удалена (<a href='$rs_tmp' target='_blank'>URL ссылки</a>) код ответа $rs_message</span>";
                        $rs_content_link->outertext = $rs_content_link->innertext;
                    }
                } else {
                    $rs_content_link->href = $rs_tmp;
                }
            }
            echo $rs_i ? "<br><span class='rs_ok'> * ссылки на странице исправлены (удалено $rs_i: /web/$_POST[rs_version_date]/)</span>" : '';

            //--поиск изображений и исправление ссылок
            $rs_i = 0;
            foreach ($rs_content->find('img') as $rs_content_img) {
                $rs_i++;
                $rs_tmp = $rs_content_img->src;

                // убираем из ссылки код веб архива
                $rs_tmp = str_replace("/web/$_POST[rs_version_date]im_/", '', $rs_tmp);

                // если сайт локально востанавливаем, то добавляем localhost
                if (get_option('rs_is_localhost')) {
                    $rs_tmp = str_replace($rs_domain_name, 'localhost/' . $rs_domain_name, $rs_tmp);
                }

                //Приведем все ссылки к стандартному виду, поставив всем в начало протокол http
                //if (strpos($rs_tmp,"http:") === FALSE){ echo $rs_tmp="http://".$rs_tmp; }

                //если включена опция "При востановлении поста автоматически определять и удалять не существующие картинки"
                if (get_option('rs_is_detect_broken_images')) {
                    $rs_message = rs_open_url($rs_tmp);
                    if ($rs_message != '200') {

                        echo "<br><span class='rs_error'> * картинка удалена (<a href='$rs_tmp' target='_blank'>URL ссылки</a>) код ответа $rs_message</span>";
                        $rs_tmp_array[] = $rs_content_img->outertext;
                        $rs_content_img->outertext = '';
                    } else {
                        $rs_content_img->src = $rs_tmp;
                    }
                } else {
                    $rs_content_img->src = $rs_tmp;
                }


                //echo "<br>".rs_open_url($rs_tmp)." - $rs_tmp";


            }
            echo $rs_i ? "<br><span class='rs_ok'> * адреса картинок исправлены (удалено $rs_i: /web/$_POST[rs_version_date]im_/)</span>" : '';
            //print_r($rs_tmp_array);

            $html = $rs_content->innertext;

            foreach ($rs_tmp_array as $rs_tmp) {

                $html = str_replace($rs_tmp, '', $html);
                echo '*';

            }


            //--поиск и исправление ссылок
            //rise_links("/web/$_POST[rs_version_date]/", "ссылки на странице исправлены", $html);

            //--поиск и исправление адресов картинок
            rise_links("/web/$_POST[rs_version_date]im_/", "адреса картинок исправлены", $html);

            //--поиск и исправление адресов js файлов
            rise_links("/web/$_POST[rs_version_date]js_/", "адреса js файлов исправлены", $html);

            //--поиск и исправление адресов iframe тегов
            rise_links("/web/$_POST[rs_version_date]if_/", "адреса iframe тегов исправлены", $html);

            //--поиск и исправление адресов frame тегов
            rise_links("/web/$_POST[rs_version_date]fw_/", "адреса frame тегов исправлены", $html);

            //--поиск и исправление адресов css файлов
            rise_links("/web/$_POST[rs_version_date]cs_/", "адреса css файлов исправлены", $html);

            //--поиск и исправление адресов тега object
            rise_links("/web/$_POST[rs_version_date]oe_/", "адреса тега object исправлены", $html);

            $tmp_strrpos = strrpos($html, 'web/');
            echo $tmp_strrpos ? "<br><span class='rs_error'>* In post is set web archive code!!!</span>" : '';


            echo "<textarea  name='rs_content' style='display: block;height: 400px;margin: 10px 0;width: 99%; border:1px solid #777;'>" . trim($html) . "</textarea>";

            echo '<div class="submit">
		          <input name="rs_save" type="submit" class="button-primary" value="Save Page" />
		      </div>
		</form>';
        }

    } elseif (isset($_POST['rs_save'])) {
        //****************************************************************************************************************************************
        // СОХРАНЕНИЕ ПОСТА ИЛИ СТРАНИЦЫ

        $np = array(
            'slug' => $_POST['rs_slug'],
            'title' => $_POST['rs_title'],
            'content' => $_POST['rs_content'],
            'post_date' => $_POST['rs_date'], //Дата создания поста.
            'post_date_gmt' => $_POST['rs_date'], //Дата создания поста по Гринвичу.
            'post_category' => explode(",", $_POST['rs_category']) //Добавление ID категорий.
        );
        $rs_id = wp_insert_post(array(
            'post_title' => $np['title'],
            'post_type' => 'post', // тип записи
            'post_name' => $np['slug'], // URL, будут совпадения? WordPress сам все испраит.
            'comment_status' => 'closed', // обсждение закрыть
            'ping_status' => 'closed', // пинги запретить
            'post_content' => $np['content'],
            'post_status' => 'publish', // опубликовать
            'post_author' => 1, // кто будет автором
            'menu_order' => 0, // положение пункта в меню
            'post_date' => $np['post_date'], //Дата создания поста.
            'post_date_gmt' => $np['post_date_gmt'], //Дата создания поста по Гринвичу.
            'post_category' => $np['post_category'] //Добавление ID категорий.
        ));

        if ($rs_id) {
            if($_POST['rs_tag']) {
                wp_set_post_tags($rs_id, $_POST['rs_tag'], true);
            }
            wp_redirect(get_site_url() . "/wp-admin/post.php?post=$rs_id&action=edit");
            exit;
        } else {
            echo '<div id="setting-error-settings_updated" class="updated settings-error"><p><b>Error</b></p></div>';
        }

    } else {
        //****************************************************************************************************************************************
        //ВЫВОД СПИСКА ДОСТУПНЫХ РЕСУРСОВ ДЛЯ ВОСТАНОВЛЕНИЯ

        $rs_is_localhost = get_option('rs_is_localhost');

        $posts = get_posts(array('post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1));

        foreach ($posts as $one) {
            $slug_array[] = $one->post_name;
        }


        $posts = get_posts(array('post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1));

        foreach ($posts as $one) {
            $slug_array[] = $one->post_name;
        }


        $posts = get_posts(array('post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1));

        foreach ($posts as $one) {
            $slug_array[] = $one->post_name;
        }

        print_r($slug_array);
        // количество циклов по 500 строк
        $count_page = file_get_contents("http://web.archive.org/cdx/search/cdx?url=" . $rs_domain_name . "/&matchType=prefix&showNumPages=true");

        do {
            $count_page = $count_page - 1;
            $list_of_pages = file_get_contents("http://web.archive.org/cdx/search/cdx?url=" . $rs_domain_name . "/&matchType=prefix&output=json&page=" . $count_page);
            $list_of_pages = json_decode($list_of_pages, true);
            array_shift($list_of_pages);
            rsort($list_of_pages);

            foreach ($list_of_pages as $one_page) {
                //if($one_page[4]=='200') {
                //чистим урлы, убираем домен и 80 порт, тоесть убираются дубли страниц с 80 портом, www и http
                if (substr_count($one_page[2], ':80')) $one_page[2] = str_replace(':80', '', $one_page[2]);

                // Если в настройках включено удаление GET переменных, то удаляем
                if (get_option('rs_del_get_parameter')) {
                    if (substr_count($one_page[2], '?')) $one_page[2] = substr($one_page[2], 0, strpos($one_page[2], '?'));
                }
                $tmp_url_id = substr($one_page[2], strpos($one_page[2], $rs_domain_name) + 1 + strlen($rs_domain_name));
                if (!$tmp_url_id) {
                    $tmp_url_id = '/';
                }
                $data[$tmp_url_id] = $one_page[3];
                //}
                //$data[$one_page[2]] = $one_page[3];
                //if (!substr_count($one_page[2],'search_type')) $data[$tmp_url_id] = $one_page[3]; исключения из списка файлов и страниц по куску имени
            }

            //print_r($data);

        } while ($count_page > 0);

        array_multisort($data);

        if ($data) {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function () {
                    jQuery(".rs_list tr:not('.rs_list tr:first')").click(function () {
                        jQuery(this).find('input').prop("checked", true);
                        jQuery(".rs_list td").removeAttr('style');
                        jQuery(this).find('td').css({"backgroundColor": "#D6D4D4"});
                    });
                });
            </script>
            <form method="post" target="_blank">
                <table class='rs_list' cellspacing='0' style="margin: 0 auto;">
                    <tr>
                        <td><b>№</b></td>
                        <td><b>Тип Файла</b></td>
                        <td><b>Ссылка</b></td>
                        <td><b>Востановить</b></td>
                    </tr>

                    <tr>
                        <td></td>
                        <td></td>
                        <td>
                            <b><a href="http://web.archive.org/web/*/<?= $rs_domain_name ?>" target="_blank">Главная
                                    страница сайта</a></b>
                            (<a style='color:grey;' href="http://<? echo $rs_is_localhost ? 'localhost/'.$rs_domain_name : $rs_domain_name ?>" target="_blank">На сайте</a>)

                        </td>
                        <td><label><input type='radio' name='rs_url_page' value="<?= $rs_domain_name ?>"/></label></td>
                    </tr>

                    <?php
                    $i = 1;
                    foreach ($data as $key => $value) { ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo $value; ?></td>
                            <td>
                                <a style='<?php echo is_set_slug($key, $slug_array); ?>'
                                   href="http://web.archive.org/web/*/<?= $rs_domain_name . '/' . $key ?>"
                                   target="_blank"><?php echo $key; ?></a>
                                (<a style='color:grey;' href="http://<? echo $rs_is_localhost ? 'localhost/'.$rs_domain_name. '/' . $key : $rs_domain_name. '/' . $key ?>"
                                    target="_blank">На сайте</a>)

                            </td>
                            <td><label><input type='radio' name='rs_url_page'
                                              value="<?= $rs_domain_name . '/' . $key ?>"/></label></td>
                        </tr>
                    <?php } ?>
                </table>
                <div class="submit" style='bottom: 20px;position: fixed;right: 30px;'>
                    <input name="rs_scan" type="submit" class="button-primary" value="<?php echo __('SCAN'); ?>"/>
                </div>
            </form>
        <?php } else {
            echo "<br><span class='rs_error'>* В Веб Архиве нет о текущем сайте!</span>";
        }
    }
}

//******************************************************************************************************************************************************
// Страница настроек плагина
function rs_settings()
{
    echo "<h2>Настройки плагина <b>RiseSiteWP:	</b></h2>";

    // Сохранение настроек
    if (isset($_POST['rs_save_settings'])) {
        update_option('rs_domain_name', $_POST['rs_domain_name']);
        update_option('rs_get_title', $_POST['rs_get_title']);
        update_option('rs_title_path', $_POST['rs_title_path']);
        update_option('rs_get_slug', $_POST['rs_get_slug']);
        update_option('rs_slug_path', $_POST['rs_slug_path']);
        update_option('rs_get_date', $_POST['rs_get_date']);
        update_option('rs_date_path', $_POST['rs_date_path']);
        update_option('rs_get_content', $_POST['rs_get_content']);
        update_option('rs_content_path', $_POST['rs_content_path']);
        update_option('rs_get_category', $_POST['rs_get_category']);
        update_option('rs_category_path', $_POST['rs_category_path']);
        update_option('rs_get_tag', $_POST['rs_get_tag']);
        update_option('rs_tag_path', $_POST['rs_tag_path']);
        update_option('rs_del_get_parameter', $_POST['rs_del_get_parameter']);
        update_option('rs_auto_select_version', $_POST['rs_auto_select_version']);
        update_option('rs_is_detect_broken_links', $_POST['rs_is_detect_broken_links']);
        update_option('rs_is_301_broken_links', $_POST['rs_is_301_broken_links']);
        update_option('rs_is_302_broken_links', $_POST['rs_is_302_broken_links']);
        update_option('rs_is_detect_broken_images', $_POST['rs_is_detect_broken_images']);
        update_option('rs_is_localhost', $_POST['rs_is_localhost']);
    }

    // Если в базе нет настроек, то будут установлены по умолчанию
    add_option('rs_domain_name', 'homeschoolmo.com', '', 'no');
    add_option('rs_get_title', 'on', '', 'no');
    add_option('rs_title_path', '.post-title a', '', 'no');
    add_option('rs_get_slug', 'on', '', 'no');
    add_option('rs_slug_path', '.html', '', 'no');
    add_option('rs_get_date', 'on', '', 'no');
    add_option('rs_date_path', '.date-header span', '', 'no');
    add_option('rs_get_content', 'on', '', 'no');
    add_option('rs_content_path', '.post-body', '', 'no');
    add_option('rs_get_category', 'on', '', 'no');
    add_option('rs_category_path', '.post-labels a', '', 'no');
    add_option('rs_get_tag', '', '', 'no');
    add_option('rs_tag_path', '', '', 'no');
    add_option('rs_del_get_parameter', '', '', 'no');
    add_option('rs_auto_select_version', '', '', 'no');
    add_option('rs_is_detect_broken_links', '', '', 'no');
    add_option('rs_is_301_broken_links', '', '', 'no');
    add_option('rs_is_302_broken_links', '', '', 'no');
    add_option('rs_is_detect_broken_images', '', '', 'no');
    add_option('rs_is_localhost', '', '', 'no');

    echo "<style>.rs_list input[type='text'] {width: 400px;}</style>
    <form method='POST' class='rs_list'>
		<table>
			<tbody>
				<tr>
					<td>
						<label>
						Домен 				
						</label>			
					</td>
					<td>
						<input name='rs_domain_name' id='rs_domain_name' value='" . get_option('rs_domain_name') . "' type='text' />
					</td>
				</tr>
				<tr>
					<td>
						<label>
						<input name='rs_get_title' id='rs_get_title' type='checkbox' " . (get_option('rs_get_title') ? 'checked=\'checked\'' : '') . " />
						Title 				
						</label>			
					</td>
					<td>
						<input name='rs_title_path' id='rs_title_path' value='" . get_option('rs_title_path') . "' type='text' />
					</td>
				</tr>
				<tr>
					<td>
						<label>
						<input name='rs_get_slug' id='rs_get_slug' type='checkbox' " . (get_option('rs_get_slug') ? 'checked=\'checked\'' : '') . " />
						Slug (what dell)
						</label>			
					</td>
					<td>
						<input name='rs_slug_path' id='rs_slug_path' value='" . get_option('rs_slug_path') . "' type='text' />
					</td>
				</tr>
				<tr>
					<td>
						<label>
						<input name='rs_get_date' id='rs_get_date' type='checkbox' " . (get_option('rs_get_date') ? 'checked=\'checked\'' : '') . " />
						Date
						</label>			
					</td>
					<td>
						<input name='rs_date_path' id='rs_date_path' value='" . get_option('rs_date_path') . "' type='text' />
					</td>
				</tr>
				<tr>
					<td>
						<label>
						<input name='rs_get_content' id='rs_get_content' type='checkbox' " . (get_option('rs_get_content') ? 'checked=\'checked\'' : '') . " />
						Content 				
						</label>			
					</td>
					<td>
						<input name='rs_content_path' id='rs_content_path' value='" . get_option('rs_content_path') . "' type='text' />
					</td>
				</tr>
				<tr>
					<td>
						<label>
						<input name='rs_get_category' id='rs_get_category' type='checkbox' " . (get_option('rs_get_category') ? 'checked=\'checked\'' : '') . " />
						Category 				
						</label>			
					</td>
					<td>
						<input name='rs_category_path' id='rs_category_path' value='" . get_option('rs_category_path') . "' type='text' />
					</td>
				</tr>
				<tr>
					<td>
						<label>
						<input name='rs_get_tag' id='rs_get_tag' type='checkbox' " . (get_option('rs_get_tag') ? 'checked=\'checked\'' : '') . " />
						Tag
						</label>
					</td>
					<td>
						<input name='rs_tag_path' id='rs_tag_path' value='" . get_option('rs_tag_path') . "' type='text' />
					</td>
				</tr>
				<tr>
					<td colspan='2'>
						<label>
						<input name='rs_del_get_parameter' id='rs_del_get_parameter' type='checkbox' " . (get_option('rs_del_get_parameter') ? 'checked=\'checked\'' : '') . " />
						Удалить GET переменные из URL 				
						</label>			
					</td>
				</tr>
				<tr>
				    <td colspan='2'>
				        <hr>
				    </td>
				</tr>
				<tr>
					<td colspan='2'>
						<label>
						<input name='rs_auto_select_version' id='rs_auto_select_version' type='checkbox' " . (get_option('rs_auto_select_version') ? 'checked=\'checked\'' : '') . " />
						Автоматический выбор версии страницы
						</label>
					</td>
				</tr>
				<tr>
				    <td colspan='2'>
				        <hr>
				    </td>
				</tr>
				<tr>
					<td colspan='2'>
						<label>
						<input name='rs_is_detect_broken_links' id='rs_is_detect_broken_links' type='checkbox' " . (get_option('rs_is_detect_broken_links') ? 'checked=\'checked\'' : '') . " />
						При востановлении поста автоматически определять и удалять битые ссылки
						</label>
					</td>
				</tr>
				<tr>
					<td colspan='2'>
						<label>
						<input name='rs_is_301_broken_links' id='rs_is_301_broken_links' type='checkbox' " . (get_option('rs_is_301_broken_links') ? 'checked=\'checked\'' : '') . " />
						Ссылки с 301 редиректом считать не существующими и удалять
						</label>
					</td>
				</tr>
				<tr>
					<td colspan='2'>
						<label>
						<input name='rs_is_302_broken_links' id='rs_is_302_broken_links' type='checkbox' " . (get_option('rs_is_302_broken_links') ? 'checked=\'checked\'' : '') . " />
						Ссылки с 302 редиректом считать не существующими и удалять
						</label>
					</td>
				</tr>
				<tr>
				    <td colspan='2'>
				        <hr>
				    </td>
				</tr>
				<tr>
					<td colspan='2'>
						<label>
						<input name='rs_is_detect_broken_images' id='rs_is_detect_broken_images' type='checkbox' " . (get_option('rs_is_detect_broken_images') ? 'checked=\'checked\'' : '') . " />
						При востановлении поста автоматически определять и удалять не существующие картинки
						</label>
					</td>
				</tr>
				<tr>
				    <td colspan='2'>
				        <hr>
				    </td>
				</tr>
				<tr>
					<td colspan='2'>
						<label>
						<input name='rs_is_localhost' id='rs_is_localhost' type='checkbox' " . (get_option('rs_is_localhost') ? 'checked=\'checked\'' : '') . " />
						Востановление сайта проходит на localhost
						</label>
					</td>
				</tr>
				<tr>
					<td colspan='2'><input name='rs_save_settings' id='rs_save_settings' type='submit' value='Сохранить' /></td>
				</tr>
		</tbody>
		</table>
	</form>";
}


//******************************************************************************************************************************************************
// Функция востанавливает ссылки удаляя код вебархива
function rise_links($search_text, $message, &$page)
{
    $tmp = substr_count($page, $search_text);
    $page = str_replace($search_text, '', $page);
    echo $tmp ? "<br><span class='rs_ok'> * $message (удалено $tmp: \"$search_text\")</span>" : '';
    return;
}

//******************************************************************************************************************************************************
// Проверяет есть ли указаный slug в базе 
function is_set_slug($value, &$slug_array)
{   //$rs_slug_path = $rs_get_slug ? $rs_slug_path : '';

    $value = strtolower($value);
    reset($slug_array);
    foreach ($slug_array as $slug_one) {
        if (!(strpos($value, $slug_one) === FALSE)) return "color:green;";
//        if (!strpos($value, '/' . $slug_one . $rs_slug_path) === FALSE) return "color:green;";
    }
    return '';
}

//******************************************************************************************************************************************************
// Проверка существование внешней ссылки (URL)
function rs_open_url($url)
{
    if (strpos($url, "mailto:") === 0) {
        return 'ссылка на електронную почту';
    }

    $url_c = parse_url($url);
    //print_r($url_c);
    if (!empty($url_c['host'])) {
        // Ответ сервера
        if ($answer = @get_headers($url)) {
            return substr($answer[0], 9, 3);
        }
    }
    return 'не найден домен';
}


//******************************************************************************************************************************************************
?>
