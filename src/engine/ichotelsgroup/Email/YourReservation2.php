<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

class YourReservation2 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?ichotelsgroup#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#icbali\.reservation@ihg\.com#i";
    public $reProvider = "#ihg\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "ichotelsgroup/it-2208935.eml, ichotelsgroup/it-2212628.eml";
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
                        return re('#Confirmation\s*\#\s*:\s*\#?\s*([\w\-]+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $res['HotelName'] = re('#Warmest\s+greeting\s+from\s+(.*?),#');
                        $hn = preg_replace('#\s+#i', '\s+', $res['HotelName']);

                        if (preg_match('#Reservation\s+Department\s+' . $hn . '\s+((?s).*?)\s+Telp\.\s+([\+\d\s\-]+)\s+Fax\.\s+([\+\d\s\-]+)#i', $text, $m)) {
                            $res['Address'] = nice($m[1]);
                            $res['Phone'] = nice($m[2]);
                            $res['Fax'] = nice($m[3]);
                        }

                        return $res;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Arrival\s+Date\s*:\s+(.*)#'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Departure\s+Date\s*:\s+(.*)#'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re('#Guest\s+Name\s+:\s+(.*)#');
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#Number\s+of\s+Person\s*:\s+(\d+)\s+Adults#i');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $r = re('#Room\s+Type\s*:\s+(.*)#');

                        if (preg_match('#^(\d+)(?:\s*x\s+|\s+)(.*)#i', $r, $m)) {
                            return [
                                'Rooms'    => (int) $m[1],
                                'RoomType' => $m[2],
                            ];
                        }
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re('#IHG\s+Rewards\s+Club\s+\#\s+.*\#([\w\-]+)#');
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
