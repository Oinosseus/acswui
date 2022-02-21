<?php

namespace Content\Html;

class DefaultSchedule extends \core\HtmlContent {

    private $CanEdit = False;


    public function __construct() {
        parent::__construct(_("Default Schedule"),  "");
        $this->requirePermission("Settings_DefaultSchedule_View");
    }


    public function getHtml() {
        $this->CanEdit = \Core\UserManager::loggedUser()->permitted("Settings_DefaultSchedule_Edit");
        $html = "";

        // save
        if (array_key_exists("Action", $_POST)) {
            if ($_POST['Action'] == "Save") {
                if ($this->CanEdit) {
                    \Core\SessionScheduleDefault::parameterCollection()->storeHttpRequest();
                    \Core\SessionScheduleDefault::saveParameterCollection();
                } else {
                    $user_id = (\Core\UserManager::loggedUser() !== NULL) ? \Core\UserManager::loggedUser()->id() : "unknown";
                    \Core\Log::warning("Prevent from unpermitted editing from user '$user_id'!");
                }
            }
        }

        // check edit permission
        $html .= $this->newHtmlForm("POST");
        $html .= \Core\SessionScheduleDefault::parameterCollection()->getHtml(FALSE, !$this->CanEdit);
        if ($this->CanEdit) {
            $html .= "<br><br><button type=\"submit\" name=\"Action\" value=\"Save\">" . _("Save") . "</button>";
        }
        $html .= "</form>";



        return $html;
    }




}
