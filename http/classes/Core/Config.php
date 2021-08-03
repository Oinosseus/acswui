<?php

namespace Core;

class Config {

    // paths
    const AbsPathData = "/home/gamesrv/acswui_devel_data";
    const RelPathData = "../../../acswui_devel_data";
    const AbsPathHtdata = "/home/gamesrv/public_html/acswui_devel_htdata";
    const RelPathHtdata = "../../acswui_devel_htdata";
    const AbsPathAcswui = "/home/gamesrv/public_html/acswui_devel";

    // basic constants
    const DefaultTemplate = "acswui";
    const LogWarning = TRUE;
    const LogDebug = TRUE;
    const RootPassword = '$2y$10$zmAJ0bxLlwGe7PPjXgRKI.vWzfSA82xLIPw1En/g8IiLUdVlMvu2q';
    const GuestGroup = 'Visitor';
    const Locales = ['de_DE'];

    // database constants
    const DbHost = "localhost";
    const DbDatabase = "acswui_devel";
    const DbPort = "3306";
    const DbUser = "acswui_devel";
    const DbPasswd = "BNtFCZ4veJ5YheJm";

    // server_cfg
    const FixedServerConfig = array("SERVER_PASSWORD"=>"mineralquellen","SERVER_ADMIN_PASSWORD"=>"mineralquellen_adm","SERVER_SEND_BUFFER_SIZE"=>"0","SERVER_RECV_BUFFER_SIZE"=>"0","SERVER_CLIENT_SEND_INTERVAL_HZ"=>"20","SERVER_NUM_THREADS"=>"2","SERVER_SLEEP_TIME"=>"1","SERVER_REGISTER_TO_LOBBY"=>"1","SERVER_MAX_CLIENTS"=>"30","SERVER_WELCOME_MESSAGE"=>"virtueller-Asphalt.de\n\n\nHallo, wir bereitben eine kleine Community und suchen neue Fahrer.\n\nAuf unserer Website findet sich momentan nur die Server Kontrolle/Statistik.\nBesuche uns bei Discord (link auf der Website). Dort heißen wir neue Fahrer gern wilkommen.","SERVER_PICKUP_MODE_ENABLED"=>"1","SERVER_LOOP_MODE"=>"0","SERVER_AUTH_PLUGIN_ADDRESS"=>"","SERVER_KICK_QUORUM"=>"70","SERVER_BLACKLIST_MODE"=>"1","SERVER_VOTING_QUORUM"=>"70","SERVER_VOTE_DURATION"=>"30","SERVER_ALLOWED_TYRES_OUT"=>"2","FTP_HOST"=>"","FTP_LOGIN"=>"","FTP_PASSWORD"=>"","FTP_FOLDER"=>"","FTP_LINUX"=>"1","DYNAMIC_TRACK_SESSION_START"=>"96","DYNAMIC_TRACK_RANDOMNESS"=>"1","DYNAMIC_TRACK_LAP_GAIN"=>"50","DYNAMIC_TRACK_SESSION_TRANSFER"=>"80");
    const ServerSlots = array(['SERVER_NAME'=>"Jupiter4-A-Test", 'SERVER_UDP_PORT'=>"9800", 'SERVER_TCP_PORT'=>"9800", 'SERVER_HTTP_PORT'=>"9801", 'SERVER_CLIENT_SEND_INTERVAL_HZ'=>"20", 'SERVER_NUM_THREADS'=>"2", 'SERVER_UDP_PLUGIN_LOCAL_PORT'=>"9803", 'SERVER_UDP_PLUGIN_ADDRESS'=>"127.0.0.1:9802"],
                                 ['SERVER_NAME'=>"Jupiter4-B-Test", 'SERVER_UDP_PORT'=>"9900", 'SERVER_TCP_PORT'=>"9900", 'SERVER_HTTP_PORT'=>"9901", 'SERVER_CLIENT_SEND_INTERVAL_HZ'=>"10", 'SERVER_NUM_THREADS'=>"1", 'SERVER_UDP_PLUGIN_LOCAL_PORT'=>"9903", 'SERVER_UDP_PLUGIN_ADDRESS'=>"127.0.0.1:9902"]);

    // discord webhooks
    const DWhManSrvStrtUrl = "https://discord.com/api/webhooks/828059315125485569/oeTndDdijIS_yPtp1B25kphabK5m1Pea_Nnw3Gh2OjJTGD8XmETBf2x4nQi94FpXdzrH";
    const DWhManSrvStrtGMntn = "783408977198448670";
    const DWhSchSrvStrtUrl = "https://discord.com/api/webhooks/828059315125485569/oeTndDdijIS_yPtp1B25kphabK5m1Pea_Nnw3Gh2OjJTGD8XmETBf2x4nQi94FpXdzrH";
    const DWhSchSrvStrtGMntn = "783408977198448670";

    // misc
    const DriverRanking = array("XP"=>array("R"=>50,"Q"=>25,"P"=>10),"SX"=>array("R"=>1,"Q"=>0.1,"RT"=>1,"BT"=>1),"SF"=>array("CT"=>-0.1,"CE"=>-2.0,"CC"=>-5.0),"DEF"=>array("DAYS"=>30));
}
