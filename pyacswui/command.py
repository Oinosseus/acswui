import configparser
import os.path
import shutil
from .verbosity import Verbosity


class ArgumentException(BaseException):
    pass



class Command(object):



    def __init__(self, argparse, cmd_name, cmd_help, require_ini_file=True):
        self.__require_ini_file = require_ini_file
        self.__ini_dict = {}
        self.__argparse = argparse.add_parser(cmd_name, help=cmd_help)
        if self.__require_ini_file:
            self.__argparse.add_argument('inifile', help="path to INI file with configuration definitions")
        self.__argparse.set_defaults(CmdObject=self)
        self.__args = None



    def getGeneralArg(self, arg):
        return self.__ini_dict["GENERAL"][arg]



    def add_argument(self, *args, **kwargs):
        # forward to argparser
        self.__argparse.add_argument(*args, **kwargs)


    def cleanDir(self, dir_path):
        shutil.rmtree(dir_path)
        self.mkdirs(dir_path)


    def parseArgs(self, args):
        self.__args = args

        if self.__require_ini_file:
            cp = configparser.ConfigParser()
            cp.read(self.__args.inifile)
            self.__ini_dict = cp



    def getArg(self, arg_name):
        arg_name = arg_name.replace("-", "_")
        return getattr(self.__args, arg_name)



    def getIniSection(self, section_name):
        if section_name in self.__ini_dict:
            return self.__ini_dict[section_name]
        else:
            return None



    def process(self):
        raise NotImplementedError("You must subclass the Command class and overload the process() method!")



    def mkdirs(self, dirs):
        """
            Create complete directory path.
            No error is raised if path already existent.
        """
        if not os.path.isdir(dirs):
            os.makedirs(dirs)



    def copytree(self, src, dst):
        """
            Copies a source directory to a destination directory,
            including all subdirectories and files
        """

        # ignore if src and dst are equal
        src = os.path.abspath(src)
        dst = os.path.abspath(dst)
        if src == dst:
            return

        if not os.path.isdir(src):
            raise ValueError("Source path must be a directory: " + str(src))
        if not os.path.isdir(dst):
            self.mkdirs(dst)

        for entry in os.listdir(src):
            entry_src = os.path.join(src, entry)
            entry_dst = os.path.join(dst, entry)

            if os.path.isdir(entry_src):
                self.copytree(entry_src, entry_dst)
            elif os.path.isfile(entry_src):
                shutil.copy(entry_src, entry_dst)
