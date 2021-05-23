#!/usr/bin/python3

# acswui - Asetto Corsa Server Web User Interface
# by Thomas Weinhold
#
# This script supports the acswui installaion, maintenance and update.

import argparse
import os
import sys
import json
import time
from pyacswui import CommandPackage, CommandInstall, CommandSrvrun, CommandDbCleanup, CommandUpdateLocales



# ---------------------------------
#  - Parse Commandline Arguments -
# ---------------------------------

__helpstring  = "Examples for complete safe setup:\n"
__helpstring += "./acswui -vv local.ini package\n"
__helpstring += "./acswui -vv remote.ini install --install-base-data --root-pwd \"mypassword\"\n"

# main arguments
argparser = argparse.ArgumentParser(prog="acswui", description="Assetto Corsa Server Web User Interface", epilog=__helpstring, formatter_class=argparse.RawTextHelpFormatter)
argparser.add_argument('-v', action='count', default=0, help="each 'v' increases the verbosity level")
argparsersubs = argparser.add_subparsers(dest='command')

CommandPackage(argparsersubs)
CommandInstall(argparsersubs)
CommandSrvrun(argparsersubs)
CommandDbCleanup(argparsersubs)
CommandUpdateLocales(argparsersubs)


# ---------------------
#  - Execute Command -
# ---------------------

duration_start = time.monotonic()
args = argparser.parse_args()
args.CmdObject.parseArgs(args)
args.CmdObject.process()
duration_end = time.monotonic()

if args.v >=2:
    duration = duration_end - duration_start
    if duration < 1.0:
        print("processing duration: %0.1fms" % (1000 * duration))
    else:
        print("processing duration: %0.2fs" % (duration))
