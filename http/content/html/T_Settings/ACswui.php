<?php

namespace Content\Html;

class ACswui extends \core\HtmlContent {

    private $CanEdit = FALSE;


    public function __construct() {
        parent::__construct(_("ACswui"),  "");
        $this->requirePermission("Settings_ACswui_View");
    }


    public function getHtml() {
        $html = "";

        $this->CanEdit = \Core\UserManager::loggedUser()->permitted("Settings_ACswui_Edit");
        $acswui = new \Core\ACswui();

        if (array_key_exists("Action", $_POST)) {
            if ($_POST['Action'] == "Save") {
                if ($this->CanEdit) {
                    $acswui->parameterCollection()->storeHttpRequest();
                    $acswui->save();
                } else {
                    $user_id = (\Core\UserManager::loggedUser() !== NULL) ? \Core\UserManager::loggedUser()->id() : "unknown";
                    \Core\Log::warning("Prevent from unpermitted editing from user '$user_id'!");
                }
            }
        }

        // check edit permission
        $html .= $this->newHtmlForm("POST");
        $html .= $acswui->parameterCollection()->getHtml(FALSE, !$this->CanEdit);
        if ($this->CanEdit) {
            $html .= "<br><br><button type=\"submit\" name=\"Action\" value=\"Save\">" . _("Save") . "</button>";
        }
        $html .= "</form>";


        return $html;
    }


}
