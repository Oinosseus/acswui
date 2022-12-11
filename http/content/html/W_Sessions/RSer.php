<?php

declare(strict_types=1);
namespace Content\Html;

class RSer extends \core\HtmlContent {

    private $CanCreate = FALSE;
    private $CanEdit = FALSE;
    private $CanRegister = FALSE;

    private $CurrentSeries = NULL;


    public function __construct() {
        parent::__construct(_("Race Series"),  _("Race Series"));
        $this->requirePermission("ServerContent_RaceSeries_View");
    }


    public function getHtml() {
        $html = "";

        // check permissions
        $this->CanRegister = \Core\UserManager::currentUser()->permitted("ServerContent_RaceSeries_Register");
        $this->CanEdit = \Core\UserManager::currentUser()->permitted("ServerContent_RaceSeries_Edit");
        $this->CanCreate = \Core\UserManager::currentUser()->permitted("ServerContent_RaceSeries_Create");

        // get current requested series
        if (array_key_exists("RSerSeries", $_REQUEST)) {
            $this->CurrentSeries = \DbEntry\RSerSeries::fromId((int) $_REQUEST['RSerSeries']);
        }

        // process actions
        if (array_key_exists("Action", $_REQUEST)) {

            // create new series
            if ($this->CanCreate && $_REQUEST['Action'] == "CreateNewSeries") {
                $this->CurrentSeries = \DbEntry\RSerSeries::createNew();
                $this->reload(["RSerSeries"=>$this->CurrentSeries->id(),
                               "View"=>"EditSeries"]);
            }

            // save series settings
            if ($this->CanEdit && $_REQUEST['Action'] == "SaveRaceSeries") {

                // update name
                $this->CurrentSeries->setName($_POST['SeriesName']);

                // update logo
                if (array_key_exists("SeriesLogoFile", $_FILES) && strlen($_FILES['SeriesLogoFile']['name']) > 0) {
                    $this->CurrentSeries->uploadLogoFile($_FILES['SeriesLogoFile']['tmp_name'], $_FILES['SeriesLogoFile']['name']);
                }

                // parameter collection
                $this->CurrentSeries->parameterCollection()->storeHttpRequest();
                $this->CurrentSeries->save();

                // save car class parameters
                foreach ($this->CurrentSeries->listClasses() as $rser_c) {
                    $rser_c->parameterCollection()->storeHttpRequest("Class{$rser_c->id()}_");
                    $rser_c->save();
                }

                // add car classes
                $cc_id = $_POST['AddCarClass'];
                if (strlen($cc_id) > 0) {
                    $cc = \DbEntry\CarClass::fromId((INT) $cc_id);
                    \DbEntry\RSerClass::createNew($this->CurrentSeries, $cc);
                }

                // reload
                $this->reload(['RSerSeries'=>$this->CurrentSeries->id(),
                               "View"=>"EditSeries"]);
            }

            // remove car class
            if ($this->CanEdit && $_REQUEST['Action'] == "DeactivateClass") {

                // get car class
                $rser_c = \DbEntry\RSerClass::fromId((int) $_REQUEST['Class']);

                // check if class is valid
                $valid = FALSE;
                foreach ($this->CurrentSeries->listClasses() as $c) {
                    if ($c->id() == $rser_c->id()) {
                        $valid = TRUE;
                        break;
                    }
                }

                // deactivate
                if ($valid) {
                    $rser_c->deactivate();
                    $this->reload(['RSerSeries'=>$this->CurrentSeries->id(),
                                   'View'=>"EditSeries"]);
                }
            }

        }

        // determine HTML output
        if ($this->CurrentSeries == NULL) {
            $html .= $this->getHtmlOverview();
        } else if (array_key_exists("View", $_REQUEST)) {

            if ($_REQUEST["View"] == "EditSeries") {
                $html .= $this->getHtmlEditSeries();
            }
        } else {
            $html .= $this->gehtHtmlShowSeries();
        }

        return $html;
    }


