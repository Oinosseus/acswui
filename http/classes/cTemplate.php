<?php


// Baseclass for templates
abstract class cTemplate {

    public $Menus       = array();
    public $ContentPage = Null;

    // this must be implemented by a template class
    abstract public function getHtml();
}

?>
