<?php

namespace AwardWallet\Engine\goldcrown\Email;

class It2498185 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]bestwestern[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]bestwestern[.]#', 'blank', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "26.05.2015, 09:07";
    public $crDate = "26.05.2015, 08:55";
    public $xPath = "";
    public $mailFiles = "goldcrown/it-2498185.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('Confirmation number : (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Getting to the hotel')]/preceding::span[2]");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Check-in : (.+?) Check-out :');

                        return strtotime(uberDateTime($date));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Check-out : (.+?) Room information :');

                        return strtotime(uberDateTime($date));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Getting to the hotel')]/preceding::span[1]");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return reni('Phone : ([()\d\s]+)');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[contains(text(), 'Named Guest:')]/following::span[1]"));
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return reni('Occupancy : (\d+)');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[contains(text(), 'Cancellation policy:')]/following::span[1]"));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[contains(text(), 'Room type:')]/following::span[1]"));
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Reservation Amount : ([^\s].+?) \n');

                        return cost($x);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Taxes & Fees : ([^\s].+?) \n');

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Total Stay : ([^\s].+?) \n');

                        return total($x, 'Total');
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
