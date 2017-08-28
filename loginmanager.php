<?php

  define ('LOGINMANAGER_USER_KEY','USER');
  define ('LOGINMANAGER_PASS_KEY','PASSWORD');
  define ('LOGINMANAGER_LOGOUT_KEY','LOGOUT');
  define ('LOGINMANAGER_SESSION_NAME','LOGINMANAGER_SESSION');
  define ('LOGINMANAGER_LOGGED_NAME','LOGINMANAGER_IS_LOGGED');
  define ('LOGINMANAGER_RETRIES_NAME','LOGINMANAGER_LOG_RETRIES');

  /**
  *   LoginManager
  *
  *   Class to
  *
  **/

  final class LoginManager {

    private $realm;
    private $user;
    private $pass;
    private static $validLoginFn;
    private static $retries;
    private static $ttl;
    private static $oldSession;


    private function __construct($realm) {

      $fn = self::$validLoginFn;
      $this->realm = $realm;

      $usr = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : null;
      $pwd = isset($_SERVER['PHP_AUTH_PW'  ]) ? $_SERVER['PHP_AUTH_PW'  ] : null;

      if ($usr and $pwd) {
        if (is_string($fn)) {
          $aut = function_exists($fn) ? call_user_func($fn,$usr,$pwd) : null;
        } else {
          $aut = $fn($usr,$pwd);
        }
      } else {
        $aut = null;
      }

      self::openSession();
      if (!self::getIsLoggedFlag() or !$aut) {
        if ($aut) {
          self::setIsLoggedFlag();
          self::clearTriesCount();
        } else {
          $this->doLogin();
          self::closeSession();
          die();
        }
      }
      self::closeSession();

      $this->user = $usr;
      $this->pass = $pwd;

    }

    public static function login($validationFn, $realm = '', $retries = 3, $ttl = 3600) {
      static $inst = null;
      self::$validLoginFn = $validationFn;
      self::$retries = $retries;
      self::$ttl = $ttl;
      if ($inst === null) {
        $inst = new LoginManager($realm);
      }
      return $inst;
    }

    public static function logout() {

//      function curPageURL() {
//        $pageURL = 'http';
//        if (isset($_SERVER["HTTPS"]) and ($_SERVER["HTTPS"] == "on")) {
//          $pageURL .= "s";
//        }
//        $pageURL .= "://";
//        if ($_SERVER["SERVER_PORT"] != "80") {
//          $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
//        } else {
//          $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
//        }
//        return $pageURL;
//      }

      self::openSession();
      header('HTTP/1.0 401 Unauthorized');
//      header('Refresh: 0;url=' . curPageURL());
      header('Refresh: 0');
      unset($_SERVER['PHP_AUTH_USER']);
      unset($_SERVER['PHP_AUTH_PW']);
      self::clearIsLoggedFlag();
      self::clearTriesCount();
      self::closeSession(TRUE);
      die();
    }

    private static function openSession() {
      if (isset($_SESSION)) {
        self::$oldSession = session_name();
        session_write_close();
      } else {
        self::$oldSession = null;
      }
      session_unset();
      session_name(LOGINMANAGER_SESSION_NAME);
      session_start();
    }

    private static function closeSession($destroy = FALSE) {

      if ($destroy) {
        session_unset();
        if (ini_get("session.use_cookies")) {
          $params = session_get_cookie_params();
          setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
      } else {
        session_write_close();
        session_unset();
      }

      if (self::$oldSession) {
        session_name(self::$oldSession);
        session_start();
        self::$oldSession = null;
      }

    }

    private static function getIsLoggedFlag() {
      if (isset($_SESSION[LOGINMANAGER_LOGGED_NAME]) and ($_SESSION[LOGINMANAGER_LOGGED_NAME] > time())) {
        return true;
      } else {
        if (isset($_SESSION[LOGINMANAGER_LOGGED_NAME])) {
          self::logout();
        }
        self::clearIsLoggedFlag();
        return false;
      }
    }

    private static function setIsLoggedFlag() {
      $_SESSION[LOGINMANAGER_LOGGED_NAME] = time() + self::$ttl;
    }

    private static function clearIsLoggedFlag() {
      unset($_SESSION[LOGINMANAGER_LOGGED_NAME]);
    }

    private static function getLoginTries() {
      return isset($_SESSION[LOGINMANAGER_RETRIES_NAME]) ? $_SESSION[LOGINMANAGER_RETRIES_NAME] : 0;
    }

    private static function incrementLoginTries() {
      $_SESSION[LOGINMANAGER_RETRIES_NAME] = self::getLoginTries() + 1;
    }

    private static function clearTriesCount() {
      unset($_SESSION[LOGINMANAGER_RETRIES_NAME]);
    }

    public static function validate($user,$pass) {
      return self::$validLoginFn($user,$pass);
    }

    function __get($name) {
      switch ($name) {
        case 'user':
          return $this->user;
        case 'password':
          return $this->pass;
      }
    }

    function doLogin() {
      self::clearIsLoggedFlag();
      sleep(self::getLoginTries());
      self::incrementLoginTries();
      header('WWW-Authenticate: Basic realm="' . $this->realm . '"');
      header('HTTP/1.0 401 Unauthorized');
//      print_r($this); print_r($_SESSION);
    }

  }
