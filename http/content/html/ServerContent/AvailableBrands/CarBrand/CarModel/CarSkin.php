<?php

namespace Content\Html;

class CarSkin extends \core\HtmlContent {

    private $CurrentCarSkin = NULL;
    private $CanCreateSkin = FALSE;
    private $CanEditSkin = FALSE;

    public function __construct() {
        parent::__construct(_("Car Skin"),  _("Car Skin"));
        $this->requirePermission("ServerContent_Cars_View");
    }

    public function getHtml() {
        // check permissions
        if (\Core\UserManager::currentUser()->permitted("Skins_Create"))
            $this->CanCreateSkin = TRUE;


        $html  = '';

        // get car skin from request
        if (array_key_exists("Id", $_REQUEST) && $_REQUEST['Id'] != "") {
            $this->CurrentCarSkin = \DbEntry\CarSkin::fromId($_REQUEST['Id']);
            $this->CanEditSkin = $this->CanCreateSkin && $this->CurrentCarSkin->owner() == \Core\UserManager::currentUser();
        }

        // save car skin changes
        if (array_key_exists("Action", $_POST) && $_POST['Action'] == "Save") {
            if ($this->CurrentCarSkin === NULL) {
                \Core\Log::warning("Cannot edit undefined CarSkin by user" . \Core\UserManager::currentUser()->id() . "!");
            } else if (!$this->CanEditSkin) {
                \Core\Log::warning("Not permitted editing of CarSkin {$this->CurrentCarSkin->id()} from user " . \Core\UserManager::currentUser()->id() . "!");
            } else {
                // get values from request
                $skin_name = $_POST['CarSkinName'];
                $skin_number = $_POST['CarSkinNumber'];

                // save values
                $this->CurrentCarSkin->saveName($skin_name);
                $this->CurrentCarSkin->saveNumber($skin_number);
                $html .= $this->getHtmlCarSkin();

                // upload file
                if (array_key_exists("CarSkinFile", $_FILES) && strlen($_FILES['CarSkinFile']['name']) > 0) {
                    $this->CurrentCarSkin->addUploadedFile($_FILES['CarSkinFile']['tmp_name'], $_FILES['CarSkinFile']['name']);
                }
            }

        // create new carskin
        } else if (array_key_exists("CreateNewCarSkin", $_REQUEST)) {
            if ($this->CanCreateSkin !== TRUE) {
                \Core\Log::warning("User ID '" . \Core\UserManager::currentUser()->id() . "' is not permitted to create new car skins!");
            } else {
                $car_id = $_REQUEST['CarModel'];
                $car = \DbEntry\Car::fromId($car_id);
                if ($car == NULL) {
                    \Core\Log::warning("User ID '" . \Core\UserManager::currentUser()->id() . "' prevented from creating skin for non-exisiting car ID '$car_id'!");
                } else {
                    $this->CurrentCarSkin = \DbEntry\CarSkin::createNew($car, \Core\UserManager::currentUser());
                    $this->CanEditSkin = $this->CanCreateSkin && $this->CurrentCarSkin->owner() == \Core\UserManager::currentUser();
                    $html .= $this->getHtmlCarSkin();
                }
            }

        } else if ($this->CurrentCarSkin !== NULL) {
            $html .= $this->getHtmlCarSkin();

        } else {
            \Core\Log::warning("No Id parameter given!");
        }

        return $html;
    }


    private function getHtmlCarSkin() {
        $s = $this->CurrentCarSkin;

        $html = "";

        $brand = $s->car()->brand();
        $html .= "<div id=\"BrandInfo\">";
        $html .= $brand->html();
        $html .= "<label>" . $s->car()->name() . "</label>";
        $html .= "</div>";

        $html .= "<h1>" . $s->name() . "</h1>";

        if ($this->CanEditSkin) {
            $html .= $this->newHtmlForm("POST", "", TRUE);
            $html .= "<input type=\"hidden\" name=\"Id\" value=\"{$this->CurrentCarSkin->id()}\">";
        }

        $html .= "<table id=\"CarSkinInformation\">";
        $html .= "<caption>" . _("General Info") . "</caption>";
        $html .= "<tr><th>" . _("Brand") . "</th><td><a href=\"?HtmlContent=CarBrand&Id=" . $s->car()->brand()->id() . "\">" . $s->car()->brand()->name() . "</a></td></tr>";
        $html .= "<tr><th>" . _("Car") . "</th><td><a href=\"?HtmlContent=CarModel&Id=" . $s->car()->id() . "\">" . $s->car()->name() . "</a></td></tr>";

        $owner = $s->owner();
        $owner_str = ($owner === NULL)  ? "" : $owner->html();
        $html .= "<tr><th>" . _("Owner") . "</th><td>$owner_str</td></tr>";

        // skin name
        $html .= "<tr><th>" . _("Name") . "</th><td>";
        if ($this->CanEditSkin) {
            $html .= "<input type=\"text\" name=\"CarSkinName\" value=\"{$s->name()}\">";
        } else {
            $html .= $s->name();
        }
        $html .= "</td></tr>";

        // skin number
        $html .= "<tr><th>" . _("Number") . "</th><td>";
        if ($this->CanEditSkin) {
            $html .= "<input type=\"text\" name=\"CarSkinNumber\" value=\"{$s->number()}\">";
        } else {
            $html .= $s->number();
        }
        $html .= "</td></tr>";

        $html .= "</table>";

        $html .= "<table id=\"CarSkinRevision\">";
        $html .= "<caption>" . _("Revision Info") . "</caption>";
        $html .= "<tr><th>" . _("Database Id") . "</th><td>". $s->id() . "</td></tr>";
        $path = "content/cars/" . $s->car()->model() . "/skins/" . $s->skin();
        $html .= "<tr><th>AC-Directory</th><td>$path</td></tr>";
        $html .= "<tr><th>" . _("Deprecated") . "</th><td>". (($s->deprecated()) ? _("yes") : ("no")) . "</td></tr>";
        $html .= "</table>";

        $html .= "<br id=\"CarSkinImageBreak\">";

        $html .= "<a id=\"SkinImgPreview\" href=\"" . $s->previewPath() . "\">";
        $html .= "<img src=\"" . $s->previewPath() . "\" title=\"" . $s->skin() . "\"\">";
        $html .= "</a></div>";

        // files of the skin
        if ($this->CurrentCarSkin->owner() !== NULL) {
            $html .= "<h1>" . _("Files") . "</h1>";

            // list existing files
            $html .= "<table>";
            $html .= "<tr>";
            $html .= "<th>" . _("File") . "</th>";
            $html .= "</tr>";
            foreach ($this->CurrentCarSkin->files() as $f) {
                $html .= "<tr>";
                $html .= "<td>{$f}</td>";
                $html .= "</tr>";
            }
            $html .= "</table>";

            // upload new file
            $html .= "<p>";
            $html .= _("For upload only *.dds files, 'livery.png' and 'preview.jpg' are allowed. The 'ui_skin.json' is not automatically generated.");
            $html .= "</p>";
            $html .= "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"52428800\" />";
            $html .= "<input type=\"file\" name=\"CarSkinFile\"><br>";
        }


        if ($this->CanEditSkin) {
            $html .= "<br><button type=\"submit\" name=\"Action\" value=\"Save\">" . _("Save Skin") . "</button>";
            $html .= "</form>";
        }

        return $html;
    }
}

?>
