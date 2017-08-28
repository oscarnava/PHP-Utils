<?php

require_once "tryclass.php";
require_once "tablemanager.php";

/*********************************************************
*     JoomlaMenuRecord
**********************************************************/

class JoomlaMenuRecord extends QueryRecord {

  public $id;
  public $title;
  public $path;
  public $parent_id;
  public $children;

  function __construct($id = null, $title = null, $path = null, $parent_id = null) {
    $this->id = $id;
    $this->title = $title;
    $this->path = $path;
    $this->parent_id = $parent_id;
    $this->children = Array();
  }

  //  index.php/home/acerca-del-iem/estructura-organica

  function asHTMLList($branchClass = null, $leafClass = null) {

    $ret = "";
    foreach ($this->children as $child) {
      $ret .= $child->asHTMLList($branchClass,$leafClass);
    }

    $link    = ROOT_PATH . "/index.php/". $this->path;
    $content = ($link ? "<a href='{$link}'>{$this->title}</a>" : $this->title);

    if ($this->children) {
      $liClass = $branchClass ? " {$branchClass}" : "";
      $open  = "<li class='branch{$liClass}'>{$content}";
    } else {
      $liClass = $leafClass   ? " {$leafClass}"   : "";
      $open  = "<li class='leaf{$liClass}'>{$content}";
    }

    return "<ul>{$open}{$ret}</li></ul>";

  }

}

/*********************************************************
*     JoomlaMenuManager
**********************************************************/

class JoomlaMenuManager extends JoomlaQueryManager {

  private $nodes;

  function __construct() {
    parent::__construct("SELECT id, title, path, parent_id FROM wiem_menu WHERE (menutype = 'mainmenu') AND (Published = 1) ORDER BY `level`, `parent_id`, `lft`");
    $this->setRecordClass('JoomlaMenuRecord');
  }

  function readNodes() {

    function lookup($id,$parent) {
      if ($parent) {
        if ($parent->id == $id) {
          return $parent;
        } else {
          foreach ($parent->children as $child) {
            $node = lookup($id,$child);
            if ($node) {
              return $node;
            }
          }
        }
      }
      return null;
    }

    $root = new JoomlaMenuRecord(1,"Inicio");
    return $this->foldLeft($root,
      function ($acum,$obj) {
        $par = lookup($obj->parent_id,$acum);
        if ($par) {
          $par->children[$obj->id] = $obj;
        } else {
          throw new Exception("Invalid node! id = {$obj->id}, title = {$obj->title}, parent = {$obj->parent_id}");
        }
        return $acum;
      }
    );

  }

  function getNodes() {
    if (!$this->nodes) {
      $this->nodes = $this->readNodes();
    }
    return $this->nodes;
  }

  function toHTMLList($id = null, $class = null, $branchClass = null, $leafClass = null) {
    return $this->getNodes()->map(
      function ($node) use ($id, $class, $branchClass, $leafClass) {
        return "<div" . ($id ? " id='{$id}'" : "") . ($class ? " class='{$class}'" : "") . ">" . $node->asHTMLList($branchClass,$leafClass) . "</div>";
      }
    );
  }

}   //  MediosInfoManager

//=========================================================
