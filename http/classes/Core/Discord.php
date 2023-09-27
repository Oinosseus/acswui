<?php

namespace Core;

/**
 * Discord Integration
 */
class Discord {

    /**
     * @param §schedules An array, retrieved from ServerPreset->schedule()
     * @return The session schedule as string
     */
    private static function createContentSchedule(array $schedules) {
        $content = "";


        $time = new \DateTime("now");
        for ($i = 0; $i < count($schedules); ++$i) {
            [$interval, $uncertainty, $type, $name] = $schedules[$i];

            if ($type == \Enums\SessionType::Invalid && ($i+1) < count($schedules)) continue; // do not care for intermediate break

            $content_line = \Core\UserManager::currentUser()->formatTimeNoSeconds($time) . " - $name";
            if (($i + 1) == count($schedules)) {
                $content .= "*$content_line*";
            } else if ($type == \Enums\SessionType::Race) {
                $content .= "**$content_line**";
            } else {
                $content .= $content_line;
            }


            if (($i + 1) < count($schedules))
                $content .= " *(" . \Core\UserManager::currentUser()->formatTimeInterval($interval) . ")*";
            $content .= "\n";
            $time->add($interval->toDateInterval());
        }
        $content .= "\n*" . _("Time Zone") . ": " . \Core\UserManager::currentUser()->getParam("UserTimezone") . "*\n";

        return $content;
    }


    /**
     * Inform about a manually started session
     * @param $ss The ServerSlot object of the started session
     * @param $t The Track object of the started session
     * @param $cc The CarClass object of the started session
     * @param $sp The ServerPreset object of the started session
     */
    public static function messageManualStart(\Core\ServerSlot $ss,
                                              \DbEntry\Track $t,
                                              \DbEntry\CarClass $cc,
                                              \DbEntry\ServerPreset $sp) {

        // get configuration
        $webhook_url = \Core\ACswui::getParam("DiscordManualWebhookUrl");
        if (strlen($webhook_url) == 0) return;
        $mention_group = \Core\ACswui::getParam("DiscordManualWebhookMention");

        // head message
        $content = "";
        if ($mention_group != "") $content .= "<@&$mention_group> ";
        $uname = \Core\UserManager::currentUser()->name();
        $content .= "**$uname**\n\n";

        // settings message
        $content .= "*" . _("Server Slot") . ": {$ss->name()}*\n";
        $content .= "*" . _("Server Preset") . ": {$sp->name()}*\n";
        $content .= "*" . _("Car Class") . ": {$cc->name()}*\n";
        $content .= "*" . _("Track") . ": {$t->name()}*\n";

        // schedule message
        $schedules = $sp->schedule($t, $cc);
        $content .= Discord::createContentSchedule($schedules);

        // add CM join link
        $port = $ss->parameterCollection()->child("AcServerPortsInetHttp")->value();
        $content .= "https://acstuff.ru/s/q:race/online/join?ip={$_SERVER['SERVER_ADDR']}&httpPort=$port\n";

        // send message
        Discord::sendWebhook($webhook_url, $content);
    }


    /**
     * Inform about a manually started session
     * @param $ss The ServerSlot object of the started session
     * @param $schd The \Compound\ScheduledItem object that has been started
     */
    public static function messageScheduleStart(\Core\ServerSlot $ss,
                                                \Compound\ScheduledItem $schd) {

        // get configuration
        $webhook_url = \Core\ACswui::getParam("DiscordScheduleWebhookUrl");
        if (strlen($webhook_url) == 0) return;
        $mention_group = \Core\ACswui::getParam("DiscordScheduleWebhookMention");

        // head message
        $content = "";
        if ($mention_group != "") $content .= "<@&$mention_group> ";

        // settings message
        $content .= $schd->discordMessage();

        // add CM join link
        $port = $ss->parameterCollection()->child("AcServerPortsInetHttp")->value();
        $content .= "https://acstuff.ru/s/q:race/online/join?ip={$_SERVER['SERVER_ADDR']}&httpPort=$port\n";

        // send message
        Discord::sendWebhook($webhook_url, $content);
    }


    //! Internal function to submit a message via webhook
    private static function sendWebhook($webhook_url, $message) {
        if (strlen($webhook_url) == 0) return;

        // create message json
        $json_array = array();
        $json_array['content'] = $message;
        $json_array['username'] = "ACswui";//\Core\UserManager::currentUser()->name();
        $json_post = json_encode($json_array);

        // send webhook request
        $curl = curl_init($webhook_url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json_post);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        $response = curl_exec($curl);
        curl_close($curl);

        if (strlen($response)) {
            \Core\Log::error($response);
        }
    }
}
