<?php

namespace AwardWallet\Engine\opentable\Email;

class YourReservationConfirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#member_services@opentable\.com#i";
    public $reProvider = "#opentable\.com#i";
    public $rePlain = "#OpenTable Member Services#i";
    public $rePlainRange = "/1";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "opentable/it-5.eml, opentable/it-6.eml, opentable/it-1803702.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "ConfNo" => function ($text = '', $node = null, $it = null) {
                        return re("#Your confirmation number: ([0-9]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "E";
                    },

                    "Name" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#You're\s+(.*?)\s+at#"));
                    },

                    "StartDate" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//td[contains(., "Date:")]/following-sibling::td[1]//text()';
                        $subj = implode("\n", nodes($xpath));
                        $regex = '#';
                        $regex .= '(.*)\s+';
                        $regex .= '\w+,\s+(\w+\s+\d+,\s+\d+)\s+';
                        $regex .= '(\d+:\d+\s*(?:am|pm))\s+\|?\s*';
                        $regex .= '(\d+)';
                        $regex .= '#i';

                        if (preg_match($regex, $subj, $m)) {
                            return [
                                'DinerName' => $m[1],
                                'StartDate' => strtotime($m[2] . ', ' . $m[3]),
                                'Guests'    => $m[4],
                            ];
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= '(?:or\s+here\s+to\s+cancel.*|cancel\s+this\s+reservation.*|cancel\s+>)\s*';
                        $regex .= '\n\s*((?s).*?)\n\s*(.*)\s+';
                        $regex .= 'See\s+menus';
                        $regex .= '#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'Address' => nice($m[1], ','),
                                'Phone'   => nice($m[2]),
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
