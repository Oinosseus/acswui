<?php

namespace Content\Html;

class AvailableBrands extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Car Brands"),  _("Available Car Brands"), 'ServerContent');
        $this->requirePermission("ServerContent_Cars_View");
    }

    public function getHtml() {
        $html = "";

        foreach (\DbEntry\CarBrand::listBrands() as $brand) {
            $html .= $brand->html();
        }

        return $html;
    }
}
