<?php

namespace AwardWallet\Engine\iberia\Email;

class It2012082 extends \TAccountCheckerExtended
{
    public $rePlain = "#CHECKIN\s+ONLINEs+CONFIRMATION#i";
    public $rePlainRange = "5000";
    public $reHtml = "#CHECKIN\s+ONLINEs+CONFIRMATION#";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@.*\biberia\b#i";
    public $reProvider = "#iberia#i";
    public $caseReference = "6833";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "iberia/it-2051439.eml, iberia/it-2051440.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $subj = $this->parser->getHeader('subject');

                        return orval(
                            re_white('Iberia Boarding Pass (\w+)', $subj),
                            re("#\n\s*Número de vuelo\s*:\s*([A-Z\d\-]+)#")
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//b[contains(text(), '.') and contains(text(), '/')]");
                        $ppl = array_map(function ($x) { return clear('/\d+[.]\s*/', $x); }, $ppl);

                        return $ppl;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Departure')]/ancestor::table[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re_white('Número de vuelo: (\w+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return between('De:', 'Departure:');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dt = between('Departure:', 'A:');
                            $dt = uberDateTime($dt);

                            return strtotime($dt, $this->date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return between('A:', 'Arrival:');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $dt = between('Arrival:', 'Seat:');
                            $dt = uberDateTime($dt);

                            return strtotime($dt, $this->date);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return between('Group:', 'De:');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return between('Seat:', 'Terminal:');
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
