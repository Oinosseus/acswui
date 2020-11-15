import argparse
import os
import sys
import shutil
import json
from PIL import Image
from .command import Command, ArgumentException
from .verbosity import Verbosity


class CommandInstallFiles(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "install-files", "Copy files to the http and AC server directories")
        self.add_argument('--path-ac', help="Path to AC installation directory")
        self.add_argument('--path-acs-source', help="Path to AC server source directory")
        self.add_argument('--path-acs-target', help="Path to AC server target/install directory")

        self.add_argument('--http-path', help="Target directory of http server")
        self.add_argument('--http-path-acs-content', help="Path that stores AC data for http access (eg. track car preview images)")



    def __copy2acs(self, *ac_file):
        """ Copy a file from AC source directory to AC server target directory
            Ignored when files does not exist
            @param ac_file Filename relative from AC directory (a path list will be joined)
            @return True if file exist
        """

        src_file = os.path.join(self.getArg("path-ac"), *ac_file)
        dst_file = os.path.join(self.getArg("path-acs-target"), *ac_file)
        if os.path.isfile(src_file):
            self.mkdirs(os.path.dirname(dst_file))
            shutil.copy(src_file, dst_file)
            return True
        return False


    def __copy2http(self, *ac_file):
        """ Copy a file from AC source directory to http/acs_content directory
            Ignored when files does not exist.
            When ac_file is an image, it is resized
            @param ac_file Filename relative from AC directory (a path list will be joined)
            @return True if file exist
        """
        src_file = os.path.join(self.getArg("path-ac"), *ac_file)
        dst_file = os.path.join(self.getArg("http-path-acs-content"), *ac_file)
        if os.path.isfile(src_file):
            self.mkdirs(os.path.dirname(dst_file))
            shutil.copy(src_file, dst_file)

            suffix = src_file[-4:].lower()
            if suffix in [".png", ".jpg", ".gif", "jpeg"]:
                self._sizeImage(dst_file)

            return True
        return False



    def _sizeImage(self, image_file):

        # max size
        size_x_max = 400
        size_y_max = 300

        # load image
        img = Image.open(image_file)
        size_x, size_y = img.size

        # resize x
        if size_x > size_x_max:
            size_x_old = size_x
            size_x = size_x_max
            size_y = int(size_y * size_x / size_x_old)

        # resize y
        if size_y > size_y_max:
            size_y_old = size_y
            size_y = size_y_max
            size_x = int(size_x * size_y / size_y_old)

        # save file if size has changed
        if (size_x, size_y) != img.size:
            img = img.resize((size_x, size_y), Image.ANTIALIAS)
            img.save(image_file)



    def process(self):
        self.__create_dirs_http()
        self.__create_dirs_acs()
        self.__create_server_cfg_json()
        self.__scan_cars()
        self.__scan_tracks()



    def __scan_cars(self):
        """
            1. copy all car acd files from path_ac to path_acs
            2. copy ui_car.json from path_ac to path_http
            3. copy data/*.ini files to from path_ac path_acs
            4. copy all car skins preview images from path_ac to path_http
        """
        self.Verbosity.print("Scanning cars")
        verb2 = Verbosity(self.Verbosity)
        verb3 = Verbosity(verb2)

        path_ac_cars = os.path.join(self.getArg("path-ac"), "content", "cars")
        for car in sorted(os.listdir(path_ac_cars)):
            path_ac_car = os.path.join("content", "cars", car)

            # skip all non-directories or hidden items
            if car[:1] == "." or not os.path.isdir(os.path.join(self.getArg('path-ac'), path_ac_car)):
                continue

            # user info
            verb3.print(car)

            self.__copy2acs(path_ac_car, "data.acd")
            self.__copy2http(path_ac_car, "ui", "ui_car.json")

            # copy all data/*.ini files
            path_ac_car_data = os.path.join(path_ac_cars, car, "data")
            if os.path.isdir(path_ac_car_data):
                for ini_file in os.listdir(path_ac_car_data):
                    if ini_file[:1] == "." or ini_file[-4:] != ".ini" or not os.path.isfile(os.path.join(path_ac_car_data, ini_file)):
                        continue
                    self.__copy2acs(path_ac_car, "data", ini_file)


            # scan all skins
            path_ac_car_skins = os.path.join(path_ac_cars, car, "skins")
            if os.path.isdir(path_ac_car_skins):
                for skin in os.listdir(path_ac_car_skins):
                    #print("HERE", car, skin)

                    if self.__copy2http(path_ac_car, "skins", skin, "preview.jpg"):
                        pass
                    elif self.__copy2http(path_ac_car, "skins", skin, "Preview.jpg"):
                        path = os.path.join(self.getArg("http-path-acs-content"), "cars", car, "skins", skin)
                        src = os.path.join(path, "Preview.jpg")
                        dst = os.path.join(path, "preview.jpg")
                        shutil.move(src, dst)
                    else:
                        print("ERROR: cannot find preview for skin 'cars/%s/skins/%s'" % (car, skin), file=sys.stderr)



    def __scan_tracks(self):
        """
            1. copy surfaces.ini from path_ac to path_acs
            2. copy outline.png from path_ac to path_http
            3. copy ui_track.json from path_ac to path_hhtp
            4. copy preview.png from path_ac to path_http
            5. copy surfaces.ini, outline.png and preview.png of track configurations
        """
        self.Verbosity.print("Scanning tracks")
        verb2 = Verbosity(self.Verbosity)
        verb3 = Verbosity(verb2)

        path_ac_tracks = os.path.join(self.getArg("path-ac"), "content", "tracks")
        for track in os.listdir(path_ac_tracks):

            path_track = os.path.join("content", "tracks", track)

            # skip all non-directories or hidden items
            if track[:1] == "." or not os.path.isdir(os.path.join(path_ac_tracks, track)):
                continue

            # user info
            verb3.print(track)

            # copy files
            self.__copy2acs(path_track, "data", "surfaces.ini")
            self.__copy2http(path_track, "ui", "outline.png")
            self.__copy2http(path_track, "ui", "preview.png")
            self.__copy2http(path_track, "ui", "ui_track.json")

            # scan subdirectories for track configurations
            for configtrack in os.listdir(os.path.join(path_ac_tracks, track)):
                self.__copy2acs(path_track, configtrack, "data", "surfaces.ini")
                self.__copy2http(path_track, "ui", configtrack, "ui_track.json")
                self.__copy2http(path_track, "ui", configtrack, "outline.png")
                self.__copy2http(path_track, "ui", configtrack, "preview.png")
                self.__copy2http(path_track, "ui", configtrack, "ui_track.json")



    def __create_dirs_http(self):
        """
            1. Delete current HTTP target directory
            2. Copy files from http/ to target directory
            3. Create acs_content directory
        """
        self.Verbosity.print("Create http target directory")
        verb2 = Verbosity(self.Verbosity)
        verb3 = Verbosity(verb2)

        path_http = os.path.abspath(self.getArg("http-path"))
        if not os.path.isdir(path_http):
            verb2.print("create http target directory: " + path_http)
            self.mkdirs(path_http)

        verb2.print("create new directory: " + path_http)
        http_src = os.path.join(os.path.abspath(os.path.dirname(__file__)), "..", "http")
        self.copytree(http_src, self.getArg("http-path"))

        path_acs_content = self.getArg("http-path-acs-content")
        if os.path.isdir(path_acs_content):
            verb2.print("delete current directory: " + path_acs_content)
            shutil.rmtree(path_acs_content)

        verb2.print("create acs_content directory: " + path_acs_content)
        self.mkdirs(path_acs_content)



    def __create_server_cfg_json(self):
        self.Verbosity.print("create server_cfg.json")

        # read template
        with open(os.path.join(os.path.abspath(os.path.dirname(__file__)), "server_cfg.json"), "r") as f:
            json_string = f.read()
        server_cfg_json = json.loads(json_string)

        # scan for weather enums
        weathers = []
        weather_path = os.path.join(self.getArg('path-ac'), "content", "weather")
        for w in sorted(os.listdir(weather_path)):
            if w[:1] != "." and os.path.isdir(os.path.join(weather_path, w)):
                weathers.append(w)

        # append to wether graphics
        for field in server_cfg_json["WEATHER"][0]["FIELDS"]:
            if field["TAG"] == "GRAPHICS":
                for i in range(len(weathers)):
                    field["ENUMS"].append({"VALUE":i, "TEXT":weathers[i]})

        # dump to http directory
        with open(os.path.join(self.getArg("http-path-acs-content"), "server_cfg.json"), "w") as f:
            json.dump(server_cfg_json, f, indent=4)



    def __create_dirs_acs(self):
        self.Verbosity.print("Create AC server target directory")
        verb2 = Verbosity(self.Verbosity)
        verb3 = Verbosity(verb2)


        # ---------------------------------------------------------------------
        # 2. Create new AC server directories

        verb2.print("Create new directories")
        path_acs_target = os.path.abspath(self.getArg("path-acs-target"))

        verb3.print(path_acs_target)
        self.mkdirs(path_acs_target)

        path_acs_target_cfg = os.path.join(path_acs_target, "cfg")
        verb3.print(path_acs_target_cfg)
        self.mkdirs(path_acs_target_cfg)

        path_acs_target_system = os.path.join(path_acs_target, "system")
        verb3.print(path_acs_target_system)
        self.mkdirs(path_acs_target_system)

        path_acs_target_system_data = os.path.join(path_acs_target_system, "data")
        verb3.print(path_acs_target_system_data)
        self.mkdirs(path_acs_target_system_data)


        # ---------------------------------------------------------------------
        # 3. Copy required AC server files

        verb2.print("Copy required files")

        src_file = os.path.join(self.getArg("path-acs-source"), "acServer")
        verb3.print(src_file)
        shutil.copy(src_file, path_acs_target)

        src_file = os.path.join(self.getArg("path-acs-source"), "system", "data", "surfaces.ini")
        verb3.print(src_file)
        shutil.copy(src_file, path_acs_target_system_data)


        # ---------------------------------------------------------------------
        # 4. Create acServer binary for each server slot

        verb2.print("Ccreate acServer binary for every server slot")
        slot_nr = 0
        while True:
            slot_dict = self.getIniSection("SERVER_SLOT_" + str(slot_nr))
            if slot_dict is None:
                break

            src = os.path.join(path_acs_target, "acServer")
            dst = os.path.join(path_acs_target, "acServer" + str(slot_nr))
            verb3.print(dst)
            shutil.copy(src, dst)

            slot_nr += 1
