<?php

require_once "tryclass.php";
require_once "streamclass.php";
require_once "logmanager.php";
require_once "reportmanager.php";

class DataSourceConfiguration {
  public $host = 'localhost';
  public $user = 'root';
  public $password = '';
  public $db = 'iemorgm_271828182845904';

  function __construct($db, $host = null, $user = null, $pass = null) {
    $this->db = $db;
    if ($host) $this->host = $host;
    if ($user) $this->user = $user;
    if ($pass) $this->pass = $pass;
  }

}

function __unmatchingField($dst, $src) {
  $psrc = get_object_vars ($src);
  foreach ($psrc as $key => $val) {
    if (!property_exists($dst, $key)) {
      return $key;
    }
  }
  return null;
}

function __props2str($obj) {
  $props = get_object_vars($obj);
  $ret   = Array();
  foreach ($props as $key => $val) {
    array_push($ret, $key);
  }
  return '[' . implode('; ', $ret) .']';
}

function __mixin ($dst, $src, $strict = false) {
  if ($strict and ($unmatch = __unmatchingField($dst, $src))) {
    return new Failure(new Exception("Field '$unmatch' don't match; " . __props2str($src) . ", " . __props2str($dst)));
  }
  $props = get_object_vars($dst);
  foreach ($props as $key => $val) {
    if (property_exists($src, $key)) {
      $dst->$key = $src->$key;
    }
  }
  return new Success($dst);
}



/**
*   TableManager
*
*   Class to handle MySQL tables. Implements methods to record JSON encoded data into a MySQL table.
*
**/

//  define ('KEY_FIELD','oid');

class TableMember {

  public function __construct(array $arguments = array()) {
    if (!empty($arguments)) {
      foreach ($arguments as $property => $argument) {
        $this->{$property} = $argument;
      }
    }
  }

  function __get($name) {
    $mn = 'get'.ucfirst($name);
    if (method_exists($this,$mn)) {
      return $this->$mn();
    } else {
      return null;
    }
  }

/*
  function calcRowSpan() {
    $rowSpan = 1;
    foreach ($this as $key => $val) {
      if (is_object($val)) {
        $rowSpan = max($val->calcRowSpan() + 1, $rowSpan);
      }
    }
    return $rowSpan;
  }

//*******************************************************************************************************

  function calcColSpan() {
    $colSpan = 0;
    foreach ($this as $key => $val) {
      if (is_object($val)) {
        $colSpan += $val->calcColSpan();
      } else {
        $colSpan += 1;
      }
    }
    return $colSpan;
  }
*/

//*******************************************************************************************************

/*
  function asTableHeadBis($formatMap = array(), $buffer = array(), $row = 0, $rowSpan = null, $class = '', $parent = null) {
    while (!isset($buffer[$row])) array_push($buffer, "");
    if (!$rowSpan) {
      $rowSpan = $this->calcRowSpan();
    }
    foreach ($this as $key => $val) {
      $idx = $parent ? "{$parent}.{$key}" : $key;

      if (isset($formatMap[$idx])) {
        $fmap = $formatMap[$idx];
        if (is_array($fmap)) {
          $tit = $fmap['label'];
        } else {
          $tit = $fmap;
        }
      } else {
        $tit = ucfirst($key);
      }

      $cls = trim("{$class} " . str_replace('.', '-', $idx));
      if (is_object($val)) {
        $cspan = $val->calcColSpan();
//      $rspan = $rowSpan - logVal("RowSpan de {$key}", $val->calcRowSpan());
        $buffer[$row] .= "<th class=\"{$cls}\" colspan=\"{$cspan}\">$tit</th>\n";
        $buffer  = $val->asTableHeadBis($formatMap, $buffer, $row + 1, $rowSpan, "{$class} head {$key}", $idx);
      } else {
        $rspan = $rowSpan - $row;
        $buffer[$row] .= "<th class=\"{$cls}\" rowspan=\"{$rspan}\">$tit</th>\n";
      }
    }
    return logVal("Buffer", $buffer);
  }
*/

//*******************************************************************************************************

/*
  function asTableRowBis($formatMap = array(), $parent = null, $root = null) {

    $ret = "";
    if (!$root) $root = $this;
    $defFmt = function($val, $obj) { return $val; };
    foreach ($this as $key => $val) {

      $idx = $parent ? "{$parent}.{$key}" : $key;
      $fmt = $defFmt;

      if (isset($formatMap[$idx]) and is_array($fmap = $formatMap[$idx])) {
        if (isset($fmap['formatter'])) {
          if (is_string($fmt = $fmap['formatter'])) {
            $fmt = function($val, $obj) use ($fmap) { return sprintf($fmap['formatter'], $val); };
          }
        }
        if (isset($fmap['get'])) {
          $val = $fmap['get']($root);
        }
      }

      if (is_object($val)) {
        $ret .= $val->asTableRowBis($formatMap, $idx, $root);
      } else {
        if (is_bool($val)) $val = $val ? 'Sí' : 'No';
        $val = str_replace(Array("\r\n","\r","\n"),Array("<br/>","<br/>","<br/>"),$val);
        if (is_array($val)) $val = '[' . implode(',',$val) . ']';
        $ret .= "<td class=\"" . str_replace('.', '-', $idx) . "\">" . $fmt($val, $this) . "</td>";
      }
    }
    return $ret;
  }
*/

}

