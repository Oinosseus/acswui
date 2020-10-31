import argparse
import subprocess
import shutil
import os
import json
from .db_wrapper import DbWrapper

class Installer(object):

    def __init__(self, config, verbosity = 0, install_base_data = False):

        if type(config) != type({}):
            raise TypeError("Parameter 'config' must be dict!")

        # check http directory
        if 'path_http' not in config or not os.path.isdir(config['path_http']):
            raise NotImplementedError("Http directory '%s' invalid!" % config['path_http'])

        self.__config = {}
        self.__config.update(config)
        self.__verbosity = int(verbosity)
        self.__db = DbWrapper(config)
        self.__db.Verbosity = self.__verbosity - 1

        if install_base_data == True:
            self.__install_base_data = True
        else:
            self.__install_base_data = False



    def work(self):
        """
            1. create or update database tables
            2. create cConfig.php
            3. scan cars into database
            4. scan tracks into database
            5. install basic data
        """

        self.__work_db_tables()
        self.__work_cconfig()
        self.__work_scan_cars()
        self.__work_scan_tracks()
        self.__work_server_cfg()
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

        # ------------
        #  red tables

        # check table installer
        if self.__verbosity > 0:
            print("check database table `installer`")
        self.__db.appendTable("installer")
        self.__db.appendColumnCurrentTimestamp("installer", "timestamp")
        self.__db.appendColumnString("installer", "version", 10)
        self.__db.appendColumnText("installer", "info")

        # insert installer info
        self.__db.insertRow("installer", {"version": "0.1a", "info": ""})

        # check table users
        if self.__verbosity > 0:
            print("check database table `Users`")
        self.__db.appendTable("Users")
        self.__db.appendColumnString("Users", "Login", 50)
        self.__db.appendColumnString("Users", "Password", 100)
        self.__db.appendColumnString("Users", "Steam64GUID", 50)

        # check table Groups
        if self.__verbosity > 0:
            print("check database table `Groups`")
        self.__db.appendTable("Groups")
        self.__db.appendColumnString("Groups", "Name", 50)

        # check table UserGroupMap
        if self.__verbosity > 0:
            print("check database table `UserGroupMap`")
        self.__db.appendTable("UserGroupMap")
        self.__db.appendColumnInt("UserGroupMap", "User")
        self.__db.appendColumnInt("UserGroupMap", "Group")


        # -------------
        #  grey tables

        # check table Cars
        if self.__verbosity > 0:
            print("check database table `Cars`")
        self.__db.appendTable("Cars")
        self.__db.appendColumnString("Cars", "Car", 80)
        self.__db.appendColumnString("Cars", "Name", 80)
        self.__db.appendColumnInt("Cars", "Parent")
        self.__db.appendColumnString("Cars", "Brand", 80)

        # check table CarSkins
        if self.__verbosity > 0:
            print("check database table `CarSkins`")
        self.__db.appendTable("CarSkins")
        self.__db.appendColumnInt("CarSkins", "Car")
        self.__db.appendColumnString("CarSkins", "Skin", 50)

        # check table Tracks
        if self.__verbosity > 0:
            print("check database table `Tracks`")
        self.__db.appendTable("Tracks")
        self.__db.appendColumnString("Tracks", "Track", 80)
        self.__db.appendColumnString("Tracks", "Config", 80)
        self.__db.appendColumnString("Tracks", "Name", 80)
        self.__db.appendColumnFloat("Tracks", "Length")
        self.__db.appendColumnInt("Tracks", "Pitboxes")


        # ----------------
        #  Server Logging

        if self.__verbosity > 0:
            print("check database table `Sessions`")
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

        if self.__verbosity > 0:
            print("check database table `Laps`")
        self.__db.appendTable("Laps")
        self.__db.appendColumnInt("Laps", "Session")
        self.__db.appendColumnInt("Laps", "CarSkin")
        self.__db.appendColumnInt("Laps", "User")
        self.__db.appendColumnUInt("Laps", "Laptime")
        self.__db.appendColumnInt("Laps", "Cuts")
        self.__db.appendColumnFloat("Laps", "Grip")
        self.__db.appendColumnCurrentTimestamp("Laps", "Timestamp")

        if self.__verbosity > 0:
            print("check database table `CollisionEnv`")
        self.__db.appendTable("CollisionEnv")
        self.__db.appendColumnInt("CollisionEnv", "Session")
        self.__db.appendColumnInt("CollisionEnv", "CarSkin")
        self.__db.appendColumnInt("CollisionEnv", "User")
        self.__db.appendColumnFloat("CollisionEnv", "Speed")
        self.__db.appendColumnCurrentTimestamp("CollisionEnv", "Timestamp")

        if self.__verbosity > 0:
            print("check database table `CollisionCar`")
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

        if self.__verbosity > 0:
            print("check database table `ServerPresets`")
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

        if self.__verbosity > 0:
            print("check database table `CarClasses`")
        self.__db.appendTable("CarClasses")
        self.__db.appendColumnString("CarClasses", 'Name', 50)

        if self.__verbosity > 0:
            print("check database table `CarClassesMap`")
        self.__db.appendTable("CarClassesMap")
        self.__db.appendColumnInt("CarClassesMap", 'CarClass')
        self.__db.appendColumnInt("CarClassesMap", 'Car')
        self.__db.appendColumnInt("CarClassesMap", 'Ballast')


        # --------------
        #  Race Series

        if self.__verbosity > 0:
            print("check database table `RaceSeries`")
        self.__db.appendTable("RaceSeries")
        self.__db.appendColumnString("RaceSeries", 'Name', 50)

        if self.__verbosity > 0:
            print("check database table `RaceSeriesMap`")
        self.__db.appendTable("RaceSeriesMap")
        self.__db.appendColumnInt("RaceSeriesMap", 'RaceSeries')
        self.__db.appendColumnInt("RaceSeriesMap", 'CarClass')



    def __work_server_cfg(self):

        # user info
        if self.__verbosity > 0:
            print("create http/server_cfg.json")

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

            if self.__verbosity > 0:
                print("Install car '" + car + "'")

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

            if self.__verbosity > 0:
                print("Install track '" + track + "'")

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

        if self.__verbosity > 0:
            print("Install Base Data")

        # add guest group
        if 'http_guest_group' in self.__config and len(self.__config['http_guest_group']) > 0:
            if len(self.__db.findIds("Groups", {"Name": self.__config['http_guest_group']})) == 0:
                self.__db.insertRow("Groups", {"Name": self.__config['http_guest_group']})

        # optional data
        if self.__install_base_data:

            # default groups
            if len(self.__db.findIds("Groups", {"Name": "Driver"})) == 0:
                self.__db.insertRow("Groups", {"Name": "Driver"})
            if len(self.__db.findIds("Groups", {"Name": "Race Orga"})) == 0:
                self.__db.insertRow("Groups", {"Name": "Race Orga"})
            if len(self.__db.findIds("Groups", {"Name": "Server Admin"})) == 0:
                self.__db.insertRow("Groups", {"Name": "Server Admin"})

            # default server preset 'easy'
            srv_preset_easy = {}
            srv_preset_easy.update({'Name': "Easy Drivin'"})
            srv_preset_easy.update({'srv_SUN_ANGLE': '-8'})
            srv_preset_easy.update({'srv_TC_ALLOWED': '2'})
            srv_preset_easy.update({'srv_ABS_ALLOWED': '2'})
            srv_preset_easy.update({'srv_STABILITY_ALLOWED': '1'})
            srv_preset_easy.update({'srv_AUTOCLUTCH_ALLOWED': '1'})
            srv_preset_easy.update({'srv_DAMAGE_MULTIPLIER': '0'})
            srv_preset_easy.update({'srv_FUEL_RATE': '0'})
            srv_preset_easy.update({'srv_TYRE_WEAR_RATE': '0'})
            srv_preset_easy.update({'srv_TYRE_BLANKETS_ALLOWED': '1'})
            srv_preset_easy.update({'srv_WELCOME_MESSAGE': 'Easy driving without damage.'})
            srv_preset_easy.update({'srv_FORCE_VIRTUAL_MIRROR': '0'})
            srv_preset_easy.update({'srv_LEGAL_TYRES': 'V;E;HR;ST'})
            srv_preset_easy.update({'srv_MAX_BALLAST_KG': '50'})
            srv_preset_easy.update({'dyt_SESSION_START': '100'})
            srv_preset_easy.update({'dyt_RANDOMNESS': '0'})
            srv_preset_easy.update({'dyt_LAP_GAIN': '1'})
            srv_preset_easy.update({'dyt_SESSION_TRANSFER': '100'})
            srv_preset_easy.update({'prt_NAME': 'Practice'})
            srv_preset_easy.update({'prt_TIME': '10'})
            srv_preset_easy.update({'prt_IS_OPEN': '1'})
            srv_preset_easy.update({'qly_NAME': 'Qualify'})
            srv_preset_easy.update({'qly_TIME': '15'})
            srv_preset_easy.update({'qly_IS_OPEN': '1'})
            srv_preset_easy.update({'rce_NAME': 'Race'})
            srv_preset_easy.update({'rce_LAPS': '5'})
            srv_preset_easy.update({'rce_WAIT_TIME': '30'})
            srv_preset_easy.update({'rce_IS_OPEN': '1'})
            srv_preset_easy.update({'wth_GRAPHICS': '3_clear'})
            srv_preset_easy.update({'wth_BASE_TEMPERATURE_AMBIENT': '22'})
            srv_preset_easy.update({'wth_VARIATION_AMBIENT': '2'})
            srv_preset_easy.update({'wth_BASE_TEMPERATURE_ROAD': '9'})
            srv_preset_easy.update({'wth_VARIATION_ROAD': '2'})
            if len(self.__db.findIds("ServerPresets", {"Name": "Easy Drivin'"})) == 0:
                self.__db.insertRow("ServerPresets", srv_preset_easy)

            # default server preset 'pro'
            srv_preset_easy = {}
            srv_preset_easy.update({'Name': "Professional"})
            srv_preset_easy.update({'srv_SUN_ANGLE': '-18'})
            srv_preset_easy.update({'srv_TC_ALLOWED': '1'})
            srv_preset_easy.update({'srv_ABS_ALLOWED': '1'})
            srv_preset_easy.update({'srv_STABILITY_ALLOWED': '0'})
            srv_preset_easy.update({'srv_AUTOCLUTCH_ALLOWED': '0'})
            srv_preset_easy.update({'srv_DAMAGE_MULTIPLIER': '50'})
            srv_preset_easy.update({'srv_FUEL_RATE': '100'})
            srv_preset_easy.update({'srv_TYRE_WEAR_RATE': '100'})
            srv_preset_easy.update({'srv_TYRE_BLANKETS_ALLOWED': '0'})
            srv_preset_easy.update({'srv_WELCOME_MESSAGE': 'Professional driving simulation.'})
            srv_preset_easy.update({'srv_FORCE_VIRTUAL_MIRROR': '0'})
            srv_preset_easy.update({'srv_LEGAL_TYRES': 'V;E;HR;ST'})
            srv_preset_easy.update({'srv_MAX_BALLAST_KG': '50'})
            srv_preset_easy.update({'dyt_SESSION_START': '80'})
            srv_preset_easy.update({'dyt_RANDOMNESS': '5'})
            srv_preset_easy.update({'dyt_LAP_GAIN': '2'})
            srv_preset_easy.update({'dyt_SESSION_TRANSFER': '90'})
            srv_preset_easy.update({'prt_NAME': 'Practice'})
            srv_preset_easy.update({'prt_TIME': '60'})
            srv_preset_easy.update({'prt_IS_OPEN': '1'})
            srv_preset_easy.update({'qly_NAME': 'Qualify'})
            srv_preset_easy.update({'qly_TIME': '20'})
            srv_preset_easy.update({'qly_IS_OPEN': '1'})
            srv_preset_easy.update({'rce_NAME': 'Race'})
            srv_preset_easy.update({'rce_LAPS': '15'})
            srv_preset_easy.update({'rce_WAIT_TIME': '60'})
            srv_preset_easy.update({'rce_IS_OPEN': '1'})
            srv_preset_easy.update({'wth_GRAPHICS': '3_clear'})
            srv_preset_easy.update({'wth_BASE_TEMPERATURE_AMBIENT': '18'})
            srv_preset_easy.update({'wth_VARIATION_AMBIENT': '5'})
            srv_preset_easy.update({'wth_BASE_TEMPERATURE_ROAD': '9'})
            srv_preset_easy.update({'wth_VARIATION_ROAD': '5'})
            if len(self.__db.findIds("ServerPresets", {"Name": "Professional"})) == 0:
                self.__db.insertRow("ServerPresets", srv_preset_easy)

            # default server preset 'hotlap'
            srv_preset_easy = {}
            srv_preset_easy.update({'Name': "Hotlapping"})
            srv_preset_easy.update({'srv_SUN_ANGLE': '-8'})
            srv_preset_easy.update({'srv_TC_ALLOWED': '1'})
            srv_preset_easy.update({'srv_ABS_ALLOWED': '1'})
            srv_preset_easy.update({'srv_STABILITY_ALLOWED': '0'})
            srv_preset_easy.update({'srv_AUTOCLUTCH_ALLOWED': '0'})
            srv_preset_easy.update({'srv_DAMAGE_MULTIPLIER': '20'})
            srv_preset_easy.update({'srv_FUEL_RATE': '50'})
            srv_preset_easy.update({'srv_TYRE_WEAR_RATE': '30'})
            srv_preset_easy.update({'srv_TYRE_BLANKETS_ALLOWED': '1'})
            srv_preset_easy.update({'srv_WELCOME_MESSAGE': 'Perfect server for training hotlaps.'})
            srv_preset_easy.update({'srv_FORCE_VIRTUAL_MIRROR': '0'})
            srv_preset_easy.update({'srv_LEGAL_TYRES': 'V;E;HR;ST'})
            srv_preset_easy.update({'srv_MAX_BALLAST_KG': '50'})
            srv_preset_easy.update({'dyt_SESSION_START': '100'})
            srv_preset_easy.update({'dyt_RANDOMNESS': '0'})
            srv_preset_easy.update({'dyt_LAP_GAIN': '2'})
            srv_preset_easy.update({'dyt_SESSION_TRANSFER': '100'})
            srv_preset_easy.update({'prt_NAME': 'Practice'})
            srv_preset_easy.update({'prt_TIME': '60'})
            srv_preset_easy.update({'prt_IS_OPEN': '1'})
            srv_preset_easy.update({'qly_NAME': ''})
            srv_preset_easy.update({'rce_NAME': ''})
            srv_preset_easy.update({'wth_GRAPHICS': '3_clear'})
            srv_preset_easy.update({'wth_BASE_TEMPERATURE_AMBIENT': '25'})
            srv_preset_easy.update({'wth_VARIATION_AMBIENT': '1'})
            srv_preset_easy.update({'wth_BASE_TEMPERATURE_ROAD': '9'})
            srv_preset_easy.update({'wth_VARIATION_ROAD': '1'})
            if len(self.__db.findIds("ServerPresets", {"Name": "Hotlapping"})) == 0:
                self.__db.insertRow("ServerPresets", srv_preset_easy)
