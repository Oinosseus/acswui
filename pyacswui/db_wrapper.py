import pymysql

class DbWrapper(object):



    def __init__(self, config, verbosity):

        if type(config) != type({}):
            raise TypeError("Parameter 'config' must be dict!")

        # check db values
        for key in ["db_type", "db_host", "db_database", "db_port", "db_user", "db_passwd"]:
            if key not in config:
                raise TypeError("Config must contain '" + key + "'!")

        self.__config = {}
        self.__config.update(config)
        self.__verbosity = int(verbosity)

        # connect to db_database
        self.__db_handle = pymysql.connect(host=self.__config['db_host'], port=int(self.__config['db_port']), user=self.__config['db_user'], passwd=self.__config['db_passwd'], db=self.__config['db_database'], charset='utf8')



    def __del__(self):
        if hasattr(self, "__db_handle"):
            self.__db_handle.close()



    def appendTable(self, tblname):
        """
            Create table if not existent.
            A column 'Id' is created and used as index with auto increment.
        """

        # check if table already exist
        table_exist = False
        query = "SELECT `TABLE_NAME` FROM information_schema.TABLES WHERE table_schema = '%s';" % self.__config['db_database']
        if self.__verbosity > 2:
            print("  " + query)
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        for r in cursor.fetchall():
            if r[0] == tblname:
                table_exist = True
        cursor.close()

        # create query
        if table_exist is False:

            query = "CREATE TABLE `" + tblname + "` ( `Id` INT NOT NULL AUTO_INCREMENT , PRIMARY KEY (`Id`)) ENGINE = InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
            if self.__verbosity > 1:
                print("    " + query)

            # execute query
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            cursor.close()
            self.__db_handle.commit()

        # table already exist
        else:

            # check index
            primary_index_found = False
            primary_index_correct = False
            query = "SHOW INDEX FROM %s WHERE Key_name = 'PRIMARY';" % tblname
            if self.__verbosity > 2:
                print("  " + query)
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            for r in cursor.fetchall():
                if r[2].lower() == "primary":
                    primary_index_found = True
                    if r[4] == "Id":
                        primary_index_correct = True
            cursor.close()
            self.__db_handle.commit()

            if primary_index_correct is False:
                if primary_index_found is True:
                    query = "ALTER TABLE `" + tblname + "` DROP PRIMARY KEY, ADD PRIMARY KEY(`Id`);"
                else:
                    query = "ALTER TABLE `" + tblname + "` ADD PRIMARY KEY(`Id`);"
                if self.__verbosity > 1:
                    print("    " + query)
                # execute query
                cursor = self.__db_handle.cursor()
                cursor.execute(query)
                cursor.close()
                self.__db_handle.commit()

        # alter collation
        query = "ALTER TABLE `" + tblname + "` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;"
        if self.__verbosity > 2:
            print("    " + query)
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        cursor.close()
        self.__db_handle.commit()



    def __appendColumn(self, tblname, colname, coltype, coldefault = None, colextra = None):

        # assume column exist already
        column_exist = False
        column_needs_change = False

        # check column info
        query = "SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_DEFAULT`, `EXTRA` FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s';" % (self.__config['db_database'], tblname, colname)
        if self.__verbosity > 2:
            print("  " + query)
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        if cursor.rowcount > 0:
            column_exist = True
            res = cursor.fetchall()[0]
            if self.__verbosity > 2:
                print("    sql result: COLUMN_NAME=%s, COLUMN_TYPE=%s, COLUMN_DEFAULT=%s, EXTRA=%s" % (res[0], res[1], res[2], res[3]))
            if res[0] != colname:
                column_needs_change = True
            if coltype is not None and res[1].lower() != coltype.lower():
                column_needs_change = True
            if coldefault is not None:
                if res[2] is None or res[2].lower() != coldefault.lower():
                    column_needs_change = True
            if colextra is not None and colextra.lower() not in res[3].lower():
                column_needs_change = True
        cursor.close()
        self.__db_handle.commit()

        # retype parameters
        if coldefault is not None:
            coldefault = "DEFAULT %s" % coldefault
        else:
            coldefault = ""

        if colextra is None:
            colextra = ""

        # create column
        if column_exist is False:
            # create query
            query = "ALTER TABLE `%s` ADD `%s` %s NOT NULL %s %s;" % (tblname, colname, coltype, coldefault, colextra)
            if self.__verbosity > 1:
                print("    " + query)

            # execute query
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            cursor.close()
            self.__db_handle.commit()

        # update column
        elif column_needs_change is True:
            # create query
            query = "ALTER TABLE `%s` CHANGE `%s` `%s` %s NOT NULL %s %s;" % (tblname, colname, colname, coltype, coldefault, colextra)
            if self.__verbosity > 1:
                print("    " + query)

            # execute query
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            cursor.close()
            self.__db_handle.commit()



    def appendColumnInt(self, tblname, colname, length = 11):
        self.__appendColumn(tblname, colname, "int(" + str(length) + ")", "'0'")

    def appendColumnFloat(self, tblname, colname):
        self.__appendColumn(tblname, colname, "float", "'0'")

    def appendColumnString(self, tblname, colname, length = 100):
        self.__appendColumn(tblname, colname, "varchar(" + str(length) + ")", "''")

    def appendColumnText(self, tblname, colname):
        self.__appendColumn(tblname, colname, "text")

    def appendColumnCurrentTimestamp(self, tblname, colname):
        self.__appendColumn(tblname, colname, "timestamp", "CURRENT_TIMESTAMP")


    def findIds(self, tblname, where_values):
        """
            Returns a list of IDs of all rows that match the where_values dictionary
        """

        ret = []

        # ignore empty where request
        if len(where_values) <= 0:
            return ret

        # create WHERE term
        where = ""
        for key in where_values.keys():
            if len(where) > 0:
                where += " AND"
            where += " `" + key + "` = " + self.__db_handle.escape((where_values[key]))

        # create query
        query = "SELECT `Id` FROM `" + tblname + "` WHERE " + where + ";";

        # execute query
        if self.__verbosity > 2:
            print("  " + query)
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        for res in cursor.fetchall():
            ret.append(res[0])
        cursor.close()

        # user info
        if self.__verbosity > 2:
            print("  found IDs:", ret)

        return ret



    def insertRow(self, tblname, field_values):
        """
            Insert new row with values as defined in field_values.
            The functions returns the Id of the inserted row.
        """
        #INSERT INTO `acswui`.`Cars` (`Id`, `Car`, `Name`, `Parent`, `Brand`) VALUES (NULL, 'foo', 'bar', 'mu', '')

        # create query
        fields = ""
        values = ""
        for key in field_values.keys():
            if len(fields) > 0:
                fields += ", "
                values += ", "
            fields += "`" + str(key) + "`"
            v = self.__db_handle.escape((field_values[key]))
            values += v#"'" + v + "'"
        query = "INSERT INTO `" + tblname + "` (" + fields + ") VALUES (" + values + ");"

        # execute query
        if self.__verbosity > 2:
            print("  " + query)
        cursor = self.__db_handle.cursor()
        cursor.execute(query)

        # get insert ID
        cursor.execute("SELECT LAST_INSERT_ID();")
        insert_id = cursor.fetchall()[0][0]
        cursor.close()
        self.__db_handle.commit()

        return insert_id



    def updateRow(self, tblname, id_value, field_values):
        """
            Updates a row with values as defined in field_values.
        """
        #UPDATE `acswui`.`Cars` SET `Parent` = '1', `Brand` = 'dfdfdsf' WHERE `Cars`.`Id` = 1;

        # create query
        set_string = ""
        for key in field_values.keys():
            if len(set_string) > 0:
                set_string += ", "
            set_string += "`" + str(key) + "` = '" + str(field_values[key]) + "'"
        query = "UPDATE `" + tblname + "` SET " + set_string + " WHERE `Id` = " + str(id_value) + ";"

        # execute query
        if self.__verbosity > 2:
            print("  " + query)
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        cursor.close()
        self.__db_handle.commit()
