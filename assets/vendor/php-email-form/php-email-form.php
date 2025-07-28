<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class PHP_Email_Form {
  public $to;
  public $from_name;
  public $from_email;
  public $subject;
  public $smtp;
  public $ajax = false;
  private $messages = [];

  public function add_message($content, $label, $min_length = 0) {
    if(strlen($content) >= $min_length) {
      $this->messages[] = "$label: $content";
    }
  }

  public function send() {
    if (!empty($this->smtp)) {
      // Envoi via SMTP avec PHPMailer
      require 'PHPMailer/PHPMailer.php';
      require 'PHPMailer/SMTP.php';
      require 'PHPMailer/Exception.php';

      $mail = new PHPMailer(true);
      try {
        $mail->isSMTP();
        $mail->Host = $this->smtp['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $this->smtp['username'];
        $mail->Password = $this->smtp['password'];
        $mail->SMTPSecure = $this->smtp['encryption'] ?? 'tls';
        $mail->Port = $this->smtp['port'];

        $mail->setFrom($this->from_email, $this->from_name);
        $mail->addAddress($this->to);
        $mail->Subject = $this->subject;
        $mail->Body = implode("\n", $this->messages);

        $mail->send();
        return 'Message envoyé avec succès via SMTP !';
      } catch (Exception $e) {
        return 'Erreur SMTP : ' . $mail->ErrorInfo;
      }

    } else {
      // Envoi standard PHP
      $email_text = implode("\n", $this->messages);
      $headers = "From: {$this->from_name} <{$this->from_email}>\r\n";
      $headers .= "Reply-To: {$this->from_email}\r\n";

      if(mail($this->to, $this->subject, $email_text, $headers)) {
        return 'Message envoyé avec succès !';
      } else {
        return 'Erreur lors de l’envoi du message.';
      }
    }
  }
}
?>
