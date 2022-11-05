<?php

namespace Core;

class Database {

    private static $DbHandle = NULL;


    public static function initialize(string $host, string $user, string $password, string $database) {

        // connect to MySQL database
        @$mysqli = new \mysqli($host, $user, $password, $database);
        if ($mysqli->connect_errno || is_null($mysqli)) {
            Log::error("Failed to connect to MySQL: " . $mysqli->connect_error);
        } else {
            Database::$DbHandle = $mysqli;
        }

        // set timezone
        $now = new \DateTime("now");
        $res = Database::query("SET time_zone = '{$now->format('P')}';");
        if ($res === FALSE) {
            \Core\Log::error("Could not set time_zone to '{$now->format('P')}'");
        }
    }


    //! @return An 1D array with all column names a table
    public static function columns(string $tablename) {
        $ret = array();

        // break if no connection available
        if (is_null(Database::$DbHandle)) return array();

        // MySQL request
        $query = "SHOW COLUMNS FROM `" . Database::escape($tablename) . "`;";
        $result = Database::$DbHandle->query($query);
        if ($result === False) {
            Log::error("Failed SQL query: " . Database::$DbHandle->error);
        } else {
            $ret = array();
            while ($res_row = $result->fetch_array(MYSQLI_ASSOC)) {
                $ret[count($ret)] = $res_row['Field'];
            }
            $result->close();
        }

        return $ret;
    }


    /**
     * Converts a DateTime object to the representation format of the database with correct timezone
     * @param $dt A DateTime object with any timezone
     * @return A string that can be used in database requests
     */
    public static function timestamp(\DateTime $dt) {
        $tz = (new \DateTime("now"))->getTimeZone();
        $dt->setTimezone($tz);
        return $dt->format("Y-m-d H:i:s");
    }


    //! Delete a row from a table
    public static function delete(string $table, int $id) {

        // break if no connection available
        if (is_null(Database::$DbHandle)) {
            Log::error("Database not initialized!");
            return;
        }

        // MySQL request
        $table = Database::escape($table);
        $id = Database::escape($id);
        $query = "DELETE FROM `$table` WHERE `Id` = $id;";
        $result = Database::$DbHandle->query($query);
        if ($result === False) {
            Log::error("Failed SQL query: " . Database::$DbHandle->error);
        }
    }


    public static function escape(string $s) {
        if (Database::$DbHandle === NULL) {
            Log::error("Uninitialized database");
            return $s;
        }

        return Database::$DbHandle->escape_string($s);
    }


    /**
     * @return A 2D associative array [row]['$columns[0]', '$columns[1]', .., '$columns[n-1]']
     * @param $coulumns must be an string array (can be NULL to fetch all columns)
     * @param $table must be a string
     * @param $where is an accociative array (key = table column, value = column value)
     */
    public static function fetch(string $table, array $columns = NULL, array $where = [], string $sort_by = NULL, bool $order_asc = true) {
        $ret = array();

        // all columns
        if (is_null($columns)) {
            $columns = Database::columns($table);
        }

        // break if no connection available
        if (is_null(Database::$DbHandle)) {
            Log::error("Database not initialized!");
            return array();
        }

        // prepare columns
        $colums_list = "";
        foreach ($columns as $c) {
            if (strlen($colums_list) > 0)
                $colums_list .= ", ";
            $colums_list .= "`" . Database::escape($c) . "`";
        }

        // prepare table
        $table = Database::escape($table);

        // prepare WHERE
        $where_string = "";
        foreach (Database::columns($table) as $col) {
            if (array_key_exists($col, $where)) {
                if (strlen($where_string) > 0)
                    $where_string .= " AND ";
                $where_string .= "`$col` = '" . Database::escape($where[$col]) . "'";
            }
        }
        if (strlen($where_string) > 0)
            $where_string = "WHERE " . $where_string;

        // order ASC / DESC
        $order = "";
        if (!is_null($sort_by)) {
            $order = "ORDER BY `" . Database::escape($sort_by) . "` ";
            if ($order_asc == true) $order .= "ASC";
            else $order .= "DESC";
        }

        // execute query
        $query = "SELECT $colums_list FROM $table $where_string $order;";
        return Database::fetchRaw($query);
    }


