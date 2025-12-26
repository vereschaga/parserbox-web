<?php

namespace AwardWallet\Engine\singaporeair\Email;

class SSHBookingConfirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#booking@singaporeair\.com\.sg#i";
    public $reProvider = "#singaporeair\.com\.sg#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?singaporeair#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Singapore\s+Stopover\s+Holiday\s+Booking\s+Confirmation#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "singaporeair/it-1495772.eml";
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
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#BOOKING\s+REFERENCE:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $res['HotelName'] = re('#Hotel\s*:\s*(.*)#i');
                        $res['Address'] = $res['HotelName'];

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-in\s+date\s*:\s*(.*)#'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-out\s+date\s*:\s*(.*)#'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Passenger\(s\)\s*:\s*(.*)\n\s*Hotel#si');

                        if ($subj and preg_match_all('#\n\s*(.*)#i', "\n" . $subj, $m)) {
                            return array_filter($m[1]);
                        }
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#(.*)\s+has\s+been\s+charged\s+to#i'), 'Total');
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
