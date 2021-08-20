import json
import math
import os.path
import re
import PIL

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
    if os.path.isfile(src_img_path):
        img_preview = openImg(src_img_path)
        img_htmlimg.alpha_composite(img_preview)

    # save as default preview
    img_htmlimg.save(path_htmlimg, "PNG")

    # merge existing outline
    if os.path.isfile(src_img_hover):
        img_outline = openImg(src_img_hover)
        img_htmlimg.alpha_composite(img_outline)

    # save hover image
    img_htmlimg.save(path_htmlimg_hover, "PNG")
