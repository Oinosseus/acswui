import socket
from configparser import ConfigParser
import json
import os.path
from .verbosity import Verbosity
from .udp_packet import UdpPacket
from .udp_plugin_session import UdpPluginSession
from .udp_plugin_car_entry import UdpPluginCarEntry


class NoCarEntryError(BaseException):
    pass



class UdpPluginServer(object):

    def __init__(self,
                 acswui_info,
                 port_server, port_plugin,
                 database,
                 entry_list_path,
                 ac_server_path,
                 realtime_json_path = None,
                 verbosity=0):

        # This is the typical duration for the process() method
        # used for UDP polling timeout
        # and for realtime data update
        self.__PROCESS_TIME = 0.1 # seconds

        self.__verbosity = Verbosity(verbosity)
        self.__acswui_info = acswui_info
        self.__session = None
        self.__entries = []
        self.__database = database
        self.__ac_server_path = ac_server_path
        self.__realtime_json_path = realtime_json_path

        self.__port_plugin = int(port_plugin)
        self.__port_server = int(port_server)

        # bind UDP socket
        self.__sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)

        address = "127.0.0.1"
        Verbosity(self.__verbosity).print("Bind UdpPluginSever to %s:%i" % (address, self.__port_plugin))
        try:
            self.__sock.bind((address, self.__port_plugin))
        except BaseException as be:
            msg = "Could not bind UPD Plugin server to address "
            msg += "'%s'" % address
            msg += " on port %i!" % self.__port_plugin
            msg += "\n%s" % str(be)
            raise BaseException(msg)
        self.__sock.settimeout(self.__PROCESS_TIME)

        # parse car entries
        self.__entry_list = ConfigParser()
        self.__entry_list.read(entry_list_path)
        for entry_section in self.__entry_list.sections():
            upce = UdpPluginCarEntry(self.__database,
                                     entry_section, self.__entry_list[entry_section],
                                     self.__verbosity)
            self.__entries.append(upce)



    def process(self):
        """ This must be called periodically
        """

        try:
            data, addr = self.__sock.recvfrom(2**12)
            pkt = UdpPacket(data, addr)
            self.parse_packet(pkt)
        except socket.timeout:
            pass

        if self.__session is not None and self.__session.IsActive:
            self.dump_realtime_json()



    def parse_packet(self, pkt):

        prot = pkt.readByte()


        # ACSP_NEW_SESSION
        if prot == 50:

            # set new session object
            self.__session = UdpPluginSession(self.__acswui_info, self.__database, pkt, self.__session, self.__verbosity)

            # enable realtime update
            self.enable_realtime_report()

        # ACSP_SESSION_INFO
        elif prot == 59:
            if self.__session is None:
                self.__session = UdpPluginSession(self.__acswui_info, self.__database, pkt, self.__session, self.__verbosity)
            else:
                self.__session.update(pkt)

        # ACSP_NEW_CONNECTION
        elif prot == 51:
            driver_name = pkt.readStringW()
            driver_guid = pkt.readStringW()
            car_id = pkt.readByte()
            car_model = pkt.readString()
            car_skin = pkt.readString()
            entry = self.get_car_entry(car_id, car_model, car_skin)
            entry.occupy(driver_name, driver_guid, self.__session)


        # ACSP_CONNECTION_CLOSED
        elif prot == 52:
            driver_name = pkt.readStringW()
            driver_guid = pkt.readStringW()
            car_id = pkt.readByte()
            car_model = pkt.readString()
            car_skin = pkt.readString()

            entry = self.get_car_entry(car_id, car_model, car_skin)
            entry.release()


        # ACSP_CAR_UPDATE
        elif prot == 53:
            car_id = pkt.readByte()
            pos = pkt.readVector3f()
            velocity = pkt.readVector3f()
            gear = pkt.readByte()
            engine_rpm = pkt.readUint16()
            normalized_spline_pos = pkt.readSingle();

            entry = self.get_car_entry(car_id)
            entry.realtime_update(pos, velocity, gear, engine_rpm, normalized_spline_pos)


        # ACSP_CAR_INFO
        elif prot == 54:
            self.__verbosity.print("ACSP_CAR_INFO")


        # ACSP_END_SESSION
        elif prot == 55:
            self.__verbosity.print("ACSP_END_SESSION")

            json_file_relpath = pkt.readStringW()
            json_file_abspath = os.path.join(self.__ac_server_path, json_file_relpath)

            # read result json
            with open(json_file_abspath, "r") as f:
                json_string = f.read()
            json_dict = json.loads(json_string)

            # store all results
            if "Result" in json_dict:
                position = 0
                for rslt in json_dict['Result']:
                    position += 1

                    # only care for ordinariy drivers
                    if rslt['DriverGuid'] == "":
                        continue

                    # get car entry object and occupy it
                    entrylist_section = "CAR_%i" % rslt['CarId']
                    car_entry = UdpPluginCarEntry(self.__database,
                                                  entrylist_section,
                                                  self.__entry_list[entrylist_section],
                                                  verbosity=self.__verbosity)
                    car_entry.occupy(rslt['DriverName'], rslt['DriverGuid'])

                    # save result to DB
                    field_values = {}
                    field_values['Position'] = position
                    field_values['Session'] = self.__session.Id
                    field_values['User'] = car_entry.DriverId
                    field_values['CarSkin'] = car_entry.SkinId
                    field_values['BestLap'] = rslt['BestLap']
                    field_values['TotalTime'] = rslt['TotalTime']
                    field_values['Ballast'] = rslt['BallastKG']
                    field_values['Restrictor'] = rslt['Restrictor']
                    self.__database.insertRow("SessionResults", field_values)


        # ACSP_LAP_COMPLETED
        elif prot == 73:
            self.__verbosity.print("ACSP_LAP_COMPLETED")
            car_id = pkt.readByte()
            laptime = pkt.readUint32()
            cuts = pkt.readByte()

            cars_count = pkt.readByte()
            for i in range(cars_count):
                rcar_id = pkt.readByte()
                rtime = pkt.readUint32()
                rlaps = pkt.readUint16()
                has_completed_flag = pkt.readByte()
                self.__verbosity.print("rcar_id: ", rcar_id, ", rtime: ", rtime, ", rlaps: ", rlaps, ", has_completed_flag: ", has_completed_flag, sep="")

            grip = pkt.readSingle()

            entry = self.get_car_entry(car_id)
            entry.complete_lap(self.__session, laptime, cuts, grip)


        # ACSP_VERSION
        elif prot == 56:
            version = pkt.readByte()
            self.__verbosity.print("ACSP_VERSION:", version)


        # ACSP_ERROR
        elif prot == 60:
            err_str = pkt.readStringW()
            self.__verbosity.print("ACSP_ERROR", err_str)


        # ACSP_CLIENT_EVENT
        elif prot == 130:
            self.__verbosity.print("ACSP_CLIENT_EVENT")
            ev_type = pkt.readByte()
            car_id = pkt.readByte()
            other_car_id = None

            if ev_type == 10: # collision with car
                other_car_id = pkt.readByte()

            speed = pkt.readSingle()
            world_pos = pkt.readVector3f()
            rel_pos = pkt.readVector3f()


            if ev_type == 10: # collision with car
                entry = self.get_car_entry(car_id)
                entry_other = self.get_car_entry(other_car_id)
                entry.collision(self.__session, speed, entry_other)
            elif ev_type == 11: # collision with environment
                entry = self.get_car_entry(car_id)
                entry.collision(self.__session, speed)
            else:
                raise NotImplementedError("Undefined event type '%i'!" % ev_type)


        # ACSP_CHAT
        elif prot == 57:
            self.__verbosity.print("ACSP_CHAT")


        # ACSP_CLIENT_LOADED
        elif prot == 58:
            self.__verbosity.print("ACSP_CLIENT_LOADED")


        # undefined
        else:
            raise NotImplementedError("UNKNOWN PACKET: ID '%i'!" % prot)



    def get_car_entry(self, car_id, car_model=None, car_skin=None):
        # find entry and occupy car with driver

        entry = None
        for e in self.__entries:
            if e.Id == car_id:

                # sanity check
                if car_model is not None and e.Model != car_model:
                    msg = "Unexpected connection on car ID '%i': " % car_id
                    msg += " for driver '%s'!\n" % driver_name
                    msg += "Car model in entry_list.ini='%s'" % e.Model
                    msg += ", car model in connection='%s'!" % car_model
                    raise ValueError(msg)
                if car_skin is not None and e.Skin != car_skin:
                    msg = "Unexpected connection on car ID '%i': " % car_id
                    msg += " for driver '%s'!\n" % driver_name
                    msg += "Car model in entry_list.ini='%s'" % e.Skin
                    msg += ", car model in connection='%s'!" % car_skin
                    raise ValueError(msg)

                entry = e
                break

        # sanity check
        if entry is None:
            raise NoCarEntryError("Cannot understand driver connection of requested CarId %i!" %car_id)

        return entry



    def enable_realtime_report(self):
        rate_milliseconds = int(self.__PROCESS_TIME * 1000)

        data = bytearray(100)
        data[0] = 200
        data[1] = 0xff & rate_milliseconds
        data[2] = 0xff & (rate_milliseconds >> 8)

        self.__sock.sendto(data, ("127.0.0.1", self.__port_server))



    def dump_realtime_json(self):
        if self.__realtime_json_path is None or self.__realtime_json_path == "":
            return
        if self.__session is None:
            return

        # collect session data
        data_session = {}
        data_session['Id'] = self.__session.Id

        # collect entry data
        data_entries = []
        for e in self.__entries:
            if e.DriverId is not None:
                data_entries.append(e.RealtimeJsonDict)

        # merge data
        data = {}
        data["Session"] = data_session
        data["Entries"] = data_entries

        # dump data
        with open(self.__realtime_json_path, "w") as f:
            json.dump(data, f, indent=4)