class ObjectField extends TableMember {

//*******************************************************************************************************

  function __toString() {
    return json_encode($this);
  }

//*******************************************************************************************************

  function asTableHead() {
    $ret = "";
    foreach ($this as $key => $val) {
      $ret .= "<th class=\"head_{$key}\">$key</th>";
    }
    return $ret ? "<tr>$ret</tr>" : "";
  }

//*******************************************************************************************************

  function asTableRow() {
    $ret = "";
    foreach ($this as $key => $val) {
      if (is_object($val)) {
        $val = $val->toHTMLTable("obj_{$key}");
      } else {
        if (is_bool($val)) $val = $val ? 'S�' : 'No';
        $val = str_replace(Array("\r\n","\r","\n"),Array("<br/>","<br/>","<br/>"),$val);
      }
      if (is_array($val)) $val = '[' . implode(',',$val) . ']';
      $ret .= "<td class=\"body_{$key}\">$val</td>";
    }
    return $ret ? "<tr>$ret</tr>" : "";
  }

//*******************************************************************************************************

  function toHTMLTable($class = "") {
    return "<table".($class ? ' class="'.$class.'"' : '').">\n<thead>".$this->asTableHead()."</thead>\n<tbody>".$this->asTableRow()."</tbody></table>";
  }

}

class QueryRecord extends TableMember {
  function doBeforeRead()   { }
  function doAfterRead()    { }
  function doAfterCloning() { }
}

class QueryStream {

  private $qry;
  private $head;

  function __construct($qry) {
    $this->qry  = $qry;
    $this->head = ($record = $qry->fetch_assoc()) ? $qry->recordToObject($record) : new Failure(new Exception('No more records available'));
  }

  function head() {
    return $this->head;
  }

  function tail() {
    return $this->head->isFailure() ? null : new QueryStream($this->qry);
  }

}

class QueryManager {

  protected $db;
  protected $sql;
  protected $result;
  protected $affectedRows;
  protected $aliases     = Array();
  protected $json_fields = Array();
  protected $recordClass = 'QueryRecord';
  protected $fieldInfo   = null;

  /**
  *   __construct
  **/

  function __construct($host,$user,$password,$db,$sql) {
    $this->sql = $sql;
    try {
      $db = new mysqli($host,$user,$password,$db);
      if ($db->connect_errno) {
        throw new Exception("Connect failed: " . $db->connect_error);
      }
      $db->set_charset('utf8');
      $this->db = new Success($db);
    } catch (Exception $e) {
      $this->db = new MySQLFailure($e);
    }
  }

  function escapeString($str) {
    if ($this->db->isFailure()) {
      return $str;
    } else {
      return $this->db->getData()->real_escape_string($str);
    }
  }

