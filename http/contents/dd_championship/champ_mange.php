<?php

class champ_mange extends cContentPage {

    private $Championship = NULL;
    private $CanCreateNew = FALSE;
    private $CanDelete = FALSE;
    private $CanEdit = FALSE;

    public function __construct() {
        $this->MenuName   = _("Manage");
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["Championship_View"];
    }

    public function getHtml() {
        global $acswuiUser;

        $html = "";


        // --------------------------------------------------------------------
        //                            Check Permissions
        // --------------------------------------------------------------------

        if ($acswuiUser->hasPermission('Championship_Create')) $this->CanCreateNew = TRUE;
        if ($acswuiUser->hasPermission('Championship_Delete')) $this->CanDelete = TRUE;
        if ($acswuiUser->hasPermission('Championship_Edit')) $this->CanEdit = TRUE;



        // --------------------------------------------------------------------
        //                             Process Actions
        // --------------------------------------------------------------------

        // determine requested championship
        if (array_key_exists("CHMP_ID", $_REQUEST)) {
            $this->Championship = new Championship($_REQUEST['CHMP_ID']);
            $_SESSION['CHAMPIONCHIP'] = $this->Championship->id();
        } else if (array_key_exists('CHAMPIONCHIP', $_SESSION) && $_SESSION['CHAMPIONCHIP'] !== NULL) {
            $this->Championship = new Championship($_SESSION['CHAMPIONCHIP']);
        }

        // check for valid Championship
        if ($this->Championship !== NULL && !$this->Championship->isValid()) {
            $this->Championship = NULL;
            $_SESSION['CHAMPIONCHIP'] = NULL;
        }

        // delete championship
        if (array_key_exists("DELETE", $_REQUEST) && $this->CanDelete && $this->Championship !== NULL) {
            $this->Championship->delete();
            $this->Championship = NULL;
        }

        if (array_key_exists("ACTION", $_REQUEST)) {

            if ($_REQUEST['ACTION'] == "NEW" && $this->CanCreateNew) {
                $this->Championship = Championship::createNew();

            } else if ($_REQUEST['ACTION'] == "SAVE" && $this->CanEdit && $this->Championship !== NULL) {
                $this->Championship->setName($_REQUEST['CHMP_NAME']);

                // add/remove car classes
                $car_classes = array();
                foreach ($this->Championship->carClasses() as $cc) {
                    $cc_id = $cc->id();
                    if (array_key_exists("CARCLASS_ID_$cc_id", $_REQUEST))
                        $car_classes[] = new CarClass($cc_id);
                }
                if ($_REQUEST['ADD_CARCLASS_ID'] != "") {
                    $car_classes[] = new CarClass($_REQUEST['ADD_CARCLASS_ID']);
                }
                $this->Championship->setCarClasses($car_classes);
            }
        }


        // --------------------------------------------------------------------
        //                          Chmp Select
        // --------------------------------------------------------------------

        $html .= "<form method=\"post\">";

        $html .= "<select name=\"CHMP_ID\" onchange=\"this.form.submit()\">";
        foreach (Championship::list() as $chmp) {
            if ($this->Championship === NULL) $this->Championship = $chmp;
            $selected = ($chmp->id() == $this->Championship->id()) ? "selected" : "";
            $html .= "<option value=\"" . $chmp->id() . "\" $selected>" . $chmp->name() ."</option>";
        }
        $html .= "</select>";
        $html .= "<br>";

        if ($this->CanDelete && $this->Championship !== NULL)
            $html .= " <button type=\"submit\" name=\"DELETE\" value=\"" . $this->Championship->id() . "\">" . _("Delete This Championship") . "</button>";
        if ($this->CanCreateNew)
            $html .= " <button type=\"submit\" name=\"ACTION\" value=\"NEW\">" . _("Create New Championship") . "</button>";
        $html .= "</form>";



        // --------------------------------------------------------------------
        //                          General Setup
        // --------------------------------------------------------------------

        if ($this->Championship !== NULL && $this->CanEdit) {
            $html .= "<h1>" . _("General Setup") . "</h1>";

            $html .= "<form method=\"post\" id=\"general_setup\">";
            $html .= "<input type=\"hidden\" name=\"CHMP_ID\" value=\"" . $this->Championship->id() . "\">";
            $html .= "<input type=\"hidden\" name=\"ACTION\" value=\"SAVE\">";

            $html .= "<label>Name</label>";
            $html .= "<input type=\"text\" name=\"CHMP_NAME\" value=\"" . $this->Championship->name() . "\" /> ";

            $html .= "<label>Server Prest</label>";
            $html .= "<select name=\"PRESET_ID\">";
            foreach (ServerPreset::listPresets() as $prst) {
                $selected = ($this->Championship->serverPreset() !== NULL && $this->Championship->serverPreset()->id() == $prst->id()) ? "selected" : "";
                $html .= "<option value=\"" . $prst->id() . "\" $selected>" . $prst->name() ."</option>";
            }
            $html .= "</select>";

            $html .= "<label>Car Classes</label>";
            $html .= "<ul>";
            foreach ($this->Championship->carClasses() as $cc) {
                $html .= "<li>";
                $html .= "<input type=\"checkbox\" name=\"CARCLASS_ID_" . $cc->id() . "\" value=\"TRUE\" checked=\"yes\"> ";
                $html .= $cc->name();
                $html .= "</li>";
            }
            $html .= "<li><select name=\"ADD_CARCLASS_ID\" onchange=\"this.form.submit()\">";
            $html .= "<option value=\"\" selected> </option>";
            foreach (CarClass::listClasses() as $cc) {
                $html .= "<option value=\"" . $cc->id() . "\">" . $cc->name() . "</option>";
            }
            $html .= "</select></li>";
            $html .= "</ul>";



            $html .= " <button type=\"submit\">" . _("Save Championship") . "</button>";

            $html .= "</form>";
        }


        return $html;
    }
}

?>
