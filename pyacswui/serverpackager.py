import argparse
import os
import shutil
from PIL import Image


class ServerPackager():
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



    def work(self, config, verbosity = 0):
        """
            Run the server packager.

            Parameter
            ---------

                config: dictionary with configuration elements
        """



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


        # ===============================
        #  = Create Server Directories =
        # ===============================

        self._mkdirs(config['path_acs'] + "/content/cars")
        self._mkdirs(config['path_acs'] + "/content/tracks")



        # ===================
        #  = Scan All Cars =
        # ===================

        for car in os.listdir(config['path_ac'] + "/content/cars"):

            # skip all non-directories or hidden items
            if car[:1] == "." or not os.path.isdir(config['path_ac'] + "/content/cars/" + car):
                continue

            # user info
            if verbosity > 0:
                print("cars/" + car)

            # create server car directory
            self._mkdirs(config['path_acs'] + "/content/cars/" + car)
            self._mkdirs(config['path_http'] + "/acs_content/cars/" + car)

            # copy acd file
            if os.path.isfile(config['path_ac'] + "/content/cars/" + car + "/data.acd"):
                shutil.copy(config['path_ac'] + "/content/cars/" + car + "/data.acd", config['path_acs'] + "/content/cars/" + car + "/data.acd")

            #copy all data/*.ini files
            if os.path.isdir(config['path_ac'] + "/content/cars/" + car + "/data"):
                self._mkdirs(config['path_acs'] + "/content/cars/" + car + "/data")
                for ini_file in os.listdir(config['path_ac'] + "/content/cars/" + car + "/data"):
                    # skip hidden files and non-ini files
                    if ini_file[:1] == "." or ini_file[-4:] != ".ini" or not os.path.isfile(config['path_ac'] + "/content/cars/" + car + "/data/" + ini_file):
                        continue
                    # copy ini file
                    shutil.copy(config['path_ac'] + "/content/cars/" + car + "/data/" + ini_file, config['path_acs'] + "/content/cars/" + car + "/data/" + ini_file)


            # scan all skins
            if os.path.isdir(config['path_ac'] + "/content/cars/" + car + "/skins"):
                for skin in os.listdir(config['path_ac'] + "/content/cars/" + car + "/skins"):
                    # if preview image present
                    if os.path.isfile(config['path_ac'] + "/content/cars/" + car + "/skins/" + skin + "/preview.jpg"):
                        # create server skin directory
                        self._mkdirs(config['path_http'] + "/acs_content/cars/" + car + "/skins/" + skin)
                        # copy preview image
                        shutil.copy(config['path_ac'] + "/content/cars/" + car + "/skins/" + skin + "/preview.jpg", config['path_http'] + "/acs_content/cars/" + car + "/skins/" + skin + "/preview.jpg")
                        # resize image
                        self._sizeImage(config['path_http'] + "/acs_content/cars/" + car + "/skins/" + skin + "/preview.jpg")



        # =====================
        #  = Scan All Tracks =
        # =====================

        for track in os.listdir(config['path_ac'] + "/content/tracks"):

            # skip all non-directories or hidden items
            if track[:1] == "." or not os.path.isdir(config['path_ac'] + "/content/tracks/" + track):
                continue

            # user info
            if verbosity > 0:
                print("tracks/" + track)

            # create server car directory
            self._mkdirs(config['path_acs'] + "/content/tracks/" + track)
            self._mkdirs(config['path_http'] + "/acs_content/tracks/" + track)

            # copy surfaces.ini
            if os.path.isfile(config['path_ac'] + "/content/tracks/" + track + "/data/surfaces.ini"):
                self._mkdirs(config['path_acs'] + "/content/tracks/" + track + "/data/")
                shutil.copy(config['path_ac'] + "/content/tracks/" + track + "/data/surfaces.ini", config['path_acs'] + "/content/tracks/" + track + "/data/surfaces.ini")

            # copy outline.png
            if os.path.isfile(config['path_ac'] + "/content/tracks/" + track + "/ui/outline.png"):
                self._mkdirs(config['path_http'] + "/acs_content/tracks/" + track + "/ui/")
                shutil.copy(config['path_ac'] + "/content/tracks/" + track + "/ui/outline.png", config['path_http'] + "/acs_content/tracks/" + track + "/ui/outline.png")
                self._sizeImage(config['path_http'] + "/acs_content/tracks/" + track + "/ui/outline.png")

            # copy preview.png
            if os.path.isfile(config['path_ac'] + "/content/tracks/" + track + "/ui/preview.png"):
                self._mkdirs(config['path_http'] + "/acs_content/tracks/" + track + "/ui/")
                shutil.copy(config['path_ac'] + "/content/tracks/" + track + "/ui/preview.png", config['path_http'] + "/acs_content/tracks/" + track + "/ui/preview.png")
                self._sizeImage(config['path_http'] + "/acs_content/tracks/" + track + "/ui/preview.png")

            # scan subdirectories for track configurations
            for configtrack in os.listdir(config['path_ac'] + "/content/tracks/" + track):

                # copy surfaces.ini
                if os.path.isfile(config['path_ac'] + "/content/tracks/" + track + "/" + configtrack + "/data/surfaces.ini"):
                    self._mkdirs(config['path_acs'] + "/content/tracks/" + track + "/" + configtrack + "/data/")
                    shutil.copy(config['path_ac'] + "/content/tracks/" + track + "/" + configtrack + "/data/surfaces.ini", config['path_acs'] + "/content/tracks/" + track + "/" + configtrack + "/data/surfaces.ini")

                # copy outline.png
                if os.path.isfile(config['path_ac'] + "/content/tracks/" + track + "/ui/" + configtrack + "/outline.png"):
                    self._mkdirs(config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack)
                    shutil.copy(config['path_ac'] + "/content/tracks/" + track + "/ui/" + configtrack + "/outline.png", config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack + "/outline.png")
                    self._sizeImage(config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack + "/outline.png")

                # copy preview.png
                if os.path.isfile(config['path_ac'] + "/content/tracks/" + track + "/ui/" + configtrack + "/preview.png"):
                    self._mkdirs(config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack)
                    shutil.copy(config['path_ac'] + "/content/tracks/" + track + "/ui/" + configtrack + "/preview.png", config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack + "/preview.png")
                    self._sizeImage(config['path_http'] + "/acs_content/tracks/" + track + "/ui/" + configtrack + "/preview.png")

