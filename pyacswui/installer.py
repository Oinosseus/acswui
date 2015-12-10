import argparse
import pymysql
import subprocess


class Installer(object):

    def _dbAppendTable(self, tblname, idxname, idxtype, idxdefault = None, colextra = None):
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

            if colextra is None:
                colextra = ""

            query = "CREATE TABLE IF NOT EXISTS `%s` (`%s` %s NOT NULL %s %s, PRIMARY KEY (`%s`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;" % (tblname, idxname, idxtype, idxdefault, colextra, idxname)
            if self.__args.v > 1:
                print("    " + query)

            # execute query
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            cursor.close()

        # table already exist
        else:
            # ensure to have index column
            self._dbAppendColumn(tblname, idxname,idxtype, idxdefault, colextra)

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




    def _dbAppendColumn(self, tblname, colname, coltype, coldefault = None, colextra = None):

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
            if colextra is not None and colextra.lower() not in res[3].lower():
                column_needs_change = True
        cursor.close()

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
            if self.__args.v > 1:
                print("    " + query)

            # execute query
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            cursor.close()

        # update column
        elif column_needs_change is True:
            # create query
            query = "ALTER TABLE `%s` CHANGE `%s` `%s` %s NOT NULL %s %s;" % (tblname, colname, colname, coltype, coldefault, colextra)
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
            print("check database table `installer`")
        self._dbAppendTable("installer", "timestamp", "timestamp", "CURRENT_TIMESTAMP", "ON UPDATE CURRENT_TIMESTAMP")
        self._dbAppendColumn("installer", "version", "VARCHAR(10)")
        self._dbAppendColumn("installer", "info", "TEXT")

        # insert installer info
        cursor = self.__db_handle.cursor()
        cursor.execute("INSERT INTO `installer` (`version`, `info`) VALUES ('0.1a', '');")
        cursor.close()
        self.__db_handle.commit()

        # check table users
        if self.__args.v > 0:
            print("check database table `Users`")
        self._dbAppendTable("Users", "Id", "int(11)", colextra = "AUTO_INCREMENT")
        self._dbAppendColumn("Users", "Login", "VARCHAR(50)")
        self._dbAppendColumn("Users", "Pasword", "VARCHAR(100)")
        self._dbAppendColumn("Users", "Steam64GUID", "VARCHAR(50)")

        # check table Groups
        if self.__args.v > 0:
            print("check database table `Groups`")
        self._dbAppendTable("Groups", "Id", "int(11)", colextra = "AUTO_INCREMENT")
        self._dbAppendColumn("Groups", "Name", "VARCHAR(50)")

        # check table UserGroupMap
        if self.__args.v > 0:
            print("check database table `UserGroupMap`")
        self._dbAppendTable("UserGroupMap", "Id", "int(11)", colextra = "AUTO_INCREMENT")
        self._dbAppendColumn("UserGroupMap", "User", "int(11)")
        self._dbAppendColumn("UserGroupMap", "Group", "int(11)")

        # check table TrackRating
        if self.__args.v > 0:
            print("check database table `TrackRating`")
        self._dbAppendTable("TrackRating", "Id", "int(11)", colextra = "AUTO_INCREMENT")
        self._dbAppendColumn("TrackRating", "User", "int(11)")
        self._dbAppendColumn("TrackRating", "Track", "int(11)")
        self._dbAppendColumn("TrackRating", "RateGraphics", "int(11)")
        self._dbAppendColumn("TrackRating", "RateDrive", "int(11)")

        # check table UserDriversMap
        if self.__args.v > 0:
            print("check database table `UserDriversMap`")
        self._dbAppendTable("UserDriversMap", "Id", "int(11)", colextra = "AUTO_INCREMENT")
        self._dbAppendColumn("UserDriversMap", "User", "int(11)")
        self._dbAppendColumn("UserDriversMap", "Driver", "int(11)")



        # close database
        self.__db_handle.close()



        # ========================
        #  = Create cConfig.php =
        # ========================

        # user info
        if self.__args.v > 0:
            print("create cConfig.php")

        # encrypt root password
        http_root_password = subprocess.check_output(['php', '-r', 'echo(password_hash("%s", PASSWORD_BCRYPT));' % self.__args.http_root_password])
        http_root_password = str(http_root_password).replace('$', '\$')

        with open(self.__args.http_path + "/classes/cConfig.php", "w") as f:
            f.write("<?php\n")
            f.write("  class cConfig {\n")
            f.write("\n")
            f.write("    private $DefaultTemplate = \"%s\";\n" % self.__args.http_default_template)
            f.write("    private $LogPath = \"%s\";\n" % self.__args.http_log_path)
            f.write("    private $LogDebug = \"true\";\n")
            f.write("    private $RootPassword = \"%s\";\n" % http_root_password)
            f.write("\n")
            f.write("    // this allows read-only access to private properties\n")
            f.write("    public function __get($name) {\n")
            f.write("      return $this->$name;\n")
            f.write("    }\n")
            f.write("  }\n")
            f.write("?>\n")

        # user info
        if self.__args.v > 0:
            print("  done")







