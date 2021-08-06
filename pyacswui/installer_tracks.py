import json
import math
import os
import PIL
import re

from .verbosity import Verbosity

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
            track_version = self.__parse_json(track_path + "/ui/ui_track.json", "version", "")

            existing_track_ids = self._find_track_ids(track_location_id)
            if len(existing_track_ids) == 0:
                self.__db.insertRow("Tracks", {"Location": track_location_id, "Config": "", "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs, "Deprecated":0})
                Verbosity(verb).print("adding new track", track)
            else:
                self.__db.updateRow("Tracks", existing_track_ids[0], {"Config": "", "Name": track_name, "Length": track_length, "Pitboxes": track_pitbxs, "Deprecated":0})

            self._update_track_info(track_location_id, [track_name])

        # update track configs
        if os.path.isdir(track_path + "/ui"):
            track_names = []
            for track_config in os.listdir(track_path + "/ui"):
                if os.path.isdir(track_path + "/ui/" + track_config):
                    if os.path.isfile(track_path + "/ui/" + track_config + "/ui_track.json"):
                        Verbosity(verb).print("track config", track_config)
                        track_name   = self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "name", track)
                        track_length = self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "length", "0")
                        track_length = self._interpret_length(track_length)
                        track_pitbxs = self._interpret_pitboxes(self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "pitboxes", "0"))
                        track_version = self.__parse_json(track_path + "/ui/" + track_config + "/ui_track.json", "version", "")
                        track_names.append(track_name)

                        existing_track_ids = self._find_track_ids(track_location_id, track_config)
                        table_fields = {"Location": track_location_id,
                                        "Config": track_config,
                                        "Name": track_name,
                                        "Length": track_length,
                                        "Pitboxes": track_pitbxs,
                                        "Deprecated":0,
                                        "Version": track_version}
                        if len(existing_track_ids) == 0:
                            self.__db.insertRow("Tracks", table_fields)
                        else:
                            self.__db.updateRow("Tracks", existing_track_ids[0], table_fields)

            if len(track_names) > 0:
                self._update_track_info(track_location_id, track_names)



    def _find_track_ids(self, track_location_id, track_config=None):

        ids = []

        where_dict ={'Location': track_location_id}
        if track_config:
            where_dict.update({'Config': track_config})

        res = self.__db.fetch("Tracks", ['Id'], where_dict)
        for row in res:
            ids.append(int(row['Id']))

        return ids



    def _update_track_info(self, track_location_id, track_names):

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

        # cleanup name
        track_location_name = track_location_name.strip()
        while track_location_name[0] in ['-']:
            track_location_name = track_location_name[1:]
        while track_location_name[-1] in ['-']:
            track_location_name = track_location_name[:-1]
        track_location_name = track_location_name.strip()

        self.__db.updateRow("TrackLocations", track_location_id, {"Name": track_location_name, "Deprecated": 0})



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



    def _generateHtmlImgs(self, id, track, config):
        verb =  Verbosity(self._verbosity)
        verb.print("generating htmlimg for track id", id, "(", track, config, ")")

        # define size
        htmlimg_width = 300
        htmlimg_height = 200


        def shrink(img):
            w, h = img.size
            if w > htmlimg_width:
                h = h * htmlimg_width / w
                w = htmlimg_width
            if h > htmlimg_height:
                w = w * htmlimg_height / h
                h = htmlimg_height
            w = math.ceil(w)
            h = math.ceil(h)
            return img.resize((w, h))


        def openImg(path):
            # basic image as canvas
            img_canvas = PIL.Image.new( mode = "RGBA", size = (htmlimg_width, htmlimg_height) )

            # open requested image and shrink to max size
            img_paste = PIL.Image.open(path)
            img_paste = shrink(img_paste)

            # determine padding
            pad_x = (htmlimg_width - img_paste.size[0]) // 2
            pad_y = (htmlimg_height - img_paste.size[1]) // 2
            img_canvas.paste(img_paste, (pad_x, pad_y))

            return img_canvas



        # get paths
        path_track = os.path.join(self.__path_htdata, "content", "tracks", track)
        path_htmlimg_dir = os.path.join(self.__path_htdata, "htmlimg", "tracks")
        if not os.path.isdir(path_htmlimg_dir):
            Verbosity(verb).print("mkdirs " + path_htmlimg_dir)
            os.makedirs(path_htmlimg_dir)
        path_htmlimg = os.path.join(path_htmlimg_dir, str(id) + ".png")
        path_htmlimg_hover = os.path.join(path_htmlimg_dir, str(id) + ".hover.png")
        if config == "":
            path_preview = os.path.join(path_track, "ui", "preview.png")
            path_outline = os.path.join(path_track, "ui", "outline.png")
        else:
            path_preview = os.path.join(path_track, "ui", config, "preview.png")
            path_outline = os.path.join(path_track, "ui", config, "outline.png")

        # create new image
        img_htmlimg  = PIL.Image.new( mode = "RGBA", size = (htmlimg_width, htmlimg_height) )

        # merge existing preview
        if os.path.isfile(path_preview):
            img_preview = openImg(path_preview)
            img_htmlimg.alpha_composite(img_preview)

        # save as default preview
        img_htmlimg.save(path_htmlimg, "PNG")

        # merge existing outline
        if os.path.isfile(path_outline):
            img_outline = openImg(path_outline)
            img_htmlimg.alpha_composite(img_outline)

        # save hover image
        img_htmlimg.save(path_htmlimg_hover, "PNG")
