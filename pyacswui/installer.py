import argparse
import pymysql


class Installer(object):

    def _dbAppendTable(self, tblname, idxname, idxtype, idxdefault = None, idxonupdate = None):
        """
            Create table if not existent and set index.
        """

        # check if table already exist
        table_exist = False
        query = "SELECT `TABLE_NAME` FROM information_schema.TABLES WHERE table_schema = '%s';" % self.__args.db_database
        if self.__args.v > 2:
            print("  " + query)
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        for r in cursor.fetchall():
            if r[0] == tblname:
                table_exist = True
        cursor.close()

        # create query
        if table_exist is False:

            # retype parameters
            if idxdefault is not None:
                idxdefault = "DEFAULT %s" % idxdefault
            else:
                idxdefault = ""

            if idxonupdate is not None:
                idxonupdate = "ON UPDATE %s" % idxonupdate
            else:
                idxonupdate = ""

            query = "CREATE TABLE IF NOT EXISTS `%s` (`%s` %s NOT NULL %s %s) ENGINE=InnoDB DEFAULT CHARSET=latin1;" % (tblname, idxname, idxtype, idxdefault, idxonupdate)
            if self.__args.v > 1:
                print("    " + query)

            # execute query
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            cursor.close()

        # table already exist
        else:
            # ensure to have index column
            self._dbAppendColumn(tblname, idxname,idxtype, idxdefault, idxonupdate)

            # check index
            primary_index_found = False
            primary_index_correct = False
            query = "SHOW INDEX FROM %s WHERE Key_name = 'PRIMARY';" % tblname
            if self.__args.v > 2:
                print("  " + query)
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            for r in cursor.fetchall():
                if r[2].lower() == "primary":
                    primary_index_found = True
                    if r[4] == idxname:
                        primary_index_correct = True
            cursor.close()

            if primary_index_correct is False:
                if primary_index_found is True:
                    query = "ALTER TABLE `%s` DROP PRIMARY KEY, ADD PRIMARY KEY(`%s`);" % (tblname, idxname)
                else:
                    query = "ALTER TABLE `%s` ADD PRIMARY KEY(`%s`);" % (tblname, idxname)
                if self.__args.v > 1:
                    print("    " + query)
                # execute query
                cursor = self.__db_handle.cursor()
                cursor.execute(query)
                cursor.close()




    def _dbAppendColumn(self, tblname, colname, coltype, coldefault = None, colonupdate = None):

        # assume column exist already
        column_exist = False
        column_needs_change = False

        # check column info
        query = "SELECT `COLUMN_NAME`, `COLUMN_TYPE`, `COLUMN_DEFAULT`, `EXTRA` FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s';" % (self.__args.db_database, tblname, colname)
        if self.__args.v > 2:
            print("  " + query)
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        if cursor.rowcount > 0:
            column_exist = True
            res = cursor.fetchall()[0]
            if self.__args.v > 2:
                print("    sql result: COLUMN_NAME=%s, COLUMN_TYPE=%s, COLUMN_DEFAULT=%s, EXTRA=%s" % (res[0], res[1], res[2], res[3]))
            if res[0] != colname:
                column_needs_change = True
            if coltype is not None and res[1].lower() != coltype.lower():
                column_needs_change = True
            if coldefault is not None and res[2].lower() != coldefault.lower():
                column_needs_change = True
            if colonupdate is not None and ("ON UPDATE %s" % colonupdate).lower() not in res[3].lower():
                column_needs_change = True
        cursor.close()

        # retype parameters
        if coldefault is not None:
            coldefault = "DEFAULT %s" % coldefault
        else:
            coldefault = ""

        if colonupdate is not None:
            colonupdate = "ON UPDATE %s" % colonupdate
        else:
            colonupdate = ""

        # create column
        if column_exist is False:
            # create query
            query = "ALTER TABLE `%s` ADD `%s` %s NOT NULL %s %s;" % (tblname, colname, coltype, coldefault, colonupdate)
            if self.__args.v > 1:
                print("    " + query)

            # execute query
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            cursor.close()

        # update column
        elif column_needs_change is True:
            # create query
            query = "ALTER TABLE `%s` CHANGE `%s` `%s` %s NOT NULL %s %s;" % (tblname, colname, colname, coltype, coldefault, colonupdate)
            if self.__args.v > 1:
                print("    " + query)

            # execute query
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            cursor.close()



    def work(self, args):

        self.__args = args


        # ===============================
        #  = Create Database Structure =
        # ===============================

        # try to connect to database
        self.__db_handle = pymysql.connect(host=args.db_host, port=int(args.db_port), user=args.db_user, passwd=args.db_passwd, db=args.db_database)


        # check table installer
        if self.__args.v > 0:
            print("append database table `installer`")
        self._dbAppendTable("installer", "timestamp", "timestamp", "CURRENT_TIMESTAMP", "CURRENT_TIMESTAMP")
        self._dbAppendColumn("installer", "version", "VARCHAR(10)")
        self._dbAppendColumn("installer", "info", "TEXT")




