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



    def work(self, args):
        """
            Run the server packager.

            Params
            ======
                args: ArgumentParser.Namespace
        """



        # ========================
        #  = Input Sanity Check =
        # ========================

        if not isinstance(args, argparse.Namespace):
            raise TypeError("Parameter 'args' must be of argparse.Namespace type!")

        # check ac directory
        if type(args.path_ac) != type("abc") or not os.path.isdir(args.path_ac) or not os.path.isfile(args.path_ac + "/AssettoCorsa.exe"):
            raise NotImplementedError("Asetto Corsa directory '%s' invalid!" % args.path_ac)

        # check acs directory
        if type(args.path_acs) != type("abc") or not os.path.isdir(args.path_acs) or not os.path.isfile(args.path_acs + "/acServer"):
            raise NotImplementedError("Asetto Corsa Server directory '%s' invalid!" % args.path_ac)



        # ===============================
        #  = Create Server Directories =
        # ===============================

        self._mkdirs(args.path_acs + "/content/cars")
        self._mkdirs(args.path_acs + "/content/tracks")



        # ===================
        #  = Scan All Cars =
        # ===================

        for car in os.listdir(args.path_ac + "/content/cars"):

            # skip all non-directories or hidden items
            if car[:1] == "." or not os.path.isdir(args.path_ac + "/content/cars/" + car):
                continue

            # user info
            if args.v > 0:
                print("cars/" + car)

            # create server car directory
            self._mkdirs(args.path_acs + "/content/cars/" + car)

            # copy acd file
            if os.path.isfile(args.path_ac + "/content/cars/" + car + "/data.acd"):
                shutil.copy(args.path_ac + "/content/cars/" + car + "/data.acd", args.path_acs + "/content/cars/" + car + "/data.acd")

			# copy all data/*.ini files
            if os.path.isdir(args.path_ac + "/content/cars/" + car + "/data"):
                self._mkdirs(args.path_acs + "/content/cars/" + car + "/data")
                for ini_file in os.listdir(args.path_ac + "/content/cars/" + car + "/data"):
                    # skip hidden files and non-ini files
                    if ini_file[:1] == "." or ini_file[-4:] != ".ini" or not os.path.isfile(args.path_ac + "/content/cars/" + car + "/data/" + ini_file):
                        continue
                    # copy ini file
                    shutil.copy(args.path_ac + "/content/cars/" + car + "/data/" + ini_file, args.path_acs + "/content/cars/" + car + "/data/" + ini_file)


            # scan all skins
            if os.path.isdir(args.path_ac + "/content/cars/" + car + "/skins"):
                for skin in os.listdir(args.path_ac + "/content/cars/" + car + "/skins"):
                    # if preview image present
                    if os.path.isfile(args.path_ac + "/content/cars/" + car + "/skins/" + skin + "/preview.jpg"):
                        # create server skin directory
                        self._mkdirs(args.path_acs + "/content/cars/" + car + "/skins/" + skin)
                        # copy preview image
                        shutil.copy(args.path_ac + "/content/cars/" + car + "/skins/" + skin + "/preview.jpg", args.path_acs + "/content/cars/" + car + "/skins/" + skin + "/preview.jpg")
                        # resize image
                        self._sizeImage(args.path_acs + "/content/cars/" + car + "/skins/" + skin + "/preview.jpg")



        # =====================
        #  = Scan All Tracks =
        # =====================

        for track in os.listdir(args.path_ac + "/content/tracks"):

            # skip all non-directories or hidden items
            if track[:1] == "." or not os.path.isdir(args.path_ac + "/content/tracks/" + track):
                continue

            # user info
            if args.v > 0:
                print("tracks/" + track)

            # create server car directory
            self._mkdirs(args.path_acs + "/content/tracks/" + track)

            # copy surfaces.ini
            if os.path.isfile(args.path_ac + "/content/tracks/" + track + "/data/surfaces.ini"):
                self._mkdirs(args.path_acs + "/content/tracks/" + track + "/data/")
                shutil.copy(args.path_ac + "/content/tracks/" + track + "/data/surfaces.ini", args.path_acs + "/content/tracks/" + track + "/data/surfaces.ini")

            # copy outline.png
            if os.path.isfile(args.path_ac + "/content/tracks/" + track + "/ui/outline.png"):
                self._mkdirs(args.path_acs + "/content/tracks/" + track + "/ui/")
                shutil.copy(args.path_ac + "/content/tracks/" + track + "/ui/outline.png", args.path_acs + "/content/tracks/" + track + "/ui/outline.png")
                self._sizeImage(args.path_acs + "/content/tracks/" + track + "/ui/outline.png")

            # copy preview.png
            if os.path.isfile(args.path_ac + "/content/tracks/" + track + "/ui/preview.png"):
                self._mkdirs(args.path_acs + "/content/tracks/" + track + "/ui/")
                shutil.copy(args.path_ac + "/content/tracks/" + track + "/ui/preview.png", args.path_acs + "/content/tracks/" + track + "/ui/preview.png")
                self._sizeImage(args.path_acs + "/content/tracks/" + track + "/ui/preview.png")

            # scan subdirectories for track configurations
            for configtrack in os.listdir(args.path_ac + "/content/tracks/" + track):

                # copy surfaces.ini
                if os.path.isfile(args.path_ac + "/content/tracks/" + track + "/" + configtrack + "/data/surfaces.ini"):
                    self._mkdirs(args.path_acs + "/content/tracks/" + track + "/" + configtrack + "/data/")
                    shutil.copy(args.path_ac + "/content/tracks/" + track + "/" + configtrack + "/data/surfaces.ini", args.path_acs + "/content/tracks/" + track + "/" + configtrack + "/data/surfaces.ini")

                # copy outline.png
                if os.path.isfile(args.path_ac + "/content/tracks/" + track + "/ui/" + configtrack + "/outline.png"):
                    self._mkdirs(args.path_acs + "/content/tracks/" + track + "/ui/" + configtrack)
                    shutil.copy(args.path_ac + "/content/tracks/" + track + "/ui/" + configtrack + "/outline.png", args.path_acs + "/content/tracks/" + track + "/ui/" + configtrack + "/outline.png")
                    self._sizeImage(args.path_acs + "/content/tracks/" + track + "/ui/" + configtrack + "/outline.png")

                # copy preview.png
                if os.path.isfile(args.path_ac + "/content/tracks/" + track + "/ui/" + configtrack + "/preview.png"):
                    self._mkdirs(args.path_acs + "/content/tracks/" + track + "/ui/" + configtrack)
                    shutil.copy(args.path_ac + "/content/tracks/" + track + "/ui/" + configtrack + "/preview.png", args.path_acs + "/content/tracks/" + track + "/ui/" + configtrack + "/preview.png")
                    self._sizeImage(args.path_acs + "/content/tracks/" + track + "/ui/" + configtrack + "/preview.png")

