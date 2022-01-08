<?php

namespace Content\Html;

class CarSkin extends \core\HtmlContent {

    private $CurrentBrand = NULL;
    private $CurrentCar = NULL;

    public function __construct() {
        parent::__construct(_("Car Skin"),  _("Car Skin"));
        $this->requirePermission("ServerContent_Cars_View");
    }

    public function getHtml() {
        $html  = '';

        // retrieve requests
        if (array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            $skin = \DbEntry\CarSkin::fromId($_REQUEST['Id']);

            $brand = $skin->car()->brand();
            $html .= "<div id=\"BrandInfo\">";
            $html .= $brand->html();
            $html .= "<label>" . $skin->car()->name() . "</label>";
            $html .= "</div>";

            $html .= "<h1>" . $skin->name() . "</h1>";

            $html .= "<table id=\"CarSkinInformation\">";
            $html .= "<caption>" . _("General Info") . "</caption>";
            $html .= "<tr><th>" . _("Brand") . "</th><td><a href=\"?HtmlContent=CarBrand&Id=" . $skin->car()->brand()->id() . "\">" . $skin->car()->brand()->name() . "</a></td></tr>";
            $html .= "<tr><th>" . _("Car") . "</th><td><a href=\"?HtmlContent=CarModel&Id=" . $skin->car()->id() . "\">" . $skin->car()->name() . "</a></td></tr>";
            $html .= "<tr><th>" . _("Name") . "</th><td>" . $skin->name() . "</td></tr>";
            $html .= "<tr><th>" . _("Number") . "</th><td>" . $skin->number() . "</td></tr>";
            $html .= "<tr><th>" . _("Team") . "</th><td>" . $skin->team() . "</td></tr>";
            $html .= "</table>";

            $html .= "<table id=\"CarSkinRevision\">";
            $html .= "<caption>" . _("Revision Info") . "</caption>";
            $html .= "<tr><th>" . _("Database Id") . "</th><td>". $skin->id() . "</td></tr>";
            $path = "content/cars/" . $skin->car()->model() . "/skins/" . $skin->skin();
            $html .= "<tr><th>AC-Directory</th><td>$path</td></tr>";
            $html .= "<tr><th>" . _("Deprecated") . "</th><td>". (($skin->deprecated()) ? _("yes") : ("no")) . "</td></tr>";
            $html .= "</table>";

            $html .= "<br id=\"CarSkinImageBreak\">";

            $html .= "<a id=\"SkinImgPreview\" href=\"" . $skin->previewPath() . "\">";
            $html .= "<img src=\"" . $skin->previewPath() . "\" title=\"" . $skin->skin() . "\"\">";
            $html .= "</div>";


        } else {
            \Core\Log::warning("No Id parameter given!");
        }

        return $html;
    }
}

?>
