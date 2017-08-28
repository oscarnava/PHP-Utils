<?php

  require_once "tryclass.php";

  class FileUploader {

    protected $path;

    function __construct($targetPath) {
      $this->path = (substr($targetPath,-1) === DIRECTORY_SEPARATOR) ? $targetPath : $targetPath . DIRECTORY_SEPARATOR;
    }

    function getPath() {
      return $this->path;
    }

    function codeToMessage($code) {
      switch ($code) {
          case UPLOAD_ERR_INI_SIZE:
              $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
              break;
          case UPLOAD_ERR_FORM_SIZE:
              $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
              break;
          case UPLOAD_ERR_PARTIAL:
              $message = "The uploaded file was only partially uploaded";
              break;
          case UPLOAD_ERR_NO_FILE:
              $message = "No file was uploaded";
              break;
          case UPLOAD_ERR_NO_TMP_DIR:
              $message = "Missing a temporary folder";
              break;
          case UPLOAD_ERR_CANT_WRITE:
              $message = "Failed to write file to disk";
              break;
          case UPLOAD_ERR_EXTENSION:
              $message = "File upload stopped by extension";
              break;

          default:
              $message = "Unknown upload error";
              break;
      }
      return $message;
    }

    function preprocessFileName($filename) {
      if (preg_match("`^[-0-9A-Z_./()]+$`i",$filename)) {
        return new Success($filename);
      } else {
        return new Failure(new Exception ('El nombre del archivo es inválido'));
      }
    }

    function moveUploadedFile() {

      $try = $this->preprocessFileName(basename($_FILES['uploadedfile']['name']));
      if ($try->isFailure()) {
        return $try;
      }

      $target = $this->path . $try->data;
      if (move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $target)) {
        return new Success($target);
      } else {
        if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
          $msg = "please try again!";
        } else {
          $msg = codeToMessage($_FILES['file']['error']);
        }
        return new Failure(new Exception ("There was an error moving the file to '{$target}', $msg"));
      }
    }

  }
