import re
import json

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
