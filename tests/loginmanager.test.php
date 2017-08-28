<?php

  include "../loginmanager.php";
  include "../request.php";

  session_start();
  $_SESSION['Dummy'] = isset($_SESSION['Dummy']) ? $_SESSION['Dummy'] + 1 : 0;

  $request = new Request('GET');
  if ($request->restore_session) {
    LoginManager::logout();
  }

  $login = LoginManager::Login(
    function ($user,$pass) {
      return ($user === 'oscarnt') and ($pass === 'xyz');
    }, 'LoginManager test', 3, 10
  );

//  function LoginValidator($user,$pass) {
//    return ($user === 'oscarnt') and ($pass === 'xyz');
//  }
//
//  $login = LoginManager::Login('LoginValidator', 'LoginManager test');

?>
<!DOCTYPE html>
<html>
  <head>
    <title></title>
    <meta charset="UTF-8" />
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
  </head>
  <body>
  <?php
    echo "<h3>User = {$login->user}</h3>";
    echo "<h3>Pass = {$login->password}</h3>";
    echo "<h3>Sess = " . json_encode($_SESSION) . "</h3>";
    echo "<h3>Time = " . time() . "</h3>";
    echo "<h3>Name = " . session_name() . "</h3>";
  ?>
  </body>
</html>
