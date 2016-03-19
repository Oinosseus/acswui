import argparse
import pymysql
import subprocess
import shutil
import os


class Installer(object):

    def __init__(self, config, verbosity = 0):

        if type(config) != type({}):
            raise TypeError("Parameter 'config' must be dict!")

        # check http directory
        if 'path_http' not in config or not os.path.isdir(config['path_http']):
            raise NotImplementedError("Http directory '%s' invalid!" % config['path_http'])

        self.__config = {}
        self.__config.update(config)
        self.__verbosity = int(verbosity)



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

            query = "CREATE TABLE IF NOT EXISTS `%s` (`%s` %s NOT NULL %s %s, PRIMARY KEY (`%s`)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;" % (tblname, idxname, idxtype, idxdefault, colextra, idxname)
            if self.__verbosity > 1:
                print("    " + query)

            # execute query
            cursor = self.__db_handle.cursor()
            cursor.execute(query)
            cursor.close()
            self.__db_handle.commit()

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
            self.__db_handle.commit()

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
                self.__db_handle.commit()

        # alter collation
        query = "ALTER TABLE `" + tblname + "` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;"
        if self.__verbosity > 2:
            print("    " + query)
        cursor = self.__db_handle.cursor()
        cursor.execute(query)
        cursor.close()
        self.__db_handle.commit()




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



    def _dbFindIds(self, tblname, where_values):
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
            where += " `" + key + "` = '" + str(where_values[key]) + "'"

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



    def _dbInsertRow(self, tblname, field_values):
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
            values += "'" + str(field_values[key]) + "'"
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



    def _dbUpdateRow(self, tblname, id_value, field_values):
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



    def work(self):
        """
            1. create or update database tables
            2. create cConfig.php
            3. scan cars into database
            4. scan tracks into database
        """


        # ===============================
        #  = Create Database Structure =
        # ===============================

        # try to connect to database
        self.__db_handle = pymysql.connect(host=self.__config['db_host'], port=int(self.__config['db_port']), user=self.__config['db_user'], passwd=self.__config['db_passwd'], db=self.__config['db_database'], charset='utf8')


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
        self._dbAppendColumn("Tracks", "Track", "varchar(80)")
        self._dbAppendColumn("Tracks", "Config", "varchar(80)")
        self._dbAppendColumn("Tracks", "Name", "varchar(80)")
        self._dbAppendColumn("Tracks", "Length", "float")



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
            f.write("    private $DefaultTemplate = \"%s\";\n" % self.__config['http_dflt_tmplt'])
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



        # ===============
        #  = Scan Cars =
        # ===============

        def parse_json(json_file, key_name, default_value):
            ret = default_value
            key_name = '"' + key_name + '":'
            if os.path.isfile(json_file):
                with open(json_file, "r", encoding='utf-8', errors='ignore') as f:
                    for line in f.readlines():
                        if key_name in line.lower():
                            ret = line.split(key_name, 1)[1]
                            ret = ret.strip()
                            if ret[:1] == '"':
                                ret = ret[1:].strip()
                            if ret[-1:] == ',':
                                ret = ret[:-1].strip()
                            if ret[-1:] == '"':
                                ret = ret[:-1]
            return ret

        for car in os.listdir(self.__config['path_http'] + "/acs_content/cars"):
            car_path   = self.__config['path_http'] + "/acs_content/cars/" + car
            car_name   = parse_json(car_path + "/ui/ui_car.json", "name", car)
            car_parent = parse_json(car_path + "/ui/ui_car.json", "parent", "")
            car_brand  = parse_json(car_path + "/ui/ui_car.json", "brand", "")

            # get skins
            car_skins = []
            if os.path.isdir(self.__config['path_http'] + "/acs_content/cars/" + car + "/skins"):
                for skin in os.listdir(self.__config['path_http'] + "/acs_content/cars/" + car + "/skins"):
                    car_skins.append(skin)

            if self.__verbosity > 0:
                print("Install car '" + car + "'")

            # get IDs of existing cars (should be exactly one car)
            existing_car_ids = self._dbFindIds("Cars", {"Car": car})

            # insert car if not existent
            if len(existing_car_ids) == 0:
                self._dbInsertRow("Cars", {"Car": car, "Name": car_name, "Parent": 0, "Brand": car_brand})
                existing_car_ids = self._dbFindIds("Cars", {"Car": car})

            # update all existing cars
            for eci in existing_car_ids:
                self._dbUpdateRow("Cars", eci, {"Car": car, "Name": car_name, "Parent": 0, "Brand": car_brand})

                # insert not existing skins
                for skin in car_skins:
                    existing_car_skins = self._dbFindIds("CarSkins", {"Car": eci, "Skin": skin})
                    if len(existing_car_skins) == 0:
                        self._dbInsertRow("CarSkins", {"Car": eci, "Skin": skin})



        # ================
        #  = Scan Track =
        # ================

        def interpret_length(length):
            ret = ""

            length = str(length.strip())

            # catch decimal number out of string
            sep_found    = False
            for char in length:
                if char in "0123456789":
                    ret += char
                elif len(ret) > 0 and char in ".,":
                    if not sep_found:
                        sep_found = True
                        ret += char
                    else:
                        break
                else:
                    break
            ret = ret.strip()

            # stop if to decimal value could be found
            if len(ret) == 0:
                return 0.0

            # catch unit if length
            unit = length.split(ret, 1)[1].strip()

            # convert length to float
            ret = float(ret.replace(",", "."))

            # interpret unit
            if unit[:2].lower() == "km":
                ret *= 1000.0

            #print("LENGTH before=", length, "after=", ret, "unit=", unit)
            return ret



        for track in os.listdir(self.__config['path_http'] + "/acs_content/tracks"):
            track_path   = self.__config['path_http'] + "/acs_content/tracks/" + track

            if self.__verbosity > 0:
                print("Install track '" + track + "'")

            # update track
            if os.path.isfile(track_path + "/ui/ui_track.json"):
                track_name   = parse_json(track_path + "/ui/ui_track.json", "name", track)
                track_length = parse_json(track_path + "/ui/ui_track.json", "length", "0")
                track_length = interpret_length(track_length)

                existing_track_ids = self._dbFindIds("Tracks", {"Track": track})
                if len(existing_track_ids) == 0:
                    self._dbInsertRow("Tracks", {"Track": track, "Config": "", "Name": track_name, "Length": track_length})

            # update track configs
            if os.path.isdir(track_path + "/ui"):
                for track_config in os.listdir(track_path + "/ui"):
                    if os.path.isdir(track_path + "/ui/" + track_config):
                        if os.path.isfile(track_path + "/ui/" + track_config + "/ui_track.json"):
                            track_name   = parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "name", track)
                            track_length = parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "length", "0")
                            track_length = interpret_length(track_length)

                            existing_track_ids = self._dbFindIds("Tracks", {"Track": track, "Config": track_config})
                            if len(existing_track_ids) == 0:
                                self._dbInsertRow("Tracks", {"Track": track, "Config": track_config, "Name": track_name, "Length": track_length})






        # =================
        #  = Finish Work =
        # =================

        # close database
        self.__db_handle.close()

        # user info
        if self.__verbosity > 0:
            print("  done")
