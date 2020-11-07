#!/usr/bin/python3

# acswui - Asetto Corsa Server Web User Interface
# by Thomas Weinhold
#
# This script supports the acswui installaion, maintenance and update.

import argparse
import os
import sys
import json
#from pyacswui import CommandSrvctl, , ServerPackager
from pyacswui import CommandInstallFiles, CommandInstallHttp, CommandSrvrun, CommandDbCleanup



# ---------------------------------
#  - Parse Commandline Arguments -
# ---------------------------------

__helpstring  = "Examples for complete safe setup:\n"
__helpstring += "./acswui -vvv --ini local.ini install-files\n"
__helpstring += "./acswui -vv --ini remote.ini install-http --http-root-password my-secret\n"

# main arguments
argparser = argparse.ArgumentParser(prog="acswui", description="Assetto Corsa Server Web User Interface", epilog=__helpstring, formatter_class=argparse.RawTextHelpFormatter)
argparser.add_argument('-i', '--ini', help="read arguments from INI file")
argparser.add_argument('-j', '--json', help="read arguments from json string")
argparser.add_argument('-v', action='count', default=0, help="each 'v' increases the verbosity level")
argparsersubs     = argparser.add_subparsers(dest='command')

CommandInstallFiles(argparsersubs)
CommandInstallHttp(argparsersubs)
CommandSrvrun(argparsersubs)
CommandDbCleanup(argparsersubs)


# ---------------------
#  - Execute Command -
# ---------------------

args = argparser.parse_args()
args.CmdObject.readArgs(args)
args.CmdObject.process()
