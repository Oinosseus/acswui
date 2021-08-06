import json
import os
import re

from .verbosity import Verbosity
from .helper_functions import parse_json

class InstallerCars(object) :


    def __init__(self,
                 database,
                 path_srvpkg,
                 verbosity=0
                ):
        self.__db = database
        self.__path_srvpkg = path_srvpkg
        self._verbosity = Verbosity(verbosity, self.__class__.__name__)



    def process(self):
        self._verbosity.print("scanning cars")

        # paths
        abspath_data = os.path.abspath(self.__path_srvpkg)

        # set all current cars and skins to 'deprecated'
        self.__db.rawQuery("UPDATE Cars SET Deprecated=1 WHERE Deprecated=0")
        self.__db.rawQuery("UPDATE CarSkins SET Deprecated=1 WHERE Deprecated=0")
        self.__db.rawQuery("UPDATE CarSkins SET Steam64GUID=''")

        path_cars = os.path.join(abspath_data, "htdata", "content", "cars")
        for car in sorted(os.listdir(path_cars)):
            self._scan_car(path_cars, car)



    def _scan_car(self, path_cars, car):
        verb = Verbosity(self._verbosity)
        verb.print("scanning car", car)

        car_path   = os.path.join(path_cars, car)
        ui_car_json = parse_json(car_path + "/ui/ui_car.json", ['name', 'brand', 'description'])
        car_name   = ui_car_json["name"]
        if car_name == "":
            car_name = car
        car_brand  = ui_car_json["brand"]
        car_descr = ui_car_json["description"].replace("<br>", "\n")

        # update brand
        res = self.__db.fetch("CarBrands", ['Id'], {'Name':car_brand})
        if len(res) > 0:
            car_brand_id = res[0]['Id']
        else:
            car_brand_id = self.__db.insertRow("CarBrands", {'Name':car_brand})
        Verbosity(verb).print("brand", car_brand, "[%s]" % car_brand_id)

        # get skins
        car_skins = []
        path_car_skins = os.path.join(car_path, "skins")
        if os.path.isdir(path_car_skins):
            for skin in os.listdir(path_car_skins):
                if skin == "":
                    raise NotImplementedError("Unexpected empty skin name for car '%s'" % car)
                car_skins.append(skin)


        # get IDs of existing cars (should be exactly one car)
        existing_car_ids = self.__db.findIds("Cars", {"Car": car})

        table_fields = {"Car": car,
                        "Name": car_name,
                        "Parent": 0,
                        "Brand": car_brand_id,
                        "Deprecated":0,
                        "Description": car_descr}

        # insert car if not existent
        if len(existing_car_ids) == 0:
            self.__db.insertRow("Cars", table_fields)
            existing_car_ids = self.__db.findIds("Cars", {"Car": car})
            Verbosity(verb).print("added as new car")

        # copy badge
        badge_path = os.path.join(car_path, "ui", "badge.png")
        if os.path.isfile(badge_path):
            self.__db.updateRow("CarBrands", car_brand_id, {"BadgeCar": existing_car_ids[0]})

        # update all existing cars
        for eci in existing_car_ids:
            self.__db.updateRow("Cars", eci, table_fields)

            # insert not existing skins
            added_skins = 0
            for skin in car_skins:
                self._scan_carskins(eci, path_car_skins, skin)



    def _scan_carskins(self, existing_car_id, path_car_skins, skin):
        verb = Verbosity(self._verbosity)
        verb.print("scanning skin", skin)

        # read ui_skin.json
        ui_skin_json_path = os.path.join(path_car_skins, skin, "ui_skin.json")
        ui_skin_dict = self._parse_ui_skin_json(ui_skin_json_path)

        # update database
        existing_car_skins = self.__db.findIds("CarSkins", {"Car": existing_car_id, "Skin": skin})
        if len(existing_car_skins) == 0:
            fields = {"Car": existing_car_id, "Skin": skin, "Deprecated":0}
            fields.update(ui_skin_dict)
            self.__db.insertRow("CarSkins", fields)
            added_skins += 1
        else:
            for skin_id in existing_car_skins:
                fields = {"Deprecated":0}
                fields.update(ui_skin_dict)
                self.__db.updateRow("CarSkins", skin_id, fields)



    def _parse_ui_skin_json(self, ui_skin_json_path):
        ret = {'Name':"", "Number": 0, 'Steam64GUID': ""}

        verb = Verbosity(self._verbosity)
        verb.print("parsing", ui_skin_json_path)

        REGEX_COMP_UISKIN_SKINNAME = re.compile("\"(?i)skinname\"\s*:\s*\"(.*)\"")
        REGEX_COMP_UISKIN_STEAM64GUID = re.compile("\"Steam64GUID\"\s*:\s*\"([a-zA-Z0-9]*)\"")
        REGEX_COMP_UISKIN_NUMBER = re.compile("\"(?i)number\"\s*:\s*\"([0-9]*)\"")

        if os.path.isfile(ui_skin_json_path):
            with open(ui_skin_json_path, "r") as f:

                try:
                    lines = f.readlines()
                except UnicodeDecodeError as err:
                    print("WARNING: Cannot parse '" + ui_skin_json_path + "'\nBecause of " + str(err))
                    lines = []

                for line in lines:
                    line = line.strip()

                    # Name
                    match = REGEX_COMP_UISKIN_SKINNAME.match(line)
                    if match:
                        ret['Name'] = match.group(1)
                        Verbosity(verb).print("Name", ret['Name'])

                    # Steam64GUID
                    match = REGEX_COMP_UISKIN_STEAM64GUID.match(line)
                    if match:
                        ret['Steam64GUID'] = match.group(1)
                        Verbosity(verb).print("Steam64GUID", ret['Steam64GUID'])

                    # Number
                    match = REGEX_COMP_UISKIN_NUMBER.match(line)
                    if match:
                        n = match.group(1)
                        if n != "":
                            ret['Number'] = n
                        Verbosity(verb).print("Number", ret['Number'])


        return ret
