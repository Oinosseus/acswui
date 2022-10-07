<?php

namespace Content\Html;

class CarSkin extends \core\HtmlContent {

    private $CanCreateSkin = FALSE;

    public function __construct() {
        parent::__construct(_("Car Skin"),  _("Car Skin"));
        $this->requirePermission("ServerContent_Cars_View");
    }

    public function getHtml() {
        // check permissions
        if (\Core\UserManager::currentUser()->permitted("Skins_Create"))
            $this->CanCreateSkin = TRUE;


        $html  = '';

        // process requests
        if (array_key_exists("CreateNewCarSkin", $_REQUEST)) {
            if ($this->CanCreateSkin !== TRUE) {
                \Core\Log::warning("User ID '" . \Core\UserManager::currentUser()->id() . "' is not permitted to create new car skins!");
            } else {
                $car_id = $_REQUEST['CarModel'];
                $car = \DbEntry\Car::fromId($car_id);
                if ($car == NULL) {
                    \Core\Log::warning("User ID '" . \Core\UserManager::currentUser()->id() . "' prevented from creating skin for non-exisiting car ID '$car_id'!");
                } else {
                    $skin = \DbEntry\CarSkin::createNew($car, \Core\UserManager::currentUser());
                    $html .= $this->getHtmlCarSkin($skin);
                }
            }

        } else if (array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            $skin = \DbEntry\CarSkin::fromId($_REQUEST['Id']);
            $html .= $this->getHtmlCarSkin($skin);

        } else {
            \Core\Log::warning("No Id parameter given!");
        }

        return $html;
    }


    private function getHtmlCarSkin($skin) {
        $html = "";

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
        $html .= "</a></div>";

        return $html;
    }
}

?>
