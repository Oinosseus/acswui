import socket
from configparser import ConfigParser
import json
import os.path
import time
from .verbosity import Verbosity
from .udp_packet import UdpPacket
from .udp_plugin_session import UdpPluginSession
from .udp_plugin_car_entry import UdpPluginCarEntry


class NoCarEntryError(BaseException):
    pass



class UdpPluginServer(object):

    def __init__(self,
                 server_slot, server_preset,
                 port_server, port_plugin,
                 port_rp_events_tx, port_rp_events_rx, rp_admin_password,
                 database,
                 entry_list_path,
                 ac_server_path,
                 realtime_json_path = None,
                 kick_illegal_occupations = False,
                 bop_map_car_ballasts = {},          # key=car_model, value=ballast
                 bop_map_car_restrictors = {},       # key=car_model, value=restrictor
                 bop_map_user_ballasts = {},         # key=Steam64GUID (or OTHER), value=ballast
                 bop_map_user_restrictors = {},      # key=Steam64GUID (or OTHER), value=restrictor
                 bop_map_teamcar_ballasts = {},      # key=TeamCarId, value=ballast
                 bop_map_teamcar_restrictors = {},   # key=TeamCarId, value=restrictor
                 referenced_session_schedule_id = None,
                 verbosity=0):

        # This is the typical duration for the process() method
        # used for UDP polling timeout
        # and for realtime data update
        self.__PROCESS_TIME = 0.1 # seconds

        self.__verbosity = Verbosity(verbosity, self.__class__.__name__)
        self.__server_slot = int(server_slot)
        self.__server_preset = int(server_preset)
        self.__session = None
        self.__entries = []
        self.__database = database
        self.__ac_server_path = ac_server_path
        self.__realtime_json_path = realtime_json_path
        self.__last_kick_illegal_occupations = None
        self.__kick_illegal_occupations = kick_illegal_occupations

        # referenced_session_schedule_id
        try:
            self.__referenced_session_schedule_id = int(referenced_session_schedule_id)
        except:
            self.__referenced_session_schedule_id = None

        # force ballast and restrictor
        self.__last_checked_balancing = None

        self.__port_plugin = int(port_plugin)
        self.__port_server = int(port_server)

        # bind UDP socket for RP event listening
        self.__sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        address = "127.0.0.1"
        self.__verbosity.print("Bind UdpPluginSever to %s:%i" % (address, self.__port_plugin))
        try:
            self.__sock.bind((address, self.__port_plugin))
        except BaseException as be:
            msg = "Could not bind UPD Plugin server to address "
            msg += "'%s'" % address
            msg += " on port %i!" % self.__port_plugin
            msg += "\n%s" % str(be)
            raise BaseException(msg)
        self.__sock.settimeout(self.__PROCESS_TIME / 2)

        # bind UDP socket for RP event listening
        self.__port_rp_events_tx = int(port_rp_events_tx)
        self.__port_rp_events_rx = int(port_rp_events_rx)
        self.__rp_event_start_confirmed = False
        self.__rp_event_start_send = time.time()
        self.__rp_admin_password = str(rp_admin_password)
        self.__sock_rp_events = None
        if self.__port_rp_events_rx > 0:
            self.__sock_rp_events = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            address = "127.0.0.1"
            self.__verbosity.print("Bind RP Event Listener to %s:%i" % (address, self.__port_rp_events_rx))
            try:
                self.__sock_rp_events.bind((address, self.__port_rp_events_rx))
            except BaseException as be:
                msg = "Could not bind RP Event Listener server to address "
                msg += "'%s'" % address
                msg += " on port %i!" % self.__port_plugin
                msg += "\n%s" % str(be)
                raise BaseException(msg)
            self.__sock_rp_events.settimeout(self.__PROCESS_TIME / 2)

        # parse car entries
        self.__entry_list = ConfigParser()
        self.__entry_list.read(entry_list_path)
        for entry_section in self.__entry_list.sections():
            upce = UdpPluginCarEntry(self.__database,
                                     entry_section, self.__entry_list[entry_section],
                                     self.__verbosity)
            self.__entries.append(upce)

        # remember BOP maps
        self.__bop_map_car_ballasts = {key: int(bop_map_car_ballasts[key]) for key in bop_map_car_ballasts.keys()}
        self.__bop_map_car_restrictors = {key: int(bop_map_car_restrictors[key]) for key in bop_map_car_restrictors.keys()}
        self.__bop_map_user_ballasts = {key: int(bop_map_user_ballasts[key]) for key in bop_map_user_ballasts.keys()}
        self.__bop_map_user_restrictors = {key: int(bop_map_user_restrictors[key]) for key in bop_map_user_restrictors.keys()}
        self.__bop_map_teamcar_ballasts = {key: int(bop_map_teamcar_ballasts[key]) for key in bop_map_teamcar_ballasts.keys()}
        self.__bop_map_teamcar_restrictors = {key: int(bop_map_teamcar_restrictors[key]) for key in bop_map_teamcar_restrictors.keys()}



    def kick(self, entry):
        """ Kick a user from a car entry
        """
        self.__verbosity.print("kick", entry.DriverName, "[", entry.DriverGuid, "]", "from car", entry.Id)
        data = bytearray(100)
        data[0] = 206
        data[1] = entry.Id
        self.__sock.sendto(data, ("127.0.0.1", self.__port_server))



    def process(self):
        """ This must be called periodically
        """

        # read from AC Server
        new_data = False
        try:
            data, addr = self.__sock.recvfrom(2**12)
            pkt = UdpPacket(data, addr)
            self.parse_packet(pkt)
            new_data = True
        except socket.timeout:
            pass

        # update real time data
        if new_data and self.__session is not None and self.__session.IsActive:
            self.dump_realtime_json()


        # readfrom RP
        if self.__sock_rp_events is not None:
            try:
                data, addr = self.__sock_rp_events.recvfrom(2**12)
                self.parse_rp_data(data)
            except socket.timeout:
                pass


        # send start request to RP
        if not self.__rp_event_start_confirmed and (time.time() - self.__rp_event_start_send) > 5:
            self.__rp_event_start_send = time.time()
            if self.__port_rp_events_tx > 0:
                self.__verbosity.print("Send new RP Event start request")
                data = bytes("{\"request\": \"start\", \"password\": \"" + self.__rp_admin_password + "\"}", "utf-8")
                self.__sock_rp_events.sendto(data, ("127.0.0.1", self.__port_rp_events_tx))


        # check for illegal occupations
        if self.__kick_illegal_occupations:
            if self.__last_kick_illegal_occupations is None or (time.time() - self.__last_kick_illegal_occupations) > 5:
                self.__last_kick_illegal_occupations = time.time()

                for entry in self.__entries:
                    if entry.illegalOccupation():
                        self.__verbosity.print("Illegal Occupation of driver " + entry.DriverName + " [" + str(entry.DriverGuid) + "] for car " + str(entry.Id) + "!")
                        self.send_chat_broadcast("ACswui: kick " + entry.DriverName + " because using preserved car!")
                        self.kick(entry)


        # check for balalst and restrictor
        if self.__last_checked_balancing is None or (time.time() - self.__last_checked_balancing) > 15:
            self.__last_checked_balancing = time.time()

            for entry in self.__entries:

                # ignored not occupied cars
                if entry.DriverGuid is None:
                    continue


                # -------------------------------------------------------------
                #  Ballast
                # -------------------------------------------------------------

                # for car model
                if entry.Model in self.__bop_map_car_ballasts:
                    ballast_car_model = self.__bop_map_car_ballasts[entry.Model]
                else:
                    ballast_car_model = 0

                # for dirver
                if entry.DriverGuid in self.__bop_map_user_ballasts:
                    ballast_user = self.__bop_map_user_ballasts[entry.DriverGuid]
                elif 'OTHER' in self.__bop_map_user_ballasts:
                    ballast_user = self.__bop_map_user_ballasts['OTHER']
                else:
                    ballast_user = 0

                # for team
                if entry.TeamCarId in self.__bop_map_teamcar_ballasts:
                    ballast_team = self.__bop_map_teamcar_ballasts[entry.TeamCarId]
                else:
                    ballast_team = 0

                # apply ballast
                ballast = ballast_car_model + max(ballast_user, ballast_team)
                self.send_admin_command("/ballast %i %i" % (entry.Id, ballast))
                entry.BallastCurrent = ballast


                # -------------------------------------------------------------
                #  Restrictor
                # -------------------------------------------------------------

                # for car model
                if entry.Model in self.__bop_map_car_restrictors:
                    restrictor_car_model = self.__bop_map_car_restrictors[entry.Model]
                else:
                    restrictor_car_model = 0

                # for dirver
                if entry.DriverGuid in self.__bop_map_user_restrictors:
                    restrictor_user = self.__bop_map_user_restrictors[entry.DriverGuid]
                elif 'OTHER' in self.__bop_map_user_restrictors:
                    restrictor_user = self.__bop_map_user_restrictors['OTHER']
                else:
                    restrictor_user = 0

                # for team
                if entry.TeamCarId in self.__bop_map_teamcar_restrictors:
                    restrictor_team = self.__bop_map_teamcar_restrictors[entry.TeamCarId]
                else:
                    restrictor_team = 0

                # apply ballast
                restrictor = restrictor_car_model + max(restrictor_user, restrictor_team)
                self.send_admin_command("/restrictor %i %i" % (entry.Id, restrictor))
                entry.RestrictorCurrent = restrictor



    def parse_packet(self, pkt):

        prot = pkt.readByte()


        # ACSP_NEW_SESSION
        if prot == 50:
            self.__verbosity.print("ACSP_NEW_SESSION")

            # set new session object
            self.__session = UdpPluginSession(self.__server_slot,
                                              self.__server_preset,
                                              self.__database,
                                              pkt,
                                              self.__session,
                                              self.__referenced_session_schedule_id,
                                              self.__verbosity)

            # enable realtime update
            self.enable_realtime_report()

        # ACSP_SESSION_INFO
        elif prot == 59:
            if self.__session is None:
                self.__verbosity.print("ACSP_SESSION_INFO")
                self.__session = UdpPluginSession(self.__server_slot,
                                                  self.__server_preset,
                                                  self.__database,
                                                  pkt,
                                                  self.__session,
                                                  self.__referenced_session_schedule_id,
                                                  self.__verbosity)
            else:
                self.__session.update(pkt)

        # ACSP_NEW_CONNECTION
        elif prot == 51:
            self.__verbosity.print("ACSP_NEW_CONNECTION")
            v = Verbosity(self.__verbosity)

            driver_name = pkt.readStringW()
            driver_guid = pkt.readStringW()
            car_id = pkt.readByte()
            car_model = pkt.readString()
            car_skin = pkt.readString()

            entry = self.get_car_entry(car_id, car_model, car_skin)
            entry.occupy(driver_name, driver_guid, self.__session)

            v.print("Name:", driver_name)
            v.print("GUID:", driver_guid)
            v.print("Car-ID:", car_id)
            v.print("Car-Model:", car_model)
            v.print("Car-Skin:", car_skin)


        # ACSP_CONNECTION_CLOSED
        elif prot == 52:
            self.__verbosity.print("ACSP_CONNECTION_CLOSED")
            v = Verbosity(self.__verbosity)
            driver_name = pkt.readStringW()
            driver_guid = pkt.readStringW()
            car_id = pkt.readByte()
            car_model = pkt.readString()
            car_skin = pkt.readString()

            entry = self.get_car_entry(car_id, car_model, car_skin)
            entry.release()

            v.print("Name:", driver_name)
            v.print("GUID:", driver_guid)


        # ACSP_CAR_UPDATE
        elif prot == 53:
            #Verbosity(Verbosity(Verbosity(self.__verbosity))).print("ACSP_CAR_UPDATE")
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
            pass
            #self.__verbosity.print("ACSP_CAR_INFO")


        # ACSP_END_SESSION
        elif prot == 55:
            print("ACSP_END_SESSION")
            self.__verbosity.print("ACSP_END_SESSION")

            json_file_relpath = pkt.readStringW()
            json_file_abspath = os.path.join(self.__ac_server_path, json_file_relpath)

            # remember result file
            if self.__session is None:
                print("ERROR: ACSP_END_SESSION received, but no active session existent!")
            else:
                self.__session.parse_result_json(json_file_abspath, self.__entries)


        # ACSP_LAP_COMPLETED
        elif prot == 73:
            #self.__verbosity.print("ACSP_LAP_COMPLETED")
            car_id = pkt.readByte()
            laptime = pkt.readUint32()
            cuts = pkt.readByte()

            cars_count = pkt.readByte()
            for i in range(cars_count):
                rcar_id = pkt.readByte()
                rtime = pkt.readUint32()
                rlaps = pkt.readUint16()
                has_completed_flag = pkt.readByte()
                #self.__verbosity.print("rcar_id: ", rcar_id, ", rtime: ", rtime, ", rlaps: ", rlaps, ", has_completed_flag: ", has_completed_flag, sep="")

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
            #self.__verbosity.print("ACSP_CLIENT_EVENT")
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



    def parse_rp_data(self, data):
        data = json.loads(data)
        # .decode("utf-8")

        # malformed data
        if "type" not in data:
            self.__verbosity.print("Malformed data from RP plugin")
            print(data)

        # startConfirm
        elif data["type"] == "startConfirm":
            self.__rp_event_start_confirmed = True
            self.__verbosity.print("RP event registration confirmed")

        # newSession
        elif data["type"] == "newSession":
            self.__verbosity.print("RP event", data["type"])

        # endRace
        elif data["type"] == "endRace":

            # notify about penalty
            if 'seconds_penalty' in data["type"]:
                self.impose_penalty("Real-Penalty: End-Race/Seconds-Penalty",
                                    data["driver"], data["seconds_penalty"], False)

            else:
                print("Unexpected RP Event:", data)

        # dsq
        elif data["type"] == "dsq":
            self.impose_penalty("Real-Penalty: DSQ\n" + data['cause'],
                                data["driver"], 0, True)

        # newSecondsPenalty
        elif data["type"] == "newSecondsPenalty":

            if 'cause' not in data['penalty']:
                print("Unexpected RP Event:", data)

            elif data['penalty']['cause'] == "missingSwaps":
                self.impose_penalty("Real-Penalty: DSQ\n" + data['penalty']['cause'],
                                    data["driver"], data['penalty']['seconds'], False)

            elif data['penalty']['cause'] == "jump":
                pass

            else:
                print("Unexpected RP Event:", data)

        # leavePit
        elif data["type"] == "leavePit":
            self.__verbosity.print("RP event", data["type"])
            print(data)

        # enterPit
        elif data["type"] == "enterPit":
            self.__verbosity.print("RP event", data["type"])
            print(data)

        # ignored types
        elif data["type"] in ["vscRestart", "fcyStart", "fcyRestart", "fcyDeployed", "fcyEnding", "vscDeployed", "vscEnding", "scDeployed", "scThisLap", "cutWarning", "penaltyTaken", "drsEnabled", "scRestart", "longpit", "swap", "penaltyConverted", "secondsPenaltyConverted", "newPenalty"]:
            self.__verbosity.print("Ignoring RP event", data["type"])
            pass

        # unwon type
        else:
            # this is no error, since future RP versions can implement new stuff
            print("Unexpected RP Event:", __line__, data)



    def get_car_entry(self, car_id, car_model=None, car_skin=None):
        # find entry and occupy car with driver

        entry = None
        for e in self.__entries:
            if e.Id == car_id:

                # sanity check
                if car_model is not None and e.Model != car_model:
                    msg = "Unexpected connection on car ID '%i': " % car_id
                    msg += " for driver '%s'!\n" % e.DriverName
                    msg += "Car model in entry_list.ini='%s'" % e.Model
                    msg += ", car model in connection='%s'!" % car_model
                    raise ValueError(msg)
                if car_skin is not None and e.Skin != car_skin:
                    msg = "Unexpected connection on car ID '%i': " % car_id
                    msg += " for driver '%s'!\n" % e.DriverName
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


    def send_chat_broadcast(self, message):

        # cut message
        if len(message) > 254:
            message = message[:254]
        if message[-1] != "\n":
            message += "\n"

        data = bytearray(10 + 4*len(message))
        data[0] = 203
        data[1] = len(message)

        index = 2
        for byte in message.encode("utf-32")[4:]:
            data[index] = byte
            index += 1

        self.__sock.sendto(data, ("127.0.0.1", self.__port_server))



    def send_admin_command(self, command):
        """ command is without leading '/'
        """
        command = command

        data = bytearray(10 + 4*len(command))
        data[0] = 209
        data[1] = len(command)

        index = 2
        for byte in bytearray(command, "utf-32")[4:]:
            data[index] = byte
            index += 1

        self.__sock.sendto(data, ("127.0.0.1", self.__port_server))


    def impose_penalty(self,
                       cause_drescription,
                       driver,
                       pen_seconds,
                       pen_dsq):

        # check data
        if self.__session.Id is None:
            print("ERROR", "Cannot impose penalty because Session-Id is None:", cause_drescription, driver)
            return

        # identify driver
        car_id = int(driver['carId'])
        guid = driver['guid']
        entry = None
        for e in self.__entries:
            if e.Id == car_id:
                entry = e
                break
        if entry is None:
            print("ERROR", "Cannot impose penalty because Entry is None:", cause_drescription, driver)
            return
        if entry.DriverGuid != guid:
            print("WARNING", "At imposing penalty Entry.DriverGuid '%s' is not equal to RP guid '%s'" % (entry.DriverGuid, guid))

        # enhancing cause by driver
        cause_drescription += "\nDriver: " + driver['Driver name']
        cause_drescription += "\nCar: " + driver['car model']
        cause_drescription += "\nSkin: " + driver['car skin']

        columns = {'Session': self.__session.Id,
                   'Cause': cause_drescription,
                   'TeamCar': entry.TeamCarId,
                   'User': entry.DriverId if entry.TeamCarId == 0 else 0,
                   'PenTime': pen_seconds,
                   'PenDsq': 1 if pen_dsq else 0}
        self.__db.insertRow("SessionPenalties", columns)
