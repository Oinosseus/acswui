import os
import subprocess
import signal
import time
from .udp_plugin_server import UdpPluginServer
from .verbose_class import VerboseClass
from .db_wrapper import DbWrapper


class CommandSrvrun(VerboseClass):

    def __init__(self, argsubparser):
        VerboseClass.__init__(self)

        ap = argsubparser.add_parser('srvrun', help="run the ac server")
        ap.set_defaults(func=self.run)

        self.__server_proc = None
        self.__udp_srv = None
        self.__database = None


    def run(self, args, config):
        self.print(1, "prepare environment")
        self.Verbosity = args.v

        # setup database
        self.__database = DbWrapper(config)
        self.__database.Verbosity = self.Verbosity - 2

        # setup UDP plugin listener
        self.print(2, "starting acServer")
        self.__udp_srv = UdpPluginServer("127.0.0.1", 9603, self.__database)
        self.__udp_srv.Verbosity = self.Verbosity - 1

        # start acServer as separate process
        self.print(2, "starting acServer")
        self.__server_proc = subprocess.Popen("./acServer", cwd=config['path_acs'], stdout=subprocess.DEVNULL)

        # run server
        self.print(1, "run server")
        while True:

            # process server
            try:
                self.__udp_srv.process()
            except BaseException as e:
                self.__server_proc.terminate()
                self.__server_proc.wait(timeout=5.0)
                raise e

            # quit parsing when acServer is stopped
            ret = self.__server_proc.poll()
            if ret is not None:
                self.print(2, "acServer has finished with returncode", ret)
                break

        self.print(1, "finish ac server run")