  protected function getSQL() {
    return $this->sql;
  }

  protected function setRecordClass($className = null) {
    $this->recordClass = $className ? $className : 'QueryRecord';
  }

  protected function addJsonField($fieldName) {
    array_push ($this->json_fields,$fieldName);
  }

  protected function addFieldAlias($fieldName,$alias) {
    $this->aliases[$alias] = $fieldName;
  }

  /**
  *   __set
  **/

//  public function __set($name, $value) {
//    $this->buffer[$name] = $value;
//  }

  /**
  *   __get
  **/

//  public function __get($name) {
//    return $this->buffer[$name];
//  }

/**
 * Change the class of an object
 *
 * @param object $obj
 * @param string $newClass
 * @see http://www.php.net/manual/en/language.types.type-juggling.php#50791
 */

  function changeClass($obj, $newClass) {
    if(class_exists($newClass,true)) {
      $clone = unserialize(preg_replace("/^O:[0-9]+:\"[^\"]+\":/i", "O:".strlen($newClass).":\"".$newClass."\":", serialize($obj)));
      if (method_exists($clone,'doAfterCloning')) {
        $clone->doAfterCloning();
      }
      return new Success($clone);
    } else {
      return new Failure (new Exception("Class {$newClass} does not exists"));
    }
  }

  /**
  *   query
  **/

  function query($sql) {

    if ($this->db->isFailure()) {
      return $this->db;
    }

    $db = $this->db->getData();
    $this->result = $db->query($sql);
    $this->affectedRows = $db->affected_rows;

//  http://php.net/manual/en/mysqli.constants.php

    $this->fieldInfo = array();
    if (is_object($this->result)) {
      while ($fi = $this->result->fetch_field()) {
        $this->fieldInfo[$fi->name] = $fi;
      }
    }

/*
//  Debug:
    if ($this->fieldInfo['oid']->table != 'usuarios') {
      foreach ($this->fieldInfo as $key => $val) {
        $val->other = $this->getFieldInfo($key)->getDataWithDefault();
      }
      $this->fieldInfo['sql'] = $sql;
      echo json_encode($this->fieldInfo);
      die();
    }
//  Debug:
*/

    return ($this->result === false)
      ? new MySQLFailure(new Exception($db->error . "sql: '$sql'"))
      : new Success($this->affectedRows);

  }

  function fetch_assoc() {
    return is_object($this->result) ?  $this->result->fetch_assoc() : null;
  }

  function getFieldInfo($fldName) {
    return isset($this->fieldInfo[$fldName]->other)
      ? new Success($this->fieldInfo[$fldName]->other)
      : (
          (isset($this->fieldInfo[$fldName]) and ($fi = $this->fieldInfo[$fldName]) and (($tblName = $fi->orgtable) == '') or (($fldName = $fi->orgname) == ''))
          ? new Success(null)
          : $this->db->map(
              function ($db) use ($tblName, $fldName, $fi) {
                $result = $db->query($sql = "SHOW FIELDS FROM {$tblName} where Field ='{$fldName}';");
                if ($result === false) {
                  return new MySQLFailure(new Exception($db->error . "sql: '$sql'"));
                } else {
                  $ret = new StdClass;
                  if ($record = $result->fetch_assoc()) {
                    foreach ($record as $nom => $val) {
                      $ret->$nom = $val;
                    }
                    $fi->other = $ret;
                    return $ret;
                  } else {
                    return null;
                  }
                }
              }
            )->flatten()
      );
  }

  function bool2Field($value) {
    return $value ? "'!V'" : "'!F'";
  }

  // TODO: Cambiar esta función para que regrese un Try y omitir la siguiente (valueIsBool)

  function field2Bool($value) {
    return ($value == '!V') or ($value == '1');
  }

  function valueIsBool($value) {
    return ($value == '!V') or ($value == '!F') or ($value == '0') or ($value == '1');
  }

