<?php
// Xatoliklarni ko'rsatish
error_reporting(E_ALL);
ini_set('display_errors', 1);

// PHP serverining vaqt zonasini sozlash (O'zbekiston vaqt zonasi)
date_default_timezone_set('Asia/Tashkent');

// Telegram Bot API token
$token = 'token-joyi';

// OLX URL
$url = 'https://www.olx.uz/oz/rabota/it-telekom-kompyutery/';

// cURL bilan OLX sahifasini o'qish
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
$response = curl_exec($ch);
curl_close($ch);

// DOMDocument bilan HTMLni o'qish
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($response);
libxml_clear_errors();

// E'lonlarni olish
$xpath = new DOMXPath($dom);
$ads = $xpath->query('//div[contains(@class, "css-l9drzq")]');

// E'lonlar ro'yxatini yig'ish
$ads_data = [];
foreach ($ads as $ad) {
    $title_elements = $ad->getElementsByTagName('h4');
    $title = ($title_elements->length > 0) ? trim($title_elements[0]->textContent) : 'Sarlavha yo‘q';

    $link_elements = $ad->getElementsByTagName('a');
    $link = ($link_elements->length > 0) ? $link_elements[0]->getAttribute('href') : '';
    $full_link = !empty($link) ? 'https://olx.uz' . $link : 'Havola yo‘q';

    $date_elements = $xpath->query('.//p[contains(@class, "css-996jis")]', $ad);
    $date = ($date_elements->length > 0) ? trim($date_elements[0]->textContent) : 'Sana yo‘q';

    if (strpos($date, 'Bugunda') !== false) {
        $ads_data[] = [
            'title' => $title,
            'link' => $full_link,
            'date' => date('Y-m-d H:i:s'),
        ];
    }
}

// Debug uchun OLX ma'lumotlarini yozish
file_put_contents('debug.log', "OLX ma'lumotlari:\n" . print_r($ads_data, true), FILE_APPEND);

// Telegramga yuborish funktsiyasi
function sendMessage($token, $chat_id, $message)
{
    // Ensure HTML tags are properly escaped
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    
    // Ensure UTF-8 encoding and no unclosed tags
    $message = mb_convert_encoding($message, 'UTF-8', 'auto');

    $url = "https://api.telegram.org/bot$token/sendMessage";
    $post_data = [
        'chat_id' => $chat_id,
        'text' => $message,
        'parse_mode' => 'HTML',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log the response
    file_put_contents('debug.log', "Telegramga yuborish:\n" . print_r($response, true), FILE_APPEND);
    file_put_contents('debug.log', "HTTP kodi: $http_code\n", FILE_APPEND);

    return $response;
}



// Xabarni qismlarga bo'lib yuborish funktsiyasi
function sendSplitMessage($token, $chat_id, $message, $chunk_size = 4096)
{
    $messages = str_split($message, $chunk_size);
    foreach ($messages as $msg) {
        sendMessage($token, $chat_id, $msg);
    }
}

// Telegramdan kelgan so'rovlarni olish
$update = json_decode(file_get_contents("php://input"), true);
file_put_contents('debug.log', "Kelgan so'rov:\n" . print_r($update, true), FILE_APPEND);

// Agar so'rovda message mavjud bo'lsa
if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $text = $update['message']['text'];

    // Buyruqni tekshirish
    if (strpos($text, '/today') === 0) {
        $message_text = "Bugungi e'lonlar:\n\n";
        if (!empty($ads_data)) {
            foreach ($ads_data as $ad) {
                $message_text .= "Sarlavha: <a href=\"" . htmlspecialchars($ad['link']) . "\">" . htmlspecialchars($ad['title']) . "</a>\n";
                $message_text .= "Sana: " . $ad['date'] . "\n\n";
            }
        } else {
            $message_text .= "Bugungi e'lonlar mavjud emas.";
        }

        // Xabarni qismlarga bo'lib yuborish
        sendSplitMessage($token, $chat_id, $message_text);
    }
}
