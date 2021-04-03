<?php

class ServerPresetField {
    private $ServerPresetFieldset = NULL;
    private $Tag = NULL;
    private $IsFixed = NULL;

    public function __construct(ServerPresetFieldset $fieldset, string $field_tag) {
        $this->ServerPresetFieldset = $fieldset;
        $this->Tag = $field_tag;
    }

    public function __toString() {
        return "ServerPreset(Id=" . $this->Id . ")";
    }

    //! @return The tag name of the field
    public function tag() {
        return $this->Tag;
    }

    //! @return Name of the field
    public function name() {
        return $this->accessServerCfg()['NAME'];
    }

    //! @return Type of the field
    public function type() {
        return $this->accessServerCfg()['TYPE'];
    }

    //! @return Size of the field
    public function size() {
        return $this->accessServerCfg()['SIZE'];
    }

    //! @return Help text for the field
    public function help() {
        return $this->accessServerCfg()['HELP'];
    }

    //! @return Physical unit of the field
    public function unit() {
        if (!array_key_exists('UNIT', $this->accessServerCfg())) return "";
        else return $this->accessServerCfg()['UNIT'];
    }

    //! @return Default value of the field
    public function default() {
        return $this->accessServerCfg()['DEFAULT'];
    }

    //! @return Current value of the field
    public function current() {
        return $this->accessServerCfg()['CURRENT'];
    }

    //! @return The minimum allowed value (only for int type)
    public function min() {
        if (!array_key_exists('MIN', $this->accessServerCfg())) return 0;
        else return $this->accessServerCfg()['MIN'];
    }

    //! @return The maximumallowed value (only for int type)
    public function max() {
        if (!array_key_exists('MAX', $this->accessServerCfg())) return 0;
        else return $this->accessServerCfg()['MAX'];
    }

    //! @return An array with acciciative sub arrays: [{VALUE, TEXT}]
    public function enums() {
        if (!array_key_exists('ENUMS', $this->accessServerCfg())) return [];
        else return $this->accessServerCfg()['ENUMS'];
    }

    //! @return Name of the dabase column in ServerPresets table
    public function dbColumn() {
        return $this->accessServerCfg()['DB_COLUMN_NAME'];
    }

    //! @return True when this is a fixed field or defined by all server slots
    public function isFixed() {
        global $acswuiConfig;

        if ($this->IsFixed !== NULL) return $this->IsFixed;

        // check if fixed setting or fixed server slot
        $this->IsFixed = FALSE;
        if (array_key_exists($this->dbColumn(), $acswuiConfig->FixedServerConfig)) {
            $this->IsFixed = TRUE;
        }
        if (in_array($this->dbColumn(), ServerSlot::listCommonFixedSettingTags())) {
            $this->IsFixed = TRUE;
        }

        return $this->IsFixed;
    }


    private function accessServerCfg() {
        return $this->ServerPresetFieldset->accessServerCfg()[$this->Tag];
    }
}



class ServerPresetFieldset {
    private $ServerPresetSection = NULL;
    private $Tag = NULL;

    public function __construct(ServerPresetSection $section, string $fieldset_tag) {
        $this->ServerPresetSection = $section;
        $this->Tag = $fieldset_tag;
    }

    //! @return The tag name of the fieldset
    public function tag() {
        return $this->Tag;
    }


    //! @return The name of the fieldset
    public function name() {
        return $this->Tag;
    }


    //! @return A list of available ServerPresetField objects
    public function fields() {
        $fields = array();
        foreach (array_keys($this->accessServerCfg()) as $f) {
            $fields[] = new ServerPresetField($this, $f);
        }
        return $fields;
    }


    /**
     * This shall only be used by ServerPresetFieldset class.
     * This is a workaround for not having friend classes.
     */
    public function accessServerCfg() {
        return $this->ServerPresetSection->accessServerCfg()[$this->Tag];
    }
}



class ServerPresetSection {
    private $ServerPreset = NULL;
    private $Tag = NULL;

    public function __construct(ServerPreset $server_preset, string $section_tag) {
        $this->ServerPreset = $server_preset;
        $this->Tag = $section_tag;
    }


    //! @return The current value of a certain field in a section
    public function currentValue($field_tag) {
        global $acswuiConfig;

        foreach (array_keys($this->accessServerCfg()) as $f) {
            $fieldset = new ServerPresetFieldset($this, $f);
            foreach ($fieldset->fields() as $field) {
                if ($field->tag() == $field_tag) {
                    return $field->current();
                }
            }
        }

        $acswuiConfig->logError("Cannot find field '$field_tag' in section '" . $this->Tag . "'!");
    }


    //! @return The tag name of the section
    public function tag() {
        return $this->Tag;
    }


    //! @return A list of available ServerPresetFieldset objects
    public function fieldsets() {
        $fieldsets = array();
        foreach (array_keys($this->accessServerCfg()) as $f) {
            $fieldsets[] = new ServerPresetFieldset($this, $f);
        }
        return $fieldsets;
    }


    /**
     * This shall only be used by ServerPresetFieldset class.
     * This is a workaround for not having friend classes.
     */
    public function accessServerCfg() {
        return $this->ServerPreset->accessServerCfg()[$this->Tag];
    }
}



