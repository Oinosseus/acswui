import argparse
import pymysql
import subprocess
import shutil
import os


class Installer(object):

    def _dbAppendTable(self, tblname, idxname, idxtype, idxdefault = None, colextra = None):
        """
            Create table if not existent and set index.
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

            # retype parameters
            if idxdefault is not None:
                idxdefault = "DEFAULT %s" % idxdefault
            else:
                idxdefault = ""

            if colextra is None:
                colextra = ""

            query = "CREATE TABLE IF NOT EXISTS `%s` (`%s` %s NOT NULL %s %s, PRIMARY KEY (`%s`)) ENGINE=InnoDB DEFAULT CHARSET=latin1;" % (tblname, idxname, idxtype, idxdefault, colextra, idxname)
            if self.__verbosity > 1:
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
            if self.__verbosity > 2:
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
                if self.__verbosity > 1:
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
            if self.__verbosity > 1:
                print("    " + query)

            # execute query
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            cursor.close()

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



    def work(self, config, verbosity = 0):



        # ========================
        #  = Input Sanity Check =
        # ========================

        if type(config) != type({}):
            raise TypeError("Parameter 'config' must be dict!")
        self.__config = config

        # check http directory
        if 'path_http' not in config or not os.path.isdir(config['path_http']):
            raise NotImplementedError("Http directory '%s' invalid!" % config['path_http'])

        self.__verbosity = verbosity



        # ===============================
        #  = Create Database Structure =
        # ===============================

        # try to connect to database
        self.__db_handle = pymysql.connect(host=self.__config['db_host'], port=int(self.__config['db_port']), user=self.__config['db_user'], passwd=self.__config['db_passwd'], db=self.__config['db_database'])


        # ------------
        #  red tables

        # check table installer
        if self.__verbosity > 0:
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
        if self.__verbosity > 0:
            print("check database table `Users`")
        self._dbAppendTable("Users", "Id", "int(11)", colextra = "AUTO_INCREMENT")
        self._dbAppendColumn("Users", "Login", "VARCHAR(50)")
        self._dbAppendColumn("Users", "Password", "VARCHAR(100)")
        self._dbAppendColumn("Users", "Steam64GUID", "VARCHAR(50)")

        # check table Groups
        if self.__verbosity > 0:
            print("check database table `Groups`")
        self._dbAppendTable("Groups", "Id", "int(11)", colextra = "AUTO_INCREMENT")
        self._dbAppendColumn("Groups", "Name", "VARCHAR(50)")

        # check table UserGroupMap
        if self.__verbosity > 0:
            print("check database table `UserGroupMap`")
        self._dbAppendTable("UserGroupMap", "Id", "int(11)", colextra = "AUTO_INCREMENT")
        self._dbAppendColumn("UserGroupMap", "User", "int(11)")
        self._dbAppendColumn("UserGroupMap", "Group", "int(11)")

        # check table TrackRating
        if self.__verbosity > 0:
            print("check database table `TrackRating`")
        self._dbAppendTable("TrackRating", "Id", "int(11)", colextra = "AUTO_INCREMENT")
        self._dbAppendColumn("TrackRating", "User", "int(11)")
        self._dbAppendColumn("TrackRating", "Track", "int(11)")
        self._dbAppendColumn("TrackRating", "RateGraphics", "int(11)")
        self._dbAppendColumn("TrackRating", "RateDrive", "int(11)")

        # check table UserDriversMap
        if self.__verbosity > 0:
            print("check database table `UserDriversMap`")
        self._dbAppendTable("UserDriversMap", "Id", "int(11)", colextra = "AUTO_INCREMENT")
        self._dbAppendColumn("UserDriversMap", "User", "int(11)")
        self._dbAppendColumn("UserDriversMap", "Driver", "int(11)")


        # -------------
        #  grey tables

        # check table Cars
        if self.__verbosity > 0:
            print("check database table `Cars`")
        self._dbAppendTable("Cars", "Id", "int(11)", colextra = "AUTO_INCREMENT")
        self._dbAppendColumn("Cars", "Car", "varchar(80)")
        self._dbAppendColumn("Cars", "Name", "varchar(80)")
        self._dbAppendColumn("Cars", "Parent", "int(11)")
        self._dbAppendColumn("Cars", "Brand", "varchar(80)")

        # check table CarSkins
        if self.__verbosity > 0:
            print("check database table `CarSkins`")
        self._dbAppendTable("CarSkins", "Id", "int(11)", colextra = "AUTO_INCREMENT")
        self._dbAppendColumn("CarSkins", "Car", "int(11)")
        self._dbAppendColumn("CarSkins", "Skin", "varchar(50)")

        # check table Tracks
        if self.__verbosity > 0:
            print("check database table `Tracks`")
        self._dbAppendTable("Tracks", "Id", "int(11)", colextra = "AUTO_INCREMENT")
        self._dbAppendColumn("Tracks", "Name", "varchar(80)")
        self._dbAppendColumn("Tracks", "Skin", "varchar(50)")
        self._dbAppendColumn("Tracks", "Length", "float")



        # ===============
        #  = Scan Cars =
        # ===============

        for car in os.listdir(self.__config['path_http'] + "/acs_content/cars"):
            pass
            # FIXME - scan all cars and put them to database

            # skip all non-directories or hidden items
            if car[:1] == "." or not os.path.isdir(self.__config['path_ac '] + "/content/cars/" + car):
                continue

            # user info
            if verbosity > 0:
                print("cars/" + car)

            ## create http car directory
            #self._mkdirs(self.__config['path_http'] + "/acs_content/cars/" + car)

            ## scan all skins
            #if os.path.isdir(args.path_ac + "/content/cars/" + car + "/skins"):
                #for skin in os.listdir(args.path_ac + "/content/cars/" + car + "/skins"):
                    ## if preview image present
                    #if os.path.isfile(args.path_ac + "/content/cars/" + car + "/skins/" + skin + "/preview.jpg"):
                        ## create server skin directory
                        #self._mkdirs(self.__config['path_http'] + "/acs_content/cars/" + car + "/skins/" + skin)
                        ## copy preview image
                        #shutil.copy(args.path_ac + "/content/cars/" + car + "/skins/" + skin + "/preview.jpg", args.path_ac + "/acs_content/cars/" + car + "/skins/" + skin + "/preview.jpg")
                        ## resize image
                        #self._sizeImage(args.path_ac + "/acs_content/cars/" + car + "/skins/" + skin + "/preview.jpg")





        # ========================
        #  = Create cConfig.php =
        # ========================

        # user info
        if self.__verbosity > 0:
            print("create cConfig.php")

        # encrypt root password
        http_root_password = subprocess.check_output(['php', '-r', 'echo(password_hash("%s", PASSWORD_BCRYPT));' % self.__config['http_root_passwd']])
        http_root_password = http_root_password.decode("utf-8")

        with open(self.__config['path_http'] + "/classes/cConfig.php", "w") as f:
            f.write("<?php\n")
            f.write("  class cConfig {\n")
            f.write("\n")
            f.write("    // basic constants\n")
            f.write("    private $DefaultTemplate = \"%s\";\n" % self.__config['http_default_template'])
            f.write("    private $LogPath = '%s';\n" % self.__config['http_log_path'])
            f.write("    private $LogDebug = \"false\";\n")
            f.write("    private $RootPassword = '%s';\n" % http_root_password)
            f.write("\n")
            f.write("    // database constants\n")
            f.write("    private $DbType = \"%s\";\n" % self.__config['db_type'])
            f.write("    private $DbHost = \"%s\";\n" % self.__config['db_host'])
            f.write("    private $DbDatabase = \"%s\";\n" % self.__config['db_database'])
            f.write("    private $DbPort = \"%s\";\n" % self.__config['db_port'])
            f.write("    private $DbUser = \"%s\";\n" % self.__config['db_user'])
            f.write("    private $DbPasswd = \"%s\";\n" % self.__config['db_passwd'])
            f.write("\n")
            f.write("    // this allows read-only access to private properties\n")
            f.write("    public function __get($name) {\n")
            f.write("      return $this->$name;\n")
            f.write("    }\n")
            f.write("  }\n")
            f.write("?>\n")



        # =================
        #  = Finish Work =
        # =================

        # close database
        self.__db_handle.close()

        # user info
        if self.__verbosity > 0:
            print("  done")
