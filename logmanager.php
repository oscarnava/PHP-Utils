<?php

  require_once "tryclass.php";
  define ("DEFAULT_LOGS_DIR","logs");

  date_default_timezone_set("America/Mexico_City");

//  ~~~~~~~~~~~~~~~~~~~~~~~
//      Class Logger
//  ~~~~~~~~~~~~~~~~~~~~~~~

  class LogManager {

    private $fileName;
    private $handle;
    private $dateFmt = DATE_RSS;
    private $marker  = "log";

    function __construct($fileName) {
      if (file_exists(DEFAULT_LOGS_DIR)) {
        $prefix = DEFAULT_LOGS_DIR . "/";
      } else {
        $prefix = "";
      }
      $this->fileName = $prefix.$fileName.".log";
      $this->handle = fopen($this->fileName,"a");
    }

    function __destruct() {
      fclose($this->handle);
    }

    function generateCallTrace($ofs = 0) {
      $e = new Exception();
      $trace = explode("\n", $e->getTraceAsString());
//    $trace = array_reverse($trace);                                               // reverse array to make steps line up chronologically
      array_shift($trace); // remove {main}
      array_pop($trace); // remove call to this method
      $length = count($trace);
      $result = array();
      for ($i = $ofs; $i < $length; $i++) {
        $result[] = ($i - $ofs + 1)  . ')' . substr($trace[$i], strpos($trace[$i], ' ')); // replace '#someNum' with '$i)', set the right ordering
      }
      return "\t" . implode("\n\t", $result);
    }

    private function log($prompt, $str) {
      $data = Array(
        $this->marker,
        $_SERVER['REMOTE_ADDR'],
        date($this->dateFmt),
        utf8_encode($prompt . ": " . $str)
      );
      $this->writeln(implode("\t",$data));
    }

    function writeln($str = '') {
      fwrite($this->handle,$str."\n");
    }

    function setMarker($mrk) {
      $this->marker = $mrk;
    }

    function logSection($hdr,$mrk = "log") {
      $this->writeln("\n:::::::::: ".$hdr." ::::::::::");
      $this->setMarker($mrk);
    }

    function logMsg($msg) {
      $this->log(">",$msg);
    }

    function logErr($msg) {
      $this->log("Error",$msg);
    }

    function logAlert($msg) {
      $this->log("!",$msg);
    }

    function logInfo($msg) {
      $this->log("i",$msg);
    }

    function logTry($try, $comment = '') {
      if (DEFAULT_DEBUG or $try->isFailure()) {
        if ($comment != '') {
          $this->logSection($comment, 'Try');
        }
        $this->log("Try", $try->toString());
        if ($try->isFailure()) {
          $this->writeln("\n" . $this->generateCallTrace(1) . "\n");
        }
      }
      return $try;
    }

    function logFailure($try, $comment = '') {
      if ($try->isFailure()) {
        $this->logTry($try);
      }
      return $try;
    }

    function logSuccess($try, $comment = '') {
      if (!$try->isFailure()) {
        $this->logTry($try);
      }
      return $try;
    }

    function logVal($name, $val) {
      if (is_null($val))
        $sval = 'null';
      if (gettype($val) == "string")
        $sval = "'".$val."'";
      else if (gettype($val) == "boolean")
        $sval = $val ? 'true' : 'false';
      else if (is_array($val) or is_object($val))
        $sval = json_encode($val);
      else
        $sval = $val;
      $this->log("v",$name." = ".$sval."\t( ".gettype($val)." )");
      return $val;
    }

  }

if (!isset($logger)) {

  global $logger;
  $logger = new LogManager("log_" . date("Ymd"));

  function logTry($try, $comment = '') {
    global $logger;
    return $logger->logTry($try, $comment);
  }

  function logFailure($try, $comment = '') {
    global $logger;
    return $logger->logFailure($try, $comment);
  }

  function logVal($name, $val) {
    global $logger;
    return $logger->logVal($name, $val);
  }

}