  function fieldIsBool($fldName) {
    return $this->getFieldInfo($fldName)->map(
      function ($val) {
        //logVal('fieldIsBool->val', $val);
        return ($val and (($val->Type === "enum('!F','!V')") or ($val->Type === "bit(1)")));
      }
    )->getDataWithDefault(false);
  }

  /**
  *   obj2rec
  **/

  function obj2rec($obj,$prefix = '') {

    $ret = Array();
    if ($obj) {
      $props = get_object_vars($obj);
      foreach ($props as $name => $value) {

        $key = ($prefix != '')
          ? "$prefix.$name"
          : $name;

        $fld = isset($this->aliases[$key])
          ? $this->aliases[$key]
          : $key;

        if (is_array($value)) {
          $ret[$fld] = "'" . $this->escapeString(json_encode($value)) . "'";
        } else if (is_object($value)) {
          if (in_array($key, $this->json_fields)) {
            $ret[$fld] = "'" . $this->escapeString(json_encode($value)) . "'";
          } else {
            $ret = array_merge($ret, $this->obj2rec($value,$key));
          }
        } else if (is_bool($value)) {
          $ret[$fld] = $this->bool2Field($value);
        } else if (is_null($value)) {
          $ret[$fld] = 'null';
        } else {
          $ret[$fld] = "'" . $this->escapeString($value) . "'";
        }

      }
    }
    return $ret;
  }

  /**
  *   insertProp
  **/

  function insertProp(TableMember $dst,$fld,$val) {
    $dot = strpos($fld,'.',1);
    if ($dot) {
      $par = substr($fld,0,$dot);
      $dst->$par = $this->insertProp(isset($dst->$par) ? $dst->$par : new ObjectField(), substr($fld,$dot + 1), $val);
    } else {
      $dst->$fld = $val;
    }
    return $dst;
  }

  /**
  *   validateClass
  **/

  function validateClass(QueryRecord $obj) {
    return $obj;
  }

  function recordToObject($record) {

    try {
      if (isset($record['class'])) {
        $cls = $record['class'];
        unset ($record['class']);
      } else {
        $cls = $this->recordClass;
      }
      $obj = $this->validateClass(new $cls());
      $obj->doBeforeRead();
    } catch (Exception $e) {
      return new Failure($e);
    }

    foreach ($record as $fld => $val) {
      if (gettype($val) === 'string') {
        if (strpos($val,'{"') === 0) {
          $try = $this->changeClass(json_decode($val),'ObjectField');
          if (!$try->isFailure()) {
            $val = $try->getData();
          } else {
            return new JSONFailure(new JSONException);
          }
        } else if (strpos($val,'[') === 0) {
          $val = json_decode($val);
          if ($val === null) {
            return new JSONFailure(new JSONException);
          }
        } else if ($this->valueIsBool($val) and $this->fieldIsBool($fld)) {
          $val = $this->field2Bool($val);
        } else if (isset($this->fieldInfo[$fld])) {
/*
          enum_field_types {
             MYSQL_TYPE_DECIMAL,
             MYSQL_TYPE_TINY,
             MYSQL_TYPE_SHORT,
             MYSQL_TYPE_LONG,
             MYSQL_TYPE_FLOAT,
             MYSQL_TYPE_DOUBLE,
             MYSQL_TYPE_NULL,
             MYSQL_TYPE_TIMESTAMP,
             MYSQL_TYPE_LONGLONG,
             MYSQL_TYPE_INT24,
             MYSQL_TYPE_DATE,
             MYSQL_TYPE_TIME,
             MYSQL_TYPE_DATETIME,
             MYSQL_TYPE_YEAR,
             MYSQL_TYPE_NEWDATE,
             MYSQL_TYPE_VARCHAR,
             MYSQL_TYPE_BIT,
             MYSQL_TYPE_NEWDECIMAL=246,
             MYSQL_TYPE_ENUM=247,
             MYSQL_TYPE_SET=248,
             MYSQL_TYPE_TINY_BLOB=249,
             MYSQL_TYPE_MEDIUM_BLOB=250,
             MYSQL_TYPE_LONG_BLOB=251,
             MYSQL_TYPE_BLOB=252,
             MYSQL_TYPE_VAR_STRING=253,
             MYSQL_TYPE_STRING=254,
             MYSQL_TYPE_GEOMETRY=255
          };
*/
          switch ($this->fieldInfo[$fld]->type) {
            case MYSQLI_TYPE_DECIMAL:
            case MYSQLI_TYPE_TINY:
            case MYSQLI_TYPE_SHORT:
            case MYSQLI_TYPE_LONG:
            case MYSQLI_TYPE_FLOAT:
            case MYSQLI_TYPE_DOUBLE:
              $val = $val + 0;
              break;
          }
        }
      }
      $obj = $this->insertProp($obj,$fld,$val);
    }

    $obj->doAfterRead();
    return new Success($obj);

  }

