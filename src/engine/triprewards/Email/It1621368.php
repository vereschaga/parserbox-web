<?php

namespace AwardWallet\Engine\triprewards\Email;

class It1621368 extends \TAccountCheckerExtended
{
    public $reFrom = "#@wyn\.com#";
    public $reProvider = "";
    public $rePlain = "#wyndham#i";
    public $rePlainRange = "1000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $xPath = "";
    public $mailFiles = "";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return re("#Confirmation Number:\s*(\d+)#");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(text(), "Room Information")]/ancestor::table[3]/descendant::tr[1]/following-sibling::tr[2]/descendant::td[2]/following-sibling::td[4]/descendant::span[2]');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(node('//*[contains(text(), "Room Information")]/ancestor::tr[2]/following::tr[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::td[1]/following::td[1]'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(node('//*[contains(text(), "Room Information")]/ancestor::tr[2]/following::tr[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::td[1]/following::td[2]'));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = nodes('//*[contains(text(), "Room Information")]/ancestor::table[3]/descendant::tr[1]/following-sibling::tr[2]/descendant::td[2]/following-sibling::td[4]/descendant::span[1]/descendant::font[2]/text()');

                        if ($addr) {
                            $phone = $addr[count($addr) - 1];
                            unset($addr[count($addr) - 1]);

                            return ["Address" => implode($addr), "Phone" => $phone];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Guest Name')]/following::text()[2]");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $g = explode('/', node('//*[contains(text(), "Room Information")]/ancestor::tr[2]/following::tr[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::td[1]/following::td[4]'));

                        return ["Guests" => $g[0]];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(text(), "Room Information")]/ancestor::tr[2]/following::tr[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::td[1]/following::td[3]');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(text(), "CANCELLATION POLICY:")]/ancestor-or-self::span[1]/following::text()[1]');
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return node('//*[contains(text(), "Room Information")]/ancestor::tr[2]/following::tr[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::td[1]');
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("/Wyndham Rewards #:\s*([\w\d]+)/");
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
