import pymysql
from .verbosity import Verbosity

class Database(object):

    def __init__(self, host, port, database, user, password):

        self.__db_host = str(host)
        self.__db_port = int(port)
        self.__db_database = str(database)
        self.__db_user = str(user)
        self.__db_password = str(password)

        # connect to db_database
        self.__db_handle = pymysql.connect(host=self.__db_host, port=self.__db_port, user=self.__db_user, passwd=self.__db_password, db=self.__db_database, charset='utf8')



    def __del__(self):
        if hasattr(self, "__db_handle"):
            self.__db_handle.close()



    @property
    def Handle(self):
        return self.__db_handle



    def appendTable(self, tblname, primary_keys_list=['Id']):
        """
            Create table if not existent.
            primary_keys_list is a list of strings that define multiple primary keys (of type UINT)

            If primary_keys_list contains only one key, it will be set to AUTO_INCREMENT.
            For multiple primary_keys_list AUTO_INCREMENT is not used.
        """

        # check if table already exist
        table_exist = False
        query = "SELECT `TABLE_NAME` FROM information_schema.TABLES WHERE table_schema = '%s';" % self.__db_database
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        for r in cursor.fetchall():
            if r[0] == tblname:
                table_exist = True
        cursor.close()

        # list of primary keys
        query_primary_keys = []
        for col in primary_keys_list:
            query_primary_keys.append("`" + col + "`")
        query_primary_keys = " , ".join(query_primary_keys)

        # create query
        if table_exist is False:

            query_colum_definitions = []
            for col in primary_keys_list:
                query_colum_definitions.append("`" + col + "` INT NOT NULL")
            query_colum_definitions = " , ".join(query_colum_definitions)
            if len(primary_keys_list) == 1:
                query_colum_definitions += " AUTO_INCREMENT"

            query = "CREATE TABLE `" + tblname + "` ( " + query_colum_definitions + " , PRIMARY KEY (" + query_primary_keys + ")) ENGINE = InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"

            # execute query
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            cursor.close()
            self.__db_handle.commit()

        # table already exist
        else:

            # ensure index columns do exist
            if len(primary_keys_list) > 1:

                # remove AUTO_INCREMENT for all columns
                ai_remove_queries = []
                query = "SHOW COLUMNS FROM `%s`;" % tblname
                cursor = self.__db_handle.cursor()
                cursor.execute(query)
                for r in cursor.fetchall():
                    if "auto_increment" in r[5].lower():
                        default = "" if r[4] is None else r[4]
                        ai_remove_queries.append("ALTER TABLE `" + tblname + "` CHANGE `" + r[0] + "` `" + r[0] + "` " + r[1] + " NOT NULL " + default + ";")
                cursor.close()
                for query in ai_remove_queries:
                    cursor = self.__db_handle.cursor()
                    cursor.execute(query)
                    cursor.close()
                    self.__db_handle.commit()

                # append primary keys as columns
                for col in primary_keys_list:
                    self.appendColumnUInt(tblname, col)

            else:

                # insert primary key as AUTO_INCREMENT column
                primary_key = primary_keys_list[0]
                query = "ALTER TABLE `" + tblname + "` CHANGE `" + primary_key + "` `" + primary_key + "` INT UNSIGNED NOT NULL AUTO_INCREMENT;"
                cursor = self.__db_handle.cursor()
                cursor.execute(query)
                cursor.close()
                self.__db_handle.commit()

            # check index
            any_primary_found = False
            primary_keys_missing = [col for col in primary_keys_list]
            query = "SHOW INDEX FROM %s WHERE Key_name = 'PRIMARY';" % tblname
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            for r in cursor.fetchall():
                if r[2].lower() == "primary":
                    primary_index_found = True
                    col = r[4]
                    if col in primary_keys_missing:
                        primary_keys_missing.pop(primary_keys_missing.index(col))
            cursor.close()
            self.__db_handle.commit()

            if len(primary_keys_missing) > 0:

                if primary_index_found is True:
                    query = "ALTER TABLE `" + tblname + "` DROP PRIMARY KEY, ADD PRIMARY KEY(" + query_primary_keys + ");"
                else:
                    query = "ALTER TABLE `" + tblname + "` ADD PRIMARY KEY(" + query_primary_keys + ");"
                # execute query
                cursor = self.__db_handle.cursor()
                cursor.execute(query)
                cursor.close()
                self.__db_handle.commit()

        ## alter collation
        #query = "ALTER TABLE `" + tblname + "` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;"
        #cursor = self.__db_handle.cursor()
        #cursor.execute(query)
        #cursor.close()
        #self.__db_handle.commit()


    def __appendColumn(self, tblname, colname, coltype, coldefault = None, colextra = None):
        coldefault = str(coldefault)

        # assume column exist already
        column_exist = False
        column_needs_change = False

        # check column info
        query = "SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_DEFAULT`, `EXTRA` FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s';" % (self.__db_database, tblname, colname)
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        if cursor.rowcount > 0:
            column_exist = True
            res = cursor.fetchall()[0]
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
        if coldefault == "":
            coldefault = "DEFAULT ''"
        elif coldefault is not None:
            coldefault = "DEFAULT %s" % coldefault
        else:
            coldefault = ""

        if colextra is None:
            colextra = ""

        # create column
        if column_exist is False:
            # create query
            query = "ALTER TABLE `%s` ADD `%s` %s NOT NULL %s %s;" % (tblname, colname, coltype, coldefault, colextra)

            # execute query
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            cursor.close()
            self.__db_handle.commit()

        # update column
        elif column_needs_change is True:
            # create query
            query = "ALTER TABLE `%s` CHANGE `%s` `%s` %s NOT NULL %s %s;" % (tblname, colname, colname, coltype, coldefault, colextra)

            # execute query
            cursor = self.__db_handle.cursor()
            try:
                cursor.execute(query)
            except BaseException as e:
                print("QUERY:", query)
                raise e
            cursor.close()
            self.__db_handle.commit()



    def appendColumnBool(self, tblname, colname):
        self.__appendColumn(tblname, colname, "bool", "'0'")

    def appendColumnTinyInt(self, tblname, colname):
        self.__appendColumn(tblname, colname, "tinyint", "'0'")

    def appendColumnSmallInt(self, tblname, colname):
        self.__appendColumn(tblname, colname, "smallint", "'0'")

    def appendColumnInt(self, tblname, colname):
        self.__appendColumn(tblname, colname, "int", "'0'")

    def appendColumnUInt(self, tblname, colname):
        self.__appendColumn(tblname, colname, "int unsigned", "'0'")

    def appendColumnFloat(self, tblname, colname, default=None):
        if default:
            self.__appendColumn(tblname, colname, "float", float(default))
        else:
            self.__appendColumn(tblname, colname, "float", "'0'")

    def appendColumnString(self, tblname, colname, length = 100):
        self.__appendColumn(tblname, colname, "varchar(" + str(length) + ")", "''")

    def appendColumnText(self, tblname, colname):
        self.__appendColumn(tblname, colname, "text", "")

    def appendColumnDateTime(self, tblname, colname):
        self.__appendColumn(tblname, colname, "DATETIME", "'0000-00-00 00:00'")

    def appendColumnTimestamp(self, tblname, colname):
        self.__appendColumn(tblname, colname, "TIMESTAMP", "'0000-00-00 00:00'")

    def appendColumnCurrentTimestamp(self, tblname, colname):
        self.__appendColumn(tblname, colname, "TIMESTAMP", "CURRENT_TIMESTAMP")


    def columns(self, tablename):
        ret = []

        query = "SHOW COLUMNS FROM `%s`;" % tablename

        # execute query
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        for res in cursor.fetchall():
            ret.append(res[0])
        cursor.close()

        return ret;


    def findIds(self, tblname, where_dict):
        """
            Returns a list of IDs of all rows that match the where_dict dictionary
        """

        ret = []

        # ignore empty where request
        if len(where_dict) <= 0:
            return ret

        # create WHERE term
        where = ""
        for key in where_dict.keys():
            if len(where) > 0:
                where += " AND"
            where += " `" + key + "` = " + self.__db_handle.escape((where_dict[key]))

        # create query
        query = "SELECT `Id` FROM `" + tblname + "` WHERE " + where + ";";

        # execute query
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        for res in cursor.fetchall():
            ret.append(res[0])
        cursor.close()

        # user info
        self.__db_handle.commit()

        return ret


    def rawQuery(self, query, return_result=False):
        ret = None

        # execute query
        cursor = self.__db_handle.cursor()
        try:
            cursor.execute(query)
        except BaseException as e:
            print("QUERY:", query)
            raise e
        if return_result:
            ret = []
            for res in cursor.fetchall():
                ret.append(res)

        cursor.close()
        self.__db_handle.commit()

        return ret



    def fetch(self, tblname, columns_array, where_dict, sort_by_cloumn=None, order_asc=False):
        """
            Return an array of dictionaries
        """

        ret = []

        # create select term
        if type(columns_array) != type([]):
            columns_array = [columns_array]
        select = "`" + ("`, `".join(columns_array)) + "`"

        # create WHERE term
        where = ""
        for key in where_dict.keys():
            if len(where) > 0:
                where += " AND"
            where += " `" + key + "` = " + self.__db_handle.escape((where_dict[key]))

        # create query
        query = "SELECT " + select + " FROM `" + tblname + "`"
        if len(where) > 0:
            query += " WHERE " + where;
        if sort_by_cloumn is not None:
            query += " ORDER BY `" + sort_by_cloumn + "` "
            query += "ASC" if order_asc else "DESC"
        query += ";"

        # execute query
        cursor = self.__db_handle.cursor()
        try:
            cursor.execute(query)
        except BaseException as e:
            print("QUERY:", query)
            raise e
        for res in cursor.fetchall():
            ret_dict = {}
            for col in columns_array:
                ret_dict[col] = res[columns_array.index(col)]
            ret.append(ret_dict)

        cursor.close()
        self.__db_handle.commit()

        return ret



    def insertRow(self, tblname, field_values):
        """
            Insert new row with values as defined in field_values.
            The functions returns the Id of the inserted row.
        """

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

        # create query
        set_string = ""
        for key in field_values.keys():
            if len(set_string) > 0:
                set_string += ", "
            set_string += "`" + str(key) + "` = " + self.__db_handle.escape(str(field_values[key]))
        query = "UPDATE `" + tblname + "` SET " + set_string + " WHERE `Id` = " + str(id_value) + ";"

        # execute query
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        cursor.close()
        self.__db_handle.commit()



    def deleteRow(self, tblname, id_value):
        """
            Deletes the row with id_value from table tblname
        """
        query = "DELETE FROM `" + tblname + "` WHERE `Id` = " + self.__db_handle.escape(id_value)

        # execute query
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        cursor.close()
        self.__db_handle.commit()


    def deleteColumn(self, tblname, column_name):
        query ="ALTER TABLE `" + tblname + "` DROP `" + column_name + "`"
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        cursor.close()
        self.__db_handle.commit()


    def tables(self):
        ret = []

        query = "SHOW TABLES;"

        # execute query
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        for res in cursor.fetchall():
            ret.append(res[0])
        cursor.close()

        return ret;
