<?php

define("DEFAULT_DEBUG", strpos($_SERVER["SERVER_NAME"],'localhost') >= 0);

set_error_handler(
  function($errno, $errstr, $errfile, $errline, array $errcontext) {
    global $logger;
    // error was suppressed with the @-operator
    if (0 === error_reporting()) {
      return false;
    }
    $errstr = utf8_encode($errstr);

    try {
      if (isset($logger)) {
        $logger->logErr($errstr);
      }
    } catch (Exception $e) {}

    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
  }
);

/**************************************************************************************
*   Response
***************************************************************************************/

define("SUCCESS_RESULT",'ok');
define("FAILURE_RESULT",'error');

class Response extends StdClass {
  function __construct($result, $data = null, $other = null) {
    $this->result = $result;
    if ($other) {
      foreach ($other as $key => $val) {
        $this->$key = $val;
      }
    }
    if ($data) $this->response = $data;
  }
}

class SuccessResponse extends Response {
  function __construct($data = null) {
    parent::__construct(SUCCESS_RESULT, $data);
  }
}

class FailureResponse extends Response {
  function __construct($msg) {
    parent::__construct(FAILURE_RESULT, null, Array("message" => $msg));
  }
}

/**************************************************************************************
*   JSONException
***************************************************************************************/

class JSONException extends Exception {
  function __construct() {
    if (phpversion() < '5.5.0')
      Exception::__construct(json_last_error());
    else
      Exception::__construct(json_last_error_msg());
  }
}

class TryClass {

  function toString() {
    return $this->__toString();
  }

  function log($msg = '') {
    global $logger;
    $logger->logTry($this, $msg);
    return $this;
  }

}

/**************************************************************************************
*   Failure
***************************************************************************************/

class Failure extends TryClass {

  private $exception;
  private $debug;

  function __construct(Exception $e, $debug = DEFAULT_DEBUG) {
    $this->exception = $e;
    $this->debug     = $debug;
  }

  public function __get($name) {
    switch ($name) {
      case "get":
      case "data":
        throw $this->exception;
      case "isFailure":
        return true;
    }
  }

  public function getOrElse($default) {
    return is_callable($default) ? $default($this->exception) : $default;
  }

  public function orElse($alternative) {
    return is_callable($alternative) ? $alternative($this->exception) : $alternative;
  }

  protected function getType() {
    return "Error";
  }

  function __toString() {
    return 'Failure of type '.$this->getType()." with message ".$this->getMessage()."<br/>\n".$this->exception->getTraceAsString();
  }

  function logFailure($msg = '') {
    return $this->log($msg);
  }

  function logSuccess($msg = '') {
    return $this;
  }

  function isFailure() {
    return true;
  }

  function getMessage($debug = null) {
    if (is_null($debug)) $debug = $this->debug;
    return "'".$this->exception->getMessage()."'".($debug ? " in file ".$this->exception->getFile()." line ".$this->exception->getLine() : "");
  }

  function getDataWithDefault($def = null) {
    if ($def === null) {
      return $this->__toString();
    }
    return is_callable($def) ? $def() : $def;
  }

  function getJsonResponse() {
    $type = $this->getType();
    $msg  = ($type ? "{$type} : " : "") . $this->getMessage();
    return json_encode(new FailureResponse($msg));
//  return json_encode(array("result" => "error", "message" => ($type ? "{$type} : ":"") . $this->getMessage()));
  }

  function doForEach($fn, $context = null) {
  }

  function map($fn) {
    return $this;
  }

  function flatMap($fn) {
    return $this;
  }

  function recover($fn, $context = null) {
    try {
      return $fn($this->exception, $context);
    } catch (Exception $e) {
      return new Failure($e);
    }
  }

  function flatten() {
    return $this;
  }

}

/**************************************************************************************
*   RequestFail
***************************************************************************************/

class RequestFailure extends Failure {
  protected function getType() {
    return "Request";
  }
}

/**************************************************************************************
*   JSONFail
***************************************************************************************/

class JSONFailure extends Failure {
  protected function getType() {
    return "JSON";
  }
}

/**************************************************************************************
*   MySQLFail
***************************************************************************************/

class MySQLFailure extends Failure {
  protected function getType() {
    return "MySQL";
  }
}

/**************************************************************************************
*   EmailFail
***************************************************************************************/

class EmailFailure extends Failure {
  protected function getType() {
    return "Email";
  }
}

/**************************************************************************************
*   Success
***************************************************************************************/

class Success extends TryClass {

  private $data;

  function __construct($data = null) {
    $this->data = $data;
  }

  function __toString() {
    return 'Success: '.$this->getJsonResponse();
  }

  function logFailure($msg = '') {
    return $this;
  }

  function logSuccess($msg = '') {
    return $this->log($msg);
  }

  public function __get($name) {
    switch ($name) {
      case "get":
      case "data":
        return $this->data;
      case "isFailure":
        return false;
    }
  }

  public function getOrElse($default) {
    return $this->data;
  }

  public function orElse($alternative) {
    return $this;
  }

  function isFailure() {
    return false;
  }

  function getData() {
    return $this->data;
  }

  function getDataWithDefault($def = null) {
    return $this->getData();
  }

  function getJsonResponse() {
    if (is_a($this->data,'Response')) {
      return json_encode($this->data);
    } else {
      return json_encode(new SuccessResponse($this->data));
    }

    //   $response = array("result" => "ok");
    //    if (property_exists($this,'data')) {
    //      $response = array_merge($response,array("response" => $this->data));
    //    }
    //    return json_encode($response);

  }

  function doForEach($fn, $context = null) {
    $fn($this->getData(), $context);
  }

  function map($fn = null, $context = null) {
    if ($fn === null) {
      $fn = function ($data) { return $data; };
    }
    try {
      return new Success(is_callable($fn) ? $fn($this->getData(), $context) : $fn);
    } catch (Exception $e) {
      return new Failure($e);
    }
  }

  function flatMap($fn) {
    try {
      return is_callable($fn) ? $fn($this->getData(), $context) : $fn;
    } catch (Exception $e) {
      return new Failure($e);
    }
  }

  function recover($fn) {
    return $this;
  }

  function flatten() {
    $data = $this->data;
    if (is_object($data) and method_exists($data, 'flatten')) {
      return $data->flatten();
    } else {
      return $this;
    }
  }

}
