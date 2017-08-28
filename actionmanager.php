<?php

require_once "tryclass.php";
require_once "request.php";
require_once "fileuploader.php";

class ActionFilesUploader extends FileUploader {

  protected $actionData;
  protected $copyResult;

  function setActionData($data) {
    $this->actionData = $data;
  }

  function getCopyResult() {
    return $this->copyResult;
  }

  function recordCopyResults($result) {
    return new Success($result);
  }

  function saveFile($name, $temp) {
    $self = $this;
    return $this->preprocessFileName(basename($name))->map(
      function ($name) use ($temp, $self) {
        $target = $self->getPath() . $name;
        if (@move_uploaded_file($temp, $target)) {
          return $name;
        } else {
          throw new Exception($self->codeToMessage(UPLOAD_ERR_CANT_WRITE));
        }
      }
    );
  }

  function moveUploadedFiles($descr) {

    $ret = new StdClass;
    $ret->success = array();
    $ret->failure = array();

    for ($i = 0; $i < count($descr['name']); $i++) {

      $name = $descr['name'][$i];
      $temp = $descr['tmp_name'][$i];
      $err  = $descr['error'][$i];

      $res  = ($err === UPLOAD_ERR_OK) ? $this->saveFile($name, $temp) : new Failure(new Exception(codeToMessage($err)));

      $tmp = new StdClass;
      $tmp->fileName = $name;
      if ($res->isFailure()) {
        $tmp->message = $res->getMessage(false);
        $tmp->saveName = $name;
        array_push($ret->failure, $tmp);
      } else {
        $tmp->savedName = $res->getData();
        array_push($ret->success, $tmp);
      }

    }

    $this->copyResult = $ret;
    return $this->recordCopyResults($ret);

  }


//{"result":"ok","response":{"index":"0","name":"Infonavit 2010.pdf","size":"50928","type":"application\/pdf","uploadType":"html5","files":{"uploadedfiles":{"name":["Infonavit 2010.pdf","Infonavit 2011.pdf","Infonavit 2012.pdf","Infonavit 2013.pdf","Infonavit 2014.pdf"],"type":["application\/pdf","application\/pdf","application\/pdf","application\/pdf","application\/pdf"],"tmp_name":["C:\\xampp\\tmp\\php3939.tmp","C:\\xampp\\tmp\\php393A.tmp","C:\\xampp\\tmp\\php393B.tmp","C:\\xampp\\tmp\\php393C.tmp","C:\\xampp\\tmp\\php394C.tmp"],"error":[0,0,0,0,0],"size":[50928,50929,50934,51658,51655]}},"get":{"action":"uploadFile","oid":"55280bef805e2"}}}
/*
  "files":{
    "uploadedfiles":{
      "name":["Infonavit 2010.pdf","Infonavit 2011.pdf","Infonavit 2012.pdf","Infonavit 2013.pdf","Infonavit 2014.pdf"],
      "type":["application\/pdf","application\/pdf","application\/pdf","application\/pdf","application\/pdf"],
      "tmp_name":["C:\\xampp\\tmp\\php3939.tmp","C:\\xampp\\tmp\\php393A.tmp","C:\\xampp\\tmp\\php393B.tmp","C:\\xampp\\tmp\\php393C.tmp","C:\\xampp\\tmp\\php394C.tmp"],
      "error":[0,0,0,0,0],"size":[50928,50929,50934,51658,51655]
    }
  },"get":{"action":"uploadFile","oid":"55280bef805e2"}}}
*/

}

/**
*   ActionManager
*
*   Class to
*
**/

class ActionManager {

  private $request;
  private $uploader;

  private function mixin ($dst, $src) {
    $props = get_object_vars ($dst);
    foreach ($props as $key => $val) {
      if (isset($src->$key)) {
        $dst->$key = $src->$key;
      }
    }
    return $dst;
  }

  function __construct($request = null) {
    $this->request = $request ? $request : new Request($this->defaultRequestMethod());
  }

  function grantAccess($method, $data) {
    return new Success($data);
  }

  function defaultRequestMode() {
    return $_SERVER['REQUEST_METHOD'];
  }

  function defaultRequestMethod() {
    return $this->defaultRequestMode();
  }

  function getRequest() {
    return $this->request;
  }

  function setUploader($uldr) {
    $this->uploader = $uldr;
  }

  function doAction($action, $data) {
    try {
      $method = 'do'.ucfirst($action);
      return $this->grantAccess($method, $data)->map(
        function($data, $ctx) use ($method, $action) {
          if (method_exists($ctx,$method)) {
            return call_user_func_array(array($ctx, $method),array($data));
          } else {
            throw new Exception("Invalid action `{$action}`");
          }
        }, $this
      )->flatten();
    } catch (Exception $e) {
      return new RequestFailure($e);
    }
  }

  protected function __doUploadFile($data) {
    if ($this->uploader) {
//      print_r($data);
      return $this->uploader->moveUploadedFiles($data->files['uploadedfiles']);
    } else {
      return new Failure(new Exception('No uploader available'));
    }
    return new Success($data);
  }

  function compress($contents) {

    $HTTP_ACCEPT_ENCODING = $_SERVER["HTTP_ACCEPT_ENCODING"];

    if (headers_sent()) {
      return $contents;
    } else if (strpos($HTTP_ACCEPT_ENCODING, 'x-gzip') !== false) {
      $encoding = 'x-gzip';
    } else if (strpos($HTTP_ACCEPT_ENCODING,'gzip') !== false){
      $encoding = 'gzip';
    } else {
      return $contents;
    }

    if (strlen($contents) >= 2048) {    // no need to waste resources in compressing very little data
      ini_set ('zlib.output_compression', 'Off');
      $contents = gzencode($contents,9);
      header ("Content-Encoding: $encoding");
      header ("Content-Length: " . strlen ($contents));
//    header('Cache-Control: no-cache, no-store, max-age=0, must-revalidate');
//    header('Pragma: no-cache');
    }

    return $contents;

  }

  function preprocess($json) {
    // Fix if this request is of a file upload type; includes any variable sent by the GET method
    if ($this->uploader and !isset($json->action) and ($this->request->method == 'POST') and isset($_GET['action']) and ($_GET['action'] == 'uploadFile')) {

      $json->files = $_FILES;
      $json->get   = $_GET;

      $ret = new StdClass;
      $ret->action = $_GET['action'];    // Force if action is an UploadFile
      $ret->data   = $json;
      $this->uploader->setActionData($json);

      return $ret;
    }
    return $json;
  }

  function getActionResult() {
    $json = $this->request->asObject();
    if ($json !== null) {
      $json = $this->preprocess($json);
      $ret = property_exists($json,'action') ? ($this->doAction($json->action, property_exists($json,'data') ? $json->data : new StdClass)) : new RequestFailure(new Exception("No action provided for request!"));
    } else {
      $ret = json_last_error() != JSON_ERROR_NONE ? new JSONFailure(new JSONException) : new Failure(new Exception("Request could not be processed; " . $this->request->getRequest()));
    }
    return $ret;
  }

  function execute() {
    return $this->getActionResult()->getJsonResponse();
  }

  function respondCompressed() {
    print $this->compress($this->execute());
  }

}
