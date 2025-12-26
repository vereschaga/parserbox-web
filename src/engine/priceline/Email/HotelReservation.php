<?php

namespace AwardWallet\Engine\priceline\Email;

class HotelReservation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#You\s+will\s+need\s+this\s+information\s*Priceline\s+trip\s+number#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]priceline#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]priceline#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "26.05.2015, 11:18";
    public $crDate = "26.05.2015, 10:59";
    public $xPath = "";
    public $mailFiles = "priceline/it-2757237.eml";
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
                        return re('#Hotel\s+confirmation\s+number:\s*(\w+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node('//a[contains(@pcln-gac, "Hotel_Title_Desktop")]');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['CheckIn' => 'Check-in:', 'CheckOut' => 'Check-out:'] as $key => $value) {
                            $s = node('//*[normalize-space(.) = "' . $value . '"]/following-sibling::*[1]');
                            $r = '#(\w+\s+\d+,\s+\d+).*' . ($key == 'CheckIn' ? '?' : '') . '\s+(\d+:\d+\s*[ap]m)#i';

                            if (preg_match($r, $s, $m)) {
                                $res[$key . 'Date'] = strtotime($m[1] . ', ' . $m[2]);
                            }
                        }

                        return $res;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Address:\s*(.*?)\s*Get\s+directions#'));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re('#Phone\s+number:\s*([\d\-]+)#');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re('#Reservation\s+name:\s+(.*?)\s*Hotel\s+confirm#');
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#Number\s+of\s+rooms:\s+(\d+)\s+room#');
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+price:\s+(.*?)\s+Number\s+of#');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node('//text()[normalize-space(.) = "Cancellation policy"]/ancestor::div[1]/following-sibling::div[1]');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re('#Standard Non Smoking Room-1 King Bed Free#');
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Room\s+subtotal:\s+(.*)\s+Taxes#'));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Taxes\s+&\s+fees:\s+(.*)\s+Total#'));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Total\s+charged:\s+(.*)#'));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re('#Prices\s+are\s+in\s+(\S+)#'));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Purchase\s+date:\s+(\w+\s+\d+,\s+\d+)#'));
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
