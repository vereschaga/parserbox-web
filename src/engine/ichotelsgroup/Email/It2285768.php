<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

class It2285768 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]ihg[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "#[@.]ihg[.]com#i";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]ihg[.]com#i";
    public $reProvider = "#[@.]ihg[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "23.12.2014, 09:30";
    public $crDate = "23.12.2014, 09:15";
    public $xPath = "";
    public $mailFiles = "ichotelsgroup/it-2285768.eml";
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
                        return CONFNO_UNKNOWN;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = nice(node("//*[contains(text(), 'Add to Favorites')]/preceding::a[1]"));
                        $addr = between($name, 'Hotel Front Desk:');

                        return [
                            'HotelName' => $name,
                            'Address'   => $addr,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('Check-In Date: (.+? \d{4})');

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('Check-Out Date: (.+? \d{4})');

                        return totime($date);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return reni('Hotel Front Desk: ([\d-]+)');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(text(), 'Guest Information')]/following::address[1]/text()[1]");

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return reni('(\d+) Adults');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return reni('(\d+) Children');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return reni('Number of Rooms: (\d+)');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[contains(text(), 'Average Nightly')]/following::*[1]"));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[contains(text(), 'Room Type:')]/following::*[1]"));
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[contains(text(), 'Price for')]/following::*[1]");

                        return cost($x);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[contains(text(), 'Total Tax')]/following::*[1]");

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[contains(text(), 'Estimated Total')]/following::*[1]");

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
