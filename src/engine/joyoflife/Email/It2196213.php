<?php

namespace AwardWallet\Engine\joyoflife\Email;

class It2196213 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]jdvhotels[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]jdvhotels[.]com#i";
    public $reProvider = "#[@.]jdvhotels[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "joyoflife/it-2196213.eml";
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
                        return re_white('Confirmation \#  (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return between('Thanks for choosing', 'We re');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Check In  (\d+ \/ \d+ \/ \d+)');

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Check Out  (\d+ \/ \d+ \/ \d+)');

                        return totime($date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = node("//*[contains(@alt, 'phone')]/preceding::h5[1]");

                        return nice($addr);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[contains(@alt, 'phone')]/following::*[1]");

                        return nice($x);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('Dear (.+?),');

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('Number of Adults  (\d+)');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return between('Cancellation Policy:', 'Joyride');
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Total Taxes  (.[\d.,]+)');

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Total Charges with Tax  (.[\d.,]+)');

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
