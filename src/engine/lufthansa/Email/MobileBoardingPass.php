<?php

namespace AwardWallet\Engine\lufthansa\Email;

class MobileBoardingPass extends \TAccountCheckerExtended
{
    public $rePlain = "#(?:Lufthansa\s+Mobile\s+Boarding\s+Pass|Lufthansa\s+Mobile\s+Bordkarte)#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en, de";
    public $typesCount = "3";
    public $reFrom = "#boardingpass@mobile\.lufthansa\.com#i";
    public $reProvider = "#mobile\.lufthansa\.com#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "lufthansa/it-1608163.eml, lufthansa/it-1608178.eml, lufthansa/it-1619917.eml, lufthansa/it-1655750.eml, lufthansa/it-2211847.eml, lufthansa/it-2211849.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('plain');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $subj = orval(
                            node("//*[contains(text(), 'Buchungscode:') or contains(text(), 'Booking reference:')]/following-sibling::text()"),
                            re('#(?:Booking\s+reference|Buchungscode):\s+([\w\-]+)#'),
                            CONFNO_UNKNOWN
                        );

                        return $subj;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return array_filter([re('#Name:\s+(.*?)\n#')]);
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return array_filter([re('#Etix:\s+(\d{13})\n#')]);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $regex1 = '#';
                            $regex1 .= '(?:Flight|Flug):\s+';
                            $regex1 .= '\w{3}-\w{3},\s+';
                            $regex1 .= '(?P<AirlineName>\w+?)';
                            $regex1 .= '(?P<FlightNumber>\d+)\s+';
                            $regex1 .= '#';

                            $regex2 = '#';
                            $regex2 .= '(?:Flight-No|Flug-Nr):\s+';
                            $regex2 .= '(?P<AirlineName>\w+?)';
                            $regex2 .= '(?P<FlightNumber>\d+)\s+';
                            $regex2 .= '#';

                            $res = [];

                            if (preg_match($regex1, $text, $m) || preg_match($regex2, $text, $m)) {
                                copyArrayValues($res, $m, ['AirlineName', 'FlightNumber']);
                            }

                            if (preg_match('#Operated\s+by:\s+(.*)\s+Flight:#i', $text, $m)) {
                                $res['AirlineName'] = trim($m[1]);
                            }

                            return $res;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $regex = '#';
                            $regex .= '(?:Flight|Flug):\s+';
                            $regex .= '(?P<DepCode>\w{3})-';
                            $regex .= '(?P<ArrCode>\w{3})';
                            $regex .= '#';
                            $res = [];

                            if (preg_match($regex, $text, $m)) {
                                foreach (['Dep', 'Arr'] as $pref) {
                                    $res[$pref . 'Code'] = $m[$pref . 'Code'];
                                }
                            }

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $d = re('#(?:Date|Datum):\s+(\d+)([A-Z]+)#i');

                            if (!$d) {
                                return null;
                            }
                            $d = re(1);
                            $m = re(2);
                            $y = re('#(\d{4})#i', $this->parser->getHeader('date'));

                            if (!$y) {
                                return null;
                            }
                            $t = re('#Boarding:\s+(\d+:\d+\s*(?:[ap]m)?)#i');

                            if (!$t) {
                                return null;
                            }

                            return strtotime($d . ' ' . $m . ' ' . $y . ', ' . $t);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#(?:Class|Klasse):\s+(?P<Class>\w+)#');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('#(?:Seat|Sitz):\s+(\w+)\s+#');
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ["en", "de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
