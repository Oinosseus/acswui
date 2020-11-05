import socket
from .verbosity import Verbosity
from .udp_packet import UdpPacket


class DriverConnection(object):

    def __init__(self, database,
                 driver_name, driver_guid,
                 car_id, car_model, car_skin,
                 verbosity=0):

        self.__verbosity = Verbosity(verbosity)
        self.__car_id = car_id

        # ----------------
        # Clarify CarSkin

        car_id = None
        res = database.fetch("Cars", "Id", {'Car':car_model})
        if len(res) == 0:
            car_id = database.insertRow("Cars", {'Car':car_model, 'Name':"", 'Parent':0, 'Brand':""})
        elif len(res) == 1:
            car_id = res[0]['Id']
        else:
            self.__verbosity.print("car_model =", car_model, ", car_skin =", car_skin)
            raise ValueError("Database table 'Cars' is ambigous!")

        car_skin_id = None
        res = database.fetch("CarSkins", "Id", {'Car':car_id, 'Skin':car_skin})
        if len(res) == 0:
            car_skin_id = database.insertRow("CarSkins", {'Car':car_id, 'Skin':car_skin})
        elif len(res) == 1:
            car_skin_id = res[0]['Id']
        else:
            self.__verbosity.print("car_model =", car_model, ", car_skin =", car_skin)
            raise ValueError("Database table 'CarSkins' is ambigous")
        self.__car_skin_id = car_skin_id


        # -------------
        # Clarify User
        user_id = None
        res = database.fetch("Users", "Id", {'Steam64GUID': driver_guid})
        if len(res) == 0:
            user_id = database.insertRow("Users", {'Login':driver_name, 'Steam64GUID':driver_guid, 'Password':""})
        elif len(res) == 1:
            user_id = res[0]['Id']
            database.updateRow("Users", user_id, {"Login": driver_name})
        else:
            self.__verbosity.print("driver_guid =", driver_guid)
            raise ValueError("Database table 'Users' is ambigous")
        self.__user_id = user_id


    @property
    def CarId(self):
        return self.__car_id

    @property
    def CarSkinId(self):
        return self.__car_skin_id

    @property
    def UserId(self):
        return self.__user_id



