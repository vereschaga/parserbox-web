<?php

namespace AwardWallet\Engine\ichotelsgroup\Email;

class YourInterContinentalReservationArrivalDetailReconfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:.*?(?:ichotelsgroup|InterContinental).*?Subject#is";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your InterContinental \(R\) Reservation arrival detail reconfirmation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#ichotelsgroup#i";
    public $reProvider = "#ichotelsgroup#i";
    public $xPath = "";
    public $mailFiles = "ichotelsgroup/it-1898588.eml";
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
                        return re('#Confirmation\s*(?:Number|\#)\s*:?\s*([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= 'Reservation\s+Department\s+';
                        $regex .= '(.*)\s+';
                        $regex .= '((?s).*?)\s+';
                        $regex .= 'Room Reservation Direct Call Number:\s+(.*)\s+';
                        $regex .= 'Fax:\s+(.*)';
                        $regex .= '#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                                'Phone'     => $m[3],
                                'Fax'       => $m[4],
                            ];
                        } else {
                            $regex = "#Reservationss?\s+Department\s+(.+)\n([^\n]+\n[^\n]+\n[^\n]+)\s+Phone\s+([\d\+\s]+)\s+Fax\s+([\d\+\s]+)#is";

                            if (preg_match($regex, $text, $m)) {
                                return [
                                    'HotelName' => nice($m[1], ','),
                                    'Address'   => nice($m[2], ','),
                                    'Phone'     => preg_replace("#\s+#", ' ', $m[3]),
                                    'Fax'       => preg_replace("#\s+#", ' ', $m[4]),
                                ];
                            }
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Arrival\s+Date\s*:?\s*(.*)#i'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Departure\s+Date\s*:?\s*(.*)#i'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Guest\s+Name\s*:?\s*(.*)#i')];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Number|No\.)\s+of\s+persons?\s+:?\s+.*(\d+)\s+Adults?#i');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Number|No\.)\s+of\s+persons?\s+:?\s+.*(\d+)\s+Child?#i');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Room\s+Type\s*:?\s*(.*?)\s+(?:For\s+your\s+convenient|Room\s+Rate)#is'));
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Room\s+Rate\s*:?\s*(.*?)(?:Guarantee)#is'));
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#CANCELLATION\s+POLICY\s+(.+?)\n\n#is'));
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
