from .command import Command, ArgumentException
# import datetime
from .database import Database
import os
# import shutil
import subprocess
from .verbosity import Verbosity


class CommandRestore(Command):

    def __init__(self, argparser):
        Command.__init__(self, argparser, "restore", "Restores a backup from path-srvpkg/backup/ directory")
        self.add_argument('--overwrite', action="store_true", help="[DANGEROUS !!!] This will delete existing data [DANGEROUS !!!]")
        self._v = Verbosity(0, "CMD Restore")
        self._v2 = Verbosity(self._v)

    def process(self):


        # setup database
        self.__db = Database(host=self.getGeneralArg("db-host"),
                             port=self.getGeneralArg("db-port"),
                             database=self.getGeneralArg("db-database"),
                             user=self.getGeneralArg("db-user"),
                             password=self.getGeneralArg("db-password")
                             )

        # ensure that target directory is ready
        self._v.print("checking restore directory")
        backup_path = os.path.join(self.getGeneralArg("path-srvpkg"), "backup")
        if not os.path.isdir(backup_path):
            raise NotImplementedError("Did not found backup directory: " + backup_path)

        # restore database
        self._v.print("Database")
        if len(self.__db.tables()) > 0 and not self.getArg("overwrite"):
            raise NotImplementedError("Database is not empty, please provide empty database or use '--overwrite' argument!")
        backup_path_sql = os.path.join(backup_path, "database.sql")
        if not os.path.isfile(backup_path_sql):
            self._v2.print("WARNING: no database back found in", backup_path_sql)
        else:
            self._v2.print("restoring database", backup_path_sql)
            fd = open(backup_path_sql, "r")
            cmd = ["mysql"]
            cmd.append("--password=" + self.getGeneralArg("db-password"))
            cmd.append("--host=" + self.getGeneralArg("db-host"))
            cmd.append("--port=" + self.getGeneralArg("db-port"))
            cmd.append("--user=" + self.getGeneralArg("db-user"))
            cmd.append("--protocol=TCP")
            cmd.append(self.getGeneralArg("db-database"))
            subprocess.run(cmd, stdin=fd, check=True)
            fd.close()

        # restoring htdata
        self._v.print("path-htdata/")
        backup_path_htdata = os.path.join(backup_path, "htdata")
        restore_path_htdata = self.getGeneralArg("path-htdata")
        if len(os.listdir(restore_path_htdata)) > 0 and not self.getArg("overwrite"):
            raise NotImplementedError("Cannot overwrite existing data. Use '--overwrite' argument or clear directory:", restore_path_htdata)
        self._v2.print("copy backup data")
        self.copytree(backup_path_htdata, restore_path_htdata)

        # restoring data
        self._v.print("path-data/")
        backup_path_data = os.path.join(backup_path, "data")
        restore_path_data = self.getGeneralArg("path-data")
        if len(os.listdir(restore_path_data)) > 0 and not self.getArg("overwrite"):
            raise NotImplementedError("Cannot overwrite existing data. Use '--overwrite' argument or clear directory:", restore_path_data)
        self._v2.print("copy backup data")
        self.copytree(backup_path_data, restore_path_data)
