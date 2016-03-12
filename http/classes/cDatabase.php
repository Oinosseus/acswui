<?php
  class cDatabase {

    private $db_handle = NULL;

    public function __construct() {

        global $acswuiConfig;
        global $acswuiLog;

        // connect to MySQL database
        if ($acswuiConfig->DbType === "MySQL") {
            @$mysqli = new mysqli($acswuiConfig->DbHost, $acswuiConfig->DbUser, $acswuiConfig->DbPasswd, $acswuiConfig->DbDatabase);
            if ($mysqli->connect_errno || is_null($mysqli)) {
                $acswuiLog->LogError("Failed to connect to MySQL: " . $mysqli->connect_error);
            } else {
                $this->db_handle = $mysqli;
                $acswuiLog->LogNotice("Connected to MySQL " . $mysqli->server_info);
            }

        // Unsupported database type
        } else {
            $acswuiLog->LogError("Unsupported database type: " . $acswuiConfig->DbType);
        }

    }

    public function __destruct() {

        global $acswuiConfig;

        if ($acswuiConfig->DbType === "MySQL" && !is_null($this->db_handle)) {
            $this->db_handle->close();
            $this->db_handle = NULL;
        }

    }


    // retuzrns an 1D array with all column names of the table
    private function __fetch_column_names($table) {

        global $acswuiConfig;
        $ret = array();

        // break if no connection available
        if (is_null($this->db_handle)) return array();

        // MySQL request
        if ($acswuiConfig->DbType === "MySQL") {
            $query = "SHOW COLUMNS FROM `" . $this->db_handle->escape_string($table) . "`;";
            if ($result = $this->db_handle->query($query)) {
                $ret = array();
                while ($res_row = $result->fetch_array(MYSQLI_ASSOC)) {
                    $ret[count($ret)] = $res_row['Field'];
                }
                $result->close();
            }
        }

        return $ret;
    }



    // returns an 2D associative array [row]['$columns[0]', '$columns[1]', .., '$columns[n-1]']
    // $coulumns must be an string array
    // $table must be a string
    private function __fetch_columns_from_table($columns, $table) {

        global $acswuiConfig;
        $ret = array();

        // break if no connection available
        if (is_null($this->db_handle)) return array();

        // MySQL request
        if ($acswuiConfig->DbType === "MySQL") {

            // prepare columns
            $colums_list = "";
            foreach ($columns as $c) {
                if (strlen($colums_list) > 0)
                    $colums_list .= ", ";
                $colums_list .= $this->db_handle->escape_string($c);
            }

            // prepare table
            $table = $this->db_handle->escape_string($table);

            // execute query
            $query = "SELECT $colums_list FROM $table;";
            if ($result = $this->db_handle->query($query)) {
                $ret = $result->fetch_all(MYSQLI_ASSOC);
                $result->close();
            }
        }

        return $ret;
    }



    // returns a 2D string array with all existing groups and their permissions
    // ['Id', 'Name', 'Permit**'][]
    public function getGroups() {
        $columns = $this->__fetch_column_names("Groups");
        return $this->__fetch_columns_from_table($columns, "Groups");
    }

    // returns an 1D string array with all existing group permissions
    public function getPermissions() {
        $ret = array();
        $fetch = $this->__fetch_column_names("Groups");
        foreach ($fetch as $f) {
            if (substr($f, 0, 6) === "Permit")
                $ret[count($ret)] = $f;
        }
        return $ret;
    }


  }
?>
