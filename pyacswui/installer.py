import argparse
import os
import subprocess

class Installer(object):
  
  def work(self, args):
    
    # ===============================
    #  = Create Database Structure =
    # ===============================
    
    if not os.path.isfile(os.path.dirname(__file__) + "/database.sql"):
      with open(os.path.dirname(__file__) + "/database.sql", 'w') as sqlfile:
        subprocess.call(["parsediasql", "--file", os.path.dirname(__file__) + "/../docs/database.dia", "--db", "mysql-innodb"], \
                        stdout = sqlfile)

