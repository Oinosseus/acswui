import socket
from configparser import ConfigParser
from .verbosity import Verbosity
from .udp_packet import UdpPacket
from .udp_plugin_session import UdpPluginSession
from .udp_plugin_car_entry import UdpPluginCarEntry



class UdpPluginServer(object):

    def __init__(self, address, port, database, entry_list_path, verbosity=0):
        self.__verbosity = Verbosity(verbosity)
        self.__session = None
        self.__entries = []

        port = int(port)
        self.__database = database
        self.__driver_connections = []

        # bind UDP socket
        self.__sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)

        Verbosity(self.__verbosity).print("Bind UdpPluginSever to %s:%i" % (address, int(port)))
        try:
            self.__sock.bind((address, int(port)))
        except BaseException as be:
            msg = "Could not bind UPD Plugin server to address "
            msg += "'%s'" % address
            msg += " on port %i!" % port
            msg += "\n%s" % str(be)
            raise BaseException(msg)
        self.__sock.settimeout(0.1)

        # parse car entries
        entry_list = ConfigParser()
        entry_list.read(entry_list_path)
        for entry_section in entry_list.sections():
            upce = UdpPluginCarEntry(self.__database,
                                     entry_section, entry_list[entry_section],
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



    def parse_packet(self, pkt):

        prot = pkt.readByte()
        self.__verbosity.print("UDP receive packet ", prot, "on address", pkt.Addr)


        # ACSP_NEW_SESSION
        if prot == 50:
            self.__session = UdpPluginSession(self.__database, pkt, self.__verbosity)

        # ACSP_SESSION_INFO
        elif prot == 59:
            if self.__session is None:
                self.__session = UdpPluginSession(self.__database, pkt, self.__verbosity)
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
            entry.occupy(driver_name, driver_guid)


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
            self.__verbosity.print("ACSP_CAR_UPDATE")


        # ACSP_CAR_INFO
        elif prot == 54:
            self.__verbosity.print("ACSP_CAR_INFO")


        # ACSP_END_SESSION
        elif prot == 55:
            self.__verbosity.print("ACSP_END_SESSION")


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
            raise ValueError("Cannot understand driver connection of requested CarId %i!" %car_id)

        return entry
    
