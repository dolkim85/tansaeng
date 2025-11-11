<?php
/**
 * .env 파일 로더
 * .env 파일에서 환경 변수를 로드합니다.
 */

function loadEnv($filePath = null) {
    if ($filePath === null) {
        $filePath = dirname(__DIR__) . '/.env';
    }

    if (!file_exists($filePath)) {
        return false;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // 주석 제거
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // key=value 형식 파싱
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // 따옴표 제거
            $value = trim($value, '"\'');

            // 환경 변수로 설정
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    return true;
}

/**
 * 환경 변수 가져오기
 */
function env($key, $default = null) {
    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }

    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    return $default;
}

// .env 파일 자동 로드
loadEnv();
