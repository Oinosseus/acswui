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
    public function fetch_column_names($table) {

        global $acswuiConfig;
        $ret = array();

        // break if no connection available
        if (is_null($this->db_handle)) return array();

        // MySQL request
        if ($acswuiConfig->DbType === "MySQL") {
            $query = "SHOW COLUMNS FROM `" . $this->db_handle->escape_string($table) . "`;";
            $result = $this->db_handle->query($query);
            if ($result === False) {
                $acswuiLog->LogError("Failed SQL query: " . $this->db_handle->error);
            } else {
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
    // $coulumns must be an string array (can be NULL to fetch all columns)
    // $table must be a string
    // $where is an accociative array (key = table column, value = column value)
    public function fetch_2d_array($table, $columns, $where = [], $sort_by = NULL, $order_asc = true) {

        global $acswuiConfig;
        global $acswuiLog;
        $ret = array();

        // all columns
        if (is_null($columns)) {
            $columns = $this->fetch_column_names($table);
        }

        // break if no connection available
        if (is_null($this->db_handle)) return array();

        // MySQL request
        if ($acswuiConfig->DbType === "MySQL") {

            // prepare columns
            $colums_list = "";
            foreach ($columns as $c) {
                if (strlen($colums_list) > 0)
                    $colums_list .= ", ";
                $colums_list .= "`" . $this->db_handle->escape_string($c) . "`";
            }

            // prepare table
            $table = $this->db_handle->escape_string($table);

            // prepare WHERE
            $where_string = "";
            foreach ($this->fetch_column_names($table) as $col) {
                if (array_key_exists($col, $where)) {
                    if (strlen($where_string) > 0)
                        $where_string .= ", ";
                    $where_string .= "`$col` = '" . $this->db_handle->escape_string($where[$col]) . "'";
                }
            }
            if (strlen($where_string) > 0)
                $where_string = "WHERE " . $where_string;

            // order ASC / DESC
            $order = "";
            if (!is_null($sort_by)) {
                $order = "ORDER BY `" . $this->db_handle->escape_string($sort_by) . "` ";
                if ($order_asc == true) $order .= "ASC";
                else $order .= "DESC";
            }

            // execute query
            $query = "SELECT $colums_list FROM $table $where_string $order;";
            $result = $this->db_handle->query($query);
            if ($result === False) {
                $acswuiLog->LogError("Failed SQL query: " . $query . "\nERROR: " . $this->db_handle->error);
            } else {
                $ret = $result->fetch_all(MYSQLI_ASSOC);
                $result->close();
            }
        }

        return $ret;
    }


    // update a row in a table
    // all fields that are in the keys in the associative array
    // are updated by theire new value (key 'Id' is ignored)
    // only fields with existing database column are respected
    public function update_row($table, $id, $field_list) {
        global $acswuiConfig;
        global $acswuiLog;

        // ignore update when there are no fields
        if (count($field_list) == 0) {
            $acswuiLog->logWarning("update ignored because of empty field_list");
            return;
        }

        // break if no connection available
        if (is_null($this->db_handle)) return array();

        // MySQL request
        if ($acswuiConfig->DbType === "MySQL") {
            $table = $this->db_handle->escape_string($table);
            $id = $this->db_handle->escape_string($id);

            // create set of fields
            $set = "";
            foreach ($this->fetch_column_names($table) as $col) {

                // Ignore 'Id' field
                if ($col == "Id") continue;

                // check if array contains the field
                if (array_key_exists($col, $field_list)) {
                    if (strlen($set) > 0)
                        $set .= ", ";
                    $set .= "`$col` = '" . $this->db_handle->escape_string($field_list[$col]) . "'";
                }
            }

            $query = "UPDATE `$table` SET $set WHERE `Id` = $id;";
            $result = $this->db_handle->query($query);
            if ($result === False) {
                $acswuiLog->LogError("Failed SQL query: " . $this->db_handle->error);
            }
        }
    }


    // insert a row into a table returns the Id of the new row
    // all fields that are in the keys in the associative array
    // are inserted by theire new value (key 'Id' is ignored)
    // only fields with existing database column are respected
    public function insert_row($table, $field_list) {
        global $acswuiConfig;
        global $acswuiLog;

        // break if no connection available
        if (is_null($this->db_handle)) return array();

        // MySQL request
        if ($acswuiConfig->DbType === "MySQL") {
            $table = $this->db_handle->escape_string($table);

            // create set of fields
            $insert_columns = "";
            $insert_values  = "";

            foreach ($this->fetch_column_names($table) as $col) {

                // Ignore 'Id' field
                if ($col == "Id") continue;

                // check if array contains the field
                if (array_key_exists($col, $field_list)) {
                    if (strlen($insert_columns) > 0) {
                        $insert_columns .= ", ";
                        $insert_values  .= ", ";
                    }
                    $insert_columns .= "`$col`";
                    $insert_values  .= "'" . $field_list[$col] . "'";
                }
            }

            $query = "INSERT INTO `$table` ($insert_columns) VALUES ($insert_values);";
//             echo("<br>");
//             print_r($query);
            if (!$this->db_handle->query($query)) {
                $acswuiLog->logError($this->db_handle->error);
            }


            $result = $this->db_handle->query("SELECT LAST_INSERT_ID();");
            if ($result === False) {
                $acswuiLog->LogError("Failed SQL query: " . $this->db_handle->error);
            }
            return $result->fetch_array()[0];
        }
    }

    public function insert_group_permission($permission) {
        global $acswuiConfig;
        global $acswuiLog;

        // break if no connection available
        if (is_null($this->db_handle)) return array();

        // log event
        $acswuiLog->LogNotice("New permission added: $permission");

        // MySQL request
        if ($acswuiConfig->DbType === "MySQL") {
            $permission = $this->db_handle->escape_string($permission);
            $query = "ALTER TABLE `Groups` ADD `$permission` TINYINT NOT NULL DEFAULT '0';";
            $result = $this->db_handle->query($query);
            if ($result === False) {
                $acswuiLog->LogError("Failed SQL query: " . $this->db_handle->error);
            }
        }
    }

    public function delete_row($table, $id) {
        global $acswuiConfig;

        // break if no connection available
        if (is_null($this->db_handle)) return array();

        // MySQL request
        if ($acswuiConfig->DbType === "MySQL") {
            $table = $this->db_handle->escape_string($table);
            $id = $this->db_handle->escape_string($id);
            $query = "DELETE FROM `$table` WHERE `Id` = $id;";
            $result = $this->db_handle->query($query);
            if ($result === False) {
                $acswuiLog->LogError("Failed SQL query: " . $this->db_handle->error);
            }
        }
    }

  }
?>
