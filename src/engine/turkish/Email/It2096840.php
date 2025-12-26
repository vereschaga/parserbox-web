<?php

namespace AwardWallet\Engine\turkish\Email;

class It2096840 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?turkish#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#turkish#i";
    public $reProvider = "#turkish#i";
    public $caseReference = "6922";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "turkish/it-2096840.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->setDocument("xpath", "(//*[contains(text(), 'Your reservation code')]/ancestor::table[3])[1]");

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your reservation code \(PNR\)\s*:\s*([A-Z\d\-]+)#x");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return array_unique(nodes("//*[contains(text(), 'Seat Number')]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>1]/td[1]"));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(cell("Process Date:", +1, 0));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Seat Number')]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>1]/td[3]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#^([A-Z\d]{2}\s*\d+)#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*([A-Z]{3})\s*\-#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(re("#\-\s*([^\n-]*?)\s*\-\s*(\d+:\d+)#") . ',' . re(2));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*[A-Z]{3}.*?\n\s*([A-Z]{3})\s*\-#ims");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node("following-sibling::td[1]");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return node("preceding-sibling::td[1]");
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
