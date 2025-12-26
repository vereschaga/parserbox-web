<?php

namespace AwardWallet\Engine\kimpton\Email;

class It1607218 extends \TAccountCheckerExtended
{
    public $rePlain = "#Kimpton\s+Hotels\s+&\s+Restaurants#i";
    public $rePlainRange = "-1000";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]kimptonhotels[.]com#i";
    public $reProvider = "#[@.]kimptonhotels[.]com#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "kimpton/it-1607218.eml";
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
                        return re("# Your Confirmation Number:\s*([^\s]*)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Hotel name:')]/ancestor-or-self::td[1]/following-sibling::td[1]");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(node("//*[contains(text(), 'Your Arrival Date:')]/ancestor-or-self::td[1]/following-sibling::td[1]"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(node("//*[contains(text(), 'Your Departure Date:')]/ancestor-or-self::td[1]/following-sibling::td[1]"));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Hotel address:')]/ancestor-or-self::td[1]/following-sibling::td[1]");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('([\d-]+) ph \s+');

                        return nice($x);
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('([\d-]+) fax \s+');

                        return nice($x);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Guest Name')]/ancestor-or-self::td[1]/following-sibling::td[1]");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Adults/Children:')]/ancestor-or-self::td[1]/following-sibling::td[1]", null, false, "/(.*)\s*\//");
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Adults/Children:')]/ancestor-or-self::td[1]/following-sibling::td[1]", null, false, "/\/\s*(\d*)/");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Rooms:')]/ancestor-or-self::td[1]/following-sibling::td[1]");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('nightly rate:  (\w+ [\d.,]+)');

                        return nice($x);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Policy Information:', +1);

                        return nice($x);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Your room type:')]/ancestor-or-self::td[1]/following-sibling::td[1]");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('total charges:  (\w+ [\d.,]+)');

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
