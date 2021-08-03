<?php

namespace Content\Html;

class AvailableBrands extends \core\HtmlContent {

    private $CurrentBrand = NULL;
    private $CurrentCar = NULL;

    public function __construct() {
        parent::__construct(_("Car Brands"),  _("Available Car Brands"), 'ServerContent');
    }

    public function getHtml() {
        $html = "";

        foreach (\DbEntry\CarBrand::listBrands() as $brand) {
            $brand_id = $brand->id();
            $brand_name = $brand->name();
            $html .= $brand->htmlImg();
        }

        return $html;
    }
}

?>
