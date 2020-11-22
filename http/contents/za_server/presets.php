<?php

class presets extends cContentPage {

    public function __construct() {
        global $acswuiConfig;
        global $acswuiUser;
        global $acswuiLog;

        $this->MenuName   = _("Presets");
        $this->PageTitle  = "Server Presets";
        $this->TextDomain = "acswui";
        $this->RequirePermissions = ["View_ServerContent"];

        // class variables used for html processing
        $this->CanEdit = false;
        $this->CanViewFixed = false;
        $this->CurrentPreset = NULL;

    }


    public function getHtml() {

        // access global data
        global $acswuiConfig;
        global $acswuiDatabase;
        global $acswuiUser;

        // check permissions
        if ($acswuiUser->hasPermission('Server_Presets_Edit')) $this->CanEdit = true;
        if ($acswuiUser->hasPermission('Server_Presets_ViewFixed')) $this->CanViewFixed = true;

        // determine PresetId
        if (isset($_REQUEST['PRESET_ID'])) {
            $this->CurrentPreset = new ServerPreset((int) $_REQUEST['PRESET_ID']);
        } elseif (isset($_SESSION['PRESET_ID'])) {
            $this->CurrentPreset = new ServerPreset((int) $_SESSION['PRESET_ID']);
        } else {
            $this->CurrentPreset = NULL;
        }

        if ($this->CurrentPreset !== NULL) {
            $_SESSION['PRESET_ID'] = $this->CurrentPreset->id();
        }

        // check for actions
        if (isset($_POST['SAVE'])) {
            if ($_POST['SAVE'] == "CURRENT") {
                $this->savePreset(FALSE);
            } else if ($_POST['SAVE'] == "NEW") {
                $this->savePreset(TRUE);
            }
        }


        // initialize the html output
        $html  = '';



        // -----------------
        //  Preset Selector
        // -----------------

        $html .= '<form>';
        $html .= '<select name="PRESET_ID" onchange="this.form.submit()">';

        // existing presets
        $current_preset_found = false;
        foreach (ServerPreset::listPresets() as $sp) {
            if ($this->CurrentPreset !== NULL && $this->CurrentPreset->id() == $sp->id()) {
                $selected = "selected";
                $current_preset_found = true;
            } else {
                $selected = "";
            }
            $html .= '<option value="' . $sp->id() . '"' . $selected . '>' . $sp->name() . '</option>';
        }
        if (!$current_preset_found) {
            $html .= '<option value="0" selected>???</option>';
        }

        $html .= '</select>';
        $html .= '</form><br>';



        // ---------------
        //  Config Server
        // ---------------

        if ($this->CurrentPreset !== NULL) {
            $html .= "<form action=\"\" method=\"post\"><input type=\"hidden\" name=\"PRESET_ID\" value=\"" . $this->CurrentPreset->id() . "\"/>";

            # preset name
            $html .= "<fieldset>";
            $html .= "Preset Name: <input type=\"text\" name=\"Name\" value=\"" . $this->CurrentPreset->name() . "\" " . (($this->CanEdit) ? "" : "readonly") . "/>";
            $html .= "</fieldset>";

            # save buttons
            if ($this->CanEdit) {
                $html .= "<button type=\"submit\" name=\"SAVE\" value=\"CURRENT\">" . _("Save Preset") . "</button>";
                $html .= " ";
                $html .= "<button type=\"submit\" name=\"SAVE\" value=\"NEW\">" . _("Save As New Preset") . "</button>";
            }

            # generate form for server preset options
            foreach ($this->CurrentPreset->sections() as $section) {
                foreach ($section->fieldsets() as $fieldset) {
                    $html .= $this->getFieldsetInputs($fieldset);
                }
            }

            $html .= '</form>';
        }


        return $html;
    }

    function getFieldsetInputs($fieldset) {
        global $acswuiConfig;
        global $acswuiLog;

        $html = "";
        foreach ($fieldset->fields() as $field) {

            // ignore fixed fields
            if ($field->isFixed() !== FALSE && !$this->CanViewFixed) continue;

            // create html input
            if ($field->type() == "string") {
                $html .= $this->getTableInputRowString($field);

            } else if ($field->type() == "int") {
                $html .= $this->getTableInputRowInt($field);

            } else if ($field->type() == "enum") {
                $html .= $this->getTableInputRowEnum($field);

            } else if ($field->type() == "text") {
                $html .= $this->getTableInputRowText($field);

            } else {
                $acswuiLog->logError("Unknown field type '" . $field->type() . "'!");
            }
        }

        // return html
        if ($html == "") {
            return "";
        } else {
            $fieldset_html = "<fieldset style=\"display: block; float: left;\"><legend>" . $fieldset->name() . "</legend><table>";
            $fieldset_html .= $html;
            $fieldset_html .= "</table></fieldset>";
            return $fieldset_html;
        }


    }


