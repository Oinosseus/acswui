<?php

namespace DbEntry;
use \Core\Database;
USE \Core\Log;

/**
 * Base class for all ID based database tables
 *
 * Allows cached access to column values.
 *
 * Allows cached acces to objects.
 * This allows to retrieve same objects with the getCachedObject() method
 * Downside is, that all requested table obejects are cached in memory.
 * Maybe this not allows the garbae collector to cleanup unused space.
 * On the other side, objects need to to be re-created when cached - also they are exactly the same when objects are statically cached.
 */
abstract class DbEntry {

    //! The ID of the table row (NULL for new table row entries)
    private $Id = NULL;

    //! Name of the corresponding database table
    private $TableName = NULL;

    //! The last known table values from the database
    private $ColumnValues = NULL;

    //! A cache that contains the columns of the requested tables: key=TableName, value=[Column Names]
    private static $ColumnNames = array();


    /**
     * Stores all DbEntry objects that are already retrieved from database
     * {key=Tablename, value={key=Id, value=Object}}
     * This is used by the DbEntry::fromId() method
     */
    private static $AllEntriesCache = array();


    /**
     * Must be called by inherited class
     * @param $tablename The name of the parenting table (must be set correctly)
     * @parem $id The Id of the table row (NULL for entries to be created new)
     */
    public function __construct(string $tablename, int $id = NULL) {
        $this->TableName = $tablename;
        $this->Id = $id;
    }


    //! @return The string representation of the table (just info, no serialization)
    public function __toString() {
        return $this->TableName . "[Id=" . $this->Id . "]";
    }


    /**
     * Construct an object from exising database table Id
     * This method is cached. Request the same ID again will return the same object.
     *
     * This intented to be used by inherited classes by implementing this function:
     *     public static function fromId(int $id) {
     *         return parent::getCachedObject("DbTableName", "InheritedClassName", $id);
     *     }
     *
     * @param $tablename The name of the database table that is correlated to the class
     * @param $classname The name of the class that inherits DbEntry (without namespace)
     * @param $id The database table row Id to construct the requested object from
     */
    protected static function getCachedObject(string $tablename, string $classname, int $id) {

        // create table cache
        if (!array_key_exists($tablename, DbEntry::$AllEntriesCache)) {
            DbEntry::$AllEntriesCache[$tablename] = array();
        }

        // create objects cache
        if (!array_key_exists($id, DbEntry::$AllEntriesCache[$tablename])) {
            $classname = "\\dbentry\\$classname";
            $obj = new $classname($id);
            DbEntry::$AllEntriesCache[$tablename][$id] = $obj;
        }

        // return cached object
        return DbEntry::$AllEntriesCache[$tablename][$id];
    }


    //! The Id of the database table row (NULL for new entries)
    public function id() {
        return $this->Id;
    }


    /**
     * Get the last known value of a column from the database.
     * This function is cached.
     * @return The last known value from the database.
     */
    protected function loadColumn(string $column_name) {

        // update cache
        if ($this->ColumnValues === NULL) {

            // check tablename
            if ($this->TableName === NULL) Log::error("No correct tablename given!");
            $tname = $this->TableName;

            // check id
            if ($this->Id == NULL) Log::error("Cannot read column values of new table entry!");
            $id = $this->Id;

            // get column names
            $columns = $this->tableColumns();

            // get column values
            $res = Database::fetch($tname, $columns, ['Id'=>$id]);
            if (count($res) !== 1) {
                Log::error("Cannot find $tname.Id=" . $id);
                return array();
            }

            $this->ColumnValues = $res[0];
        }

        // return from cache
        return $this->ColumnValues[$column_name];
    }


    /**
     * Store an amount of column values into database
     * If this is a new table entry (Id === NULL) then a new entry is saved and Id is updated.
     * If the entry already exists it is updated.
     * @param $column_values An associative array with the new column values
     */
    public function storeColumns(array $column_values) {

        // check tablename
        if ($this->TableName == NULL) Log::error("No correct tablename given!");
        $tname = $this->TableName;

        // store in database
        if ($this->Id === NULL) {  // insert
            $this->Id = Database::insert($tname, $column_values);
        } else {  // update
            Database::update($tname, $this->Id, $column_values);
        }

        // update column cache
        foreach ($this->ColumnValues as $cname => $cval) {
            if (array_key_exists($cname, $column_values)) {
                $this->ColumnValues[$cname] = $column_values[$cname];
            }
        }
    }


    //! @return An array with the column names of the table
    public function tableColumns() {

        // check tablename
        if ($this->TableName === NULL) Log::error("No correct tablename given!");
        $tname = $this->TableName;

        // get column names
        if (!array_key_exists($tname, DbEntry::$ColumnNames)) {
            DbEntry::$ColumnNames[$tname] = Database::columns($tname);
        }

        return DbEntry::$ColumnNames[$tname];
    }


    //! @return The name of the database table
    public function tableName() {
        if ($this->TableName === NULL) Log::error("No correct tablename given!");
        return $this->TableName;
    }
}
