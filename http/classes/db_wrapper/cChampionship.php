<?php

/**
 * Cached wrapper to databse Championship table
 */
class Championship {
    private $Id = NULL;
    private $Name = NULL;
    private $ServerPreset = NULL;
    private $CarClasses = NULL;

    /**
     * @param $id Database table id
     */
    public function __construct($id) {
        $this->Id = $id;
    }


    //! @return A list of CarClass objects allowed for this championship
    public function carClasses() {
        if ($this->CarClasses === NULL) $this->updateFromDb();
        return $this->CarClasses;
    }


    //! @return The newly created Championship object
    static public function createNew() {
        global $acswuiDatabase;
        $id = $acswuiDatabase->insert_row("Championships", ['Name'=>"New"]);
        return new Championship($id);
    }


    //! @return The database row Id
    public function id() {
        return $this->Id;
    }


    //! @return TRUE if this is valid
    public function isValid() {
        if ($this->Name === NULL) $this->updateFromDb();
        return ($this->Name === NULL) ? FALSE : TRUE;
    }


    //! Delete this Championship from the database
    public function delete() {
        global $acswuiDatabase;

        $acswuiDatabase->delete_row("Championships", $this->Id);

        $this->Id = NULL;
        $this->Name = NULL;
        $this->ServerPreset = NULL;
    }


    //! @return A list of all existing Championship objects
    public static function list() {
        global $acswuiDatabase;
        $list = array();
        $res = $acswuiDatabase->fetch_2d_array("Championships", ['Id']);
        foreach ($res as $row) {
            $list[] = new Championship($row['Id']);
        }
        return $list;
    }


    //! @return The name of the Championship
    public function name() {
        if ($this->Name === NULL) $this->updateFromDb();
        return $this->Name;
    }


    //! @return The according ServerPreset object
    public function serverPreset() {
        if ($this->ServerPreset === NULL) $this->updateFromDb();
        return $this->ServerPreset;
    }


    //! @param $car_classes A list of CarClass objects
    public function setCarClasses($car_classes) {
        global $acswuiDatabase;

        $cc_ids = array();
        foreach ($car_classes as $cc) {
            if (!in_array($cc->id(), $cc_ids))
                $cc_ids[] = $cc->id();
        }

        $cols = array();
        $cols['CarClasses'] = implode(",", $cc_ids);
        $acswuiDatabase->update_row("Championships", $this->Id, $cols);

        $this->CarClasses = NULL;
    }


    //! @param $name The new name for this Championship
    public function setName(string $name) {
        global $acswuiDatabase;

        $cols = array();
        $cols['Name'] = $name;
        $acswuiDatabase->update_row("Championships", $this->Id, $cols);

        $this->Name = $name;
    }


    //! Internal function to load data from the DB
    private function updateFromDb() {
        global $acswuiDatabase;
        global $acswuiLog;

        // request from db
        $columns = array();
        $columns[] = 'Id';
        $columns[] = 'Name';
        $columns[] = 'ServerPreset';
        $columns[] = 'CarClasses';

        $res = $acswuiDatabase->fetch_2d_array("Championships", $columns, ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find Championships.Id=" . $this->Id);
            return;
        }

        $this->Id = $res[0]['Id'];
        $this->Name = $res[0]['Name'];
        $this->ServerPreset = new ServerPreset($res[0]['ServerPreset']);

        $this->CarClasses = array();
        foreach (explode(",", $res[0]['CarClasses']) as $cc_id) {
            if ($cc_id == "") continue;
            $this->CarClasses[] = new CarClass((int) $cc_id);
        }
    }
}

?>