    function getTableInputRowString($field) {
        $tag = $field->dbColumn();
        $name = $field->name();
        $help = $field->help();
        $unit = $field->unit();
        $value = $field->current();

        if ($this->CanEdit && $field->isFixed() === FALSE) {
            return "<tr><td>$name</td><td><input type=\"text\" name=\"$tag\" value=\"$value\" title=\"$help\"/> $unit</td></tr>";
        } else {
            return "<tr><td>$name</td><td><span class=\"disabled_input\">$value $unit</span></td></tr>";
        }

    }


    function getTableInputRowText($field) {
        $tag = $field->dbColumn();
        $name = $field->name();
        $help = $field->help();
        $unit = $field->unit();
        $value = $field->current();

        if ($this->CanEdit && $field->isFixed() === FALSE) {
            return "<tr><td>$name</td><td><textarea name=\"$tag\" title=\"$help\">$value</textarea></td></tr>";
        } else {
            return "<tr><td>$name</td><td><span class=\"disabled_input\">$value</span></td></tr>";
        }

    }


    function getTableInputRowInt($field) {
        $tag = $field->dbColumn();
        $name = $field->name();
        $help = $field->help();
        $unit = $field->unit();
        $value = $field->current();
        $min = $field->min();
        $max = $field->max();

        if ($this->CanEdit && $field->isFixed() === FALSE) {
            return "<tr><td>$name</td><td><input type=\"number\" min=\"$min\" max=\"$max\" step=\"1\" name=\"$tag\" value=\"$value\" title=\"$help\"> $unit</td></tr>";
        } else {
            return "<tr><td>$name</td><td><span class=\"disabled_input\">$value $unit</span></td></tr>";
        }

    }


    function getTableInputRowEnum($field) {
        $tag = $field->dbColumn();
        $name = $field->name();
        $help = $field->help();
        $unit = $field->unit();
        $value = $field->current();

        if ($this->CanEdit && $field->isFixed() === FALSE) {
            $html = "<tr><td>$name</td><td>";
            $html .= "<select name=\"$tag\" title=\"$help\">";
            foreach ($field->enums() as $enum) {
                $opt_val = $enum['VALUE'];
                $opt_text = $enum['TEXT'];
                $opt_checked = ($opt_val == $value) ? "selected" : "";
                $html .= "<option value=\"$opt_val\" $opt_checked>$opt_text</option>";
            }
            $html .= "</select>";
            $html .= "</td></tr>";
            return $html;

        } else {
            foreach ($field->enums() as $enum) {
                if ($enum['VALUE'] == $value) {
                    $value = $enum['TEXT'];
                    break;
                }
            }
            return "<tr><td>$name</td><td><span class=\"disabled_input\">$value $unit</span></td></tr>";
        }
    }

    function savePreset($create_new = FALSE) {
        global $acswuiDatabase;
        global $acswuiLog;

        // check permission
        if (!$this->CanEdit) return;

        if ($this->CurrentPreset === NULL) {
            $acswuiLog->logError("Cannot save preset!");
            return;
        }

        // gather data
        $data = array();
        $data['Name'] = $_POST['Name'];
        foreach ($this->CurrentPreset->sections() as $section) {
            foreach ($section->fieldsets() as $fieldset) {
                foreach ($fieldset->fields() as $field) {
                    if ($field->isFixed()) continue;

                    $column = $field->dbColumn();

                    if (!array_key_exists($column, $_POST)) {
                        $acswuiLog->logError("Missing key '$column'!");
                        return;
                    }

                    $value = $_POST[$column];
                    $data[$column] = $value;
                }
            }
        }

        if ($create_new === TRUE) {
            $id = $acswuiDatabase->insert_row("ServerPresets", $data);
            $_SESSION['PRESET_ID'] = $id;
            $this->CurrentPreset = new ServerPreset($id);
        } else {
            $acswuiDatabase->update_row("ServerPresets", $this->CurrentPreset->id(), $data);
            $this->CurrentPreset = new ServerPreset($this->CurrentPreset->id());
        }

    }
}

?>