  /**
  *   retrieveObject
  **/

  function retrieveObjectFromSQL($sql) {
    $try = $this->query($sql);
    if ($try->isFailure()) {
      return $try;
    } else if ($try->getData() != 1) {
      return new MySQLFailure(new Exception("Object not found or duplicated. Query = '$sql' [".$try->getData()."]"));
    } else {
      return $this->recordToObject($this->fetch_assoc());
    }
  }

  /**
  *   doForEach
  **/

  function execDefaultSQL() {
    return $this->query($this->getSQL());
  }

  function doForEach($fn, $context = null) {
    $try = $this->execDefaultSQL();
    if ($try->isFailure()) {
      return $try;
    } else {
      while ($record = $this->fetch_assoc()) {
        $try = $this->recordToObject($record);
        if ($try->isFailure()) {
          return $try;
        }
        try {
          $fn($try->getData(), $context);
        } catch (Exception $e) {
          return new Failure($e);
        }
      }
      return new Success(true);
    }
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

  /**
  *   asHTMLTable
  **/

  function asHTMLTable($id = "", $class = "", $formatMap = null) {
    $rpt = new ReportManager($this);
    return $rpt->asHTMLTable($id, $class, $formatMap);
/*
    $head = "";
    return $this->foldLeft("",
      function($str,$obj) use (&$head) {
        if (!$head) $head = $obj->asTableHead();
        return $str . $obj->asTableRow();
      }
    )->map(
      function($body) use ($id,$class,$head) {
        return "<table" . ($id ? ' id="'.$id.'"' : '') . ($class ? ' class="'.$class.'"' : '') . "><thead>$head</thead><tbody>" . $body . "</tbody></table>";
      }
    );
*/

    /*
    $try = $this->query($this->getSQL());
    if ($try->isFailure()) {
      return $try;
    } else {
      $head = "";
      $body = "";
      while ($record = $this->fetch_assoc()) {

        $try = $this->recordToObject($record);
        if ($try->isFailure()) {
          return $try;
        }

        try {
          $obj = $try->getData();
          if (!$head) {
            $head = $obj->asTableHead();
//          echo "<pre>"; print_r($obj); echo "</pre>";
          }
          $body .= $obj->asTableRow();
        } catch (Exception $e) {
          return new Failure($e);
        }

      }
      return new Success("<table" . ($id ? ' id="'.$id.'"' : '') . ($class ? ' class="'.$class.'"' : '') . "><thead>$head</thead><tbody>$body</tbody></table>");
    }
    */
  }

//************************************************************************************************************************************************

/*
  function asHTMLTableBis($id, $class = "", $formatMap = null) {

    if (($formatMap == null) and (is_array($class))) {
      $formatMap = $class;
      $class = '';
    }

    $evod = true;
    return $this->foldLeft(array(null, ""),
      function($acum, $obj) use ($formatMap, &$evod) {
        if (!$acum[0]) $acum[0] = $obj->asTableHeadBis($formatMap);
        $cls = $evod ? 'odd' : 'even';
        $evod = !$evod;
        $acum[1] .= "<tr class=\"$cls\">" . $obj->asTableRowBis($formatMap) . "</tr>";
        return $acum;
      }
    )->map(
      function($data) use ($id,$class) {
        $headStr = "";
        foreach ($data[0] as $idx => $row) $headStr .= "<tr class=\"row_{$idx}\">{$row}</tr>";
        return
          "<table" . ($id ? ' id="'.$id.'"' : '') . ($class ? ' class="'.$class.'"' : '') . ">" .
            "<thead>$headStr</thead>" .
            "<tbody>" . $data[1] . "</tbody>" .
          "</table>";
      }
    );
  }
*/

//************************************************************************************************************************************************

  function asObjectArray() {
    return $this->map(
      function ($obj) {
        return $obj;
      }
    );
  }

  function asPackedObjectArray($fn = null) {
    if ($fn === null) {
      $fn = function ($obj) { return $obj; };
    }
    $acu = new StdClass();
    $acu->head = null;
    $acu->rows = Array();
    return $this->foldLeft($acu,
      function($acum, $obj) use ($fn) {
        $row = Array();
        if (!$acum->head) {
          $acum->head = Array();
          foreach ($fn($obj) as $key => $val) {
            array_push($acum->head, $key);
            array_push($row, $val);
          }
        } else {
          foreach ($fn($obj) as $val) {
            array_push($row, $val);
          }
        }
        array_push($acum->rows, $row);
        return $acum;
      }
    )->map(
      function ($ret) {
        return new Response(SUCCESS_RESULT, $ret, Array('encoding' => 'PackedObjectArray', 'version' => '1.0'));
      }
    );
  }

  function asStreamOld() {
    $this->execDefaultSQL();
    return new QueryStream($this);
  }

  function asStream() {
    $self = $this;
    return $this->execDefaultSQL()->map(
      function () use ($self) {
        return new Stream(
          function () use ($self) {
            if ($record = $self->fetch_assoc()) {
              $obj = logFailure($self->recordToObject($record), 'asStream failure!');
              return $obj->data;
            } else {
              throw new StreamEndException('End of result set reached');
            }
          }
        );
      }
    )->data;
  }

  /**
  *   asCSV
  **/

  function asCSV($separator = ',', $lineEnd = "\r\n") {
    $rpt = new ReportManager($this);
    return $rpt->asCSV($separator, $lineEnd);
  }

}   //  QueryManager


/**
* TableManager
*
**/

class TableManager extends QueryManager {

