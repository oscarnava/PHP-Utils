<?php

require_once "tryclass.php";

define ("EMB_IFRAME","iframe");
define ("EMB_GVIEW","googleViewer");
define ("EMB_OBJECT","object");

class EmbedDoc {

  private $browser;

  function __construct(BrowserCaps $browser) {
    $this->browser = $browser;
  }

  function PDFEmbedType() {
    if (!$this->browser->isMobile()) {
      switch ($this->browser->getBrowser()) {

        case BROW_IE:
        case BROW_CHROME:
          return EMB_IFRAME;

        case BROW_FIREFOX:
          return EMB_OBJECT;

      }
    }
    return EMB_GVIEW;
  }

  function PDFEmbedHTML($id,$doc) {
    switch ($this->PDFEmbedType()) {
      case EMB_IFRAME:
        return "<iframe id=\"{$id}\" src=\"{$doc}\"></iframe>";

      case EMB_OBJECT:
        return "<object id=\"{$id}\" data=\"{$doc}\" type=\"application/pdf\">¡El documento no se puede mostrar!</object>";

      case EMB_GVIEW:
        $url = urlencode("http://www.iem.org.mx/{$doc}");
//        echo "<h1>$url</h1><h2>[[$doc]]</h2>";
        return "<iframe id=\"{$id}\" src=\"http://docs.google.com/viewer?url={$url}&embedded=true\"></iframe>";
    }
  }

  function PDFEmbedSetDocJavascriptFunction() {
    switch ($this->PDFEmbedType()) {
      case EMB_IFRAME:
        $code = "obj.src = doc;";
        break;

      case EMB_OBJECT:
        $code = "obj.data = doc;";
        break;

      case EMB_GVIEW:
        $url = "http://docs.google.com/viewer?url=" . urlencode("http://www.iem.org.mx/");
        $code = "obj.src = '{$url}' + doc + '&embedded=true';";
        break;
    }
    return 'function(obj,doc) { '.$code.' }';
  }

}

//  <object id="doc01" style="float:left; width:100%; height:100%;" data="20140915.pdf" type="application/pdf" width="600px" height="620px">
//  <param name="wmode" value="transparent" />
//  <param name="allowFullScreen" value="true"></param>
//  <embed src="myurl.pdf"
//         type="application/pdf"
//         width="600px"
//         height="62px"
//         allowscriptaccess="always"
//         allowfullscreen="true"
//         wmode="transparent"
//         />
//  </object>
