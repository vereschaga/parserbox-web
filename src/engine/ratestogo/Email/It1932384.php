<?php

namespace AwardWallet\Engine\ratestogo\Email;

class It1932384 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?ratestogo#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#ratestogo#i";
    public $reProvider = "#ratestogo#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "ratestogo/it-1932384.eml, ratestogo/it-1932385.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $node = re("#\n\s*Hotel confirmation number\s*:\s*([^\n]+)#");

                        return $node;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Hotel confirmation number')]/ancestor-or-self::ul/preceding-sibling::p[1]/strong");

                        return $node;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Date and time:')]/ancestor-or-self::td[1]");
                        $date = re("#\s*Check-in\s*:\s*([^\n]+)\s*\|#", $node);
                        $time = re("#\s*Hotel check-in/check-out\s*:([^\n]+)#", $node);
                        $time = trim($time);
                        $time = explode(" ", $time);
                        $date = uberDate($date);
                        $datetime = $date . " " . $time[0];

                        $datetime = \DateTime::createFromFormat("d F Y Hi", $datetime);
                        $datetime = $datetime->getTimestamp();

                        return $datetime;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Date and time:')]/ancestor-or-self::td[1]");
                        $date = re("#\s*Check-out\s*:\s*([^\n]+)\s*#", $node);
                        $time = re("#\s*Hotel check-in/check-out\s*:([^\n]+)#", $node);
                        $time = trim($time);
                        $time = explode(" ", $time);
                        $date = uberDate($date);
                        $datetime = $date . " " . $time[1];

                        $datetime = \DateTime::createFromFormat("d F Y Hi", $datetime);
                        $datetime = $datetime->getTimestamp();

                        return $datetime;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Hotel confirmation number')]/ancestor-or-self::ul/following-sibling::p[1]/a");

                        return $node;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Hotel confirmation number')]/ancestor-or-self::td[1]");
                        $node = re("#\s*Phone\s*:\s*([^\n]+)\s*\|#", $node);

                        return $node;
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Hotel confirmation number')]/ancestor-or-self::td[1]");
                        $node = re("#\s*Fax\s*:\s*([0-9-]+)\n*#", $node);

                        return $node;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Hotel reservations under\s*:\s*([^\n]+)#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Guest\(s\)\s*([0-9]+)#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Room\(s\)\s*:\s*([0-9]+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Trip cost')]/ancestor-or-self::td[1]");
                        $node = re("#([0-9.]+) avg/night#", $node);

                        return "$" . $node . " avg/night";
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $node = node("(//*[contains(text(), 'Cancellation')]/ancestor-or-self::p/following-sibling::ul)[1]");

                        return $node;
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $node = re("#\n\s*Room description\s*:\s*([^\n]+)#");
                        $node = explode("-", $node);

                        return [
                            'RoomType'            => $node[0],
                            'RoomTypeDescription' => $node[1],
                        ];
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $node = cell("Taxes and fees", +1);

                        return cost($node);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(cell("Total trip cost", +1), "Total");
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
}
