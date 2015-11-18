import argparse
import os


class ServerPackager():
    def __init__(self, args):
        """
            Initialize the server packager.

            Params
            ======
                args: ArgumentParser.Namespace
        """

        # input sanity check
        if not isinstance(args, argparse.Namespace):
            raise TypeError("Parameter 'args' must be of argparse.Namespace type!")

        # check ac directory
        if type(args.path_ac) != type("abc") or not os.path.isdir(args.path_ac) or not os.path.isfile(args.path_ac + "/AssettoCorsa.exe"):
            raise NotImplementedError("Asetto Corsa directory '%s' invalid!" % args.path_ac)

        # check acs directory
        if type(args.path_acs) != type("abc") or not os.path.isdir(args.path_acs) or not os.path.isfile(args.path_acs + "/acServer"):
            raise NotImplementedError("Asetto Corsa Server directory '%s' invalid!" % args.path_ac)

        # scan all cars
        for dir in os.listdir(args.path_ac + "/content/cars"):
            pass

        print("ServerPackager")
