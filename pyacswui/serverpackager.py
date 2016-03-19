import argparse
import os
import shutil
from PIL import Image


class ServerPackager():

    def __init__(self, config, verbosity = 0):


        # ========================
        #  = Input Sanity Check =
        # ========================

        if type(config) != type({}):
            raise TypeError("Parameter 'config' must be dict!")

        # check ac directory
        if 'path_ac' not in config or not os.path.isdir(config['path_ac']) or not os.path.isfile(config['path_ac'] + "/AssettoCorsa.exe"):
            raise NotImplementedError("Asetto Corsa directory '%s' invalid!" % config['path_ac'])

        # check acs directory
        if 'path_acs' not in config or not os.path.isdir(config['path_acs']) or not os.path.isfile(config['path_acs'] + "/acServer"):
            raise NotImplementedError("Asetto Corsa Server directory '%s' invalid!" % config['path_acs'])

        # check http directory
        if 'path_http' not in config or not os.path.isdir(config['path_http']):
            raise NotImplementedError("Http directory '%s' invalid!" % config['path_http'])

        # save attributes
        self.__config = {}
        self.__config.update(config)
        self.__verbosity = int(verbosity)



    def _mkdirs(self, dirs):
        """
            Create complete directory path.
            No error is raised if path already existent.
        """
        if not os.path.isdir(dirs):
            os.makedirs(dirs)

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



    def work(self):
        """
            Do the intended work.

            Cars
            ----

                1. copy all car acd files from path_ac to path_acs
                2. copy ui_car.json from path_ac to path_http
                3. copy data/*.ini files to from path_ac path_acs
                4. copy all car skins preview images from path_ac to path_http


            Tracks
            ------

                1. copy surfaces.ini from path_ac to path_acs
                2. copy outline.png from path_ac to path_http
                3. copy ui_track.json from path_ac to path_hhtp
                4. copy preview.png from path_ac to path_http
                5. copy surfaces.ini, outline.png and preview.png of track configurations
        """



        # ===============================
        #  = Create Server Directories =
        # ===============================

        self._mkdirs(self.__config['path_acs'] + "/content/cars")
        self._mkdirs(self.__config['path_acs'] + "/content/tracks")



        # ===================
        #  = Scan All Cars =
        # ===================

        for car in os.listdir(self.__config['path_ac'] + "/content/cars"):

            # skip all non-directories or hidden items
            if car[:1] == "." or not os.path.isdir(self.__config['path_ac'] + "/content/cars/" + car):
                continue

            # user info
            if self.__verbosity > 0:
                print("cars/" + car)

            # create server car directory
            self._mkdirs(self.__config['path_acs'] + "/content/cars/" + car)
            self._mkdirs(self.__config['path_http'] + "/acs_content/cars/" + car)

            # copy acd file
            if os.path.isfile(self.__config['path_ac'] + "/content/cars/" + car + "/data.acd"):
                shutil.copy(self.__config['path_ac'] + "/content/cars/" + car + "/data.acd", self.__config['path_acs'] + "/content/cars/" + car + "/data.acd")

            # copy ui/ui_car.json
            if os.path.isfile(self.__config['path_ac'] + "/content/cars/" + car + "/ui/ui_car.json"):
                self._mkdirs(self.__config['path_http'] + "/acs_content/cars/" + car + "/ui")
                shutil.copy(self.__config['path_ac'] + "/content/cars/" + car + "/ui/ui_car.json", self.__config['path_http'] + "/acs_content/cars/" + car + "/ui/ui_car.json")

            #copy all data/*.ini files
            if os.path.isdir(self.__config['path_ac'] + "/content/cars/" + car + "/data"):
                self._mkdirs(self.__config['path_acs'] + "/content/cars/" + car + "/data")
                for ini_file in os.listdir(self.__config['path_ac'] + "/content/cars/" + car + "/data"):
                    # skip hidden files and non-ini files
                    if ini_file[:1] == "." or ini_file[-4:] != ".ini" or not os.path.isfile(self.__config['path_ac'] + "/content/cars/" + car + "/data/" + ini_file):
                        continue
                    # copy ini file
                    shutil.copy(self.__config['path_ac'] + "/content/cars/" + car + "/data/" + ini_file, self.__config['path_acs'] + "/content/cars/" + car + "/data/" + ini_file)


            # scan all skins
            if os.path.isdir(self.__config['path_ac'] + "/content/cars/" + car + "/skins"):
                for skin in os.listdir(self.__config['path_ac'] + "/content/cars/" + car + "/skins"):
                    # if preview image present
                    for preview_name in ['preview.jpg', 'Preview.jpg']:
                        if os.path.isfile(self.__config['path_ac'] + "/content/cars/" + car + "/skins/" + skin + "/" + preview_name):
                            # create server skin directory
                            self._mkdirs(self.__config['path_http'] + "/acs_content/cars/" + car + "/skins/" + skin)
                            # copy preview image
                            shutil.copy(self.__config['path_ac'] + "/content/cars/" + car + "/skins/" + skin + "/" + preview_name, self.__config['path_http'] + "/acs_content/cars/" + car + "/skins/" + skin + "/preview.jpg")
                            # resize image
                            self._sizeImage(self.__config['path_http'] + "/acs_content/cars/" + car + "/skins/" + skin + "/preview.jpg")





        # =====================
        #  = Scan All Tracks =
        # =====================

        for track in os.listdir(self.__config['path_ac'] + "/content/tracks"):

            # skip all non-directories or hidden items
            if track[:1] == "." or not os.path.isdir(self.__config['path_ac'] + "/content/tracks/" + track):
                continue

            # user info
            if self.__verbosity > 0:
                print("tracks/" + track)

            # create server car directory
            self._mkdirs(self.__config['path_acs'] + "/content/tracks/" + track)
            self._mkdirs(self.__config['path_http'] + "/acs_content/tracks/" + track)

            # copy surfaces.ini
            if os.path.isfile(self.__config['path_ac'] + "/content/tracks/" + track + "/data/surfaces.ini"):
                self._mkdirs(self.__config['path_acs'] + "/content/tracks/" + track + "/data/")
                shutil.copy(self.__config['path_ac'] + "/content/tracks/" + track + "/data/surfaces.ini", self.__config['path_acs'] + "/content/tracks/" + track + "/data/surfaces.ini")

            # copy outline.png
            if os.path.isfile(self.__config['path_ac'] + "/content/tracks/" + track + "/ui/outline.png"):
                self._mkdirs(self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/")
                shutil.copy(self.__config['path_ac'] + "/content/tracks/" + track + "/ui/outline.png", self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/outline.png")
                self._sizeImage(self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/outline.png")

            # copy ui/ui_track.json
            if os.path.isfile(self.__config['path_ac'] + "/content/tracks/" + track + "/ui/ui_track.json"):
                self._mkdirs(self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui")
                shutil.copy(self.__config['path_ac'] + "/content/tracks/" + track + "/ui/ui_track.json", self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/ui_track.json")

            # copy preview.png
            if os.path.isfile(self.__config['path_ac'] + "/content/tracks/" + track + "/ui/preview.png"):
                self._mkdirs(self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/")
                shutil.copy(self.__config['path_ac'] + "/content/tracks/" + track + "/ui/preview.png", self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/preview.png")
                self._sizeImage(self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/preview.png")

            # scan subdirectories for track configurations
            for configtrack in os.listdir(self.__config['path_ac'] + "/content/tracks/" + track):

                # copy surfaces.ini
                if os.path.isfile(self.__config['path_ac'] + "/content/tracks/" + track + "/" + configtrack + "/data/surfaces.ini"):
                    self._mkdirs(self.__config['path_acs'] + "/content/tracks/" + track + "/" + configtrack + "/data/")
                    shutil.copy(self.__config['path_ac'] + "/content/tracks/" + track + "/" + configtrack + "/data/surfaces.ini", self.__config['path_acs'] + "/content/tracks/" + track + "/" + configtrack + "/data/surfaces.ini")

                # copy ui/ui_track.json
                if os.path.isfile(self.__config['path_ac'] + "/content/tracks/" + track + "/ui/" + configtrack + "/ui_track.json"):
                    self._mkdirs(self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack)
                    shutil.copy(self.__config['path_ac'] + "/content/tracks/" + track + "/ui/" + configtrack + "/ui_track.json", self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack + "/ui_track.json")

                # copy outline.png
                if os.path.isfile(self.__config['path_ac'] + "/content/tracks/" + track + "/ui/" + configtrack + "/outline.png"):
                    self._mkdirs(self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack)
                    shutil.copy(self.__config['path_ac'] + "/content/tracks/" + track + "/ui/" + configtrack + "/outline.png", self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack + "/outline.png")
                    self._sizeImage(self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack + "/outline.png")

                # copy preview.png
                if os.path.isfile(self.__config['path_ac'] + "/content/tracks/" + track + "/ui/" + configtrack + "/preview.png"):
                    self._mkdirs(self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack)
                    shutil.copy(self.__config['path_ac'] + "/content/tracks/" + track + "/ui/" + configtrack + "/preview.png", self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack + "/preview.png")
                    self._sizeImage(self.__config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack + "/preview.png")

