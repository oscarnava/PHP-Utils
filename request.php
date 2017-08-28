<?php

/**
*   Request
*
*   Class to handle requests
*
**/

class Request {

  public  $method;
  private $reqData;
  private $reqObj;

  private function formatData($parms) {

    if (!isset($parms['action']) and isset($_GET['action'])) {
      $parms['action'] = $_GET['action'];
    }

    if (isset($parms['action']) and !isset($parms['data'])) {
      $actn = $parms['action'];
      unset($parms['action']);
      $data = json_encode($parms);
      return "{\"action\":\"{$actn}\",\"data\":{$data}}";
    } else {
      return json_encode($parms);
    }

  }

  private function urldecode($query) {
    $ret = array();
    if ($query > '') {
      foreach (explode('&', $query) as $chunk) {
        $param = explode("=", $chunk);
        if ($param) {
          $idx = urldecode($param[0]);
          $ret[$idx] = urldecode($param[1]);
        }
      }
    }
    return $ret;
  }

  function __construct($op = null) {
    switch ($this->method = $op ? $op : $_SERVER['REQUEST_METHOD']) {
      case 'PUT':
        $data = file_get_contents("php://input");
        $hdrs = getallheaders();
        if (isset($hdrs['Content-Type'])) {
          switch ($hdrs['Content-Type']) {

            case 'application/json':
              $data = json_decode($data, true);  // Usar este formato: {"action":"updateCasilla","data":{"mpio":10,"dummy":false}} ó {"action":"computosAyu","mpio":10,"dummy":false}
              $this->reqData = $data ? $this->formatData($data) : "[]";
              break;

            case 'application/x-www-form-urlencoded':
              $this->reqData = $this->formatData($this->urldecode($data));
              break;

            default:
              $this->reqData = $data;

          }
        }
        break;

      case 'GET':
        $this->reqData = $this->formatData($_GET);
        break;

      case 'POST':                                  // {"index":"0","name":"Infonavit 2013.pdf","size":"51658","type":"application\/pdf","uploadType":"html5"}
        $this->reqData = $this->formatData($_POST); // Sólo funciona con "Content-Type: application/x-www-form-urlencoded"
        break;

      default:
        $this->reqData = $op ? $op : $_SERVER['REQUEST_METHOD'];
        return;

    }
    $this->reqObj = json_decode($this->reqData);
    if (is_array($this->reqObj) and (count($this->reqObj) == 0)) {
      $this->reqObj = new StdClass();
    }
//        die("wtf! -> " . $this->method . "[" . $hdrs['Content-Type'] . "] = " . $this->reqData);
  }

  function __get($name) {
    if (isset($this->reqObj->$name)) {
      return $this->reqObj->$name;
    }
  }

  function isEmpty() {
    return (count(get_object_vars($this->asObject())) == 0);
  }

  function getRequest() {
    return $this->reqData;
  }

  function asObject() {
    return $this->reqObj;
  }

}
