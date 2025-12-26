<?php

namespace AwardWallet\Engine\lufthansa\Email;

class TravelInformation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#(?:Travel\s+information\s+for\syour\s+flight|Reise-Informationen\s+für\s+Ihren\s+Flug).*?Lufthansa#si', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#lufthansa#i', 'us', ''],
    ];
    public $reProvider = [
        ['#lufthansa#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en, de";
    public $typesCount = "2";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "13.04.2015, 12:59";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "lufthansa/it-1608165.eml, lufthansa/it-1615962.eml, lufthansa/it-1615968.eml, lufthansa/it-1626918.eml, lufthansa/it-1655790.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = re('#\d{4}#i', $this->parser->getHeader('date'));

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Booking\s+Code|Buchungscode):\s+([\w\-]+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [nice(re('#(?:Dear|Sehr\s+geehrter)\s+(.*?),#'))];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//tr[(contains(., 'Departure') or contains(., 'Abflug')) and (contains(., 'Arrival') or contains(., 'Ankunft'))]/following-sibling::tr[contains(., ':')]";

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#^(\w+)\s+(\d+)(?:\s+(?:operated\s+by:\s*)?(.*))?$#', node('./td[4]'), $m)) {
                                if (isset($m[3])) {
                                    $res['Operator'] = $m[3];
                                }
                                $res['AirlineName'] = $m[1];
                                $res['FlightNumber'] = $m[2];

                                return $res;
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            if (!$this->year) {
                                $dateStr = '';
                            } else {
                                $dateStr = re('#\d+\.\d+#', node('./td[1]'));
                                $dateStr .= '.' . $this->year;
                            }
                            $res = [];

                            foreach (['Dep' => 2, 'Arr' => 3] as $key => $value) {
                                $regex = '#';
                                $regex .= '(?P<' . $key . 'Date>\d+:\d+)(?P<Shift>\+\d)?\s+';
                                $regex .= '(?P<' . $key . 'Name>.*)';
                                $regex .= '#';

                                if (preg_match($regex, node('./td[' . $value . ']'), $m)) {
                                    if ($dateStr) {
                                        $m[$key . 'Date'] = strtotime($dateStr . ', ' . $m[$key . 'Date']);
                                    }

                                    if (isset($m['Shift']) && $m['Shift']) {
                                        $m[$key . 'Date'] = strtotime($m['Shift'] . ' day', $m[$key . 'Date']);
                                    }
                                    copyArrayValues($res, $m, [$key . 'Name', $key . 'Date']);
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[5]');

                            if (strlen($subj) == 1) {
                                return ['BookingClass' => $subj];
                            } else {
                                return ['Cabin' => $subj];
                            }
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
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
