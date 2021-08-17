<?php

namespace Content\Html;

class AvailableBrands extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Car Brands"),  _("Available Car Brands"), 'ServerContent');
    }

    public function getHtml() {
        $html = "";

        foreach (\DbEntry\CarBrand::listBrands() as $brand) {
            $html .= $brand->htmlImg();
        }

        return $html;
    }
}