class UdpPluginServer(object):

    def __init__(self, address, port, database, verbosity=0):
        self.__verbosity = Verbosity(verbosity)

        port = int(port)
        self.__database = database
        self.__session_db_id = None
        self.__session_track_id = None
        self.__driver_connections = []

        # bind UDP socket
        self.__sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)

        try:
            self.__sock.bind((address, int(port)))
        except BaseException as be:
            msg = "Could not bind UPD Plugin server to address "
            msg += "'%s'" % address
            msg += " on port %i!" % port
            msg += "\n%s" % str(be)
            raise BaseException(msg)
        self.__sock.settimeout(0.1)



    def process(self):
        """ Thisn must be called periodically
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


        if prot == 50 or prot == 59:

            version = pkt.readByte()
            sess_index = pkt.readByte()
            current_session_index = pkt.readByte()
            session_count = pkt.readByte()

            if prot == 50:
                self.__verbosity.print(1 ,"ACSP_NEW_SESSION")
            else:
                self.__verbosity.print("ACSP_SESSION_INFO")

            self.__verbosity.print("version:", version)
            self.__verbosity.print("sess_index:", sess_index)
            self.__verbosity.print("current_session_index:", current_session_index)
            self.__verbosity.print("session_count:", session_count)

            server_name = pkt.readStringW()
            track_name = pkt.readString()
            track_config = pkt.readString()
            session_name = pkt.readString()
            session_type = pkt.readByte()
            session_time = pkt.readUint16()
            session_laps = pkt.readUint16()
            session_waittime = pkt.readUint16()
            temp_amb = pkt.readByte()
            temp_road = pkt.readByte()
            weather_graphics = pkt.readString()
            elapsed_ms = pkt.readInt32()
            Verbosity(self.__verbosity).print("server name:", server_name)
            Verbosity(self.__verbosity).print("track name / config:", track_name, "/", track_config)
            Verbosity(self.__verbosity).print("session name:", session_name)
            Verbosity(self.__verbosity).print("session type:", session_type)
            Verbosity(self.__verbosity).print("session time:", session_time)
            Verbosity(self.__verbosity).print("session laps:", session_laps)
            Verbosity(self.__verbosity).print("session wait time:", session_waittime)
            Verbosity(self.__verbosity).print("temp amb / road:", temp_amb, "/", temp_road)
            Verbosity(self.__verbosity).print("weather graphics", weather_graphics)
            Verbosity(self.__verbosity).print("elapsed ms", elapsed_ms)

            self.update_session(version, sess_index, current_session_index, session_count,
                                server_name, track_name, track_config,
                                session_name, session_type, session_time, session_laps, session_waittime,
                                temp_amb, temp_road, weather_graphics, elapsed_ms)


        elif prot == 51 or prot == 52:

            driver_name = pkt.readStringW()
            driver_guid = pkt.readStringW()
            car_id = pkt.readByte()
            car_model = pkt.readString()
            car_skin = pkt.readString()

            Verbosity(self.__verbosity).print("driver_name:", driver_name)
            Verbosity(self.__verbosity).print("driver_guid:", driver_guid)
            Verbosity(self.__verbosity).print("car_id:", car_id)
            Verbosity(self.__verbosity).print("car_model:", car_model)
            Verbosity(self.__verbosity).print("car_skin:", car_skin)

            if prot == 51:
                self.__verbosity.print("ACSP_NEW_CONNECTION")
                self.connection_new(driver_name, driver_guid,
                                    car_id, car_model, car_skin)
            else:
                self.__verbosity.print("ACSP_CONNECTION_CLOSED")
                self.connection_delete(car_id)


        elif prot == 53:
            self.__verbosity.print("ACSP_CAR_UPDATE")

        elif prot == 54:
            self.__verbosity.print("ACSP_CAR_INFO")

        elif prot == 55:
            self.__verbosity.print("ACSP_END_SESSION")

        elif prot == 73:
            self.__verbosity.print("ACSP_LAP_COMPLETED")
            car_id = pkt.readByte()
            laptime = pkt.readUint32()
            cuts = pkt.readByte()
            Verbosity(self.__verbosity).print("car_id:", car_id)
            Verbosity(self.__verbosity).print("laptime:", laptime)
            Verbosity(self.__verbosity).print("cuts:", cuts)

            cars_count = pkt.readByte()
            for i in range(cars_count):
                rcar_id = pkt.readByte()
                rtime = pkt.readUint32()
                rlaps = pkt.readUint16()
                has_completed_flag = pkt.readByte()
                self.__verbosity.print("rcar_id: ", rcar_id, ", rtime: ", rtime, ", rlaps: ", rlaps, ", has_completed_flag: ", has_completed_flag, sep="")

            grip = pkt.readSingle()
            self.__verbosity.print("grip level:", grip)

            self.complete_lap(car_id, laptime, cuts, grip)


        elif prot == 56:
            version = pkt.readByte()
            self.__verbosity.print("ACSP_VERSION:", version)


        elif prot == 60:
            err_str = pkt.readStringW()
            self.__verbosity.print("ACSP_ERROR", err_str)


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
                Verbosity(self.__verbosity).print("Collision with other car")
                self.collision_car(car_id, speed, other_car_id)
            elif ev_type == 11: # collision with environment
                Verbosity(self.__verbosity).print("Collision with environment")
                self.collision_env(car_id, speed)
            else:
                Verbosity(self.__verbosity).print("undefined event")

            Verbosity(self.__verbosity).print("car_id:", car_id)
            Verbosity(self.__verbosity).print("other_car_id:", other_car_id)
            Verbosity(self.__verbosity).print("speed:", speed)
            Verbosity(self.__verbosity).print("world_pos:", world_pos)
            Verbosity(self.__verbosity).print("rel_pos:", rel_pos)


        elif prot == 57:
            self.__verbosity.print("ACSP_CHAT")
        elif prot == 58:
            self.__verbosity.print("ACSP_CLIENT_LOADED")
        else:
            self.__verbosity.print("UNKNOWN PACKET", prot)



    def get_driver_connection(self, car_id):
        for dc in self.__driver_connections:
            if dc.CarId == car_id:
                return dc
        return None




    def connection_new(self, driver_name, driver_guid,
                             car_id, car_model, car_skin):

        # check if ambigous
        for dc in self.__driver_connections:
            if dc.CarId == car_id:
                raise ValueError("CarId=%i is already connected!" % car_id)

        # new connection
        dc = DriverConnection(self.__database,
                              driver_name, driver_guid,
                              car_id, car_model, car_skin,
                              verbosity=self.__verbosity)

        # save connection
        self.__driver_connections.append(dc)



    def connection_delete(self, car_id):

        while True:

            pop_index = None
            for idx in range(len(self.__driver_connections)):
                dc = self.__driver_connections[idx]
                if dc.CarId == car_id:
                    pop_index = idx
                    break

            if pop_index is not None:
                self.__driver_connections.pop(pop_index)
            else:
                break



    def complete_lap(self, car_id, laptime, cuts, grip):
        dc = self.get_driver_connection(car_id)
        fields = {}
        fields['Session'] = self.__session_db_id
        fields['CarSkin'] = dc.CarSkinId
        fields['User'] = dc.UserId
        fields['LapTime'] = laptime
        fields['Cuts'] = cuts
        fields['Grip'] = grip
        self.__database.insertRow("Laps", fields)



    def collision_env(self, car_id, speed):
        dc = self.get_driver_connection(car_id)
        fields = {}
        fields['Session'] = self.__session_db_id
        fields['CarSkin'] = dc.CarSkinId
        fields['User'] = dc.UserId
        fields['Speed'] = speed
        self.__database.insertRow("CollisionEnv", fields)



    def collision_car(self, car_id, speed, other_car_id):
        dc = self.get_driver_connection(car_id)
        other_dc = self.get_driver_connection(other_car_id)
        fields = {}
        fields['Session'] = self.__session_db_id
        fields['CarSkin'] = dc.CarSkinId
        fields['User'] = dc.UserId
        fields['Speed'] = speed
        fields['OtherUser'] = other_dc.UserId
        fields['OtherCarSkin'] = other_dc.CarSkinId
        self.__database.insertRow("CollisionCar", fields)



    def update_session(self, protocol_version,
                             session_index,
                             current_session_index,
                             session_count,
                             server_name,
                             track_name,
                             track_config,
                             session_name,
                             session_type,
                             session_time,
                             session_laps,
                             session_waittime,
                             temp_amb,
                             temp_road,
                             wheater_graphics,
                             elapsed):


        # ---------
        # Track Id

        res = self.__database.fetch("Tracks", ["Id"], {'Track':track_name, 'Config':track_config})
        if len(res) > 0:
            self.__session_track_id = res[0]['Id']
        else:
            fields = {}
            fields['Track'] = track_name
            fields['Config'] = track_config
            fields['Name'] = ""
            fields['Length'] = 0
            fields['Pitboxes'] = 0
            self.__session_track_id = self.__database.insertRow("Tracks", fields)


        # -----------
        # Session Id

        if self.__session_db_id == None:
            new_session = True

        else:

            # check if databse contains this session
            where_dict = {}
            where_dict['ProtocolVersion'] = protocol_version
            where_dict['SessionIndex'] = session_index
            where_dict['CurrentSessionIndex'] = current_session_index
            where_dict['SessionCount'] = session_count
            where_dict['ServerName'] = server_name
            where_dict['Track'] = self.__session_track_id
            where_dict['Name'] = session_name
            where_dict['Type'] = session_type

            res = self.__database.fetch("Sessions", ["Id"], where_dict, "Id", False)
            if len(res) > 0:
                if res[0]['Id'] == self.__session_db_id:
                    new_session = False
                else:
                    new_session = True
            else:
                new_session = True


        # ---------------------
        # Update Session Table

        fields = {}
        fields['ProtocolVersion'] = protocol_version
        fields['SessionIndex'] = session_index
        fields['CurrentSessionIndex'] = current_session_index
        fields['SessionCount'] = session_count
        fields['ServerName'] = server_name
        fields['Track'] = self.__session_track_id
        fields['Name'] = session_name
        fields['Type'] = session_type
        fields['Time'] = session_time
        fields['Laps'] = session_laps
        fields['WaitTime'] = session_waittime
        fields['TempAmb'] = temp_amb
        fields['TempRoad'] = temp_road
        fields['WheatherGraphics'] = wheater_graphics
        fields['Elapsed'] = elapsed

        if new_session:
            self.__session_db_id = self.__database.insertRow("Sessions", fields)
            self.__verbosity.print("New Session in Database:", fields)
        else:
            self.__database.updateRow("Sessions", self.__session_db_id, fields)
