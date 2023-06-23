<?php

$botToken = "TOKEN";
$update = json_decode(file_get_contents("php://input"), TRUE);
$chatId = $update["message"]["chat"]["id"];
$text = $update["message"]["text"];
$telegram_ip_ranges = [
['lower' => '149.154.160.0', 'upper' => '149.154.175.255'], // literally 149.154.160.0/20
['lower' => '91.108.4.0',    'upper' => '91.108.7.255'],    // literally 91.108.4.0/22
];
$ip_dec = (float) sprintf("%u", ip2long($_SERVER['REMOTE_ADDR']));
$ok=false;
foreach ($telegram_ip_ranges as $telegram_ip_range) if (!$ok) {
    $lower_dec = (float) sprintf("%u", ip2long($telegram_ip_range['lower']));
    $upper_dec = (float) sprintf("%u", ip2long($telegram_ip_range['upper']));
    if ($ip_dec >= $lower_dec and $ip_dec <= $upper_dec) $ok=true;
}
if (!$ok) die("Are You Okay ?"); 

$supportedPrefixes = ['vmess://', 'vless://', 'trojan://'];

$qrModeFilePath = 'qrmode/settings.json';

$callback_query = $update["callback_query"];

if ($text == '/start') {
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🖼 تنظیمات خروجی QR', 'callback_data' => '/set']
            ]
        ]
    ];
    $encodedKeyboard = json_encode($keyboard);
    sendMessage($chatId, "
👋🏻به ربات تبدیل V2ray به QR خوش آمدید!
کافیه لینکت رو بفرستی تا برات تبدیلش کنم
 لینک های پشتیبانی شده : VMess / VLess / TRojan

⬇️برای تنظیم خروجی کلیک کن
", $botToken, $encodedKeyboard);

} else {
    $foundSupportedLink = false;

    foreach ($supportedPrefixes as $prefix) {
        if (strpos($text, $prefix) === 0) {
            $foundSupportedLink = true;
            break;
        }
    }

    if ($foundSupportedLink) {
        $qrImageUrl = create_qr_code_image_url($text);
        $userQrMode = getUserQrMode($chatId, $qrModeFilePath);
        send_qr_code_photo($qrImageUrl, $chatId, $botToken);
    } else {
        sendMessage($chatId, "لینک پشتیبانی نشده!", $botToken);
    }
}

function sendMessage($chatId, $message, $botToken, $encodedKeyboard = null) {
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
    ];

    if ($encodedKeyboard !== null) {
        $data['reply_markup'] = $encodedKeyboard;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function sendModeSelectionMessage($chatId, $botToken) {
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => 'به صورت عکس', 'callback_data' => 'qr_mode_photo'],
                ['text' => 'به صورت استیکر', 'callback_data' => 'qr_mode_sticker']
            ]
        ]
    ];
    $encodedKeyboard = json_encode($keyboard);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'chat_id' => $chatId,
        'text' => "لطفا حالت خروجی را انتخاب کنید:",
        'reply_markup' => $encodedKeyboard
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function setUserQrMode($userId, $mode, $qrModeFilePath) {
    $settings = json_decode(file_get_contents($qrModeFilePath), true);
    $settings['user_' . $userId] = $mode;
    file_put_contents($qrModeFilePath, json_encode($settings));
}

function getUserQrMode($userId, $qrModeFilePath) {
    $settings = json_decode(file_get_contents($qrModeFilePath), true);
    if (isset($settings['user_' . $userId])) {
        return $settings['user_' . $userId];
    }
    return 'photo'; // Default value
}

function create_qr_code_image_url($text) {
    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($text);
    return $url;
}

function send_qr_code_photo($qrImageUrl, $chatId, $botToken) {
    $url = "https://api.telegram.org/bot$botToken/sendPhoto?chat_id=$chatId";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'photo' => $qrImageUrl,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
