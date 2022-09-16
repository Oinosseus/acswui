import json
import os
import re

from .verbosity import Verbosity
from .helper_functions import parse_json, generateHtmlImg


class InstallerCars(object) :


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



    def process(self):
        self._verbosity.print("scanning cars")

        # paths
        abspath_data = os.path.abspath(self.__path_srvpkg)

        # set all current cars and skins to 'deprecated'
        self.__db.rawQuery("UPDATE Cars SET Deprecated=1 WHERE Deprecated=0")
        self.__db.rawQuery("UPDATE CarSkins SET Deprecated=1 WHERE Deprecated=0")

        path_cars = os.path.join(abspath_data, "htdata", "content", "cars")
        for car in sorted(os.listdir(path_cars)):
            self._scan_car(path_cars, car)

        # generate preview images for \DbEntry\ClarSkin::htmlImg()
        self._verbosity.print("generate htmlImg's")
        query = "SELECT CarSkins.Id, CarSkins.Skin, Cars.Car FROM `CarSkins` JOIN Cars ON Cars.Id = CarSkins.Car ORDER BY CarSkins.Id DESC"
        res = self.__db.rawQuery(query, return_result=True)
        for skin_id, skin_name, car_model in res:
            self._generateHtmlImgs(skin_id, skin_name, car_model)



    def _scan_car(self, path_cars, car):
        verb = Verbosity(self._verbosity)
        verb.print("scanning car", car)

        car_path   = os.path.join(path_cars, car)
        if not os.path.isfile(car_path + "/ui/ui_car.json"):
            return
        ui_car_json = parse_json(car_path + "/ui/ui_car.json", ['name', 'brand', 'description', 'powerCurve', 'torqueCurve'])

        car_name   = ui_car_json["name"]
        if car_name == "":
            car_name = car
        car_brand  = ui_car_json["brand"]
        car_descr = ui_car_json["description"]
        car_tcurve = json.dumps(ui_car_json["torqueCurve"])
        car_pcurve = json.dumps(ui_car_json["powerCurve"])
        car_weight = 0
        if "specs" in ui_car_json:
            ui_car_specs = ui_car_json['specs']
            if 'weight' in ui_car_specs:
                car_weight = self._parse_car_weight(ui_car_specs['weight'])

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
                        "Description": car_descr,
                        "TorqueCurve": car_tcurve,
                        "PowerCurve": car_pcurve,
                        "Weight": car_weight}

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
            for skin in car_skins:
                self._scan_carskins(eci, path_car_skins, skin)



    def _scan_carskins(self, existing_car_id, path_car_skins, skin):
        verb = Verbosity(self._verbosity)
        verb.print("scanning skin", skin)

        # read ui_skin.json
        ui_skin_json_path = os.path.join(path_car_skins, skin, "ui_skin.json")
        if os.path.isfile(ui_skin_json_path):
            ui_skin_dict = parse_json(ui_skin_json_path, ['skinname', 'number'])
        else:
            ui_skin_dict = {'skinname':"", 'number':""}
        ui_skin_db_fields = {}
        ui_skin_db_fields['Name'] = ui_skin_dict['skinname']
        ui_skin_db_fields['Number'] = str(ui_skin_dict['number'])[:20]

        # update database
        existing_car_skins = self.__db.findIds("CarSkins", {"Car": existing_car_id, "Skin": skin})
        if len(existing_car_skins) == 0:
            fields = {"Car": existing_car_id, "Skin": skin, "Deprecated":0}
            fields.update(ui_skin_db_fields)
            self.__db.insertRow("CarSkins", fields)
        else:
            for skin_id in existing_car_skins:
                fields = {"Deprecated":0}
                fields.update(ui_skin_db_fields)
                self.__db.updateRow("CarSkins", skin_id, fields)



    def _parse_car_weight(self, weight_string):

        # catch stupid mavericks
        if weight_string == '--kg': # ferrari_laferrari
            return 0
        elif weight_string == '---kg': # mustang_imsa_roush
            return 0
        elif weight_string in [None, 'null']: # tv
            return 0

        weight = None

        # option 1
        if weight is None:
            match = re.match("([0-9]*)\s*[kK][gG]", weight_string)
            if match:
                weight = int(match.group(1))

        # option 2 (found at ks_ruf_rt12r)
        if weight is None:
            match = re.match("([0-9]),([0-9]*)\s*[Kk][gG]", weight_string)
            if match:
                weight = int(match.group(1)) * 1e3 + int(match.group(2))

        # option 3 (found at legion_mclaren_f1gtr_longtail)
        if weight is None:
            match = re.match("([0-9]*)\*\s*[kK][gG]", weight_string)
            if match:
                weight = int(match.group(1))

        # option 4 (found at btcc_alfa_romeo_giulietta)
        # no units
        if weight is None:
            try:
                weight = int(weight_string)
            except ValueError:
                weight = None

        # check if weight could be determined
        if weight is None:
            raise NotImplementedError("Cannot parse weight '%s'" % str(weight_string))

        # return weight in kg
        return weight



    def _generateHtmlImgs(self, skin_id, skin_name, car_model):
        verb =  Verbosity(self._verbosity)
        verb.print("generating htmlimg for skin id", skin_id, "(", car_model, skin_name, ")")

        # get paths
        path_car = os.path.join(self.__path_htdata, "content", "cars", car_model)
        path_preview = os.path.join(path_car, "skins", skin_name, "preview.jpg")
        path_brand = os.path.join(path_car, "ui", "badge.png")
        path_htmlimg_dir = os.path.join(self.__path_htdata, "htmlimg", "car_skins")

        generateHtmlImg(path_preview, path_brand, path_htmlimg_dir, skin_id)
