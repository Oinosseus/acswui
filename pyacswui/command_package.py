import argparse
import os
import sys
import shutil
import json
from PIL import Image
from .command import Command, ArgumentException
from .verbosity import Verbosity


class CommandPackage(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "package", "Server-Packager - prepare assetto corsa file to be transferred to the linux server")
        self.add_argument('-v', action='count', default=0, help="each 'v' increases the verbosity level")



    def _copy2acs(self, *ac_file):
        """ Copy a file from AC source directory to ac server directory
            Ignored when files does not exist
            @param ac_file Filename relative from AC directory (a path list will be joined)
            @return True if file exist
        """

        src_file = self._pathAC(*ac_file)
        dst_file = self._pathRefPkg("acserver", *ac_file)
        if os.path.isfile(src_file):
            self.mkdirs(os.path.dirname(dst_file))
            shutil.copy(src_file, dst_file)
            return True
        return False



    def _copy2http(self, *ac_file):
        """ Copy a file from AC source directory to http directory
            Ignored when files does not exist
            @param ac_file Filename relative from AC directory (a path list will be joined)
            @return True if file exist
        """

        src_file = self._pathAC(*ac_file)
        dst_file = self._pathRefPkg("htdata", *ac_file)
        if os.path.isfile(src_file):
            self.mkdirs(os.path.dirname(dst_file))
            shutil.copy(src_file, dst_file)
            return True
        return False



    def _pathAC(self, *path):
        """!
            @param path assetto corsa subdirectory
            @return The path to the assettot corsa directory
        """
        base_path = self.getGeneralArg("path-ac")
        for p in path:
            base_path = os.path.join(base_path, p)
        return base_path



    def _pathRefPkg(self, *path):
        """!
            @param path Server packager subdirectory
            @return The path to the server packager directory
        """
        base_path = self.getGeneralArg("path-refpkg")
        for p in path:
            base_path = os.path.join(base_path, p)
        return base_path



    def process(self):
        self._verbosity = Verbosity(self.getArg("v"), self.__class__.__name__)

        # check refpkg directory
        path_refpkg = os.path.abspath(self.getGeneralArg("path-refpkg"))
        if not os.path.isdir(path_refpkg):
            raise NotImplementedError("Cannot find path-refpkg: " + path_refpkg)

        self.__create_server_cfg_json()
        self.__scan_cars()
        self.__scan_tracks()
        self.__scan_server()



    def __create_server_cfg_json(self):
        self._verbosity.print("create server_cfg.json")

        # read template
        with open(os.path.join(os.path.abspath(os.path.dirname(__file__)), "server_cfg.json"), "r") as f:
            json_string = f.read()
        server_cfg_json = json.loads(json_string)

        # append database column names,
        # append curent value
        for section in server_cfg_json:
            for fieldset in server_cfg_json[section]:
                for tag in server_cfg_json[section][fieldset]:
                    tag_dict = server_cfg_json[section][fieldset][tag]
                    tag_dict['DB_COLUMN_NAME'] = section + "_" + tag
                    tag_dict['CURRENT'] = tag_dict['DEFAULT']

        # scan for weather enums
        json_enum = server_cfg_json["WEATHER_0"]['Weather']["GRAPHICS"]['ENUMS']
        weather_path = os.path.join(self.getGeneralArg('path-ac'), "content", "weather")
        weather_index = 0
        for w in sorted(os.listdir(weather_path)):
            if w[:1] != "." and os.path.isdir(os.path.join(weather_path, w)):
                json_enum.append({"VALUE":weather_index, "TEXT":w})
                weather_index += 1

        # dump to output directory
        with open(self._pathRefPkg("server_cfg.json"), "w") as f:
            json.dump(server_cfg_json, f, indent=4)



    def __scan_cars(self):
        """
            1. copy all car acd files from path_ac to path_acs
            2. copy ui_car.json from path_ac to path_http
            3. copy data/*.ini files to from path_ac path_acs
            4. copy all car skins preview images from path_ac to path_http
            5. copy ui_skin.json for all skins to http directory
        """
        self._verbosity.print("Scanning cars")
        verb2 = Verbosity(self._verbosity)

        path_ac_cars = self._pathAC("content", "cars")
        for car in sorted(os.listdir(path_ac_cars)):
            path_ac_car = os.path.join("content", "cars", car)

            # skip all non-directories or hidden items
            if car[:1] == "." or not os.path.isdir(self._pathAC(path_ac_car)):
                continue

            # user info
            verb2.print(car)

            self._copy2acs(path_ac_car, "data.acd")
            self._copy2http(path_ac_car, "ui", "ui_car.json")
            if not self._copy2http(path_ac_car, "ui", "badge.png"):
                print("WARNING: No badge for", path_ac_car)

            # copy all data/*.ini files
            path_ac_car_data = os.path.join(path_ac_cars, car, "data")
            if os.path.isdir(path_ac_car_data):
                for ini_file in os.listdir(path_ac_car_data):
                    if ini_file[:1] == "." or ini_file[-4:] != ".ini" or not os.path.isfile(os.path.join(path_ac_car_data, ini_file)):
                        continue
                    self._copy2acs(path_ac_car, "data", ini_file)


            # scan all skins
            path_ac_car_skins = os.path.join(path_ac_cars, car, "skins")
            if os.path.isdir(path_ac_car_skins):
                for skin in os.listdir(path_ac_car_skins):
                    path_skin = os.path.join(path_ac_car_skins, skin)

                    # skins are in subdirectories
                    if not os.path.isdir(path_skin):
                        continue

                    if self._copy2http(path_ac_car, "skins", skin, "preview.jpg"):
                        pass
                    elif self._copy2http(path_ac_car, "skins", skin, "Preview.jpg"):
                        pass
                    else:
                        print("WARNING: cannot find preview for skin 'cars/%s/skins/%s'" % (car, skin), file=sys.stderr)

                    self._copy2http(path_ac_car, "skins", skin, "ui_skin.json")



    def __scan_tracks(self):
        """
            1. copy surfaces.ini from path_ac to path_acs
            2. copy outline.png from path_ac to path_http
            3. copy ui_track.json from path_ac to path_hhtp
            4. copy preview.png from path_ac to path_http
            5. copy surfaces.ini, outline.png and preview.png of track configurations
        """
        self._verbosity.print("Scanning tracks")
        verb2 = Verbosity(self._verbosity)

        path_ac_tracks = self._pathAC("content", "tracks")
        for track in os.listdir(path_ac_tracks):

            path_track = os.path.join("content", "tracks", track)

            # skip all non-directories or hidden items
            if track[:1] == "." or not os.path.isdir(os.path.join(path_ac_tracks, track)):
                continue

            # user info
            verb2.print(track)

            # copy files
            self._copy2http(path_track, "map.png")
            self._copy2acs(path_track, "data", "surfaces.ini")
            self._copy2http(path_track, "ui", "outline.png")
            self._copy2http(path_track, "ui", "preview.png")
            self._copy2http(path_track, "ui", "ui_track.json")

            # scan subdirectories for track configurations
            for configtrack in os.listdir(os.path.join(path_ac_tracks, track)):
                self._copy2acs(path_track, configtrack, "data", "surfaces.ini")
                self._copy2http(path_track, configtrack, "map.png")
                self._copy2http(path_track, "ui", configtrack, "ui_track.json")
                self._copy2http(path_track, "ui", configtrack, "outline.png")
                self._copy2http(path_track, "ui", configtrack, "preview.png")
                self._copy2http(path_track, "ui", configtrack, "ui_track.json")



    def __scan_server(self):

        self._verbosity.print("Scanning Server Files")

        # acServer binary
        src_file = self._pathAC(os.path.join("server", "acServer"))
        dst_file = self._pathRefPkg(os.path.join("acserver", "acServer"))
        shutil.copy(src_file, dst_file)

        # surfaces.ini
        src_file = self._copy2acs("system", "data", "surfaces.ini")
