<?php
$botToken = "TOKEN";
$update = json_decode(file_get_contents("php://input"), TRUE);
$chatId = $update["message"]["chat"]["id"];
$text = $update["message"]["text"];

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
    if ($callback_query) {
        $callbackQueryId = $callback_query["id"];
        $callbackQueryMessageId = $callback_query["message"]["message_id"];
        $callbackQueryUserId = $callback_query["from"]["id"];
        $callbackQueryData = $callback_query["data"];

        if ($callbackQueryData == '/set') {
            sendModeSelectionMessage($callbackQueryUserId, $botToken);
        } elseif ($callbackQueryData == 'qr_mode_photo') {
            setUserQrMode($callbackQueryUserId, 'photo', $qrModeFilePath);
            sendMessage($callbackQueryUserId, "حالت خروجی برای کاربر به 'عکس' تغییر یافت.", $botToken);
        } elseif ($callbackQueryData == 'qr_mode_sticker') {
            setUserQrMode($callbackQueryUserId, 'sticker', $qrModeFilePath);
            sendMessage($callbackQueryUserId, "حالت خروجی برای کاربر به 'استیکر' تغییر یافت.", $botToken);
        }

        exit();
    }

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
        if ($userQrMode == "sticker") {
            send_qr_code_sticker($qrImageUrl, $chatId, $botToken);
        } else {
            send_qr_code_photo($qrImageUrl, $chatId, $botToken);
        }
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

function send_qr_code_photo($qrImageUrl, $chatId, $botToken) {
    $url = "https://api.telegram.org/bot$botToken/sendPhoto";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'chat_id' => $chatId,
        'photo' => $qrImageUrl
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function create_qr_code_image_url($text) {
    $textEncoded = urlencode($text);
    return "https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=$textEncoded";
}

function send_qr_code_sticker($qrImageUrl, $chatId, $botToken) {
    $url = "https://api.telegram.org/bot$botToken/sendSticker";
    $tmpFilePath = download_image_to_temp_file($qrImageUrl);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:multipart/form-data"));
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'chat_id' => $chatId,
        'sticker' => new CURLFile($tmpFilePath)
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    unlink($tmpFilePath);
    return json_decode($response, true);
}

function download_image_to_temp_file($url) {
    $tmpFilePath = 'temp/' . uniqid() . '.png';
    file_put_contents($tmpFilePath, file_get_contents($url));
    return $tmpFilePath;
}
