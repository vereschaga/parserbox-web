<?php

namespace AwardWallet\Engine\vayama\Email;

class It1 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s]*From:[^\n]*?vayama#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@vayama.com#i";
    public $reProvider = "#[@.]vayama.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // Parser toggled off as it is covered by emailTicketConfirmationChecker.php
                    return null;

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return node("//*[
							contains(text(), 'Your Airline Reservation Numbers:') or
							contains(text(), 'Your Airline Confirmation Codes:')
						]/ancestor-or-self::tr[1]/following-sibling::tr[1]/td[last()]");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $text = node("//*[contains(text(), 'Travelers')]/ancestor-or-self::tr[1]/following-sibling::tr[1]");
                        $names = [];

                        re("#\d+\s*\.\s*([^\d\(]+)\s*\(#", function ($m) use (&$names) {
                            $names[] = trim($m[1]);
                        }, $text);

                        return $names;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\n\s*[^\n]+\s*(?:operated\s+By\s+[^\n]+)*\s*Flight\s+\w{2}\d+)#i");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#Flight\s+\w{2}\s*(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re("#Depart\s+(\d+\-\w+\-\d+)#") . ', ' . ure("#(\d+:\d+\w)\b#") . 'm');
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re("#Arrive\s+(\d+\-\w+\-\d+)#") . ', ' . ure("#(\d+:\d+\w)\b#", 2) . 'm');
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#Flight\s+(\w{2})\s*\d+#");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Aircraft\s+([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Flight Time\s+[^\n\|]*?\s*\|\s*(\w+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#Flight Time\s+((?:\d+\w+\s)+)#"));
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Stops\s+([^\n]+)#") == 'nonstop' ? 0 : null;
                        },
                    ],
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