  private $table;
  private $keyField = 'oid';

  function __construct($host,$user,$password,$db,$tableName) {
    $this->table = $tableName;
    parent::__construct($host,$user,$password,$db,null);
  }

  protected function getSQL($cond = null) {
    return "select * from {$this->table}" . ($cond ? " where $cond" : "") . " ORDER BY '$this->keyField';";
  }

  protected function setKeyField($fieldName) {
    $this->keyField = $fieldName;
  }

  function tableName() {
    return $this->table;
  }

  /**
  *   retrieveObject
  **/

  function retrieveObject($id,$keyField = null) {
    return $this->retrieveObjectFromSQL($this->getSQL(($keyField ? $keyField : $this->keyField) . " = '{$id}'"));
  }

  /**
  *   retrieveObjectFromCondition
  **/

  function retrieveObjectFromCondition($cond) {
    return $this->retrieveObjectFromSQL($this->getSQL($cond));
  }

  /**
  *   makeUpdateQuery
  **/

  private function makeUpdateQuery($id,$values,$oper = 'REPLACE INTO') {
    return "$oper {$this->table} (`{$this->keyField}`,`".implode("`,`",array_keys($values))."`) VALUES ('{$id}',".implode(",",$values).");";
  }

  function strictFields() {
    return false;
  }

//  private function unmatchingField($dst, $src) {
//    $psrc = get_object_vars ($src);
//    foreach ($psrc as $key => $val) {
//      if (!property_exists($dst, $key)) {
//        return $key;
//      }
//    }
//    return null;
//  }
//
//  function props2str($obj) {
//    $props = get_object_vars($obj);
//    $ret   = Array();
//    foreach ($props as $key => $val) {
//      array_push($ret, $key);
//    }
//    return '[' . implode('; ', $ret) .']';
//  }
//
  function mixin ($dst, $src) {
    return __mixin($dst, $src, $this->strictFields());
//    if ($this->strictFields() and ($unmatch = $this->unmatchingField($dst, $src))) {
//      return new Failure(new Exception("Field '$unmatch' don't match; " . $this->props2str($src) . ", " . $this->props2str($dst)));
//    }
//    $props = get_object_vars($dst);
//    foreach ($props as $key => $val) {
//      if (property_exists($src, $key)) {
//        $dst->$key = $src->$key;
//      }
//    }
//    return new Success($dst);
  }