/**
 * Cached wrapper to car databse ServerPresets table element
 */
class ServerPreset {

    private $Id = NULL;
    private $Name = NULL;
    private $Restricted = TRUE;
    private static $DatabaseColumns = NULL;
    private $ServerCfgJson = NULL;

    /**
     * @param $id Database table id
     */
    public function __construct(int $id) {
        global $acswuiDatabase;
        global $acswuiLog;

        $this->Id = (int) $id;

        $res = $acswuiDatabase->fetch_2d_array("ServerPresets", ['Id', 'Name', 'Restricted'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find ServerPresets.Id=" . $this->Id);
            return;
        }

        $this->Name = $res[0]['Name'];
        $this->Restricted = ($res[0]['Restricted'] == 0) ? FALSE : TRUE;
    }


    //! Delete the current preset from the database
    public function delete() {
        global $acswuiDatabase;
        $acswuiDatabase->delete_row("ServerPresets", $this->Id);
        $this->Id = NULL;
        $this->Name = NULL;
        $this->ServerCfgJson = NULL;
    }


    /**
     * @param $list_restricted When TRUE, also restricted presets are listed
     * @return A list of all available ServerPreset objects
     */
    public static function listPresets(bool $list_restricted = FALSE) {
        global $acswuiDatabase;

        $where = array();
        if ($list_restricted !== TRUE) $where['Restricted'] = 0;

        $presets = array();
        foreach ($acswuiDatabase->fetch_2d_array("ServerPresets", ['Id'], $where, "Name") as $sp) {
            $presets[] = new ServerPreset($sp['Id']);
        }

        return $presets;
    }


    //! @return The database ID of the preset
    public function id() {
        return $this->Id;
    }


    //! @return The name of the preset
    public function name() {
        return $this->Name;
    }


    //! @return The ServerPreset object of the newly created preset
    public static function new() {
        global $acswuiDatabase;

        $cols = array();
        $cols['Name'] = "NEW";
        $cols['SERVER_WELCOME_MESSAGE'] = "";

        $id = $acswuiDatabase->insert_row("ServerPresets", $cols);
        return new ServerPreset($id);
    }


    //! @return TRUE if this preset shall have restricted access/usage
    public function restricted() {
        return $this->Restricted;
    }


    //! @return A list of available ServerPresetSection objects
    public function sections() {
        if ($this->ServerCfgJson === NULL) $this->updateData();
        $sections = array();
        foreach (array_keys($this->ServerCfgJson) as $section_name) {
            $sections[] = new ServerPresetSection($this, $section_name);
        }
        return $sections;
    }


    /**
     * Find a certain section object
     * @param $name The name of the requested section
     * @return The requested ServerPresetSection object
     */
    public function getSection($name) {
        global $acswuiLog;
        if ($this->ServerCfgJson === NULL) $this->updateData();
        foreach (array_keys($this->ServerCfgJson) as $section_name) {
            if ($section_name == $name)
                return new ServerPresetSection($this, $section_name);
        }
        $acswuiLog->logError("Unknown section name '$name'!");
        return NULL;
    }


    /**
     * This shall only be used by ServerPresetSection class.
     * This is a workaround for not having friend classes.
     */
    public function accessServerCfg() {
        if ($this->ServerCfgJson === NULL) $this->updateData();
        return $this->ServerCfgJson;
    }


    private function updateData() {
        global $acswuiConfig;
        global $acswuiDatabase;
        global $acswuiLog;

        // read json template
        $json_string = file_get_contents($acswuiConfig->AbsPathData . "/server_cfg.json");
        $this->ServerCfgJson = json_decode($json_string, true);

        // get database columns
        $columns = ['Name'];
        foreach ($this->ServerCfgJson as $section => $fieldsets) {
            foreach ($fieldsets as $fieldset_name => $fieldset) {
                foreach ($fieldset as $tag_name => $tag) {
                    $columns[] = $tag['DB_COLUMN_NAME'];
                }
            }
        }

        // request database
        $res = $acswuiDatabase->fetch_2d_array("ServerPresets", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find ServerPresets.Id=" . $this->Id);
            return;
        }

        // write current values
        $this->Name = $res[0]['Name'];
        foreach ($this->ServerCfgJson as $section => $fieldsets) {
            foreach ($fieldsets as $fieldset_name => $fieldset) {
                foreach ($fieldset as $tag_name => $tag) {

                    $column = $tag['DB_COLUMN_NAME'];

                    // value from database
                    $new_value = $res[0][$column];

                    // fixed value from server slot
                    if (in_array($column, ServerSlot::listCommonFixedSettingTags())) {
                        $this->ServerCfgJson[$section][$fieldset_name][$tag_name]['TYPE'] = "string";
                        $new_value = "###";
                    }

                    // value from fixed setup
                    else if (array_key_exists($column, $acswuiConfig->FixedServerConfig)) {
                        $new_value = $acswuiConfig->FixedServerConfig[$column];
                    }

                    // update current value
                    $this->ServerCfgJson[$section][$fieldset_name][$tag_name]['CURRENT'] = $new_value;
                }
            }
        }
    }
}

?>
