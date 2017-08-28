<?php

date_default_timezone_set('America/Mexico_City');

class DiasFeriados {

  static private $diasFeriados  = array(array(1,1),array(1,5),array(16,9),array(25,12));
  static private $lunesFeriados = array(array(1,2),array(3,3),array(3,11));

  private $feriados;

  function __construct() {

    function lunes2dia($num,$mes) {
      $d = new DateTime("now");
      $y = $d->format('Y');
      $d = new DateTime("$y-$mes-1");
      $w = (8 - $d->format('w')) % 7 + ($num - 1) * 7;
      $d->add(new DateInterval("P{$w}D"));
      return array($d->format('j'),$mes);
    }

    $days = array();
    foreach (DiasFeriados::$diasFeriados as $d) {
      $days[$d[1] * 100 + $d[0]] = true;
    }
    foreach (DiasFeriados::$lunesFeriados as $l) {
      $d = lunes2dia($l[0],$l[1]);
      $days[$d[1] * 100 + $d[0]] = true;
    }
    $this->feriados = $days;
  }

  function esFeriado(DateTime $dt) {
    $dt = getdate($dt->getTimestamp());
    return isset($this->feriados[$dt['mon'] * 100 + $dt['mday']]);
  }

}

/*********************************************************
*     ResponseDate
**********************************************************/

class ResponseDate extends DateTime {

  static private $feriados   = null;
  static private $mesesShort = array("Ene","Feb","Mar","Abr","May","Jun","Jul","Ago","Sep","Oct","Nov","Dic");
  static private $mesesLong  = array("Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre");

  function __construct($time = "now",$timezone = null) {
    if (!$this::$feriados) {
      ResponseDate::$feriados = new DiasFeriados();
    }
    DateTime::__construct($time,$timezone);
  }

  function esFeriado() {
    if (0 + $this->format('N') > 5) { return true; }
    return ResponseDate::$feriados->esFeriado($this);
  }

  function futureDate($days) {
    $__ONEDAY  = new DateInterval('P1D');
    $dt = clone $this;
    do {
      $dt->add($__ONEDAY);
      while ($dt->esFeriado()) { $dt->add($__ONEDAY); }
      $days -= 1;
    } while ($days > 0);
    return $dt;
  }

  function getdate() {
    return getdate($this->getTimestamp());
  }

  function __toString() {
    $dt = $this->getdate();
    return ResponseDate::$mesesShort[$dt['mon']-1].' '.$dt['mday'].', '.$dt['year'];
  }

  function toString() {
    return $this->__toString();
  }

  function toStringLong() {
    $dt = $this->getdate();
    return $dt['mday'].' de '.ResponseDate::$mesesLong[$dt['mon']-1].' de '.$dt['year'];
  }

}   // ResponseDate
