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

        $html .= "<h2>" . _("General Setup") . "</h2>";
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

        $html .= "<h2>" . _("Parameters") . "</h2>";


        // --------------------------------------------------------------------
        //  Classes
        // --------------------------------------------------------------------

        $html .= "<h2>" . _("Classes") . "</h2>";


        // --------------------------------------------------------------------
        //  Fisnish Form
        // --------------------------------------------------------------------

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

