<?php

/**
*   TemplateManager
*
**/

define ('MISSING_VAR','<span style="color:red; font-weight:bold;">&lt;Undefined var: {name}&gt;</span>');

class TemplateManager {

  private $template;
  private $cleanUp = true;
  static private $mesesShort = array("Ene","Feb","Mar","Abr","May","Jun","Jul","Ago","Sep","Oct","Nov","Dic");
  static private $mesesLong  = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");


  function __construct($templateName) {
    $this->template = $templateName;
  }

  public static function __formatDate($date, $long = true) {
    $d  = is_string($date) ? new DateTime($date) : $date;
    $dt = getdate($d->getTimestamp());
    $me = $long ? TemplateManager::$mesesLong : TemplateManager::$mesesShort;
    return $me[$dt['mon']-1].' '.$dt['mday'].' de '.$dt['year'];
  }

  function formatDate($date, $long = true) {
    return TemplateManager::__formatDate($date, $long);
  }

  function templateType() {
    return null;
  }

  private function cleanupValue($value) {
    if (!$this->cleanUp) return $value;
    return str_replace("\n","<br/>",$value);
  }

  function fetchValue($obj,$name,$orgName) {

    $dot = strpos($name,'.',1);
    if ($dot) {
      $par = substr($name,0,$dot);
      return property_exists($obj,$par) ? $this->fetchValue($obj->$par,substr($name,$dot + 1),$orgName) : str_replace('{name}',$orgName,MISSING_VAR);
    }

    if (property_exists($obj,$name)) {
      return $this->cleanupValue($obj->$name);
    } else if (method_exists($obj,$n = 'get' . ucfirst($name))) {
      return $this->cleanupValue(call_user_func_array(array($obj,$n),array()));
    } else if (method_exists($obj,'__get')) {
      $value = $obj->__get($name);
      if ($value) return $this->cleanupValue($value);
    }

    return str_replace('{name}',$orgName,MISSING_VAR);

  }

  function parse($data = null, $cleanUp = true) {

    $this->cleanUp = $cleanUp;

    ob_start();
    include ($this->template);
    $out = ob_get_contents();
    ob_end_clean();

    preg_match_all('/\{([A-Za-z0-9._]+)\}/i',$out,$vars);
//  echo "<pre>"; print_r($vars); echo "</pre>";

    foreach ($vars[1] as $varName) {
      $out = str_replace('{'.$varName.'}', $this->fetchValue($data,$varName,$varName), $out);
    }

    return $out;

  }

}

class WebPageTemplate extends TemplateManager {

  function templateType() {
    return 'webpage';
  }

}
