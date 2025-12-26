<?php

namespace AwardWallet\Engine\rovia\Email;

class DreamTripsConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@rovia#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@rovia#i";
    public $reProvider = "#@rovia\.#i";
    public $xPath = "";
    public $mailFiles = "rovia/it-1829111.eml, rovia/it-1921415.eml";
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
                        return re("#Order ID Number:\s*([\w-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $info = node('//*[contains(text(), "Destinations/Beaches") or contains(., "Arts & Culture")]/following::li[1]');

                        $res = [];
                        $res['HotelName'] = re('/\s*at\s*the\s*(.+?),/i', $info);
                        $res['Address'] = re("/{$res['HotelName']}[,]\s*(.+?)[.]/", $info);
                        $res['Phone'] = re('/Phone[:]?\s*(.+?)[.]/i', $info);

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $dt = re('/Check in:\s*(.+)\s+/i');

                        return totime(uberDateTime($dt));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $dt = re('/Check out:\s*(.+)\s+/i');

                        return totime(uberDateTime($dt));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('/traveler\s*\d+[:]?\s*(.+)\s{2,}/i', $text, $ms)) {
                            return $ms[1];
                        }
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $n = re("#adults:\s*(\d+)#i");

                        if (strlen($n)) {
                            return intval($n);
                        }
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        $n = re("#children:\s*(\d+)#i");

                        if (strlen($n)) {
                            return intval($n);
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return preg_replace('#\s*Disclaimer:\s+.*#is', '', node('//tr[contains(., "Cancellation Policy")]/following-sibling::tr[1]'));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(text(), "Room 1")]/following::*[1]');
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(text(), "Destinations/Beaches") or contains(., "Arts & Culture")]/following::li[2]');
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $dt = node('//*[contains(text(), "Order ID Number")]/following::span[1]');

                        return totime(uberDateTime($dt));
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
