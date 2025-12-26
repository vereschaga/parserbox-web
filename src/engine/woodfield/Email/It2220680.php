<?php

namespace AwardWallet\Engine\woodfield\Email;

// parsers with similar formats: gcampaigns/HResConfirmation (object), marriott/ReservationConfirmation (object), marriott/It2506177, mirage/It1591085, triprewards/It3520762, goldpassport/WelcomeTo

class It2220680 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?laquinta|The\s+La\s+Quinta\s+Resort#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#woodfield#i";
    public $reProvider = "#woodfield#i";
    public $caseReference = "8276";
    public $isAggregator = "0";
    public $fnDateFormat = "";
    public $xPath = "";
    public $mailFiles = "woodfield/it-2220680.eml";
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
                        return cell("Hotel Confirmation", +1, 0);
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[contains(text(), 'www.laquintaresort.com')]/ancestor-or-self::p[1]"));

                        return [
                            'HotelName' => detach("#^[^\n]+#", $text),
                            'Phone'     => detach("#\n\s*([\d\(\)+\-.]{5,})\s*[^\n]*?$#", $text),
                            'Address'   => nice($text),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell("Arrival Date", +1, 0));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell("Departure Date", +1, 0));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return cell("Reservation Name", +1, 0);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell("Number of Guests", +1, 0);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell("Cancel Policy", +1, 0);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return cell("Room Type", +1, 0);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Total Charge", +1, 0));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell("Date Booked", +1, 0));
                    },
                ],
            ],

            "functions" => [
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
