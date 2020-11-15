import configparser
from .verbosity import Verbosity


class ArgumentException(BaseException):
    pass



class Command(object):



    def __init__(self, argparse, cmd_name, cmd_help):
        self.__ini_dict = {}
        self.__argparse = argparse.add_parser(cmd_name, help=cmd_help)
        self.__argparse.set_defaults(CmdObject=self)
        self.__args = None
        self.__verbosity = Verbosity(0)



    @property
    def Verbosity(self):
        return self.__verbosity



    def add_argument(self, *args, **kwargs):
        # forward to argparser
        self.__argparse.add_argument(*args, **kwargs)



    def readArgs(self, args):
        self.__args = args
        self.__verbosity = Verbosity(self.__args.v)

        if self.__args.ini is not None:
            cp = configparser.ConfigParser()
            cp.read(self.__args.ini)
            self.__ini_dict = cp



    def getArg(self, arg_name):

        arg_name_escaped = arg_name.replace("-", "_")

        if not hasattr(self.__args, arg_name_escaped):
            raise ArgumentException("Argument '%s' is not defined at argparser!" % str(arg_name))

        # try to find from argparser
        from_args = getattr(self.__args, arg_name_escaped)
        if from_args is not None:
            return from_args

        # try to find it from global arguments (INI or JSON)
        if arg_name_escaped in self.__ini_dict['COMMANDLINE_ARGUMENTS']:
            return self.__ini_dict['COMMANDLINE_ARGUMENTS'][arg_name_escaped]

        raise ArgumentException("Argument '%s' is neither set as commandline argument, nor in INI, nor in JSON!" % arg_name)



    def getIniSection(self, section_name):
        if section_name in self.__ini_dict:
            return self.__ini_dict[section_name]
        else:
            return None



    def process(self):
        raise NotImplementedError("You must subclass the Command class and overload the process() method!")
