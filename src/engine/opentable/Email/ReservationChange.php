<?php

namespace AwardWallet\Engine\opentable\Email;

class ReservationChange extends \TAccountCheckerExtended
{
    public $rePlain = "#The\s+OpenTable\s+Team\s*www\.OpenTable\.com#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#member_services@opentable\.com#i";
    public $reProvider = "#opentable\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "opentable/it-2038149.eml";
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
                        return re('#Confirmation\s+Number\s*:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "E";
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= 'You\'ve\s+successfully\s+changed\s+your\s+reservation\.\s+';
                        $regex .= '(.*)\s+will\s+be\s+ready\s+for\s+your\s+party\s+of\s+(\d+)\s+';
                        $regex .= 'at\s+(\d+:\d+\s*(?:am|pm))\s+on\s+\w+,\s+(\w+\s+\d+,\s+\d+)\.';
                        $regex .= '#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'Name'      => $m[1],
                                'Guests'    => $m[2],
                                'StartDate' => strtotime($m[4] . ', ' . $m[3]),
                            ];
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#To\s+get\s+there:\s+((?s).*)\s*Cross\s+Street:\s+.*\s*(.*)\s+To\s+see#i', $text, $m)) {
                            return [
                                'Address' => nice($m[1]),
                                'Phone'   => $m[2],
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
