<?php

namespace Content\Html;

class UserSettings extends \core\HtmlContent {

    public function __construct() {
        parent::__construct(_("Settings"),  _("User Settings"));
        $this->requirePermission("User_Settings");
    }

    public function getHtml() {
        $html = "";

        $user = \Core\UserManager::loggedUser();
        if ($user === NULL) return "";

        // save settings
        if (array_key_exists("Action", $_POST) && $_POST['Action'] == "Save") {
            $user->parameterCollection()->storeHttpRequest();
            $user->saveParameterCollection();
        }

        $html .= $this->newHtmlForm("POST");
        $html .= $user->parameterCollection()->getHtml(TRUE);
        $html .= "<br><br><button type=\"submit\" name=\"Action\" value=\"Save\">" . _("Save") . "</button>";
        $html .= "</form>";

        return $html;
    }
}

?>
