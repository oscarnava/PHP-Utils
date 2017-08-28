<?php

require_once "tryclass.php";

define ('STREAM_EXCEPTION_CODE', 0x5605a51e6da86);

class StreamEndException extends Exception {
  function __construct($msg) {
    Exception::__construct($msg);
    $this->code = STREAM_EXCEPTION_CODE;
  }
}

class Cons {

  private $_head;
  private $_tail;

  function __construct($head, $tail = null) {
    $this->_head = $head;
    $this->_tail = $tail;
  }

  function head() {
    return $this->_head;
  }

  function tail() {
    return $this->_tail;
  }

}

class Stream {

  private $_genFn;

  function __construct($genFn) {
    $this->_genFn = $genFn;
  }

  function __get($name) {
    if ($name == 'head') {
      return $this->head();
    } else {
      return null;
    }
  }

  function head() {
    try {
      $fn = $this->_genFn;
      return new Success($fn($this));
    } catch (StreamEndException $e) {
      return new Failure($e);
    }
  }

  function tail() {
    return new Stream($this->_genFn);
  }

  function doForEach($fn, $context = null) {
    $strm = $this;
    $head = $strm->head();
    while (!$head->isFailure()) {
      $fn($head->data, $context);
      $strm = $strm->tail();
      $head = $strm->head();
    }
    return new Success(true);
  }

  function foldLeft($acum, $fn, $context = null) {
    $buffer = $acum;
    $try = $this->doForEach(
      function($obj) use (&$buffer, $fn, $context) {
        $buffer = $fn($buffer, $obj, $context);
      }
    );
    return $try->map(function($data) use ($buffer) { return $buffer; });
  }

  function map($fn = null, $context = null) {
    if ($fn === null) {
      $fn = function ($obj) { return $obj; };
    }
    return $this->foldLeft(Array(),
      function($acum,$obj) use ($fn, $context) {
        array_push($acum, $fn($obj, $context));
        return $acum;
      }
    );
  }

  function reduce($fn = null, $context = null) {
    if ($fn === null) {
      $fn = function ($buf, $obj, $ctx) { return $obj; };
    }
    $buffer = null;
    $try = $this->doForEach(
      function($obj) use (&$buffer, $fn, $context) {
        if ($buffer == null) {
          $buffer = $obj;
        } else {
          $buffer = $fn($buffer, $obj, $context);
        }
      }
    );
    return $try->map(function($data) use ($buffer) { return $buffer; });
  }

  function filter($fn = null, $context = null) {
    if ($fn === null) {
      $fn = function ($obj) { return true; };
    }
    return $this->foldLeft(Array(),
      function($acum,$obj) use ($fn, $context) {
        if ($fn($obj, $context)) {
          array_push($acum,$obj);
        }
        return $acum;
      }
    );
  }

  function toArray() {
    $ret = array();
    $val = $this->head();
    while (!$val->isFailure()) {
      $ret[] = $val->getData();
      $val = $this->head();
    }
    return $ret;
  }

}

class MapStream extends Stream {

  private $_strm;

  function __construct($strm, $fn) {
    $this->_strm = $strm;
    parent::__construct(
      function () use ($strm, $fn) {
        return $strm->head()->map($fn)->data;
      }
    );
  }

}

function arrayStreamFn(&$array) {
  $v = current($array);
  if ($v !== false) {
    next($array);
    return $v;
  } else {
    throw new StreamEndException('No more elements!');
  }
}

class ArrayStream extends Stream {

  private $_array;

  function __construct($array) {
    $this->_array = $array;
    reset($array);
    parent::__construct(
      function ($stm) use (&$array) {
        return arrayStreamFn($array);
       }
    );
  }

  function toArray() {
    return $this->_array;
  }

}

class XMLStream extends Stream {

  private $_xml;

  function __construct($xml, $asObject = false) {

    function normalizeSimpleXML($obj, &$result) {
      $data = $obj;
      if (is_object($data)) {
        $data = get_object_vars($data);
      }
      if (is_array($data)) {
        foreach ($data as $key => $value) {
          $res = null;
          normalizeSimpleXML($value, $res);
          if (($key == '@attributes') && ($key)) {
            $result = $res;
          } else {
            $result[$key] = $res;
          }
        }
      } else {
        $result = $data;
      }
    }

    function array2Obj($arr) {
      $obj = new StdClass;
      foreach ($arr as $id => $val) $obj->$id = $val;
      return $obj;
    }

    normalizeSimpleXML($xml, $obj);

    $this->_xml = $obj;
    $aux = $obj["ROWDATA"]["ROW"];
    parent::__construct(
      function ($stm) use (&$aux, $asObject) {
        $val = arrayStreamFn($aux);
        return $asObject ? array2Obj($val) : $val;
      }
    );
  }

}