    // edit settings of a race series
    private function getHtmlEditSeries() : string {
        $html = "";

        // permission check
        if (!$this->CanEdit) return "";
        if ($this->CurrentSeries === NULL) return "";

        // create new form
        $html .= $this->newHtmlForm("POST");
        $html .= "<input type=\"hidden\" name=\"RSerSeries\" value=\"{$this->CurrentSeries->id()}\">";


        // --------------------------------------------------------------------
        //  General Settings
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("General Setup") . "</h1>";
        $html .= "<table id=\"RSerSeriesEditGeneralSetup\">";
        $html .= "<caption>" . _("General Setup") . "</caption>";

        $html .= "<tr>";
        $html .= "<td rowspan=\"2\"><img src=\"{$this->CurrentSeries->logoPath()}\"></td>";

        $html .= "<th>" . _("Name") . "</th>";
        $html .= "<td><input type=\"text\" name=\"SeriesName\" value=\"{$this->CurrentSeries->name()}\" /></td>";
        $html .= "</tr>";

        $html .= "<tr>";
        $html .= "<th>" . _("Logo") . "</th>";
        $html .= "<td><input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"524288\" />";
        $html .= "<input type=\"file\" name=\"SeriesLogoFile\"></td>";
        $html .= "</tr>";

        $html .= "</table>";


        // --------------------------------------------------------------------
        //  Parameters
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Parameters") . "</h1>";
        $html .= $this->CurrentSeries->parameterCollection()->getHtml(TRUE, FALSE);


        // --------------------------------------------------------------------
        //  Classes
        // --------------------------------------------------------------------

        $html .= "<h1>" . _("Classes") . "</h2>";

        // add new
        $html .= _("Add Car Class") . ": ";
        $html .= "<select name=\"AddCarClass\">";
        $html .= "<option value=\"\"> </option>";
        foreach (\DbEntry\CarClass::listClasses() as $cc) {
            $html .= "<option value=\"{$cc->id()}\">{$cc->name()}</option>";
        }
        $html .= "</select>";

        // show classes
        foreach ($this->CurrentSeries->listClasses() as $rser_c) {
            $html .= "<h2>{$rser_c->name()}</h2>";

            $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                               'Action'=>"DeactivateClass",
                               'Class'=>$rser_c->id()]);
            $html .= "<a href=\"$url\">" . _("Remove Class") . "</a><br>";

            $html .= $rser_c->carClass()->html();
            $html .= $rser_c->parameterCollection()->getHtml(TRUE, FALSE, "Class{$rser_c->id()}_");
        }


        // --------------------------------------------------------------------
        //  Fisnish Form
        // --------------------------------------------------------------------

        $html .= "<br><br><br><br>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveRaceSeries\">";
        $html .=_("Save Race Series");
        $html .= "</button>";
        $html .= "</form>";

        return $html;
    }


    // Overview of available race series
    private function getHtmlOverview() : string {
        $html = "";

        // list available series
        foreach (\DbEntry\RSerSeries::listSeries() as $rser_s) {
            $html .= $rser_s->html();
        }

        // found new team
        if ($this->CanCreate) {
            $html .= "<br><br>";
            $url = $this->url(['Action'=>"CreateNewSeries"]);
            $html .= "<a href=\"$url\">" . _("Create new Race Series") . "</a>";
        }

        return $html;
    }


    // Show a certain race series
    private function gehtHtmlShowSeries() : string {
        $html = "";

        $html .= "<h1>{$this->CurrentSeries->name()}</h1>";

        // edit option
        if ($this->CanEdit) {
            $url = $this->url(['RSerSeries'=>$this->CurrentSeries->id(),
                               'View'=>"EditSeries"]);
            $html .= "<a href=\"$url\">" . _("Edit Race Series") . "</a>";
            $html .= "<br><br>";
        }




        return $html;
    }

}
