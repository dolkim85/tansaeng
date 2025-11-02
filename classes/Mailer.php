<?php
require_once __DIR__ . '/../config/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;

    public function __construct() {
        // PHPMailer 라이브러리 로드
        require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
        require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
        require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

        $this->mail = new PHPMailer(true);
        $this->configure();
    }

    private function configure() {
        try {
            // SMTP 설정
            $this->mail->isSMTP();
            $this->mail->Host = env('SMTP_HOST', 'smtp.gmail.com');
            $this->mail->SMTPAuth = true;
            $this->mail->Username = env('SMTP_USERNAME');
            $this->mail->Password = env('SMTP_PASSWORD');

            // TLS(587) 또는 SSL(465) 설정
            $smtpSecure = env('SMTP_SECURE', 'tls');
            if (strtolower($smtpSecure) === 'ssl') {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $this->mail->Port = env('SMTP_PORT', 587);
            $this->mail->CharSet = 'UTF-8';

            // 발신자 정보
            $this->mail->setFrom(
                env('SMTP_FROM_EMAIL', 'superjun1985@gmail.com'),
                env('SMTP_FROM_NAME', '탄생 스마트팜')
            );

            // 디버그 끄기 (프로덕션)
            $this->mail->SMTPDebug = 0;

        } catch (Exception $e) {
            error_log("Mailer configuration error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 이메일 전송
     *
     * @param string $to 수신자 이메일
     * @param string $subject 제목
     * @param string $body 본문
     * @param string $recipientName 수신자 이름 (선택)
     * @return bool 전송 성공 여부
     */
    public function send($to, $subject, $body, $recipientName = '') {
        try {
            // 수신자 설정
            $this->mail->addAddress($to, $recipientName);

            // 이메일 내용
            $this->mail->isHTML(false); // 텍스트 형식
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;

            // 전송
            $result = $this->mail->send();

            if ($result) {
                error_log("Email sent successfully to: $to");
            }

            return $result;

        } catch (Exception $e) {
            error_log("Email send failed to $to: " . $e->getMessage());
            error_log("Mailer Error: " . $this->mail->ErrorInfo);
            return false;
        } finally {
            // 수신자 목록 초기화 (다음 전송을 위해)
            $this->mail->clearAddresses();
        }
    }

    /**
     * 비밀번호 재설정 이메일 전송
     *
     * @param string $to 수신자 이메일
     * @param string $name 수신자 이름
     * @param string $verificationCode 인증 코드
     * @return bool 전송 성공 여부
     */
    public function sendPasswordResetEmail($to, $name, $verificationCode) {
        $subject = '[탄생] 비밀번호 재설정 인증 코드';

        $body = "안녕하세요, {$name}님.

비밀번호 재설정을 위한 인증 코드입니다.

인증 코드: {$verificationCode}

이 코드는 5분간 유효합니다.
본인이 요청하지 않았다면 이 메일을 무시하세요.

감사합니다.
탄생 스마트팜";

        return $this->send($to, $subject, $body, $name);
    }
}
?>
