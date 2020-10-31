import argparse
import subprocess
import shutil
import os
import json
from .db_wrapper import DbWrapper
from .verbose_class import VerboseClass

class CommandInstallHttp(VerboseClass):

    def __init__(self, argsubparser):
        VerboseClass.__init__(self)

        argparser_cmd  = argsubparser.add_parser('install-http', help="install database and files to http target directory")
        argparser_cmd.add_argument('--install-base-data', action="store_true", help="install basic http data (default groups, etc.)")
        argparser_cmd.set_defaults(func=self.work)


    def work(self, args, config):
        self.Verbosity = int(args.v)

        # check http directory
        if 'path_http' not in config or not os.path.isdir(config['path_http']):
            raise NotImplementedError("Http directory '%s' invalid!" % config['path_http'])

        self.__config = {}
        self.__config.update(config)

        self.__db = DbWrapper(config)
        self.__db.Verbosity = self.Verbosity - 2

        self.__work_db_tables()
        self.__work_cconfig()
        self.__work_scan_cars()
        self.__work_scan_tracks()
        self.__work_server_cfg()

        if args.install_base_data:
            self.__work_install_basics()


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



    def __parse_server_cfg_json(self):

        # read template
        with open(os.path.join(os.path.abspath(os.path.dirname(__file__)), "server_cfg.json"), "r") as f:
            json_string = f.read()
        json_obj = json.loads(json_string)

        # scan for weather enums
        weathers = []
        for w in sorted(os.listdir(self.__config['path_ac'] + "/content/weather")):
            if w[:1] != "." and os.path.isdir(self.__config['path_ac'] + "/content/weather/" + w):
                weathers.append(w)

        # append to wether graphics
        for field in json_obj["WEATHER"][0]["FIELDS"]:
            if field["TAG"] == "GRAPHICS":
                for i in range(len(weathers)):
                    field["ENUMS"].append({"VALUE":i, "TEXT":weathers[i]})

        return json_obj



    def __work_db_tables(self):

        self.print(1, "Create database tables")

        # ------------
        #  red tables

        # check table installer
        self.print(2, "check database table `installer`")
        self.__db.appendTable("installer")
        self.__db.appendColumnCurrentTimestamp("installer", "timestamp")
        self.__db.appendColumnString("installer", "version", 10)
        self.__db.appendColumnText("installer", "info")

        # insert installer info
        self.__db.insertRow("installer", {"version": "0.1a", "info": ""})

        # check table users
        self.print(2, "check database table `Users`")
        self.__db.appendTable("Users")
        self.__db.appendColumnString("Users", "Login", 50)
        self.__db.appendColumnString("Users", "Password", 100)
        self.__db.appendColumnString("Users", "Steam64GUID", 50)

        # check table Groups
        self.print(2, "check database table `Groups`")
        self.__db.appendTable("Groups")
        self.__db.appendColumnString("Groups", "Name", 50)

        # check table UserGroupMap
        self.print(2, "check database table `UserGroupMap`")
        self.__db.appendTable("UserGroupMap")
        self.__db.appendColumnInt("UserGroupMap", "User")
        self.__db.appendColumnInt("UserGroupMap", "Group")


        # -------------
        #  grey tables

        # check table Cars
        self.print(2, "check database table `Cars`")
        self.__db.appendTable("Cars")
        self.__db.appendColumnString("Cars", "Car", 80)
        self.__db.appendColumnString("Cars", "Name", 80)
        self.__db.appendColumnInt("Cars", "Parent")
        self.__db.appendColumnString("Cars", "Brand", 80)

        # check table CarSkins
        self.print(2, "check database table `CarSkins`")
        self.__db.appendTable("CarSkins")
        self.__db.appendColumnInt("CarSkins", "Car")
        self.__db.appendColumnString("CarSkins", "Skin", 50)

        # check table Tracks
        self.print(2, "check database table `Tracks`")
        self.__db.appendTable("Tracks")
        self.__db.appendColumnString("Tracks", "Track", 80)
        self.__db.appendColumnString("Tracks", "Config", 80)
        self.__db.appendColumnString("Tracks", "Name", 80)
        self.__db.appendColumnFloat("Tracks", "Length")
        self.__db.appendColumnInt("Tracks", "Pitboxes")


        # ----------------
        #  Server Logging

        self.print(2, "check database table `Sessions`")
        self.__db.appendTable("Sessions")
        self.__db.appendColumnInt("Sessions", "ProtocolVersion")
        self.__db.appendColumnInt("Sessions", "SessionIndex")
        self.__db.appendColumnInt("Sessions", "CurrentSessionIndex")
        self.__db.appendColumnInt("Sessions", "SessionCount")
        self.__db.appendColumnString("Sessions", 'ServerName', 50)
        self.__db.appendColumnInt("Sessions", "Track")
        self.__db.appendColumnString("Sessions", 'Name', 50)
        self.__db.appendColumnInt("Sessions", "Type")
        self.__db.appendColumnInt("Sessions", "Time")
        self.__db.appendColumnInt("Sessions", "Laps")
        self.__db.appendColumnInt("Sessions", "WaitTime")
        self.__db.appendColumnInt("Sessions", "TempAmb")
        self.__db.appendColumnInt("Sessions", "TempRoad")
        self.__db.appendColumnString("Sessions", 'WheatherGraphics', 50)
        self.__db.appendColumnUInt("Sessions", "Elapsed")
        self.__db.appendColumnCurrentTimestamp("Sessions", "Timestamp")

        self.print(2, "check database table `Laps`")
        self.__db.appendTable("Laps")
        self.__db.appendColumnInt("Laps", "Session")
        self.__db.appendColumnInt("Laps", "CarSkin")
        self.__db.appendColumnInt("Laps", "User")
        self.__db.appendColumnUInt("Laps", "Laptime")
        self.__db.appendColumnInt("Laps", "Cuts")
        self.__db.appendColumnFloat("Laps", "Grip")
        self.__db.appendColumnCurrentTimestamp("Laps", "Timestamp")

        self.print(2, "check database table `CollisionEnv`")
        self.__db.appendTable("CollisionEnv")
        self.__db.appendColumnInt("CollisionEnv", "Session")
        self.__db.appendColumnInt("CollisionEnv", "CarSkin")
        self.__db.appendColumnInt("CollisionEnv", "User")
        self.__db.appendColumnFloat("CollisionEnv", "Speed")
        self.__db.appendColumnCurrentTimestamp("CollisionEnv", "Timestamp")

        self.print(2, "check database table `CollisionCar`")
        self.__db.appendTable("CollisionCar")
        self.__db.appendColumnInt("CollisionCar", "Session")
        self.__db.appendColumnInt("CollisionCar", "CarSkin")
        self.__db.appendColumnInt("CollisionCar", "User")
        self.__db.appendColumnFloat("CollisionCar", "Speed")
        self.__db.appendColumnInt("CollisionCar", "OtherUser")
        self.__db.appendColumnInt("CollisionCar", "OtherCarSkin")
        self.__db.appendColumnCurrentTimestamp("CollisionCar", "Timestamp")



        # ----------------
        #  Server Presets

        self.print(2, "check database table `ServerPresets`")
        self.__db.appendTable("ServerPresets")
        self.__db.appendColumnString("ServerPresets", "Name", 60)
        json = self.__parse_server_cfg_json()

        for group in json.keys():
            for fieldset in json[group]:
                for field in fieldset['FIELDS']:

                    db_col_name = group + '_' + field['TAG']

                    if field['TYPE'] == "hidden":
                        pass
                    elif field['TYPE'] == "string":
                        self.__db.appendColumnString("ServerPresets", db_col_name, field['SIZE'])
                    elif field['TYPE'] in ["int", "enum"]:
                        self.__db.appendColumnInt("ServerPresets", db_col_name)
                    elif field['TYPE'] == "text":
                        self.__db.appendColumnText("ServerPresets", db_col_name)
                    else:
                        print("group =", group, ", field =", field)
                        raise NotImplementedError("Unknown field TYPE '%s'" % field['TYPE'])


        # --------------
        #  Car Classes

        self.print(2, "check database table `CarClasses`")
        self.__db.appendTable("CarClasses")
        self.__db.appendColumnString("CarClasses", 'Name', 50)

        self.print(2, "check database table `CarClassesMap`")
        self.__db.appendTable("CarClassesMap")
        self.__db.appendColumnInt("CarClassesMap", 'CarClass')
        self.__db.appendColumnInt("CarClassesMap", 'Car')
        self.__db.appendColumnInt("CarClassesMap", 'Ballast')


        # --------------
        #  Race Series

        self.print(2, "check database table `RaceSeries`")
        self.__db.appendTable("RaceSeries")
        self.__db.appendColumnString("RaceSeries", 'Name', 50)

        self.print(2, "check database table `RaceSeriesMap`")
        self.__db.appendTable("RaceSeriesMap")
        self.__db.appendColumnInt("RaceSeriesMap", 'RaceSeries')
        self.__db.appendColumnInt("RaceSeriesMap", 'CarClass')



    def __work_server_cfg(self):
        self.print(1, "create http/server_cfg.json")

        # predefined server presets
        server_cfg_json = self.__parse_server_cfg_json()

        # check fixed presets from ini file
        for group in server_cfg_json.keys():
            for fieldset in server_cfg_json[group]:
                for field in fieldset['FIELDS']:
                    config_key = group + "_" + field['TAG']

                    # check for fixed values
                    if config_key in self.__config:
                        field['FIXED'] = True
                        field['DEFAULT'] = self.__config[config_key]
                    else:
                        field['FIXED'] = False

                    # ensure DEFAULT exists
                    if 'DEFAULT' not in field:
                        field['DEFAULT'] = ""

        # dump to http directory
        with open(self.__config['path_http'] + "server_cfg.json", "w") as f:
            json.dump(server_cfg_json, f, indent=4)


    def __work_cconfig(self):
        self.print(1, "create cConfig.php")

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
            f.write("    private $GuestGroup = '%s';\n" % self.__config['http_guest_group'])
            f.write("\n")
            f.write("    // database constants\n")
            f.write("    private $DbType = \"%s\";\n" % self.__config['db_type'])
            f.write("    private $DbHost = \"%s\";\n" % self.__config['db_host'])
            f.write("    private $DbDatabase = \"%s\";\n" % self.__config['db_database'])
            f.write("    private $DbPort = \"%s\";\n" % self.__config['db_port'])
            f.write("    private $DbUser = \"%s\";\n" % self.__config['db_user'])
            f.write("    private $DbPasswd = \"%s\";\n" % self.__config['db_passwd'])
            f.write("\n")
            f.write("    // server_cfg\n")
            f.write("    private $SrvCfgJsonPath = \"server_cfg.json\";\n")
            f.write("\n")
            f.write("    // this allows read-only access to private properties\n")
            f.write("    public function __get($name) {\n")
            f.write("      return $this->$name;\n")
            f.write("    }\n")
            f.write("  }\n")
            f.write("?>\n")



    def __work_scan_cars(self):
        self.print(1, "scanning for cars")

        for car in os.listdir(self.__config['path_http'] + "/acs_content/cars"):
            car_path   = self.__config['path_http'] + "/acs_content/cars/" + car
            car_name   = self.__parse_json(car_path + "/ui/ui_car.json", "name", car)
            car_parent = self.__parse_json(car_path + "/ui/ui_car.json", "parent", "")
            car_brand  = self.__parse_json(car_path + "/ui/ui_car.json", "brand", "")

            # get skins
            car_skins = []
            if os.path.isdir(self.__config['path_http'] + "/acs_content/cars/" + car + "/skins"):
                for skin in os.listdir(self.__config['path_http'] + "/acs_content/cars/" + car + "/skins"):
                    car_skins.append(skin)

            self.print(2, "Found car '" + car + "'")

            # get IDs of existing cars (should be exactly one car)
            existing_car_ids = self.__db.findIds("Cars", {"Car": car})

            # insert car if not existent
            if len(existing_car_ids) == 0:
                self.__db.insertRow("Cars", {"Car": car, "Name": car_name, "Parent": 0, "Brand": car_brand})
                existing_car_ids = self.__db.findIds("Cars", {"Car": car})

            # update all existing cars
            for eci in existing_car_ids:
                self.__db.updateRow("Cars", eci, {"Car": car, "Name": car_name, "Parent": 0, "Brand": car_brand})

                # insert not existing skins
                for skin in car_skins:
                    existing_car_skins = self.__db.findIds("CarSkins", {"Car": eci, "Skin": skin})
                    if len(existing_car_skins) == 0:
                        self.__db.insertRow("CarSkins", {"Car": eci, "Skin": skin})



    def __work_scan_tracks(self):
        self.print(1, "Scanning for tracks")

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

        def interpret_pitboxes(pitbxs):
            ret = 0
            for char in pitbxs:
                if char in "0123456789":
                    ret *= 10
                    ret += int(char)
                else:
                    break
            return ret



        for track in os.listdir(self.__config['path_http'] + "/acs_content/tracks"):
            track_path   = self.__config['path_http'] + "/acs_content/tracks/" + track

            self.print(2, "Found track '" + track + "'")

            # update track
            if os.path.isfile(track_path + "/ui/ui_track.json"):
                track_name   = self.__parse_json(track_path + "/ui/ui_track.json", "name", track)
                track_length = self.__parse_json(track_path + "/ui/ui_track.json", "length", "0")
                track_length = interpret_length(track_length)
                track_pitbxs = interpret_pitboxes(self.__parse_json(track_path + "/ui/ui_track.json", "pitboxes", "0"))

                existing_track_ids = self.__db.findIds("Tracks", {"Track": track})
                if len(existing_track_ids) == 0:
                    self.__db.insertRow("Tracks", {"Track": track, "Config": "", "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs})
                else:
                    self.__db.updateRow("Tracks", existing_track_ids[0], {"Track": track, "Config": "", "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs})

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
                                self.__db.insertRow("Tracks", {"Track": track, "Config": track_config, "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs})
                            else:
                                self.__db.updateRow("Tracks", existing_track_ids[0], {"Track": track, "Config": track_config, "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs})



    def __work_install_basics(self):
        self.print(1, "Install base data")

        # add guest group
        if 'http_guest_group' in self.__config and len(self.__config['http_guest_group']) > 0:
            if len(self.__db.findIds("Groups", {"Name": self.__config['http_guest_group']})) == 0:
                self.print(2, "Create guest group '%s'" % self.__config['http_guest_group'])
                self.__db.insertRow("Groups", {"Name": self.__config['http_guest_group']})

        # default groups
        if len(self.__db.findIds("Groups", {"Name": "Driver"})) == 0:
            self.print(2, "Create group 'Driver")
            self.__db.insertRow("Groups", {"Name": "Driver"})
        if len(self.__db.findIds("Groups", {"Name": "Race Orga"})) == 0:
            self.print(2, "Create group 'Race Orga")
            self.__db.insertRow("Groups", {"Name": "Race Orga"})
        if len(self.__db.findIds("Groups", {"Name": "Server Admin"})) == 0:
            self.print(2, "Create group 'Server Admin")
            self.__db.insertRow("Groups", {"Name": "Server Admin"})

        # default server preset 'Practice'
        with open(os.path.join(os.path.abspath(os.path.dirname(__file__)), "basic_data_default_presets.json"), "r") as f:
            json_string = f.read()
        json_obj = json.loads(json_string)
        for preset in json_obj:
            if len(self.__db.findIds("ServerPresets", {"Name": preset['Name']})) == 0:
                self.print(2, "Create server preset '%s" % preset['Name'])
                self.__db.insertRow("ServerPresets", preset)
