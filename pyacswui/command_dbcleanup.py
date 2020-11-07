import argparse
import subprocess
import shutil
import os
import json
from .command import Command, ArgumentException
from .database import Database
from .verbosity import Verbosity

class CommandDbCleanup(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "db-cleanup", "Cleanup database from unuseful data")

        ##################
        # Basic Arguments

        # database
        self.add_argument('--db-host', help="Database host (not needed when global config is given)")
        self.add_argument('--db-port', help="Database port (not needed when global config is given)")
        self.add_argument('--db-database', help="Database name (not needed when global config is given)")
        self.add_argument('--db-user', help="Database username (not needed when global config is given)")
        self.add_argument('--db-password', help="Database password (not needed when global config is given)")

        ## http settings
        #self.add_argument('--http-path', help="Target directory of http server")
        #self.add_argument('--http-log-path', help="Directory for http logfiles")
        #self.add_argument('--http-root-password', help="Password for root user")
        #self.add_argument('--http-guest-group', default="Visitor", help="Group name of visitors that are not logged in")
        #self.add_argument('--http-default-tmplate', default="acswui", help="Default template for http")
        #self.add_argument('--http-guid', help="Name of webserver group on the server (needed to chmod access rights)")

        #self.add_argument('--path-acs-target', help="path to AC server target directory")
        #self.add_argument('--install-base-data', action="store_true", help="install basic http data (default groups, etc.)")
        #self.add_argument('--http-path-acs-content', help="Path that stores AC data for http access (eg. track car preview images)")


    def process(self):

        # setup database
        self.__db = Database(host=self.getArg("db_host"),
                             port=self.getArg("db_port"),
                             database=self.getArg("db_database"),
                             user=self.getArg("db_user"),
                             password=self.getArg("db_password"),
                             verbosity=Verbosity(self.Verbosity)
                             )

        self.__cleanup_empty_sessions()



    def __cleanup_empty_sessions(self):
        self.Verbosity.print("Delete sessions without laps")
        verb2 = Verbosity(self.Verbosity)

        # find empty sessions
        empty_session_ids = []
        for row in self.__db.fetch("Sessions", ['Id'], {}):
            session_id = row['Id']
            laps = self.__db.fetch("Laps", ['Id'], {"Session":session_id})
            if len(laps) == 0:
                empty_session_ids.append(session_id)

        verb2.print("%i empty sessions found" % len(empty_session_ids))

        # delete empty sessions
        for session_id in empty_session_ids:
            self.__db.deleteRow("Sessions", session_id)
