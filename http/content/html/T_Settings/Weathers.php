<?php

namespace Content\Html;

class Weathers extends \core\HtmlContent {

    private $CurrentWeather = NULL;
    private $CanEdit = False;


    public function __construct() {
        parent::__construct(_("Weathers"),  "");
        $this->requirePermission("Settings_Weather_View");
    }


    public function getHtml() {
        $this->CanEdit = \Core\UserManager::loggedUser()->permitted("Settings_Weather_Edit");
        $html = "";

        // get requested weather
        if (array_key_exists('Weather', $_GET)) {
            $weather = \DbEntry\Weather::fromId($_GET['Weather']);
            if ($weather !== NULL) {
                $this->CurrentWeather = $weather;
            }
        }

        // process actions
        if (array_key_exists('Action', $_POST)) {
            $current_user = \Core\UserManager::loggedUser();

            if ($_POST['Action'] == "SaveWeather" && $this->CurrentWeather !== NULL) {

                // prevent editing root weather
                if ($this->CanEdit) {
                    $this->CurrentWeather->parameterCollection()->storeHttpRequest();
                    $this->CurrentWeather->save();
                    $this->reload(["Weather"=>$this->CurrentWeather->id()]);
                } else {
                    \Core\Log::warning("Prevent editing weather '" . $this->CurrentWeather->id() . "' from user '" . $current_user->id() . "'");
                }

            } else if ($_POST['Action'] == "SaveManagementTable") {

                // delete server weathers
                function check_delete($weathers) {
                    foreach ($weathers as $p) {
                        $post_key = "DeleteWeather" . $p->id();
                        if (array_key_exists($post_key, $_POST)) {
                            $p->delete();
                        }
                        check_delete($p->children());
                    }
                }
                check_delete(\DbEntry\Weather::fromId(0)->children());

                // it can happen that the current weather has been deleted
                $this->CurrentWeather = NULL;

                $this->reload();
            }


        // create new derived weather
        } else if (array_key_exists('DeriveWeather', $_GET)) {
            $parent_weather = \DbEntry\Weather::fromId($_GET['DeriveWeather']);
            if ($parent_weather !== NULL) {
                $current_user = \Core\UserManager::loggedUser();
                if ($this->CanEdit) {
                    $this->CurrentWeather = NULL;
                    $dw = \DbEntry\Weather::derive($parent_weather);
                    $this->reload(["Weather"=>$dw->id()]);
                } else {
                    \Core\Log::warning("Prevent from creating a new weather, derived from '$parent_weather' for user '$current_user'.");
                }
            }
        }

        $html .= $this->managementTable();



        if ($this->CurrentWeather !== NULL)
            $html .= $this->viewWeather($this->CurrentWeather);




        return $html;
    }



    private function viewWeather($weather) {
        $html = "<h1>" . $weather->name() . "</h1>";
        $html .= $this->newHtmlForm("POST");
        $pc = $this->CurrentWeather->parameterCollection();

        if ($this->CurrentWeather->parent() === NULL || !$this->CanEdit) {
            $html .= $pc->getHtml(FALSE, TRUE);
        } else {
            $html .= $pc->getHtml(count($weather->children()) == 0);
        }

        $html .= "<br><br>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveWeather\">" . _("Save Weather") . "</button>";
        $html .= "</form>";

//         $html .= "<br><br><br>";
//         $html .= $pc->getHtmlTree();

        return $html;
    }


    private function managementTable() {
        $html = "";

        $html .= "<h1>" . _("Weather Management") . "</h1>";

        $html .= $this->newHtmlForm("POST", "WeatherManagementForm");
        $html .= "<table id=\"WeatherManagementTable\">";
        $html .= "<tr>";
        $html .= "<th>"  . _("Server Weather") . "</th>";
        $html .= "<th>"  . _("Graphic") . "</th>";
        $html .= "<th colspan=\"3\">"  . _("Manage") . "</th>";
        $html .= "</tr>";

        // root weather
        $root_weather = \DbEntry\Weather::fromId(0);
        $class_current_weather = ($this->CurrentWeather && $this->CurrentWeather->id() == $root_weather->id()) ? "class=\"CurrentWeather\"" : "";
        $html .= "<tr $class_current_weather>";
        $html .= "<td><a href=\"" . $this->url(['Weather'=>$root_weather->id()]) . "\">" . $root_weather->name() . "</a></td>";
        $html .= "<td></td>";
        $html .= "<td></td>";
        $html .= "<td>";
        if ($this->CanEdit) {
            $html .= "<a href=\"" . $this->url(['DeriveWeather'=>$root_weather->id()]) . "\" title=\"" . _("Create new derived Weather") . "\">&#x21e9;</a>";
        }
        $html .= "</td>";
        $html .= "</tr>";

        // all other weathers
        $html .= $this->mangementTableRow(\DbEntry\Weather::fromId(0));

        $html .= "</table>";
        $html .= "<br><br>";
        $html .= "<button type=\"submit\" name=\"Action\" value=\"SaveManagementTable\" form=\"WeatherManagementForm\">" . _("Save Weather Management") . "</button>";
        $html .= "</form>";

        return $html;
    }


    private function mangementTableRow(\DbEntry\Weather $parent, string $indent="") {
        $html = "";

        for ($child_index=0; $child_index < count($parent->children()); ++$child_index) {
            $child = $parent->children()[$child_index];
            $child_id = $child->id();

            if ($child_index == (count($parent->children()) -  1)) {
                if (count($child->children())) $prechars = "&#x2517;&#x2578;";
                else $prechars = "&#x2516;&#x2574;";
            } else {
                if (count($child->children())) $prechars = "&#x2523;&#x2578;";
                else $prechars = "&#x2520;&#x2574;";
            }

            $class_current_weather = ($this->CurrentWeather && $this->CurrentWeather->id() == $child->id()) ? "class=\"CurrentWeather\"" : "";

            $href = $this->url(['Weather'=>$child->id()]);
            $html .= "<tr $class_current_weather>";
            $html .= "<td>" . $indent . $prechars . "<a href=\"$href\">" . $child->name() . "</a></td>";

            $html .= "<td>";
            $html .= "<a href=\"$href\">" . $child->parameterCollection()->child("Graphic")->valueLabel() . "</a>";
//             $html .= "<img src=\"$img_src\">";
            $html .= "</td>";

            $html .= "<td>" . $this->newHtmlTableRowDeleteCheckbox("DeleteWeather" . $child->id()) . "</td>";
            $html .= "<td>";
            if ($this->CanEdit) $html .= "<a href=\"" . $this->url(['DeriveWeather'=>$child->id()]) . "\" title=\"" . _("Create new derived Weather") . "\">&#x21e9;</a>";
            $html .= "</td>";
            $html .= "</tr>";

            $next_indent = $indent;
            if ($child_index == (count($parent->children()) -  1))
                $next_indent .= "&nbsp;&nbsp;";
            else
                $next_indent .= "&#x2503;&nbsp;";

            $html .= $this->mangementTableRow($child, $next_indent);
        }

        return $html;
    }


}
