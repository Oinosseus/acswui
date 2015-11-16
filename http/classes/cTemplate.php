<?php


// Baseclass for templates
abstract class cTemplate {

    public $Title      = "";
    public $SubTitle   = "";
    public $Menus      = array();
    public $Content    = "";

    // this must be implemented by a template class
    abstract public function getHtml();

}

?>
