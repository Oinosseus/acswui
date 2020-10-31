#!/usr/bin/python3

# acswui - Asetto Corsa Server Web User Interface
# by Thomas Weinhold
#
# This script supports the acswui installaion, maintenance and update.

import argparse
import os
import sys
from pyacswui import CommandSrvctl, CommandSrvrun, CommandInstallHttp, ServerPackager



def workaround_srvpkg(args, config):
    srvpkg = ServerPackager(config, args.v)
    srvpkg.work()



# ---------------------------------
#  - Parse Commandline Arguments -
# ---------------------------------

__helpstring  = "Examples for complete safe setup:\n"
__helpstring += "./acswui -v --ini acswui.ini srvpkg\n"
__helpstring += "./acswui -v --ini acswui.ini --install-base-data install\n"

# main arguments
argparser = argparse.ArgumentParser(prog="acswui", description="Assetto Corsa Server Web User Interface", epilog=__helpstring, formatter_class=argparse.RawTextHelpFormatter)
argparser.add_argument('-i', '--ini', help="path to config file")
argparser.add_argument('-v', action='count', default=0, help="each 'v' increases the verbosity level")

argparsersubs     = argparser.add_subparsers(dest='command')
argparser_srvpkg  = argparsersubs.add_parser('srvpkg', help="server packager - preparing files for http and ac server")
argparser_srvpkg.set_defaults(func=workaround_srvpkg)

CommandSrvctl(argparsersubs)
CommandSrvrun(argparsersubs)
CommandInstallHttp(argparsersubs)



# get arguments
args = argparser.parse_args()
#print("argparser.prog=", argparser.prog)
#print("sys.argv[0]=", sys.argv[0])
#print("\n\nargs=\n", args);
#exit(0)

# check config file
if args.ini is None or not os.path.isfile(args.ini):
    raise ValueError("Parameter --ini must be set with a existing config file!")

# parse config file
config = {}
with open(args.ini, "r") as f:
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
            config.update({split[0].strip(): split[1].strip()})



# ---------------------
#  - Execute Command -
# ---------------------

args.func(args, config)
if int(args.v) > 0:
    print("Finished")

