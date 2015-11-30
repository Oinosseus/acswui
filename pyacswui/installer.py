import argparse
import pymysql


class Installer(object):

    def _dbAppendTable(self, tblname, idxname, idxtype, idxdefault = None, idxonupdate = None):
        """
            Create table if not existent and set index.
        """

        # retype parameters
        if idxdefault is not None:
            idxdefault = "DEFAULT %s" % idxdefault
        else:
            idxdefault = ""

        if idxonupdate is not None:
            idxonupdate = "ON UPDATE %s" % idxonupdate
        else:
            idxonupdate = ""


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

        if table_exist is True:
            return


        # create query
        query = "CREATE TABLE IF NOT EXISTS `%s` (`%s` %s NOT NULL %s %s) ENGINE=InnoDB DEFAULT CHARSET=latin1;" % (tblname, idxname, idxtype, idxdefault, idxonupdate)
        if self.__args.v > 1:
            print("    " + query)

        # execute query
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        cursor.close()


    def _dbAppendColumn(self, tblname, colname, coltype, coldefault = None, colonupdate = None):

        # retype parameters
        if coldefault is not None:
            coldefault = "DEFAULT %s" % coldefault
        else:
            coldefault = ""

        if colonupdate is not None:
            colonupdate = "ON UPDATE %s" % colonupdate
        else:
            colonupdate = ""


        # check if column already exist
        column_exist = False
        query = "SELECT `COLUMN_NAME`, `COLUMN_DEFAULT`, `DATA_TYPE`, `EXTRA` FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '%s' AND TABLE_NAME = '%s' AND COLUMN_NAME = '%s'" % (self.__args.db_database, tblname, colname)
        if self.__args.v > 2:
            print("  " + query)
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        if cursor.rowcount > 0:
            column_exist = True
        cursor.close()

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
        else:
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




