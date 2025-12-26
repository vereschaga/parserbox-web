<?php

namespace AwardWallet\Engine\hiltongvc\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Hilton\s+Grand\s+Vacations#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#noreply@hgvc\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]hiltongvc#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "16.04.2015, 18:47";
    public $crDate = "16.04.2015, 18:41";
    public $xPath = "";
    public $mailFiles = "hiltongvc/it-2622857.eml";
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
                        return re('#Confirmation\s+Number\s*:\s+([\w\-]+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $r = '#VACATION\s+INFORMATION\s*:\s+(.*)\s*\n\s*((?s).*?)\s*\n\s*(.*)\s+Vacation\s+Package#i';

                        if (preg_match($r, $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2]),
                                'Phone'     => $m[3],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Arrival\s+Date\s*:\s+(.*)#'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Departure\s+Date\s*:\s+(.*)#'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Name\s*:\s+(.*)#'));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $r = '#Total\s+Vacation\s+Price\s*:\s+(.*?)\s+\(inc\.\s+(.*?)\s+tax\)#i';

                        if (preg_match($r, $text, $m)) {
                            return [
                                'Total'    => cost($m[1]),
                                'Currency' => currency($m[1]),
                                'Taxes'    => cost($m[2]),
                            ];
                        }
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
