<?php

namespace Content\Html;

class CarModel extends \core\HtmlContent {

    private $CurrentCar = NULL;
    private $CanCreateSkin = FALSE;
    private $CanEdit = FALSE;

    public function __construct() {
        parent::__construct(_("Car Model"),  _("Car Model"));
        $this->requirePermission("ServerContent_Cars_View");
    }

    public function getHtml() {
        // get car
        if (array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            $this->CurrentCar = \DbEntry\Car::fromId($_REQUEST['Id']);
        }

        // check permissions
        $this->CanEdit = \Core\UserManager::currentUser()->permitted("ServerContent_Cars_Edit");
        $this->CanCreateSkin = \Core\UserManager::currentUser()->permitted("Skins_Create");

        # save
        if ($this->CanEdit) {
            if (array_key_exists("Action", $_POST) && $_POST['Action'] == "Save") {
                $this->CurrentCar->setDownloadUrl($_POST['DownloadUrl']);
                $this->reload(["Id"=>$this->CurrentCar->id()]);
            }
        }

        $html  = '';

        // retrieve requests
        if ($this->CurrentCar !== NULL) {
            $car = $this->CurrentCar;

            $restrictor = 0;
            if (array_key_exists("Restrictor", $_REQUEST) && $_REQUEST['Restrictor'] != "") {
                $restrictor = (int) $_REQUEST['Restrictor'];
            }

            $brand = $car->brand();
            $html .= "<div id=\"BrandInfo\">";
            $html .= $brand->html(FALSE, FALSE, TRUE);
            $html .= "<label>" . $brand->name() . "</label>";
            $html .= "</div>";

            $html .= "<h1>" . $car->name() . "</h1>";
            $html .= $this->newHtmlForm("post");

            $html .= "<table id=\"CarModelInformation\">";
            $html .= "<caption>" . _("General Info") . "</caption>";
            $html .= "<tr><th>" . _("Brand") . "</th><td><a href=\"?HtmlContent=CarBrand&Id=" . $car->brand()->id() . "\">" . $car->brand()->name() . "</a></td></tr>";
            $html .= "<tr><th>" . _("Name") . "</th><td>" . $car->name() . "</td></tr>";
            $html .= "<tr><th>" . _("Weight") . "</th><td>" . \Core\UserManager::currentUser()->formatWeight($car->weight()) . "</td></tr>";
            $html .= "<tr><th>" . _("Torque") . "</th><td>" . (new \Core\SiPrefix($car->torque()))->humanValue("N m") . "</td></tr>";
            $html .= "<tr><th>" . _("Power") . "</th><td>" . \Core\UserManager::currentUser()->formatPower($car->power()) . "</td></tr>";
            $html .= "<tr><th>" . _("Specific Power") . "</th><td>" . \Core\UserManager::currentUser()->formatPowerSpecific(1e3 * $car->weight() / $car->power()) . "</td></tr>";
            $html .= "<tr><th>" . _("Harmonized Power") . "</th><td>" . \Core\UserManager::currentUser()->formatPowerSpecific(1e3 * $car->weight() / $car->harmonizedPower()) . "</td></tr>";
            $html .= "</table>";

            $html .= "<table id=\"CarModelRevision\">";
            $html .= "<caption>" . _("Revision Info") . "</caption>";
            $html .= "<tr><th>" . _("Database Id") . "</th><td>". $car->id() . "</td></tr>";
            $html .= "<tr><th>AC-Directory</th><td>content/cars/" . $car->model() . "</td></tr>";
            $html .= "<tr><th>" . _("Kunos Original") . "</th><td>". (($car->kunosOriginal()) ? _("yes") : ("no")) . "</td></tr>";
            $html .= "<tr><th>" . _("Deprecated") . "</th><td>". (($car->deprecated()) ? _("yes") : ("no")) . "</td></tr>";
            $html .= "<tr><th>" . _("Download") . "</th><td>";
            if ($this->CanEdit && !$car->kunosOriginal()) {
                $html .= "<input type=\"text\" name=\"DownloadUrl\" value=\"{$car->downloadUrl()}\" />";
            } else if (strlen($car->downloadUrl()) > 0) {
                $link_label = $car->downloadUrl();
                $link_label = str_replace("https://", "", $link_label);
                $link_label = str_replace("http://", "", $link_label);
                $link_label = str_replace("www.", "", $link_label);
                $link_label = substr($link_label, 0, 25);
                $html .= "<a href=\"{$car->downloadUrl()}\">{$link_label}...</a>";
            }
            $html .= "</td></tr>";
            $html .= "</table>";

            $html .= "<div id=\"CarModelTorquePowerChart\">";
            $html .= $car->htmlTorquePowerSvg($restrictor);
            $html .= "</div>";

            $html .= "<div id=\"CarModelDescription\">";
            $html .= $car->description();
            $html .= "</div>";

            if ($this->CanEdit) {
                $html .= "<button type=\"submit\" name=\"Action\" value=\"Save\">" . _("Save") . "</button>";
            }
            $html .= "</form>";

            // list skins
            $html .= "<h2>" . _("Car Skins") . "</h2>";
            $html .= "<div class=\"AvailableSkins\">";
            foreach ($car->skins() as $skin) {
                $skin_name = $skin->skin();
                $html .= $skin->html();
            }
            $html .= "</div>";


            // list owned skins
            if (\Core\UserManager::loggedUser() !== NULL) {
                $skin_list = $car->skins(TRUE, \Core\UserManager::currentUser());
                if (count($skin_list) > 0) {
                    $html .= "<h2>" . _("My Skins") . "</h2>";
                    $html .= "<div class=\"AvailableSkins\">";
                    foreach ($skin_list as $skin) {
                        $skin_name = $skin->skin();
                        $html .= $skin->html();
                    }
                    $html .= "</div>";
                }
            }


            $html .= "<h2>" . _("Car Classes") . "</h2>";
            $html .= "<ul>";
            foreach ($car->classes() as $carclass) {
                $html .= "<li>" . $carclass->htmlName() . "</li>";
            }
            $html .= "</ul>";
            if (count($car->classes()) == 0) {
                $html .= _("This car is not used in any car class");
            }


            // create new skin
            if ($this->CanCreateSkin) {
                $url = $this->url(['CreateNewCarSkin'=>TRUE, 'CarModel'=>$car->id()], "CarSkin");
                $html .= "<a href=\"$url\">" . _("Create new Skin/Livery") . "</a>";
            }



        } else {
            \Core\Log::warning("No Id parameter given!");
        }

        return $html;
    }
}
