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

class CommandInstall(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "install", "Server-Installer -  install http from server package")

        self.add_argument('--root-password', help="Password for root user")
        self.add_argument('--guest-group', default="Visitor", help="Group name of visitors that are not logged in")
        self.add_argument('--default-template', default="acswui", help="Default template for http")
        self.add_argument('--base-data', action="store_true", help="install basic http data (default groups, etc.)")



    def process(self):

        # read server_cfg json
        with open(os.path.join(self.getGeneralArg("path-srvpkg"), "server_cfg.json"), "r") as f:
            json_string = f.read()
        self.__server_cfg_json = json.loads(json_string)

        # setup database
        self.__db = Database(host=self.getGeneralArg("db-host"),
                             port=self.getGeneralArg("db-port"),
                             database=self.getGeneralArg("db-database"),
                             user=self.getGeneralArg("db-user"),
                             password=self.getGeneralArg("db-password"),
                             verbosity=Verbosity(Verbosity(self.Verbosity))
                             )

        # install work
        self.__work_copy_files()
        self.__work_db_tables()
        self.__work_cconfig()
        self.__work_scan_cars()
        self.__work_scan_tracks()
        self.__work_translations()
        if self.getArg("base-data") is True:
            self.__work_install_basics()
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

        self.Verbosity.print("copy files")
        verb2 = Verbosity(self.Verbosity)
        verb3 = Verbosity(verb2)


        #########
        # htdocs

        # create dir
        path_htdocs = os.path.abspath(self.getGeneralArg("path-htdocs"))
        if not os.path.isdir(path_htdocs):
            verb3.print("mkdirs " + path_htdocs)
            self.mkdirs(path_htdocs)

        # copy dir
        verb2.print("copy ./http/ to " + path_htdocs)
        path_htdocs_src = os.path.join(os.path.abspath(os.path.dirname(__file__)), "..", "http")
        self.copytree(path_htdocs_src, path_htdocs)


        #######
        # data

        # cfg
        path_data = os.path.abspath(self.getGeneralArg("path-data"))
        path_data_acserver = os.path.join(path_data, "acserver")
        path_data_acserver_cfg = os.path.join(path_data_acserver, "cfg")
        if not os.path.isdir(path_data_acserver_cfg):
            verb3.print("mkdirs " + path_data_acserver_cfg)
            self.mkdirs(path_data_acserver_cfg)

        # prepare cfg files (to save ownership)
        slot_nr = 0
        while True:
            slot_dict = self.getIniSection("SERVER_SLOT_" + str(slot_nr))
            if slot_dict is None:
                break

            for filename in ["entry_list_%i.ini", "server_cfg_%i.ini", "welcome_%i.txt"]:
                path_file = os.path.join(path_data_acserver_cfg, filename % slot_nr)
                with open(path_file, "w") as f:
                    f.write("\n")

            slot_nr += 1

        # results
        path_data_acserver_results = os.path.join(path_data_acserver, "results")
        if not os.path.isdir(path_data_acserver_results):
            verb3.print("mkdirs " + path_data_acserver_results)
            self.mkdirs(path_data_acserver_results)

        # log dirs
        for logdir in ['logs_acserver', 'logs_cron', 'logs_http']:
            path_data_log = os.path.join(path_data, logdir)
            if not os.path.isdir(path_data_log):
                verb3.print("mkdirs " + path_data_log)
                self.mkdirs(path_data_log)

        # htcache
        path_htcache = os.path.join(path_data, "htcache")
        if not os.path.isdir(path_htcache):
            verb3.print("mkdirs " + path_htcache)
            self.mkdirs(path_htcache)

        # acserver binaries
        path_srvpkg_acserver = os.path.join(self.getGeneralArg("path-srvpkg"), "acserver")
        slot_nr = 0
        while True:
            slot_dict = self.getIniSection("SERVER_SLOT_" + str(slot_nr))
            if slot_dict is None:
                break
            path_srvpkg_acserver_bin = os.path.join(path_srvpkg_acserver, "acServer")
            path_data_acserver_binslot = os.path.join(path_data_acserver, "acServer%i" % slot_nr)
            shutil.copy(path_srvpkg_acserver_bin, path_data_acserver_binslot)
            slot_nr += 1

        # server_cfg.json
        path_srvpkg_servercfg = os.path.join(self.getGeneralArg("path-srvpkg"), "server_cfg.json")
        path_data_servercfg = os.path.join(path_data, "server_cfg.json")
        shutil.copy(path_srvpkg_servercfg, path_data_servercfg)


        #############
        # acswui.ini

        path_acswui_ini = os.path.join(path_data, "acswui.ini")
        verb2.print("create " + path_acswui_ini)

        with open(path_acswui_ini, "w") as f:
            f.write("[GENERAL]\n")

            # database
            keys = []
            keys += ['db-host', 'db-database', 'db-port', 'db-user', 'db-password']
            for key in keys:
                value = self.getIniSection("GENERAL")[key]
                f.write(key + " = " + value + "\n")

            # paths
            keys = []
            keys += ['path-data', 'path-htdata']
            for key in keys:
                value = self.getIniSection("GENERAL")[key]
                value = os.path.abspath(value)
                f.write(key + " = " + value + "\n")


        #########
        # htdata

        # create dir
        path_htdata = os.path.abspath(self.getGeneralArg("path-htdata"))
        if not os.path.isdir(path_htdata):
            verb3.print("mkdirs " + path_htdata)
            self.mkdirs(path_htdata)

        # copy data
        path_srvpkg_htdata = os.path.join(self.getGeneralArg("path-srvpkg"), "htdata")
        verb2.print("copy " + path_srvpkg_htdata + " to " + path_htdata)
        self.copytree(path_srvpkg_htdata, path_htdata)

        # realtime
        path_realtime = os.path.join(path_htdata, "realtime")
        if not os.path.isdir(path_realtime):
            verb3.print("mkdirs " + path_realtime)
            self.mkdirs(path_realtime)


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


        # check table CronJobs
        Verbosity(self.Verbosity).print("check database table `CronJobs`")
        self.__db.appendTable("CronJobs")
        self.__db.appendColumnString("CronJobs", "Name", 60)
        self.__db.appendColumnCurrentTimestamp("CronJobs", "LastStart")
        self.__db.appendColumnUInt("CronJobs", "LastDuration")
        self.__db.appendColumnString("CronJobs", "Status", 50)


        # check table ServerPresets
        Verbosity(self.Verbosity).print("check database table `ServerPresets`")
        self.__db.appendTable("ServerPresets")
        self.__db.appendColumnString("ServerPresets", "Name", 60)
        self.__db.appendColumnInt("ServerPresets", "Restricted")

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
        self.__db.appendColumnUInt("Sessions", "ServerSlot")
        self.__db.appendColumnUInt("Sessions", "ServerPreset")
        self.__db.appendColumnUInt("Sessions", 'CarClass')

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
        self.__db.appendColumnString("Users", "Color", 10)
        self.__db.appendColumnTinyInt("Users", "Privacy")

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
        self.__db.appendColumnText("CarClasses", "Description")

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



        # ---------------------------------------------------------------------
        #                           Green Tables
        # ---------------------------------------------------------------------

        # check table Championships
        Verbosity(self.Verbosity).print("check database table `Championships`")
        self.__db.appendTable("Championships")
        self.__db.appendColumnUInt("Championships", 'ServerPreset')
        self.__db.appendColumnString("Championships", "Name", 100)
        self.__db.appendColumnString("Championships", "CarClasses", 100)
        self.__db.appendColumnString("Championships", "QualifyPositionPoints", 100)
        self.__db.appendColumnString("Championships", "RacePositionPoints", 100)
        self.__db.appendColumnString("Championships", "RaceTimePoints", 100)
        self.__db.appendColumnString("Championships", "RaceLeadLapPoints", 100)
        self.__db.appendColumnString("Championships", "BallanceBallast", 100)
        self.__db.appendColumnString("Championships", "BallanceRestrictor", 100)
        self.__db.appendColumnString("Championships", "Tracks", 100)

        # check table SessionQueue
        Verbosity(self.Verbosity).print("check database table `SessionQueue`")
        self.__db.appendTable("SessionQueue")
        self.__db.appendColumnString("SessionQueue", "Name", 100)
        self.__db.appendColumnInt("SessionQueue", 'Enabled')
        self.__db.appendColumnInt("SessionQueue", 'SeatOccupations')
        self.__db.appendColumnUInt("SessionQueue", 'Preset')
        self.__db.appendColumnUInt("SessionQueue", 'CarClass')
        self.__db.appendColumnUInt("SessionQueue", 'Track')
        self.__db.appendColumnInt("SessionQueue", 'Slot')

        # check table SessionSchedule
        Verbosity(self.Verbosity).print("check database table `SessionSchedule`")
        self.__db.appendTable("SessionSchedule")
        self.__db.appendColumnString("SessionSchedule", "Name", 100)
        self.__db.appendColumnTimestamp("SessionSchedule", 'Start')
        self.__db.appendColumnInt("SessionSchedule", 'SeatOccupations')
        self.__db.appendColumnUInt("SessionSchedule", 'Preset')
        self.__db.appendColumnUInt("SessionSchedule", 'CarClass')
        self.__db.appendColumnUInt("SessionSchedule", 'Track')
        self.__db.appendColumnInt("SessionSchedule", 'Slot')
        self.__db.appendColumnInt("SessionSchedule", 'Executed')



        # ---------------------------------------------------------------------
        #                           Cyan Tables
        # ---------------------------------------------------------------------

        # check table DriverRanking
        Verbosity(self.Verbosity).print("check database table `DriverRanking`")
        self.__db.appendTable("DriverRanking")
        self.__db.appendColumnUInt("DriverRanking", 'User')
        self.__db.appendColumnCurrentTimestamp("DriverRanking", "Timestamp")
        self.__db.appendColumnFloat("DriverRanking", 'XP_R')
        self.__db.appendColumnFloat("DriverRanking", 'XP_Q')
        self.__db.appendColumnFloat("DriverRanking", 'XP_P')
        self.__db.appendColumnFloat("DriverRanking", 'SX_R')
        self.__db.appendColumnFloat("DriverRanking", 'SX_Q')
        self.__db.appendColumnFloat("DriverRanking", 'SX_RT')
        self.__db.appendColumnFloat("DriverRanking", 'SX_BT')
        self.__db.appendColumnFloat("DriverRanking", 'SF_CT')
        self.__db.appendColumnFloat("DriverRanking", 'SF_CE')
        self.__db.appendColumnFloat("DriverRanking", 'SF_CC')

        # check table StatsGeneral
        Verbosity(self.Verbosity).print("check database table `StatsGeneral`")
        self.__db.appendTable("StatsGeneral")
        self.__db.appendColumnCurrentTimestamp("StatsGeneral", "Timestamp")
        self.__db.appendColumnUInt("StatsGeneral", "LastScannedLap")
        self.__db.appendColumnUInt("StatsGeneral", "LastScannedColCar")
        self.__db.appendColumnUInt("StatsGeneral", "LastScannedColEnv")
        self.__db.appendColumnUInt("StatsGeneral", "LapsValid")
        self.__db.appendColumnUInt("StatsGeneral", "LapsInvalid")
        self.__db.appendColumnUInt("StatsGeneral", "MetersValid")
        self.__db.appendColumnUInt("StatsGeneral", "MetersInvalid")
        self.__db.appendColumnUInt("StatsGeneral", "SecondsValid")
        self.__db.appendColumnUInt("StatsGeneral", "SecondsInvalid")
        self.__db.appendColumnUInt("StatsGeneral", "Cuts")
        self.__db.appendColumnUInt("StatsGeneral", "CollisionsCar")
        self.__db.appendColumnUInt("StatsGeneral", "CollisionsEnvironment")

        # check table StatsTrackPopularity
        Verbosity(self.Verbosity).print("check database table `StatsTrackPopularity`")
        self.__db.appendTable("StatsTrackPopularity")
        self.__db.appendColumnCurrentTimestamp("StatsTrackPopularity", "Timestamp")
        self.__db.appendColumnUInt("StatsTrackPopularity", "LastScannedLap")
        self.__db.appendColumnUInt("StatsTrackPopularity", "Track")
        self.__db.appendColumnUInt("StatsTrackPopularity", "LapCount")
        self.__db.appendColumnFloat("StatsTrackPopularity", "Popularity")
        self.__db.appendColumnFloat("StatsTrackPopularity", "LaptimeCumulative")

        # check table StatsCarClassPopularity
        Verbosity(self.Verbosity).print("check database table `StatsCarClassPopularity`")
        self.__db.appendTable("StatsCarClassPopularity")
        self.__db.appendColumnCurrentTimestamp("StatsCarClassPopularity", "Timestamp")
        self.__db.appendColumnUInt("StatsCarClassPopularity", "CarClass")
        self.__db.appendColumnUInt("StatsCarClassPopularity", "LastScannedLap")
        self.__db.appendColumnUInt("StatsCarClassPopularity", "LapCount")
        self.__db.appendColumnFloat("StatsCarClassPopularity", "TimeCount")
        self.__db.appendColumnFloat("StatsCarClassPopularity", "MeterCount")
        self.__db.appendColumnFloat("StatsCarClassPopularity", "Popularity")



    def __work_cconfig(self):
        self.Verbosity.print("create cConfig.php")

        # encrypt root password
        http_root_password = subprocess.check_output(['php', '-r', 'echo(password_hash("%s", PASSWORD_BCRYPT));' % self.getArg('root-password')])
        http_root_password = http_root_password.decode("utf-8")

        # paths
        abspath_acswui = os.path.abspath(os.curdir)
        abspath_data = os.path.abspath(self.getGeneralArg('path-data'))
        abspath_htdocs = os.path.abspath(self.getGeneralArg('path-htdocs'))
        abspath_htdata = os.path.abspath(self.getGeneralArg('path-htdata'))
        abspath_acswui_py = os.path.abspath(os.path.join(abspath_acswui, "acswui.py"))

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


        with open(os.path.join(abspath_htdocs, "classes" , "cConfig.php"), "w") as f:
            f.write("<?php\n")
            f.write("  class cConfig {\n")
            f.write("\n")
            f.write("    // paths\n")
            f.write("    private $AbsPathData = \"%s\";\n" % abspath_data)
            f.write("    private $RelPathData = \"%s\";\n" % os.path.relpath(abspath_data, abspath_htdocs))
            f.write("    private $AbsPathHtdata = \"%s\";\n" % abspath_htdata)
            f.write("    private $RelPathHtdata = \"%s\";\n" % os.path.relpath(abspath_htdata, abspath_htdocs))
            f.write("    private $AbsPathAcswui = \"%s\";\n" % abspath_acswui)
            f.write("\n")
            f.write("    // basic constants\n")
            f.write("    private $DefaultTemplate = \"%s\";\n" % self.getArg('default-template'))
            f.write("    private $LogDebug = \"false\";\n")
            f.write("    private $RootPassword = '%s';\n" % http_root_password)
            f.write("    private $GuestGroup = '%s';\n" % self.getArg('guest-group'))
            f.write("\n")
            f.write("    // database constants\n")
            f.write("    private $DbHost = \"%s\";\n" % self.getGeneralArg('db-host'))
            f.write("    private $DbDatabase = \"%s\";\n" % self.getGeneralArg('db-database'))
            f.write("    private $DbPort = \"%s\";\n" % self.getGeneralArg('db-port'))
            f.write("    private $DbUser = \"%s\";\n" % self.getGeneralArg('db-user'))
            f.write("    private $DbPasswd = \"%s\";\n" % self.getGeneralArg('db-password'))
            f.write("\n")
            f.write("    // server_cfg\n")
            f.write("    private $FixedServerConfig = array(%s);\n" % fixed_server_settings)
            f.write("    private $ServerSlots = array(%s);\n" % server_slots)
            f.write("\n")
            f.write("    // discord webhooks\n")
            f.write("    private $DWhManSrvStrtUrl = \"%s\";\n" % self.getIniSection("DISCORD_WEBHOOKS")['MANUAL_SERVER_START_URL'])
            f.write("    private $DWhManSrvStrtGMntn = \"%s\";\n" % self.getIniSection("DISCORD_WEBHOOKS")['MANUAL_SERVER_START_MENTION_GROUPID'])
            f.write("\n")
            f.write("    // misc\n")
            f.write("    private $DriverRanking = %s;\n" % self.dict2php(driver_ranking))
            #f.write("    private $DriverRankingCummulateScanDays = %s;\n" % self.getIniSection("DRIVER_RANKING")['CummulateScanDays'])
            f.write("\n")
            f.write("    // this allows read-only access to private properties\n")
            f.write("    public function __get($name) {\n")
            f.write("      return $this->$name;\n")
            f.write("    }\n")
            f.write("  }\n")
            f.write("?>\n")



    def __work_scan_cars(self):
        self.Verbosity.print("scanning for cars")

        # paths
        abspath_data = os.path.abspath(self.getGeneralArg('path-srvpkg'))

        # set all current cars and skins to 'deprecated'
        self.__db.rawQuery("UPDATE Cars SET Deprecated=1 WHERE Deprecated=0")
        self.__db.rawQuery("UPDATE CarSkins SET Deprecated=1 WHERE Deprecated=0")

        path_cars = os.path.join(abspath_data, "htdata", "content", "cars")
        for car in sorted(os.listdir(path_cars)):
            car_path   = os.path.join(path_cars, car)
            car_name   = self.__parse_json(car_path + "/ui/ui_car.json", "name", car)
            car_parent = self.__parse_json(car_path + "/ui/ui_car.json", "parent", "")
            car_brand  = self.__parse_json(car_path + "/ui/ui_car.json", "brand", "")

            # get skins
            car_skins = []
            path_car_skins = os.path.join(car_path, "skins")
            if os.path.isdir(path_car_skins):
                for skin in os.listdir(path_car_skins):
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

        # paths
        abspath_data = os.path.abspath(self.getGeneralArg('path-srvpkg'))

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



        path_tracks = os.path.join(abspath_data, "htdata", "content", "tracks")
        for track in sorted(os.listdir(path_tracks)):
            track_path = os.path.join(path_tracks, track)


            # update track
            if os.path.isfile(track_path + "/ui/ui_track.json"):
                track_name   = self.__parse_json(track_path + "/ui/ui_track.json", "name", track)
                track_length = self.__parse_json(track_path + "/ui/ui_track.json", "length", "0")
                track_length = interpret_length(track_length)
                track_pitbxs = interpret_pitboxes(self.__parse_json(track_path + "/ui/ui_track.json", "pitboxes", "0"))

                existing_track_ids = self.__db.findIds("Tracks", {"Track": track})
                if len(existing_track_ids) == 0:
                    self.__db.insertRow("Tracks", {"Track": track, "Config": "", "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs, "Deprecated":0})
                    Verbosity(self.Verbosity).print("Found new track '" + track + "' " + str(track_length) + "m")
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
            guest_group = self.getArg("guest-group")
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



    def __work_translations(self):
        self.Verbosity.print("compile translations")
        verb2 = Verbosity(self.Verbosity)
        verb3 = Verbosity(verb2)

        # scanning for languages
        path_locales = os.path.join(self.getGeneralArg('path-htdocs'), "locale")
        for locale in sorted(os.listdir(path_locales)):
            verb2.print(locale)

            # scan all .po files
            path_lc_messages = os.path.join(path_locales, locale, "LC_MESSAGES")
            for po_file in os.listdir(path_lc_messages):
                if po_file[-3:] != ".po":
                    continue

                verb3.print(po_file)
                po_path = os.path.join(path_lc_messages, po_file)
                mo_path = os.path.join(path_lc_messages, po_file[:-3] + ".mo")
                cmd = ["msgfmt", "-o", mo_path, po_path]
                subprocess.run(cmd)



    def __set_chmod(self):
        self.Verbosity.print("Setting webserver access rights")
        verb2 = Verbosity(self.Verbosity)

        # paths
        abspath_acswui = os.path.abspath(os.curdir)
        abspath_data = os.path.abspath(self.getGeneralArg('path-data'))
        abspath_htdocs = os.path.abspath(self.getGeneralArg('path-htdocs'))
        abspath_htdata = os.path.abspath(self.getGeneralArg('path-htdata'))
        abspath_acswui_py = os.path.abspath(os.path.join(abspath_acswui, "acswui.py"))

        # directory paths
        for path in [abspath_data, abspath_htdocs, abspath_htdata]:
            cmd = ["chgrp", "-R", self.getGeneralArg('http-guid'), path]
            verb2.print(" ".join(cmd))
            subprocess.run(cmd)

        # directories with write access
        paths = []
        paths.append(os.path.join(abspath_data, "logs_http"))
        paths.append(os.path.join(abspath_data, "logs_cron"))
        paths.append(os.path.join(abspath_data, "logs_acserver"))
        paths.append(os.path.join(abspath_data, "htcache"))
        paths.append(os.path.join(abspath_data, "acserver"))
        paths.append(os.path.join(abspath_data, "acserver", "cfg"))
        paths.append(os.path.join(abspath_data, "acserver", "results"))
        paths.append(os.path.join(abspath_htdata, "realtime"))
        for path in paths:
            cmd = ["chmod", "-R", "g+w", path]
            verb2.print(" ".join(cmd))
            subprocess.run(cmd)

        # acswuy python scripts
        cmd = ["chgrp", self.getGeneralArg("http-guid"), os.path.join(abspath_acswui, "acswui.py")]
        verb2.print(" ".join(cmd))
        subprocess.run(cmd)
        for script in os.listdir(os.path.join(abspath_acswui, "pyacswui")):
            if script[-3:] == ".py":
                cmd = ["chgrp", self.getGeneralArg("http-guid"), os.path.join(abspath_acswui, "pyacswui", script)]
                verb2.print(" ".join(cmd))
                subprocess.run(cmd)
