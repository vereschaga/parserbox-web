<?php

namespace AwardWallet\Engine\hertz\Email;

class It2294355 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?hertz#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#hertz#i";
    public $reProvider = "#hertz#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "28.12.2014, 00:16";
    public $crDate = "25.12.2014, 13:48";
    public $xPath = "";
    public $mailFiles = "hertz/it-1700066.eml, hertz/it-1988922.eml, hertz/it-1988949.eml, hertz/it-2294349.eml, hertz/it-2294354.eml, hertz/it-2294355.eml";
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
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Confirmation of Cancelled Reservation \(([A-Z\d-]+)\)#ix", $this->parser->getSubject()),
                            CONFNO_UNKNOWN
                        );
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return [
                            'CarModel'       => re("#You booked the (?:car )?(.*?) at ([^\n\[]+)#ix"),
                            'PickupLocation' => nice(re(2)),
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        //return totime(uberDateTime(re("#(?:\]|\n)\s*from\s+([^\n]+)#")));
                        return orval(
                            totime(uberDateTime(re("#has\s+been\s+changed\s+at\s+your\s+request\s+to,\s*(.*?)\s+to\s+.*?\.#is"))),
                            totime(uberDateTime(re("#You\s+booked\s+the\s+.*?\s+from\s+(.*?)\s+to\s+.*?\.#is")))
                        );
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return $it["PickupLocation"];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        //return totime(uberDateTime(re("#(?:request|\n|\b[A-Z]{3}\b)\s*to[,\s]+([^\n]+)#")));
                        return orval(
                            totime(uberDateTime(re("#has\s+been\s+changed\s+at\s+your\s+request\s+to,\s*.*?\s+to\s+(.*?)\.#is"))),
                            totime(uberDateTime(re("#You\s+booked\s+the\s+.*?\s+from\s+.*?\s+to\s+(.*?)\.#is")))
                        );
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Thank you for choosing ([^\n,.!]*?)\s*[.!]#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:^|\n)\s*(?:Hello|Hi)\s+([^\n,]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Confirmation of (\w+) Reservation#ix", $this->parser->getSubject()),
                            re("#we have (\w+) the following#ix"),
                            re("#reservation has been (\w+)#")
                        );
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#we have cancel+ed the following#ix") ? true : false,
                            re("#Cancel+ed Reservation#ix", $this->parser->getSubject()) ? true : false
                        );
                    },

                    "Fees" => function ($text = '', $node = null, $it = null) {
                        $fees = [];
                        re("#([^.]*?)\s+Fee\s+is\s+([^\d]+\d+)[.,]#", function ($m) use (&$fees) {
                            $fees[] = [
                                'Name'   => nice($m[1]),
                                'Charge' => nice($m[2]),
                            ];
                        }, $text);

                        return $fees;
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
