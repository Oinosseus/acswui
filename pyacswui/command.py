from .verbosity import Verbosity


class ArgumentException(BaseException):
    pass



class Command(object):



    def __init__(self, argparse, cmd_name, cmd_help):
        self.__arg_dict = {}
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
            with open(self.__args.ini, "r") as f:
                for line in f.readlines():

                    line = line.strip()

                    # ignore commented lines
                    if line[:1] == "#":
                        continue

                    # ignore empty lines
                    if line == "":
                        continue

                    # split keys and values
                    split = line.split("=",1)
                    if len(split) > 1:
                        self.__arg_dict.update({split[0].strip(): split[1].strip()})

        if self.__args.json is not None:
            json_obj = json.loads(self.__args.json)
            for key, value in json_obj.items():
                self.__arg_dict.update({key: value})

        #for key, val in self.__arg_dict.items():
            #print("HERE", key, val)



    def getArg(self, arg_name):

        arg_name_escaped = arg_name.replace("-", "_")

        if not hasattr(self.__args, arg_name_escaped):
            raise ArgumentException("Argument '%s' is not defined at argparser!" % str(arg_name))

        # try to find from argparser
        from_args = getattr(self.__args, arg_name_escaped)
        if from_args is not None:
            return from_args

        # try to find it from global arguments (INI or JSON)
        if arg_name_escaped in self.__arg_dict:
            return self.__arg_dict[arg_name_escaped]

        raise ArgumentException("Argument '%s' is neither set as commandline argument, nor in INI, nor in JSON!" % arg_name)



    def process(self):
        raise NotImplementedError("You must subclass the Command class and overload the process() method!")
