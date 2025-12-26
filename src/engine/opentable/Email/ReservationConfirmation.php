<?php

namespace AwardWallet\Engine\opentable\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#opentable#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#opentable#i";
    public $reProvider = "#opentable#i";
    public $xPath = "";
    public $mailFiles = "opentable/it-1883569.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfNo" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s*\#\s*([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "E";
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= 'Your\s+Reservation\s+is\s+(Confirmed)\s+.*\s+';
                        $regex .= 'on\s+\w+,\s+(\w+\s+\d+,\s+\d+)\s+at\s+(\d+:\d+\s*(?:am|pm)?)\s+';
                        $regex .= 'for\s+(\d+)';
                        $regex .= '#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'Status'    => $m[1],
                                'StartDate' => strtotime($m[2] . ', ' . $m[3]),
                                'Guests'    => $m[4],
                            ];
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Restaurant\s+Info:\s+((?s).*?)\n\s*([\d \-\(\)]+)\s+Special\s+Messages#';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'Address' => nice($m[1], ','),
                                'Phone'   => $m[2],
                            ];
                        }
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Upon\s+(dining),\s+you\s+will\s+receive\s+(.*)\.#', $text, $m)) {
                            return [
                                'Name'         => $m[1],
                                'EarnedAwards' => $m[2],
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
}
