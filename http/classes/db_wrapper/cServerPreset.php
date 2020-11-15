<?php

class ServerPresetField {
    private $Tag = NULL;
    private $Name = NULL;
    private $Type = NULL;
    private $Size = NULL;
    private $Help = NULL;
    private $Unit = NULL;
    private $Default = NULL;
    private $DbColumn = NULL;
    private $DbValue = NULL;
}

class ServerPresetFieldset {
    private $Name = NULL;
    private $Fields = NULL;
}

class ServerPresetGroup {
    private $Name = NULL;
    private $Fieldsets = NULL;
}

/**
 * Cached wrapper to car databse ServerPresets table element
 */
class ServerPreset {

    private $Id = NULL;
    private $ServerCfgJson = NULL;

    /**
     * @param $id Database table id
     */
    public function __construct($id) {
        global $acswuiConfig;
        global $acswuiLog;
        global $acswuiDatabase;

        $this->Id = $id;

        // parse server_cfg json
        $json_string = file_get_contents($acswuiConfig->AcsContent . "/server_cfg.json");
        $this->ServerCfgJson = json_decode($json_string, true);

        // determine database columns
        $columns = ['Name'];
        foreach ($this->ServerCfgJson as $group_name => $fieldsets) {
            $grp_data = $preset_data[$group_name];
            foreach ($fieldsets as $fieldset) {
                foreach ($fieldset['FIELDS'] as $field) {
                    $columns[] = $group_name . "_" . $field['TAG'];
                    $field['DB_COLUMN_NAME'] = "";
                }
            }
        }


        // get basic information
        $res = $acswuiDatabase->fetch_2d_array("Cars", ['Car', 'Name', 'Brand'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find Cars.Id=" . $this->Id);
            return;
        }

        $this->Model = $res[0]['Car'];
        $this->Name = $res[0]['Name'];
        $this->Brand = $res[0]['Brand'];
    }

}

?>
