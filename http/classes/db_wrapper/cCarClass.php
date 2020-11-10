<?php

/**
 * Cached wrapper to databse CarClasses table element
 */
class CarClass {

    private $Id = NULL;
    private $Name = NULL;
    private $Cars = NULL;

    /**
     * @param $id Database table id
     */
    public function __construct($id) {
        global $acswuiLog;
        global $acswuiDatabase;

        $this->Id = $id;

        // get basic information
        $res = $acswuiDatabase->fetch_2d_array("CarClasses", ['Name'], ['Id'=>$this->Id]);
        if (count($res) !== 1) {
            $acswuiLog->logError("Cannot find CarClasses.Id=" . $this->Id);
            return;
        }

        $this->Name = $res[0]['Name'];
    }

    //! @return A list of Car objects
    public function cars() {
        global $acswuiDatabase;

        // return cache
        if ($this->Cars !== NULL) return $this->Cars;

        // create cache
        $this->Cars = array();
        $query = "SELECT Cars.Id FROM CarClassesMap";
        $query .= " INNER JOIN Cars ON Cars.Id=CarClassesMap.Car";
        $query .= " WHERE CarClassesMap.CarClass=" . $this->Id;
        foreach ($acswuiDatabase->fetch_raw_select($query) as $row) {
            $this->Cars[] = new Car($row['Id']);
        }

        return $this->Cars;
    }

    //! @return Name of the CarClass
    public function name() {
        return $this->Name;
    }
}

?>