  /**
  *   storeObject
  **/

  function storeObject($id, $data, $oper = 'REPLACE INTO') {
    $try = $this->changeClass($data,$this->recordClass);
    if ($try->isFailure()) {
      return $try;
    } else {
      $obj = $try->getData();
      unset ($obj->oid);
      $this->buffer = $this->obj2rec($obj);
      $qry = logVal('Update Query', $this->makeUpdateQuery($id,$this->buffer,$oper));
      $res = $this->query($qry);
      if ($res->isFailure()) {
        return $res;
      } else {
//      From Mysql manual: "With ON DUPLICATE KEY UPDATE, the affected-rows value per row is 1 if the row is inserted as a new row and 2 if an existing row is updated."
//      if ($res->getData() !== 1) {
//        return new MySQLFailure(new Exception("Failed inserting object ({$res->getData()})"));
//      } else {
          $kf = $this->keyField;
          $obj->$kf = $id;
          return new Success($obj);
//      }
      }
    }
  }

  /**
  *   updateObject
  **/

  function updateObject($oid, $data) {
    $self = $this;
    return $this->retrieveObject($oid)->map(                                                                // Leemos el registro original
      function ($rec) use ($self, $oid, $data) {
        return $self->mixin(clone $rec, $data)->map(                                                        // Actualizamos los campos
          function ($obj) use ($self, $oid, $rec) {
            return $self->deleteObject($oid)->map(                                                          // Borramos el anterior
              function ($dummy) use ($self, $oid, $obj, $rec) {
                return $self->storeObject($oid, $obj,'INSERT INTO')->recover(                               // e insertamos el nuevo
                  function ($e) use ($self, $oid, $rec) {
                    logFailure($self->storeObject($oid, $rec,'INSERT INTO'), 'Failed to restore record!');  // y en caso de error, restauramos el original
                    return new Failure($e);
                  }
                );
              }
            );
          }
        );
      }
    )->flatten();
  }

  /**
  *   replaceObject
  **/

  function replaceObject($oid, $data) {
    return $this->retrieveObject($oid)->map(
      function ($obj, $self) use ($oid, $data) {
        return $self->mixin($obj, $data)->map(
          function ($obj, $self) use ($oid) {
            return $self->storeObject($oid, $obj,'REPLACE INTO');
          }, $self
        );
      }, $this
    )->recover(
      function ($e, $self) use ($oid, $data) {
        return $self->storeObject($oid, $data, 'REPLACE INTO');
      }, $this
    )->flatten();
  }

  /**
  *   insertObject
  **/

  function insertObject($obj) {
    return $this->storeObject(uniqid(),$obj,'INSERT INTO');
  }

  /**
  *   deleteObject
  **/

  function deleteObject($oid) {
    return $this->query("DELETE FROM {$this->tableName()} WHERE {$this->keyField} = '{$oid}'");
  }

}   //  TableManager

/**
* JoomlaQueryManager
*
**/

class JoomlaQueryManager extends QueryManager {

  function __construct($sql,$config = null) {
    if (!$config) $config = new JConfig;
    parent::__construct($config->host,$config->user,$config->password,$config->db,$sql);
  }

} //  JoomlaQueryManager

/**
* JoomlaTableManager
*
**/

class JoomlaTableManager extends TableManager {

  function __construct($tableName,$config = null) {
    if (!$config) $config = new JConfig;
    parent::__construct($config->host,$config->user,$config->password,$config->db,$tableName);
  }

} //  JoomlaTableManager
