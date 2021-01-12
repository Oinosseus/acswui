import argparse
import subprocess
import shutil
import os
import json
import re
import pymysql
from .command import Command, ArgumentException
from .database import Database
from .verbosity import Verbosity

class CommandInstallHttp(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "install-http", "install database and files to http target directory")

        ##################
        # Basic Arguments

        # database
        self.add_argument('--db-host', help="Database host (not needed when global config is given)")
        self.add_argument('--db-port', help="Database port (not needed when global config is given)")
        self.add_argument('--db-database', help="Database name (not needed when global config is given)")
        self.add_argument('--db-user', help="Database username (not needed when global config is given)")
        self.add_argument('--db-password', help="Database password (not needed when global config is given)")

        # http settings
        self.add_argument('--http-path', help="Target directory of http server")
        self.add_argument('--http-log-path', help="Directory for http logfiles")
        self.add_argument('--http-root-password', help="Password for root user")
        self.add_argument('--http-guest-group', default="Visitor", help="Group name of visitors that are not logged in")
        self.add_argument('--http-default-tmplate', default="acswui", help="Default template for http")
        self.add_argument('--http-guid', help="Name of webserver group on the server (needed to chmod access rights)")

        self.add_argument('--path-acs-target', help="path to AC server target directory")
        self.add_argument('--install-base-data', action="store_true", help="install basic http data (default groups, etc.)")
        self.add_argument('--http-path-acs-content', help="Path that stores AC data for http access (eg. track car preview images)")



    def process(self):

        # read server_cfg json
        with open(os.path.join(self.getArg("http-path-acs-content"), "server_cfg.json"), "r") as f:
            json_string = f.read()
        self.__server_cfg_json = json.loads(json_string)

        # setup database
        self.__db = Database(host=self.getArg("db_host"),
                             port=self.getArg("db_port"),
                             database=self.getArg("db_database"),
                             user=self.getArg("db_user"),
                             password=self.getArg("db_password"),
                             verbosity=Verbosity(Verbosity(self.Verbosity))
                             )

        # install work
        self.__work_copy_files()
        self.__work_db_tables()
        self.__work_cconfig()
        self.__work_scan_cars()
        self.__work_scan_tracks()

        if self.getArg("install_base_data"):
            self.__work_install_basics()

        # create log directory
        if not os.path.isdir(self.getArg("http_log_path")):
            os.mkdir(self.getArg("http_log_path"))

        self.__set_chmod()


    def dict2php(self, d):

        list_php_elements= []

        for key in d.keys():
            value = d[key]

            if type(value) == type({}):
                value = self.dict2php(value)
            elif type(value) == type([]):
                raise NotImplementedError("please implement list2php()")
            else:
                value = str(value)

            list_php_elements.append("\"" + str(key) + "\"=>" + value)

        return "array(" + (",".join(list_php_elements)) + ")"



    def __parse_json(self, json_file, key_name, default_value):
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



    def __work_copy_files(self):

        self.Verbosity.print("Create http target directory")
        verb2 = Verbosity(self.Verbosity)
        verb3 = Verbosity(verb2)

        path_http = os.path.abspath(self.getArg("http-path"))
        if not os.path.isdir(path_http):
            verb2.print("create http target directory: " + path_http)
            self.mkdirs(path_http)

        verb2.print("copy http directory: " + path_http)
        http_src = os.path.join(os.path.abspath(os.path.dirname(__file__)), "..", "http")
        self.copytree(http_src, self.getArg("http-path"))



    def __work_db_tables(self):

        self.Verbosity.print("Create database tables")

        # ---------------------------------------------------------------------
        #                           Grey Tables
        # ---------------------------------------------------------------------

        # check table installer
        Verbosity(self.Verbosity).print("check database table `installer`")
        self.__db.appendTable("installer")
        self.__db.appendColumnCurrentTimestamp("installer", "timestamp")
        self.__db.appendColumnString("installer", "version", 10)
        self.__db.appendColumnText("installer", "info")

        # insert installer info
        self.__db.insertRow("installer", {"version": "0.0", "info": ""})


        # check table ServerPresets
        Verbosity(self.Verbosity).print("check database table `ServerPresets`")
        self.__db.appendTable("ServerPresets")
        self.__db.appendColumnString("ServerPresets", "Name", 60)

        for section in self.__server_cfg_json:
            for fieldset in self.__server_cfg_json[section]:
                for tag in self.__server_cfg_json[section][fieldset]:
                    tag_dict = self.__server_cfg_json[section][fieldset][tag]
                    db_col_name = tag_dict['DB_COLUMN_NAME']

                    if tag_dict['TYPE'] == "string":
                        self.__db.appendColumnString("ServerPresets", db_col_name, tag_dict['SIZE'])
                    elif tag_dict['TYPE'] in ["int", "enum"]:
                        self.__db.appendColumnInt("ServerPresets", db_col_name)
                    elif tag_dict['TYPE'] == "text":
                        self.__db.appendColumnText("ServerPresets", db_col_name)
                    else:
                        print("db_col_name =", db_col_name)
                        raise NotImplementedError("Unknown field TYPE '%s'" % tag_dict['TYPE'])



        # ---------------------------------------------------------------------
        #                           Mustard Tables
        # ---------------------------------------------------------------------

        # check table Sessions
        Verbosity(self.Verbosity).print("check database table `Sessions`")
        self.__db.appendTable("Sessions")
        self.__db.appendColumnUInt("Sessions", "Predecessor")
        self.__db.appendColumnInt("Sessions", "ProtocolVersion")
        self.__db.appendColumnInt("Sessions", "SessionIndex")
        self.__db.appendColumnInt("Sessions", "CurrentSessionIndex")
        self.__db.appendColumnInt("Sessions", "SessionCount")
        self.__db.appendColumnString("Sessions", 'ServerName', 50)
        self.__db.appendColumnUInt("Sessions", "Track")
        self.__db.appendColumnString("Sessions", 'Name', 50)
        self.__db.appendColumnInt("Sessions", "Type")
        self.__db.appendColumnInt("Sessions", "Time")
        self.__db.appendColumnInt("Sessions", "Laps")
        self.__db.appendColumnInt("Sessions", "WaitTime")
        self.__db.appendColumnInt("Sessions", "TempAmb")
        self.__db.appendColumnInt("Sessions", "TempRoad")
        self.__db.appendColumnString("Sessions", 'WheatherGraphics', 50)
        self.__db.appendColumnInt("Sessions", "Elapsed")
        self.__db.appendColumnCurrentTimestamp("Sessions", "Timestamp")

        # check table SessionResults
        Verbosity(self.Verbosity).print("check database table `SessionResults`")
        self.__db.appendTable("SessionResults")
        self.__db.appendColumnSmallInt("SessionResults", "Position")
        self.__db.appendColumnUInt("SessionResults", "Session")
        self.__db.appendColumnUInt("SessionResults", "User")
        self.__db.appendColumnUInt("SessionResults", "CarSkin")
        self.__db.appendColumnUInt("SessionResults", "BestLap")
        self.__db.appendColumnUInt("SessionResults", "TotalTime")
        self.__db.appendColumnSmallInt("SessionResults", "Ballast")
        self.__db.appendColumnTinyInt("SessionResults", "Restrictor")

        # check table Laps
        Verbosity(self.Verbosity).print("check database table `Laps`")
        self.__db.appendTable("Laps")
        self.__db.appendColumnUInt("Laps", "Session")
        self.__db.appendColumnUInt("Laps", "CarSkin")
        self.__db.appendColumnUInt("Laps", "User")
        self.__db.appendColumnUInt("Laps", "Laptime")
        self.__db.appendColumnInt("Laps", "Cuts")
        self.__db.appendColumnFloat("Laps", "Grip")
        self.__db.appendColumnSmallInt("Laps", "Ballast")
        self.__db.appendColumnTinyInt("Laps", "Restrictor")
        self.__db.appendColumnCurrentTimestamp("Laps", "Timestamp")

        # check table CollisionEnv
        Verbosity(self.Verbosity).print("check database table `CollisionEnv`")
        self.__db.appendTable("CollisionEnv")
        self.__db.appendColumnUInt("CollisionEnv", "Session")
        self.__db.appendColumnUInt("CollisionEnv", "CarSkin")
        self.__db.appendColumnUInt("CollisionEnv", "User")
        self.__db.appendColumnFloat("CollisionEnv", "Speed")
        self.__db.appendColumnCurrentTimestamp("CollisionEnv", "Timestamp")

        # check table CollisionCar
        Verbosity(self.Verbosity).print("check database table `CollisionCar`")
        self.__db.appendTable("CollisionCar")
        self.__db.appendColumnUInt("CollisionCar", "Session")
        self.__db.appendColumnUInt("CollisionCar", "CarSkin")
        self.__db.appendColumnUInt("CollisionCar", "User")
        self.__db.appendColumnFloat("CollisionCar", "Speed")
        self.__db.appendColumnUInt("CollisionCar", "OtherUser")
        self.__db.appendColumnUInt("CollisionCar", "OtherCarSkin")
        self.__db.appendColumnCurrentTimestamp("CollisionCar", "Timestamp")



        # ---------------------------------------------------------------------
        #                           Blue Tables
        # ---------------------------------------------------------------------

        # check table Tracks
        Verbosity(self.Verbosity).print("check database table `Tracks`")
        self.__db.appendTable("Tracks")
        self.__db.appendColumnString("Tracks", "Track", 80)
        self.__db.appendColumnString("Tracks", "Config", 80)
        self.__db.appendColumnString("Tracks", "Name", 80)
        self.__db.appendColumnUInt("Tracks", "Length")
        self.__db.appendColumnInt("Tracks", "Pitboxes")
        self.__db.appendColumnTinyInt("Tracks", "Deprecated")

        # check table Cars
        Verbosity(self.Verbosity).print("check database table `Cars`")
        self.__db.appendTable("Cars")
        self.__db.appendColumnString("Cars", "Car", 80)
        self.__db.appendColumnString("Cars", "Name", 80)
        self.__db.appendColumnInt("Cars", "Parent")
        self.__db.appendColumnString("Cars", "Brand", 80)
        self.__db.appendColumnTinyInt("Cars", "Deprecated")

        # check table CarSkins
        Verbosity(self.Verbosity).print("check database table `CarSkins`")
        self.__db.appendTable("CarSkins")
        self.__db.appendColumnUInt("CarSkins", "Car")
        self.__db.appendColumnString("CarSkins", "Skin", 50)
        self.__db.appendColumnTinyInt("CarSkins", "Deprecated")



        # ---------------------------------------------------------------------
        #                           Red Tables
        # ---------------------------------------------------------------------

        # check table users
        Verbosity(self.Verbosity).print("check database table `Users`")
        self.__db.appendTable("Users")
        self.__db.appendColumnString("Users", "Login", 50)
        self.__db.appendColumnString("Users", "Password", 100)
        self.__db.appendColumnString("Users", "Steam64GUID", 50)

        # check table Groups
        Verbosity(self.Verbosity).print("check database table `Groups`")
        self.__db.appendTable("Groups")
        self.__db.appendColumnString("Groups", "Name", 50)

        # check table UserGroupMap
        Verbosity(self.Verbosity).print("check database table `UserGroupMap`")
        self.__db.appendTable("UserGroupMap")
        self.__db.appendColumnUInt("UserGroupMap", "User")
        self.__db.appendColumnUInt("UserGroupMap", "Group")



        # ---------------------------------------------------------------------
        #                           Purple Tables
        # ---------------------------------------------------------------------

        # check table CarClasses
        Verbosity(self.Verbosity).print("check database table `CarClasses`")
        self.__db.appendTable("CarClasses")
        self.__db.appendColumnString("CarClasses", 'Name', 50)

        # check table CarClassesMap
        Verbosity(self.Verbosity).print("check database table `CarClassesMap`")
        self.__db.appendTable("CarClassesMap")
        self.__db.appendColumnUInt("CarClassesMap", 'CarClass')
        self.__db.appendColumnUInt("CarClassesMap", 'Car')
        self.__db.appendColumnSmallInt("CarClassesMap", 'Ballast')
        self.__db.appendColumnSmallInt("CarClassesMap", 'Restrictor')

        # check table CarClassOccupationMap
        Verbosity(self.Verbosity).print("check database table `CarClassOccupationMap`")
        self.__db.appendTable("CarClassOccupationMap")
        self.__db.appendColumnUInt("CarClassOccupationMap", 'CarClass')
        self.__db.appendColumnUInt("CarClassOccupationMap", 'User')
        self.__db.appendColumnUInt("CarClassOccupationMap", 'CarSkin')



        # ---------------------------------------------------------------------
        #                           Brown Tables
        # ---------------------------------------------------------------------

        # check table RacePollDates
        Verbosity(self.Verbosity).print("check database table `RacePollDates`")
        self.__db.appendTable("RacePollDates")
        self.__db.appendColumnDateTime("RacePollDates", 'Date')
        self.__db.appendColumnUInt("RacePollDates", 'User')

        # check table RacePollDateMap
        Verbosity(self.Verbosity).print("check database table `RacePollDateMap`")
        self.__db.appendTable("RacePollDateMap")
        self.__db.appendColumnUInt("RacePollDateMap", 'User')
        self.__db.appendColumnUInt("RacePollDateMap", 'Date')
        self.__db.appendColumnInt("RacePollDateMap", 'Availability')

        # check table RacePollCarClasses
        Verbosity(self.Verbosity).print("check database table `RacePollCarClasses`")
        self.__db.appendTable("RacePollCarClasses")
        self.__db.appendColumnUInt("RacePollCarClasses", 'User')
        self.__db.appendColumnUInt("RacePollCarClasses", 'CarClass')
        self.__db.appendColumnUInt("RacePollCarClasses", 'Score')

        # check table RacePollTracks
        Verbosity(self.Verbosity).print("check database table `RacePollTracks`")
        self.__db.appendTable("RacePollTracks")
        self.__db.appendColumnUInt("RacePollTracks", 'Track')
        self.__db.appendColumnUInt("RacePollTracks", 'CarClass')

        # check table RacePollTrackMap
        Verbosity(self.Verbosity).print("check database table `RacePollTrackMap`")
        self.__db.appendTable("RacePollTrackMap")
        self.__db.appendColumnUInt("RacePollTrackMap", 'User')
        self.__db.appendColumnUInt("RacePollTrackMap", 'Track')
        self.__db.appendColumnUInt("RacePollTrackMap", 'Score')



    def __work_cconfig(self):
        self.Verbosity.print("create cConfig.php")

        # encrypt root password
        http_root_password = subprocess.check_output(['php', '-r', 'echo(password_hash("%s", PASSWORD_BCRYPT));' % self.getArg('http_root_password')])
        http_root_password = http_root_password.decode("utf-8")

        # determine reltive path from http to ac server
        path_current = os.path.abspath(os.curdir)
        path_acserverdir = os.path.abspath(os.path.join(path_current, self.getArg('path-acs-target')))

        # path to this command
        path_http2cmd = os.path.abspath(os.path.join(path_current, "acswui.py"))

        # path for logs
        path_log = os.path.abspath(self.getArg('http_log_path'))

        # path for acs-content
        path_http = os.path.abspath(self.getArg("http-path"))
        path_acscontent_absolute = os.path.abspath(self.getArg("http-path-acs-content"))
        path_acscontent_relative = os.path.relpath(path_acscontent_absolute, path_http)

        # server slots
        server_slot_list = []
        slot_nr = 0
        while True:
            slot_dict = self.getIniSection("SERVER_SLOT_" + str(slot_nr))
            if slot_dict is None:
                break
            slot_php_array = [];

            for section in self.__server_cfg_json:
                for fieldset in self.__server_cfg_json[section]:
                    for tag in self.__server_cfg_json[section][fieldset]:
                        tag_dict = self.__server_cfg_json[section][fieldset][tag]
                        db_col_name = tag_dict['DB_COLUMN_NAME']

                        # check for fixed values
                        if db_col_name in slot_dict:
                            slot_php_array.append("'%s'=>\"%s\"" % (db_col_name, slot_dict[db_col_name]))

            slot_php_array = "[" + (", ".join(slot_php_array)) + "]";
            server_slot_list.append(slot_php_array)
            slot_nr += 1
        server_slots = ",\n                                 ".join(server_slot_list)

        # fixed server settings
        fixed_server_settings = []
        for section in self.__server_cfg_json:
            for fieldset in self.__server_cfg_json[section]:
                for tag in self.__server_cfg_json[section][fieldset]:
                    tag_dict = self.__server_cfg_json[section][fieldset][tag]
                    db_col_name = tag_dict['DB_COLUMN_NAME']

                    # check for fixed values
                    try:
                        val = self.getIniSection("FIXED_SERVER_SETTINGS")[db_col_name]
                    except KeyError as e:
                        val = None

                    if val is not None:
                        fixed_server_settings.append("\"%s\"=>\"%s\"" % (db_col_name, val))
        fixed_server_settings = ",".join(fixed_server_settings)

        # driver ranking
        driver_ranking = {"XP":{}, "SX":{}, "SF":{}, "DEF":{}}
        driver_ranking_from_ini = self.getIniSection("DRIVER_RANKING")
        for drfi_key in driver_ranking_from_ini.keys():
            value = driver_ranking_from_ini[drfi_key]
            group, key = drfi_key.upper().split("_")
            driver_ranking[group][key] = value


        with open(self.getArg('http_path') + "/classes/cConfig.php", "w") as f:
            f.write("<?php\n")
            f.write("  class cConfig {\n")
            f.write("\n")
            f.write("    // basic constants\n")
            f.write("    private $DefaultTemplate = \"%s\";\n" % self.getArg('http_default_tmplate'))
            f.write("    private $LogPath = '%s';\n" % path_log)
            f.write("    private $LogDebug = \"false\";\n")
            f.write("    private $RootPassword = '%s';\n" % http_root_password)
            f.write("    private $GuestGroup = '%s';\n" % self.getArg('http_guest_group'))
            f.write("    private $AcServerPath = \"%s\";\n" % path_acserverdir)
            f.write("    private $AcswuiCmdDir = \"%s\";\n" % path_current)
            f.write("    private $AcswuiCmd = \"%s\";\n" % path_http2cmd)
            f.write("\n")
            f.write("    // database constants\n")
            f.write("    private $DbHost = \"%s\";\n" % self.getArg('db_host'))
            f.write("    private $DbDatabase = \"%s\";\n" % self.getArg('db_database'))
            f.write("    private $DbPort = \"%s\";\n" % self.getArg('db_port'))
            f.write("    private $DbUser = \"%s\";\n" % self.getArg('db_user'))
            f.write("    private $DbPasswd = \"%s\";\n" % self.getArg('db_password'))
            f.write("\n")
            f.write("    // server_cfg\n")
            f.write("    private $AcsContent = \"%s\";\n" % path_acscontent_relative)
            f.write("    private $AcsContentAbsolute = \"%s\";\n" % path_acscontent_absolute)
            f.write("    private $FixedServerConfig = array(%s);\n" % fixed_server_settings)
            f.write("    private $ServerSlots = array(%s);\n" % server_slots)
            f.write("\n")
            f.write("    // misc\n")
            f.write("    private $DriverRanking = %s;\n" % self.dict2php(driver_ranking))
            f.write("\n")
            f.write("    // this allows read-only access to private properties\n")
            f.write("    public function __get($name) {\n")
            f.write("      return $this->$name;\n")
            f.write("    }\n")
            f.write("  }\n")
            f.write("?>\n")



    def __work_scan_cars(self):
        self.Verbosity.print("scanning for cars")

        # set all current cars and skins to 'deprecated'
        self.__db.rawQuery("UPDATE Cars SET Deprecated=1 WHERE Deprecated=0")
        self.__db.rawQuery("UPDATE CarSkins SET Deprecated=1 WHERE Deprecated=0")

        for car in sorted(os.listdir(self.getArg('http-path-acs-content') + "/content/cars")):
            car_path   = self.getArg('http-path-acs-content') + "/content/cars/" + car
            car_name   = self.__parse_json(car_path + "/ui/ui_car.json", "name", car)
            car_parent = self.__parse_json(car_path + "/ui/ui_car.json", "parent", "")
            car_brand  = self.__parse_json(car_path + "/ui/ui_car.json", "brand", "")

            # get skins
            car_skins = []
            if os.path.isdir(self.getArg('http-path-acs-content') + "/content/cars/" + car + "/skins"):
                for skin in os.listdir(self.getArg('http-path-acs-content') + "/content/cars/" + car + "/skins"):
                    if skin == "":
                        raise NotImplementedError("Unexpected empty skin name for car '%s'" % car)
                    car_skins.append(skin)


            # get IDs of existing cars (should be exactly one car)
            existing_car_ids = self.__db.findIds("Cars", {"Car": car})

            # insert car if not existent
            if len(existing_car_ids) == 0:
                self.__db.insertRow("Cars", {"Car": car, "Name": car_name, "Parent": 0, "Brand": car_brand, "Deprecated":0})
                existing_car_ids = self.__db.findIds("Cars", {"Car": car})
                Verbosity(self.Verbosity).print("Found new car '" + car + "'")

            # update all existing cars
            for eci in existing_car_ids:
                self.__db.updateRow("Cars", eci, {"Car": car, "Name": car_name, "Parent": 0, "Brand": car_brand, "Deprecated":0})

                # insert not existing skins
                added_skins = 0
                for skin in car_skins:
                    existing_car_skins = self.__db.findIds("CarSkins", {"Car": eci, "Skin": skin})
                    if len(existing_car_skins) == 0:
                        self.__db.insertRow("CarSkins", {"Car": eci, "Skin": skin, "Deprecated":0})
                        added_skins += 1
                    else:
                        for skin_id in existing_car_skins:
                            self.__db.updateRow("CarSkins", skin_id, {"Deprecated":0})



    def __work_scan_tracks(self):
        self.Verbosity.print("Scanning for tracks")

        # set all current trakcs to 'deprecated'
        self.__db.rawQuery("UPDATE Tracks SET Deprecated=1 WHERE Deprecated=0")

        REGEX_COMP_TRACKLENGTH = re.compile("([0-9]*[,\.]?[0-9]*)\s*(m|km)?(.*)")
        def interpret_length(length):
            ret = ""

            match = REGEX_COMP_TRACKLENGTH.match(length)
            if not match:
                raise ValueError("Could not extract length information from string '%s'!\nCheck ui_track.json" % length)

            #print("MATCH:", "'", match.group(1), "'", match.group(2), "'", match.group(3))
            length = match.group(1)
            if length == "":
                length = "0"
            length = length.replace(",", ".")
            length = float(length)
            unit = match.group(2)
            rest = match.group(3)

            if unit == "km":
                length *= 1000
            #print("MATCH:", length, unit, "//", rest)

            # Guessing when a track is less than 100m the length was desired to be in [km]
            # workaround for tracks with wrong comma setting
            if length < 100:
                length *= 1000

            return length


        def interpret_pitboxes(pitbxs):
            ret = 0
            for char in pitbxs:
                if char in "0123456789":
                    ret *= 10
                    ret += int(char)
                else:
                    break
            return ret



        for track in sorted(os.listdir(self.getArg('http-path-acs-content') + "/content/tracks")):
            track_path   = self.getArg('http-path-acs-content') + "/content/tracks/" + track


            # update track
            if os.path.isfile(track_path + "/ui/ui_track.json"):
                track_name   = self.__parse_json(track_path + "/ui/ui_track.json", "name", track)
                track_length = self.__parse_json(track_path + "/ui/ui_track.json", "length", "0")
                track_length = interpret_length(track_length)
                track_pitbxs = interpret_pitboxes(self.__parse_json(track_path + "/ui/ui_track.json", "pitboxes", "0"))

                existing_track_ids = self.__db.findIds("Tracks", {"Track": track})
                if len(existing_track_ids) == 0:
                    self.__db.insertRow("Tracks", {"Track": track, "Config": "", "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs, "Deprecated":0})
                    Verbosity(self.Verbosity).print("Found new track '" + track + "'")
                else:
                    self.__db.updateRow("Tracks", existing_track_ids[0], {"Track": track, "Config": "", "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs, "Deprecated":0})

            # update track configs
            if os.path.isdir(track_path + "/ui"):
                for track_config in os.listdir(track_path + "/ui"):
                    if os.path.isdir(track_path + "/ui/" + track_config):
                        if os.path.isfile(track_path + "/ui/" + track_config + "/ui_track.json"):
                            track_name   = self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "name", track)
                            track_length = self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "length", "0")
                            track_length = interpret_length(track_length)
                            track_pitbxs = interpret_pitboxes(self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "pitboxes", "0"))

                            existing_track_ids = self.__db.findIds("Tracks", {"Track": track, "Config": track_config})
                            if len(existing_track_ids) == 0:
                                self.__db.insertRow("Tracks", {"Track": track, "Config": track_config, "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs, "Deprecated":0})
                            else:
                                self.__db.updateRow("Tracks", existing_track_ids[0], {"Track": track, "Config": track_config, "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs, "Deprecated":0})



    def __work_install_basics(self):
        self.Verbosity.print("Install base data")

        # add guest group
        try:
            guest_group = self.getArg("http_guest_group")
        except ArgumentException as e:
            guest_group = ""

        if len(guest_group) > 0:
            if len(self.__db.findIds("Groups", {"Name": guest_group})) == 0:
                Verbosity(self.Verbosity).print("Create guest group '%s'" % guest_group)
                self.__db.insertRow("Groups", {"Name": guest_group})

        # default groups
        if len(self.__db.findIds("Groups", {"Name": "Driver"})) == 0:
            Verbosity(self.Verbosity).print("Create group 'Driver")
            self.__db.insertRow("Groups", {"Name": "Driver"})
        if len(self.__db.findIds("Groups", {"Name": "Car Expert"})) == 0:
            Verbosity(self.Verbosity).print("Create group 'Car Expert")
            self.__db.insertRow("Groups", {"Name": "Car Expert"})

        # default server preset 'Practice'
        with open(os.path.join(os.path.abspath(os.path.dirname(__file__)), "basic_data_default_presets.json"), "r") as f:
            json_string = f.read()
        json_obj = json.loads(json_string)
        for preset in json_obj:
            if len(self.__db.findIds("ServerPresets", {"Name": preset['Name']})) == 0:
                Verbosity(self.Verbosity).print("Create server preset '%s" % preset['Name'])
                self.__db.insertRow("ServerPresets", preset)

        # default car classes
        with open(os.path.join(os.path.abspath(os.path.dirname(__file__)), "basic_data_car_classes.json"), "r") as f:
            json_string = f.read()
        json_obj = json.loads(json_string)
        for cclass in json_obj:
            if len(self.__db.findIds("CarClasses", {"Name": cclass['Name']})) == 0:
                Verbosity(self.Verbosity).print("Create car class '%s" % cclass['Name'])
                cc_id = self.__db.insertRow("CarClasses", {'Name': cclass['Name']})

                for car_name in cclass['CarNames']:

                    res = self.__db.fetch("Cars", ['Id'], {'Car': car_name})
                    if len(res) == 1:
                        car_id = res[0]['Id']
                        self.__db.insertRow("CarClassesMap", {'Car': car_id, 'CarClass': cc_id, 'Ballast':0})



    def __set_chmod(self):
        self.Verbosity.print("Setting webserver access rights")
        verb2 = Verbosity(self.Verbosity)

        #######################
        # ACS Target Directory

        # change group of acs target directory
        cmd = ["chgrp", "-R", self.getArg("http-guid"), self.getArg("path-acs-target")]
        verb2.print(" ".join(cmd))
        subprocess.run(cmd)

        # change access to acs target directory
        cmd = ["chmod", "-R", "g+r", self.getArg("path-acs-target")]
        verb2.print(" ".join(cmd))
        subprocess.run(cmd)
        cmd = ["chmod", "g+w", os.path.join(self.getArg("path-acs-target"))]
        verb2.print(" ".join(cmd))
        subprocess.run(cmd)
        cmd = ["chmod", "g+w", os.path.join(self.getArg("path-acs-target"), "cfg")]
        verb2.print(" ".join(cmd))
        subprocess.run(cmd)
        cmd = ["chmod", "g+w", os.path.join(self.getArg("path-acs-target"), "results")]
        verb2.print(" ".join(cmd))
        subprocess.run(cmd)


        ########################
        # HTTP Target Directory

        # change group of http target directory
        cmd = ["chgrp", "-R", self.getArg("http-guid"), self.getArg("http-path")]
        verb2.print(" ".join(cmd))
        subprocess.run(cmd)

        # change access to http target directory
        cmd = ["chmod", "-R", "g+r", self.getArg("http-path")]
        verb2.print(" ".join(cmd))
        subprocess.run(cmd)


        #####################
        # HTTP Log Directory

        # change group of http target directory
        cmd = ["chgrp", "-R", self.getArg("http-guid"), self.getArg("http-log-path")]
        verb2.print(" ".join(cmd))
        subprocess.run(cmd)

        # change access to http target directory
        cmd = ["chmod", "-R", "g+wr", self.getArg("http-log-path")]
        verb2.print(" ".join(cmd))
        subprocess.run(cmd)


        ########################
        # ACS Content Directory

        # change group of acs-content target directory
        cmd = ["chgrp", "-R", self.getArg("http-guid"), self.getArg("http-path-acs-content")]
        verb2.print(" ".join(cmd))
        subprocess.run(cmd)

        # change access to acs-content target directory
        cmd = ["chmod", "g+rw", self.getArg("http-path-acs-content")]
        verb2.print(" ".join(cmd))
        subprocess.run(cmd)
        cmd = ["chmod", "-R", "g+r", self.getArg("http-path-acs-content")]
        verb2.print(" ".join(cmd))
        subprocess.run(cmd)


        ########################
        # acswuy python scripts

        dirpath = os.path.dirname(__file__)
        for scriptpath in os.listdir(dirpath):
            if scriptpath[-3:] == ".py":
                cmd = ["chgrp", self.getArg("http-guid"), os.path.join(dirpath, scriptpath)]
                verb2.print(" ".join(cmd))
                subprocess.run(cmd)
