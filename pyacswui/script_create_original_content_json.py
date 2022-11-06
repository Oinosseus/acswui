#! /usr/bin/env python3
#
# This script generates the original_content.json file
# Execute this script only on a vanilla AC installation to scan for original content
#

import json
import os

# ask for AC installation path
dir_ac = input("AC Instllation Path: ")

# prepare output data
original_content = {"cars":[], "tracks":[]}

# scan cars
dir_ac_cars = os.path.join(dir_ac, "content", "cars")
for entry in sorted(os.listdir(dir_ac_cars)):
    dir_entry = os.path.join(dir_ac_cars, entry)
    if entry in [".", ".."]:
        continue
    elif os.path.isdir(dir_entry):
        original_content['cars'].append(entry)

# scan tracks
dir_ac_tracks = os.path.join(dir_ac, "content", "tracks")
for entry in sorted(os.listdir(dir_ac_tracks)):
    dir_entry = os.path.join(dir_ac_tracks, entry)
    if entry in [".", ".."]:
        continue
    elif os.path.isdir(dir_entry):
        original_content['tracks'].append(entry)

# export json
with open("original_content.json", "w") as f:
    f.write(json.dumps(original_content))
