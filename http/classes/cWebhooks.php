<?php


class Webhooks {

    public static function manualServerStart(ServerSlot $slot, ServerPreset $preset, CarClass $carclass, Track $track) {
        global $acswuiConfig, $acswuiUser;

        // get globals
        $url = $acswuiConfig->DWhManSrvStrtUrl;
        $gmntn = $acswuiConfig->DWhManSrvStrtGMntn;
        if (strlen($url) == 0) return;


        // message content
        $content = "";
        if ($gmntn != "") $content .= "<@&$gmntn> ";
        $content .= _("startet") . " " . $slot->name() . "\n";
        $content .= "*" . _("Server Preset") . "*: **" . $preset->name() . "**\n";
        $content .= "*" . _("Car Class") . "*: **" . $carclass->name() . "**\n";
        $content .= "*" . _("Track") . "*: **" . $track->name() . "**";

        // create message json
        $json_array = array();
        $json_array['content'] = $content;
        $json_array['username'] = $acswuiUser->user()->displayName(2);
        $json_post = json_encode($json_array);

        // send webhook request
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json_post);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        $response = curl_exec($curl);
        curl_close($curl);

        if (strlen($response)) {
            global $acswuiLog;
            $acswuiLog->logError($response);
        }
    }
}


?>
