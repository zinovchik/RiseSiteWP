<?php

// Проверка существование внешней ссылки (URL)
function rs_open_url($url)
{
    if (strpos($url, "mailto:") === 0) {
        return 'ссылка на електронную почту';
    }

    $url_c = parse_url($url);
    if (!empty($url_c['host'])) {

        //на всяк случай меняем пробелы в адресе на спецсимвол
        $url = str_replace(" ","%20", $url);

        // Ответ сервера
        if ($answer = @get_headers($url)) {
            return substr($answer[0], 9, 3);
        }
    }

    return 'не найден домен';
}

$status = rs_open_url($_GET['url']);
if (!($status =='200' || $status =='301' || $status =='302')) {
    echo $_GET['url'] . ' - ' . $status;
}