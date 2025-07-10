<?php
function get_db_connection() {
    require "/var/www/html8443/mallapi/db_info.php";
    $db_conn = mysqli_connect($db_host, $db_user, $db_pwd, $db_category, $db_port);
    if (mysqli_connect_errno()) {
        die("DB 연결 실패: " . mysqli_connect_error());
    }
    return $db_conn;
}

function log_write($order_item_code, $log_data, $file_name, $log_dir) {
    $dir = rtrim($log_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $oldUmask = umask(002);
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        umask($oldUmask);
        throw new RuntimeException("로그 디렉터리 생성 실패: {$dir}");
    }
    umask($oldUmask);

    $time = date('c');
    $sanitized = preg_replace('/[^A-Za-z0-9_\-]/', '', $order_item_code);
    $body = is_string($log_data) ? $log_data : json_encode($log_data, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        $jsonError = function_exists('json_last_error_msg') ? json_last_error_msg() : 'JSON encoding error';
        throw new RuntimeException('JSON 인코딩 실패: ' . $jsonError);
    }

    $logTxt = "\n({$sanitized} {$time})\n{$body}\n\n";
    $path = $dir . $file_name;
    if (is_file($path) && filesize($path) > 10 * 1024 * 1024) {
        rename($path, $path . '.' . date('Ymd_His'));
    }
    if (file_put_contents($path, $logTxt, FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException("로그 파일 쓰기 실패: {$path}");
    }
    return true;
}
?>
