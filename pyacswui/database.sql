-- Parse::SQL::Dia          version 0.27                                               
-- Documentation            http://search.cpan.org/dist/Parse-Dia-SQL/                 
-- Environment              Perl 5.020001, /usr/bin/perl                               
-- Architecture             x86_64-linux-thread-multi                                  
-- Target Database          mysql-innodb                                               
-- Input file               /home/thomas/Documents/acswui/pyacswui/../docs/database.dia
-- Generated at             Fri Nov 20 20:01:43 2015                                   
-- Typemap for mysql-innodb not found in input file                                    

-- get_constraints_drop 

-- get_permissions_drop 

-- get_view_drop

-- get_schema_drop
drop table if exists Drivers;
drop table if exists Tracks;
drop table if exists Laps;
drop table if exists Sessions;
drop table if exists Cars;
drop table if exists SessTypes;
drop table if exists SessCarsMap;
drop table if exists CarSkins;
drop table if exists EntryList;
drop table if exists Users;
drop table if exists Groups;
drop table if exists UserGroupMap;
drop table if exists EntryListCars;
drop table if exists UsersDriversMap;
drop table if exists Installer;
drop table if exists TrackRating;

-- get_smallpackage_pre_sql 

-- get_schema_create
create table Drivers (
   Id   int         not null,
   Name varchar(80) not null,
   constraint pk_Drivers primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table Tracks (
   Id       int            not null,
   Name     varchar(120)   not null,
   Config   varchar(120)           ,--  CONFIG_TRACK attribute in server_cfg.ini -> the track variant
   Length   float                  ,--  track length in meters
   Obsolete boolean(false) not null,--  If a track is no more available this becomes false.
   constraint pk_Tracks primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table Laps (
   Id       int       not null,
   Session  int       not null,
   CarSkin  int       not null,
   Driver   int       not null,
   Laptime  float     not null,--  lap duraton in seconds
   Cuts     int       not null,--  Number of cuts (>0 is invalid lap)
   Time     timestamp not null,--  Servertime from when the lap was finished.
   FirstLap int       not null,--  Integer that is set to 1 if this was the first lap of the session. Lap records of first laps should not count because they cannot be driven from start/finish line.
   Grip     float     not null,--  Track grip at the time where this lap was driven.
   constraint pk_Laps primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table Sessions (
   Id                     int         not null,
   SessType               int         not null,
   SessTime               int         not null,
   SessLaps               int         not null,
   Track                  int         not null,
   StartTime              timestamp   not null,
   WeatherAmbient         float       not null,
   WeatherRoad            float               ,
   WeatherGraphics        varchar(70)         ,
   SrvAbsAllowed          int         not null,
   SrvTcAllowed           int         not null,
   SrvStabilityAllowed    int         not null,
   SrvTyreBlanketsAllowed int         not null,
   constraint pk_Sessions primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table Cars (
   Id   int         not null,
   Name varchar(50) not null,
   constraint pk_Cars primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table SessTypes (
   Id   int         not null,
   Name varchar(40) not null,
   constraint pk_SessTypes primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table SessCarsMap (
   Id      int not null,
   Session int not null,
   CarSkin int not null,
   constraint pk_SessCarsMap primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table CarSkins (
   Id       int            not null,
   Car      int                    ,
   Name     varchar(50)    not null,
   Obsolete boolean(false) not null,--  If a skin (or its car) is no more available this becomes false.
   constraint pk_CarSkins primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table EntryList (
   Id   int         not null,
   Name varchar(50) not null,
   constraint pk_EntryList primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table Users (
   Id          int          not null,
   Login       varchar(50)  not null,
   Password    varchar(100) not null,
   Steam64GUID varchar(50)          ,
   constraint pk_Users primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table Groups (
   Id                      int            not null,
   Name                    varchar(50)    not null,
   PermitServerCfgEdit     boolean(false)         ,
   PermitServerWeatherEdit boolean(false)         ,
   PermitServerStart       boolean(false) not null,
   PermitServerStop        boolean(false) not null,
   PermitEditEntryList     boolean(false) not null,
   constraint pk_Groups primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table UserGroupMap (
   Id    int not null,
   User  int not null,
   Group int not null,
   constraint pk_UserGroupMap primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table EntryListCars (
   Id        int not null,
   EntryList int not null,
   User      int         ,
   CarSkin   int not null,
   Ballast   int         ,
   constraint pk_EntryListCars primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table UsersDriversMap (
   Id     int not null,
   Driver int not null,
   User   int not null,
   constraint pk_UsersDriversMap primary key (Id)
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table Installer (
   datetime DATETIME    ,
   version  varchar(10) ,
   log      text        
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;
create table TrackRating (
   User         int not null,
   Track        int not null,
   RateGraphics int         ,
   RateDrive    int         
)   ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- get_view_create

-- get_permissions_create

-- get_inserts

-- get_smallpackage_post_sql

-- get_associations_create
