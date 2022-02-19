import json
import os
import re

from .verbosity import Verbosity
from .helper_functions import generateHtmlImg

class InstallerTracks(object):


    def __init__(self,
                 database,
                 path_srvpkg,
                 path_htdata,
                 verbosity=0
                ):
        self.__db = database
        self.__path_srvpkg = path_srvpkg
        self.__path_htdata = path_htdata
        self._verbosity = Verbosity(verbosity, self.__class__.__name__)



    def __parse_json(self, json_file, key_name, default_value):
        ret = default_value
        key_name = '"' + key_name + '":'
        if os.path.isfile(json_file):
            with open(json_file, "r", encoding='utf-8', errors='ignore') as f:
                for line in f.readlines():
                    if key_name in line.lower():
                        ret = line.split(key_name, 1)[1]
                        ret = ret.strip()
                        if ret[:1] == '"':
                            ret = ret[1:].strip()
                        if ret[-1:] == ',':
                            ret = ret[:-1].strip()
                        if ret[-1:] == '"':
                            ret = ret[:-1]
        return ret


    def process(self):
        self._verbosity.print("scanning for tracks")

        # paths
        abspath_data = os.path.abspath(self.__path_srvpkg)

        # set all current trakcs to 'deprecated'
        self.__db.rawQuery("UPDATE Tracks SET Deprecated=1 WHERE Deprecated=0")
        self.__db.rawQuery("UPDATE TrackLocations SET Deprecated=1 WHERE Deprecated=0")


        path_tracks = os.path.join(abspath_data, "htdata", "content", "tracks")
        for track in sorted(os.listdir(path_tracks)):
            self._scan_track(path_tracks, track)

        # generate preview images for \DbEntry\Track::htmlImg()
        self._verbosity.print("generate htmlImg's")
        query = "SELECT Tracks.Id, TrackLocations.Track, Tracks.Config FROM `Tracks` JOIN TrackLocations ON Tracks.Location = TrackLocations.Id ORDER BY Tracks.Id DESC"
        res = self.__db.rawQuery(query, return_result=True)
        for id, track, config in res:
            self._generateHtmlImgs(id, track, config)



    def _scan_track(self, path_tracks, track):
        verb = Verbosity(self._verbosity)
        verb.print("Scanning track", track)

        track_path = os.path.join(path_tracks, track)

        # get ID in TrackLocations table
        res = self.__db.fetch("TrackLocations", ['Id'], {'Track':track})
        if len(res) > 0:
            track_location_id = res[0]['Id']
        else:
            track_location_id = self.__db.insertRow("TrackLocations", {'Track':track})


        # update track
        if os.path.isfile(track_path + "/ui/ui_track.json"):
            track_name   = self.__parse_json(track_path + "/ui/ui_track.json", "name", track)
            track_length = self.__parse_json(track_path + "/ui/ui_track.json", "length", "0")
            track_length = self._interpret_length(track_length)
            track_pitbxs = self._interpret_pitboxes(self.__parse_json(track_path + "/ui/ui_track.json", "pitboxes", "0"))
            track_version = self.__parse_json(track_path + "/ui/ui_track.json", "version", "")[:30]
            track_author = self.__parse_json(track_path + "/ui/ui_track.json", "author", "")[:50]
            track_country = self.__parse_json(track_path + "/ui/ui_track.json", "country", "")[:80]
            track_descr = self.__parse_json(track_path + "/ui/ui_track.json", "description", "")

            existing_track_ids = self._find_track_ids(track_location_id)
            if len(existing_track_ids) == 0:
                self.__db.insertRow("Tracks", {"Location": track_location_id, "Config": "", "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs, "Deprecated":0})
                Verbosity(verb).print("adding new track", track)
            else:
                self.__db.updateRow("Tracks", existing_track_ids[0], {"Config": "", "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs, "Deprecated":0})

            self._update_track_info(track_location_id, [track_name], [track_country], [track_descr])

        # update track configs
        if os.path.isdir(track_path + "/ui"):
            track_names = []
            track_countries = []
            track_descriptions = []
            for track_config in os.listdir(track_path + "/ui"):
                if os.path.isdir(track_path + "/ui/" + track_config):
                    if os.path.isfile(track_path + "/ui/" + track_config + "/ui_track.json"):
                        Verbosity(verb).print("track config", track_config)
                        track_name   = self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "name", track)
                        track_length = self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "length", "0")
                        track_length = self._interpret_length(track_length)
                        track_pitbxs = self._interpret_pitboxes(self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "pitboxes", "0"))
                        track_version = self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "version", "")[:30]
                        track_author = self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "author", "")[:50]
                        track_descr = self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "description", "")
                        track_country = self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "country", "")
                        track_names.append(track_name)
                        track_countries.append(track_country)
                        track_descriptions.append(track_descr)

                        existing_track_ids = self._find_track_ids(track_location_id, track_config)
                        table_fields = {"Location": track_location_id,
                                        "Config": track_config,
                                        "Name": track_name,
                                        "Length": track_length,
                                        "Pitboxes": track_pitbxs,
                                        "Deprecated":0,
                                        "Version": track_version,
                                        "Author": track_author,
                                        "Description": track_descr}
                        if len(existing_track_ids) == 0:
                            self.__db.insertRow("Tracks", table_fields)
                        else:
                            self.__db.updateRow("Tracks", existing_track_ids[0], table_fields)

            if len(track_names) > 0:
                self._update_track_info(track_location_id, track_names, track_countries, track_descriptions)



    def _find_track_ids(self, track_location_id, track_config=None):

        ids = []

        where_dict ={'Location': track_location_id}
        if track_config:
            where_dict.update({'Config': track_config})

        res = self.__db.fetch("Tracks", ['Id'], where_dict)
        for row in res:
            ids.append(int(row['Id']))

        return ids



    def _update_track_info(self, track_location_id, track_names, track_countries, track_descriptions):

        def detect_location(track_names):
            track_location_name = ""

            # extract longest match string from all track names (from the beginning of their names)
            pos = 0
            while len(track_names[0]) > pos:
                char = track_names[0][pos]
                chars_equal = True
                for i in range(1, len(track_names)):
                    if len(track_names[i]) <= pos:
                        char_equal = False
                    elif track_names[i][pos] != char:
                        chars_equal = False
                if chars_equal:
                    track_location_name += char
                else:
                    break
                pos += 1

            return track_location_name

        # get location name
        track_location_name = detect_location(track_names)
        if track_location_name == "":
            # reverse names
            for i in range(len(track_names)):
                track_names[i] = track_names[i][::-1]
            track_location_name = detect_location(track_names)
            track_location_name = track_location_name[::-1]

        # if still no track locations name found, try extract from track description
        # occurred with track 'Croft' from author 'Legion'
        if track_location_name == "":
            track_location_name = detect_location(track_descriptions)
        if track_location_name == "":
            # reverse names
            for i in range(len(track_descriptions)):
                track_descriptions[i] = track_descriptions[i][::-1]
            track_location_name = detect_location(track_descriptions)
            track_location_name = track_location_name[::-1]
        if len(track_location_name) == 0:
            print("HERE", track_location_id, track_location_name, track_descriptions)

        # cleanup name
        track_location_name = track_location_name.strip()
        while track_location_name[0] in ['-']:
            track_location_name = track_location_name[1:]
        while track_location_name[-1] in ['-']:
            track_location_name = track_location_name[:-1]
        track_location_name = track_location_name.strip()

        # country
        track_country = ""
        for c in track_countries:
            if len(c) > len(track_country):
                track_country = c
        #if track_country in ["U.S.A", "U.S.A."]:
            #track_country = "USA"

        self.__db.updateRow("TrackLocations", track_location_id, {"Name": track_location_name, "Country": track_country, "Deprecated": 0})



    def _interpret_length(self, length):
            ret = ""

            REGEX_COMP_TRACKLENGTH = re.compile("([0-9]*[,\.]?[0-9]*)\s*(m|km)?(.*)")
            match = REGEX_COMP_TRACKLENGTH.match(length)
            if not match:
                raise ValueError("Could not extract length information from string '%s'!\nCheck ui_track.json" % length)

            #print("MATCH:", "'", match.group(1), "'", match.group(2), "'", match.group(3))
            length = match.group(1)
            if length == "":
                length = "0"
            length = length.replace(",", ".")
            length = float(length)
            unit = match.group(2)
            rest = match.group(3)

            if unit == "km":
                length *= 1000
            #print("MATCH:", length, unit, "//", rest)

            # Guessing when a track is less than 100m the length was desired to be in [km]
            # workaround for tracks with wrong comma setting
            if length < 100:
                length *= 1000

            return length



    def _interpret_pitboxes(self, pitbxs):
            ret = 0
            for char in pitbxs:
                if char in "0123456789":
                    ret *= 10
                    ret += int(char)
                else:
                    break
            return ret



    def _generateHtmlImgs(self, track_id, track, config):
        verb =  Verbosity(self._verbosity)
        verb.print("generating htmlimg for track id", track_id, " (", track, config, ")")

        # get paths
        path_track = os.path.join(self.__path_htdata, "content", "tracks", track)
        path_htmlimg_dir = os.path.join(self.__path_htdata, "htmlimg", "tracks")
        if config == "":
            path_preview = os.path.join(path_track, "ui", "preview.png")
            path_outline = os.path.join(path_track, "ui", "outline.png")
        else:
            path_preview = os.path.join(path_track, "ui", config, "preview.png")
            path_outline = os.path.join(path_track, "ui", config, "outline.png")

        generateHtmlImg(path_preview, path_outline, path_htmlimg_dir, track_id)
