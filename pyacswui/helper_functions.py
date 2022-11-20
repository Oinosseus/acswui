import json
import math
import os.path
import PIL
import re
import shutil
import subprocess

def parse_json(file_path, expected_keys=[]):

    ret_dict = {}
    json_dict = {}

    # try to parse json directly
    could_parse_json = False
    with open(file_path, "rb") as f:

        f_content = f.read()

        # decode
        try:
            f_content_utf8 = f_content.decode('utf-8')
        except UnicodeDecodeError as e:
            try:
                f_content_utf8 = f_content.decode('iso-8859-15')
            except BaseException as e:
                print(e)
                raise NotImplementedError("Cannot parse '%s'" % file_path)

        # parse json
        try:
            json_dict = json.loads(f_content_utf8, strict=False)
            ret_dict = json_dict
            could_parse_json = True
        except json.decoder.JSONDecodeError as e:
            try:
                json_dict = json.loads(f_content, strict=False)
                ret_dict = json_dict
                could_parse_json = True
            except BaseException as e:
                print(e)

    # try parse manually
    if not could_parse_json:
        print("WARNING: Cannot correctly parse car data from JSON", file_path)
        with open(file_path, "r", encoding='utf-8', errors='ignore') as f:
            for line in f.readlines():
                for key in expected_keys:

                    match = re.match("\s*\"" + key + "\":\s*[\"]?(.*)", line)
                    if match:
                        value = match.group(1) # line.split(key, 1)[1]
                        value = value.strip()
                        if value[:1] == '"':
                            value = value[1:].strip()
                        if value[-1:] == ',':
                            value = value[:-1].strip()
                        if value[-1:] == '"':
                            value = value[:-1]
                        ret_dict[key] = value

    # ensure expected keys
    for key in expected_keys:
        if key not in ret_dict:
            ret_dict[key] = ""
        elif ret_dict[key] is None:
            ret_dict[key] = ""

    return ret_dict



def generateHtmlImg(src_img_path, src_img_hover, dst_dir, db_id):

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
        return img.resize((w, h), resample=PIL.Image.ANTIALIAS)


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
    if not os.path.isdir(dst_dir):
        os.makedirs(dst_dir)
    path_htmlimg = os.path.join(dst_dir, str(db_id) + ".png")
    path_htmlimg_hover = os.path.join(dst_dir, str(db_id) + ".hover.png")

    # create new image
    img_htmlimg  = PIL.Image.new( mode = "RGBA", size = (htmlimg_width, htmlimg_height) )

    # merge existing preview
    img_preview = None
    if os.path.isfile(src_img_path):
        try:
            img_preview = openImg(src_img_path)
        except PIL.UnidentifiedImageError:
            print("WARNING: Pillow cannot read image file", src_img_path)
        if img_preview is not None:
            img_htmlimg.alpha_composite(img_preview)

    # save as default preview
    img_htmlimg.save(path_htmlimg, "PNG")
    subprocess.run(["chmod", "u+w", path_htmlimg])  # by unknown reason saved images are not modifyable, wich causes issues at re-install

    # merge existing outline
    img_outline = None
    if os.path.isfile(src_img_hover):
        try:
            img_outline = openImg(src_img_hover)
        except PIL.UnidentifiedImageError:
            print("WARNING: Pillow cannot read image file", src_img_hover)
        if img_outline is not None:
            img_htmlimg.alpha_composite(img_outline)

    # save hover image
    img_htmlimg.save(path_htmlimg_hover, "PNG")
    subprocess.run(["chmod", "u+w", path_htmlimg_hover])  # by unknown reason saved images are not modifyable, wich causes issues at re-install

    # fallback solution if pillow cannot open preview image
    if img_preview is None and os.path.isfile(src_img_path):
        shutil.copyfile(src_img_path, path_htmlimg)
    if img_outline is None and os.path.isfile(src_img_hover):
        shutil.copyfile(src_img_hover, path_htmlimg_hover)


class Longitude(float):
    pass

class Latitude(float):
    pass


def __parse_geocoordinate_direction_apply(direction, coordinate_value):
    if direction == "N":
        return Latitude(coordinate_value)
    elif direction == "S":
        return Latitude(-1 * coordinate_value)
    elif direction == "E":
        return Longitude(coordinate_value)
    elif direction == "W":
        return Longitude(-1 * coordinate_value)
    else:
        return None


def parse_geocoordinate(string_value):
    """ Parse a string value and returning a Latitude, Longitude, float or None object
    """
    string_value = string_value.replace(",", ".").upper().strip()

    # 12.345? N
    m = re.match("(\d+\.\d+).?\s+([N,S,E,W])", string_value.upper())
    if m:
        value = float(m.group(1))
        direction = m.group(2)
        coordinate = __parse_geocoordinate_direction_apply(direction, value)
        if coordinate:
            return coordinate

    # 13?16?14.34?E
    m = re.match("(\d+).?\s*(\d+).?\s*(\d+\.\d+).?\s*([N,S,E,W])", string_value.upper())
    if m:
        value = float(m.group(1)) + float(m.group(2))/60 + float(m.group(3))/3600
        direction = m.group(4)
        coordinate = __parse_geocoordinate_direction_apply(direction, value)
        if coordinate:
            return coordinate

    # 13?16?14?E
    m = re.match("(\d+).?\s*(\d+).?\s*(\d+).?\s*([N,S,E,W])", string_value.upper())
    if m:
        value = float(m.group(1)) + float(m.group(2))/60 + float(m.group(3))/3600
        direction = m.group(4)
        coordinate = __parse_geocoordinate_direction_apply(direction, value)
        if coordinate:
            return coordinate

    # 13? 16.3456? E
    m = re.match("(\d+).?\s+(\d+\.\d+).?\s+([N,S,E,W])", string_value.upper())
    if m:
        value = float(m.group(1)) + float(m.group(2))/60
        direction = m.group(3)
        coordinate = __parse_geocoordinate_direction_apply(direction, value)
        if coordinate:
            return coordinate

    # LAT-25.5955
    m = re.match("LAT\s*([-]*\d+\.\d+)", string_value.upper())
    if m:
        value = float(m.group(1))
        return Latitude(value)

    # LON 28.0408
    m = re.match("LON\s*([-]*\d+\.\d+)", string_value.upper())
    if m:
        value = float(m.group(1))
        return Longitude(value)

    # 13? 16.3456?
    m = re.match("(\d+).?\s+(\d+\.\d+).?", string_value.upper())
    if m:
        value = float(m.group(1)) + float(m.group(2))/60
        return value

    # 12.345
    m = re.match("(\d+\.\d+)", string_value.upper())
    if m:
        value = float(m.group(1))
        return value

    return None
