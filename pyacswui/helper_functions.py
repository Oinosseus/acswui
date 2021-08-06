import re
import json

def parse_json(file_path, expected_keys=[]):

    ret_dict = {}

    # try to parse json directly
    could_parse_json = False
    with open(file_path, "rb") as f:

        #try:
        f_content = f.read()
        try:
            json_dict = json.loads(f_content, strict=False)
            ret_dict = json_dict
            could_parse_json = True
        except UnicodeDecodeError as e:
            print(e)
            print("WARNING: Cannot correctly parse car data from JSON", file_path)
            could_parse_json = False

    # try parse manually
    if not could_parse_json:
        with open(file_path, "r", encoding='utf-8', errors='ignore') as f:
            for line in f.readlines():
                for key in expected_keys:

                    match = re.match("\s*\"" + key + "\":.*", line)
                    if match:
                        value = line.split(key, 1)[1]
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
