<?php

/**************************************************************************************************
 *
 *  Ejemplo de $formatMap
 *
 *     $formatMap = array(
 *       'oid' => array('hidden' => true)
 *       'fechaAlta' => 'Fecha de alta',
 *       'demarcacion' => 'Demarcación',
 *       'generales.apPaterno' => 'Apellido Paterno',
 *       'generales.apMaterno' => 'Apellido Materno',
 *       'generales.ocupacion' => 'Ocupación',
 *       'nacimiento.acta' => 'Copia de&nbsp;acta',
 *       'nacimiento.nomPadre' => 'Nombre del padre',
 *       'nacimiento.nomMadre' => 'Nombre de la madre',
 *       'credencial.clave' => 'Clave de elector',
 *       'credencial.emision' => 'Emisión',
 *       'credencial.ocr' => 'OCR',
 *       'credencial.cic' => 'CIC',
 *       'credencial.curp' => 'CURP',
 *       'credencial.domicilio.credCalle' => 'Calle',
 *       'credencial.domicilio.credNoExt' => 'No.Ext',
 *       'credencial.domicilio.credNoInt' => 'No.Int',
 *       'credencial.domicilio.credColonia' => 'Colonia',
 *       'credencial.domicilio.credCiudad' => 'Ciudad',
 *       'credencial.domicilio.credCP' => 'CP',
 *       'domicilio.noExt' => 'No.Ext',
 *       'domicilio.noInt' => 'No.Int',
 *       'domicilio.cp' => 'CP',
 *       'docs' => 'Otros documentos',
 *       'docs.rfc' => array(
 *         'label' => 'RFC',
 *         'formatter' => function($value, $obj) { return "({$value})"; },
 *         'get' => function($obj) use ($aspMgr) {
 *           $rfc = calcRFC($obj->generales->nombres, $obj->generales->apPaterno, $obj->generales->apMaterno, $obj->nacimiento->fecha);
 *           return $rfc[0];
 *         }
 *       ),
 *       'docs.noAntecedentes' => 'No antecedentes penales',
 *       'docs.residencia.originario' => '¿Es originario?',
 *       'docs.certifListaNominal' => 'Certificación de<br>inscripción a la<br>lista nominal',
 *       'docs.cartaAceptacion' => 'Carta de<br>aceptación de<br>la candidatura',
 *       'docs.escritoNoCargo' => 'Escrito de no ocupar cargo',
 *       'docs.escritoNoCargo.general' => 'Formato PROTESTA',
 *       'docs.escritoNoCargo.cumple' => '¿Cumple con los requisitos?',
 *       'docs.renuncia' => 'Renuncia al cargo actual',
 *       'docs.aprobCuenta' => 'Apobación de cuenta'
 *     );
 */

class ReportManager {

  private $source;

  function __construct($source) {
    $this->source = $source;
  }

//*******************************************************************************************************

  function calcRowSpan($obj) {
    $rowSpan = 1;
    foreach ($obj as $key => $val) {
      if (is_object($val)) {
        $rowSpan = max($this->calcRowSpan($val) + 1, $rowSpan);
      }
    }
    return $rowSpan;
  }

//*******************************************************************************************************

  function calcColSpan($obj) {
    $colSpan = 0;
    foreach ($obj as $key => $val) {
      if (is_object($val)) {
        $colSpan += $this->calcColSpan($val);
      } else {
        $colSpan += 1;
      }
    }
    return $colSpan;
  }

//*******************************************************************************************************

  function asTableHead($obj, $formatMap = array(), $buffer = array(), $row = 0, $rowSpan = null, $class = '', $parent = null) {

    while (!isset($buffer[$row])) array_push($buffer, "");

    if (!$rowSpan) {
      $rowSpan = $this->calcRowSpan($obj);
    }

    foreach ($obj as $key => $val) {

      $idx = $parent ? "{$parent}.{$key}" : $key;

      if (isset($formatMap[$idx])) {
        $fmap = $formatMap[$idx];
        if (is_array($fmap)) {
          if (isset($fmap['hidden']) and $fmap['hidden']) continue;
          $tit = $fmap['label'];
        } else {
          $tit = $fmap;
        }
      } else {
        $tit = ucfirst($key);
      }

      $cls = trim("{$class} " . str_replace('.', '-', $idx));
      if (is_object($val)) {
        $cspan = $this->calcColSpan($val);
        $buffer[$row] .= "<th class=\"{$cls}\" colspan=\"{$cspan}\">$tit</th>\n";
        $buffer  = $this->asTableHead($val, $formatMap, $buffer, $row + 1, $rowSpan, "{$class} head {$key}", $idx);
      } else {
        $rspan = $rowSpan - $row;
        $buffer[$row] .= "<th class=\"{$cls}\" rowspan=\"{$rspan}\">$tit</th>\n";
      }
    }

    return $buffer;

  }

//*******************************************************************************************************

