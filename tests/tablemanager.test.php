<!DOCTYPE html>
<html>

  <head>
    <title></title>
    <meta charset="UTF-8" />
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
  </head>
  <body>
  <?php

    include "C:/xampp/htdocs/iemweb/user/config.php";
    include "tablemanager.php";

  /*********************************************************
  *     SAIRecord
  **********************************************************/

  class SolicitudSAI extends QueryRecord {

  } //  SAIRecord

    /*********************************************************
    *     MediosInfoManager
    **********************************************************/

    class SAITableManager extends JoomlaTableManager {

      function __construct() {
        parent::__construct('solicitud_informacion');
        $this->addJsonField('persona.fisica');
        $this->addJsonField('persona.moral');
        $this->addJsonField('contacto');
    //    $this->addFieldAlias('pregunta','infoSolicitada');
      }

    }   //  MediosInfoManager

    $table = new SAITableManager();
    $obj = $table->retrieveObject('53e2a250ce588');

    echo "<pre>";
    print_r($obj);
    echo "</pre>";

    echo "<h1>..... End .....</h1>";
  ?>
  </body>
</html>
