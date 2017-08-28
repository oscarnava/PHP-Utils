<?php

require_once "templatemanager.php";
require_once "actionmanager.php";

/**
*   EmailSend
*
**/

class EmailSend extends TemplateManager {

  private $from;
  private $charset = "utf-8";   //  ISO-8859-1

  function __construct($from,$templateName) {
    $this->from     = strip_tags($from);
    TemplateManager::__construct($templateName);
  }

  function templateType() {
    return 'email';
  }

  function getHeaders($opts) {
    $headers  = "";
    $headers .= "From: {$this->from}\r\n";
    $headers .= "Reply-To: {$this->from}\r\n";
    if (isset($opts['cc'])) $headers .= "CC: ".strip_tags($opts['cc']."\r\n");
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=\"{$this->charset}\"\r\n";
    return $headers;
  }

  function send($to, $subject, $opts = null) {
    $message = $this->parse(
      isset($opts['data']) ? $opts['data'] : new stdClass()
    );
    if (mail($to, strip_tags($subject), $message, $this->getHeaders($opts))) {
      $now = new DateTime();
      return new Success(array('from' => $this->from, 'to' => $to, 'headers' => $this->getHeaders($opts), 'subject' => $subject, 'when' => $now->format('r')));
    } else {
      return new EmailFailure(new Exception("Failed sending email from {$this->from} to {$to}"));
    }
  }

}
