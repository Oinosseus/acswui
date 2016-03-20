import argparse
import subprocess
import shutil
import os
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
        self.__db = DbWrapper(config, verbosity)

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


        # ===============================
        #  = Create Database Structure =
        # ===============================

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

        # check table TrackRating
        if self.__verbosity > 0:
            print("check database table `TrackRating`")
        self.__db.appendTable("TrackRating")
        self.__db.appendColumnInt("TrackRating", "User")
        self.__db.appendColumnInt("TrackRating", "Track")
        self.__db.appendColumnInt("TrackRating", "RateGraphics")
        self.__db.appendColumnInt("TrackRating", "RateDrive")

        # check table UserDriversMap
        if self.__verbosity > 0:
            print("check database table `UserDriversMap`")
        self.__db.appendTable("UserDriversMap")
        self.__db.appendColumnInt("UserDriversMap", "User")
        self.__db.appendColumnInt("UserDriversMap", "Driver")


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

                existing_track_ids = self.__db.findIds("Tracks", {"Track": track})
                if len(existing_track_ids) == 0:
                    self.__db.insertRow("Tracks", {"Track": track, "Config": "", "Name": track_name, "Length": track_length})

            # update track configs
            if os.path.isdir(track_path + "/ui"):
                for track_config in os.listdir(track_path + "/ui"):
                    if os.path.isdir(track_path + "/ui/" + track_config):
                        if os.path.isfile(track_path + "/ui/" + track_config + "/ui_track.json"):
                            track_name   = parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "name", track)
                            track_length = parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "length", "0")
                            track_length = interpret_length(track_length)

                            existing_track_ids = self.__db.findIds("Tracks", {"Track": track, "Config": track_config})
                            if len(existing_track_ids) == 0:
                                self.__db.insertRow("Tracks", {"Track": track, "Config": track_config, "Name": track_name, "Length": track_length})



        # =======================
        #  = Install Base Data =
        # =======================

        if self.__verbosity > 0:
            print("Install Base Data")

        # add guest group
        if 'http_guest_group' in self.__config and len(self.__config['http_guest_group']) > 0:
            self.__db.insertRow("Groups", {"Name": self.__config['http_guest_group']})

        # optional data
        if self.__install_base_data:

            # default groups
            self.__db.insertRow("Groups", {"Name": "Driver"})
            self.__db.insertRow("Groups", {"Name": "Race Orga"})
            self.__db.insertRow("Groups", {"Name": "Server Admin"})