  function asTableRow($obj, $formatMap = array(), $parent = null, $root = null) {

    $ret = "";
    if (!$root) $root = $obj;
    $defFmt = function($val, $x) { return $val; };
    foreach ($obj as $key => $val) {

      $idx = $parent ? "{$parent}.{$key}" : $key;
      $fmt = $defFmt;

      if (isset($formatMap[$idx]) and is_array($fmap = $formatMap[$idx])) {
        if (isset($fmap['hidden']) and $fmap['hidden']) continue;
        if (isset($fmap['formatter'])) {
          if (is_string($fmt = $fmap['formatter'])) {
            $fmt = function($val, $x) use ($fmap) { return sprintf($fmap['formatter'], $val); };
          }
        }
        if (isset($fmap['get'])) {
          $val = $fmap['get']($root);
        }
      }

      if (is_object($val)) {
        $ret .= $this->asTableRow($val, $formatMap, $idx, $root);
      } else {
        if (is_bool($val)) $val = $val ? 'Sí' : 'No';
        $val = str_replace(Array("\r\n","\r","\n"),Array("<br/>","<br/>","<br/>"),$val);
        if (is_array($val)) $val = '[' . implode(',',$val) . ']';
        $ret .= "<td class=\"" . str_replace('.', '-', $idx) . "\">" . $fmt($val, $obj) . "</td>";
      }
    }
    return $ret;
  }

//*******************************************************************************************************

  function asHTMLTable($id, $class = "", $formatMap = null) {

    if (($formatMap == null) and (is_array($class))) {
      $formatMap = $class;
      $class = '';
    }

    $evod = true;
    $acum = new StdClass;
    $acum->head = null;
    $acum->body = '';

    return $this->source->foldLeft($acum,
      function($acum, $obj, $ctx) use ($formatMap, &$evod) {
        if (!$acum->head) $acum->head = $ctx->asTableHead($obj, $formatMap);
        $cls = $evod ? 'odd' : 'even';
        $evod = !$evod;
        $acum->body .= "<tr class=\"$cls\">" . $ctx->asTableRow($obj, $formatMap) . "</tr>";
        return $acum;
      }, $this
    )->map(
      function($data) use ($id,$class) {
        $headStr = "";
        foreach ($data->head as $idx => $row) $headStr .= "<tr class=\"row_{$idx}\">{$row}</tr>";
        return
          "<table" . ($id ? ' id="'.$id.'"' : '') . ($class ? ' class="'.$class.'"' : '') . ">" .
            "<thead>$headStr</thead>" .
            "<tbody>" . $data->body . "</tbody>" .
          "</table>";
      }
    );
  }

  function asCSVHead($obj, $separator) {
    $acc = Array();
    foreach ($obj as $id => $val) {
      $acc[] = $id;
    }
    return implode($separator, $acc);
  }

  function asCSVRow($obj, $separator) {
    $acc = Array();
    foreach ($obj as $id => $val) {
      // if (gettype($val) == 'string')
        // $acc[] = '"'.$val.'"';
      // else
        $acc[] = $val;
    }
    return implode($separator, $acc);
  }

  function asCSV($separator = ',', $lineEnd = "\r\n") {

    $acum = new StdClass;
    $acum->head = null;
    $acum->body = Array();

    return $this->source->foldLeft($acum,
      function($acum, $obj, $ctx) use ($separator) {
        if (!$acum->head) $acum->head = $ctx->asCSVHead($obj, $separator);
        $acum->body[] = $ctx->asCSVRow($obj, $separator);
        return $acum;
      }, $this
    )->map(
      function($data) use ($lineEnd) {
        return $data->head . $lineEnd . implode($lineEnd, $data->body) . $lineEnd;
      }
    );
  }

}