    //! @return A 2D associative array for an SQL SELECT query
    public static function fetchRaw($query_string) {
        $ret = array();

        // execute query
        $result = Database::$DbHandle->query($query_string);
        if ($result === False) {
            Log::error("Failed SQL query: " . $query_string . "\nERROR: " . Database::$DbHandle->error);
        } else {
            $ret = $result->fetch_all(MYSQLI_ASSOC);
            $result->close();
        }

        return $ret;
    }


    /** Insert a row into a table returns the Id of the new row
     *  all fields that are in the keys in the associative array
     *  are inserted by theire new value (key 'Id' is ignored)
     *  only fields with existing database column are respected
     *
     * @return Id of the new inserted table row
     */
    public static function insert(string $table, array $field_list) {
        global $acswuiConfig;
        global $acswuiLog;

        // break if no connection available
        if (is_null(Database::$DbHandle)) {
            Log::error("Database not initialized");
            return 0;
        }

        // MySQL request
        $table = Database::escape($table);

        // create set of fields
        $insert_columns = "";
        $insert_values  = "";

        foreach (Database::columns($table) as $col) {

            // Ignore 'Id' field
            if ($col == "Id") continue;

            // check if array contains the field
            if (array_key_exists($col, $field_list)) {
                if (strlen($insert_columns) > 0) {
                    $insert_columns .= ", ";
                    $insert_values  .= ", ";
                }
                $insert_columns .= "`$col`";
                $insert_values  .= "'" . Database::escape($field_list[$col]) . "'";
            }
        }

        $query = "INSERT INTO `$table` ($insert_columns) VALUES ($insert_values);";
        if (!Database::$DbHandle->query($query)) {
            Log::error("Query '$query' failed: " . Database::$DbHandle->error);
        }


        $result = Database::$DbHandle->query("SELECT LAST_INSERT_ID();");
        if ($result === False) {
            Log::error("Failed SQL query: " . Database::$DbHandle->error);
        }
        return $result->fetch_array()[0];
    }


//     public function insert_group_permission($permission) {
//         global $acswuiLog;
//
//         // break if no connection available
//         if (is_null(Database::$DbHandle)) return array();
//
//         // log event
//         $acswuiLog->LogNotice("New permission added: $permission");
//
//         // MySQL request
//         $permission = Database::$DbHandle->escape_string($permission);
//         $query = "ALTER TABLE `Groups` ADD `$permission` TINYINT NOT NULL DEFAULT '0';";
//         $result = Database::$DbHandle->query($query);
//         if ($result === False) {
//             $acswuiLog->LogError("Failed SQL query: " . Database::$DbHandle->error);
//         }
//     }


    public static function shutdown() {

        if (!is_null(Database::$DbHandle)) {
            Database::$DbHandle->close();
            Database::$DbHandle = NULL;
        }

    }


    /**
     * Executing a raw database query
     * @param $query_string The SQL query
     * @return Same as mysqli::query(), either FALSE or a mysqli_result object
     */
    public static function query($query_string) {

        // execute query
        $result = Database::$DbHandle->query($query_string);
        if ($result === FALSE) {
            Log::error("Failed SQL query: " . $query_string . "\nERROR: " . Database::$DbHandle->error);
        }

        return $result;
    }


    /**
     * Update a row in a table
     * All fields that are in the keys in the associative array
     * are updated by theire new value (key 'Id' is ignored).
     * Only fields with existing database column are respected
     */
    public static function update(string $table, int $id, array $field_list) {

        // ignore update when there are no fields
        if (count($field_list) == 0) {
            Log::warning("update ignored because of empty field_list");
            return;
        }

        // break if no connection available
        if (is_null(Database::$DbHandle)) {
            Log::warning("update ignored because of database connection not initialized");
            return;
        }

        // escape strings
        $table = Database::escape($table);
        $id = Database::escape($id);

        // create set of fields
        $set = "";
        foreach (Database::columns($table) as $col) {

            // Ignore 'Id' field
            if ($col == "Id") continue;

            // check if array contains the field
            if (array_key_exists($col, $field_list)) {
                if (strlen($set) > 0) $set .= ", ";
                $set .= "`$col` = '" . Database::escape($field_list[$col]) . "'";
            }
        }

        // update
        $query = "UPDATE `$table` SET $set WHERE `Id` = $id;";
        $result = Database::$DbHandle->query($query);
        if ($result === False) {
            Log::error("Failed SQL query: " . Database::$DbHandle->error);
        }
    }


}
