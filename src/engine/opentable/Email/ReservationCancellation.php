<?php

namespace AwardWallet\Engine\opentable\Email;

class ReservationCancellation extends \TAccountCheckerExtended
{
    public $rePlain = "#You've\s+successfully\s+canceled\s+your\s+reservation.*?The\s+OpenTable\s+Team#is";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#member_services@opentable\.com#i";
    public $reProvider = "#opentable#i";
    public $xPath = "";
    public $mailFiles = "opentable/it-1867683.eml";
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
                        return re('#confirmation\s+number\s+of\s+the\s+original\s+reservation:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "E";
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= 'You\'ve\s+successfully\s+(canceled)\s+your\s+reservation\s+';
                        $regex .= 'for\s+a\s+(party)\s+';
                        $regex .= 'of\s+(\d+)\s+';
                        $regex .= 'at\s+(.*?)\s+';
                        $regex .= 'on\s+\w+,\s+(\w+\s+\d+,\s+\d+)\s+at\s+(\d+:\d+\s*(?:am|pm)?)';
                        $regex .= '#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'Status'    => $m[1],
                                'Cancelled' => true,
                                'Name'      => $m[2],
                                'Guests'    => $m[3],
                                'Address'   => $m[4],
                                'StartDate' => strtotime($m[5] . ', ' . $m[6]),
                            ];
                        }
                    },

                    "DinerName" => function ($text = '', $node = null, $it = null) {
                        return re('#Dear\s+(.*),#');
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
