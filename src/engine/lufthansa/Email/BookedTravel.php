<?php

namespace AwardWallet\Engine\lufthansa\Email;

class BookedTravel extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Ihre\s+gebuchte\s+Reise#i', 'us', ''],
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
    public $upDate = "13.04.2015, 12:34";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "lufthansa/it-1649582.eml";
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
                        $xpath = "//text()[contains(., 'Seat:') or contains(., 'Sitz:')]/ancestor::tr[1]";
                        $preferencesNodes = $this->http->XPath->query($xpath);
                        $this->seats = [];
                        $this->meal = [];

                        foreach ($preferencesNodes as $pn) {
                            $xpath = "//text()[contains(., 'Seat:') or contains(., 'Sitz:')]/ancestor::td[1]/following-sibling::td[following-sibling::td[.//img[contains(@src, 'meal')]]]";
                            $i = 0;
                            $seatNodes = nodes($xpath, $pn);

                            foreach ($seatNodes as $sn) {
                                $this->seats[$i][] = $sn;
                                $i++;
                            }
                            $xpath = "//text()[contains(., 'Seat:') or contains(., 'Sitz:')]/ancestor::td[1]/following-sibling::td[preceding-sibling::td[.//img[contains(@src, 'meal')]]][1]";
                            $this->meal[] = node($xpath);
                        }

                        $this->airlineName = re('#(?:This\s+flight\s+will\s+be\s+operated\s+by|Dieser\s+Flug\s+wird\s+von)\s+(.*?)(\s+durchgeführt)?\.#');

                        $this->airportCodes = [];

                        if (preg_match('#\s*(.*)\s+\((\w+)\)\s+to\s+(.*)\s+\((\w+)\)#', $text, $m)) {
                            $this->airportCodes[nice($m[1])] = nice($m[2]);
                            $this->airportCodes[nice($m[3])] = nice($m[4]);
                        }

                        return re('#(?:Booking\s+code|Buchungscode)\s+([\w\-]+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#(?:Passenger|Reisender)\s+\d+\s+-\s+(.*)#', $text, $m)) {
                            return $m[1];
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Departure') or contains(., 'Abflug')]/ancestor::tr[1][contains(., 'Arrival') or contains(., 'Ankunft')]/following-sibling::tr[contains(., ':')]";

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->currentSegmentIndex)) {
                                $this->currentSegmentIndex++;
                            } else {
                                $this->currentSegmentIndex = 0;
                            }
                            $subj = node('./td[7]');

                            if (preg_match('#(\w+?)(\d+)#', $subj, $m)) {
                                return ['FlightNumber' => $m[2], 'AirlineName' => $m[1]];
                            }
                        },

                        "DepCode"                                     => function ($text = '', $node = null, $it = null) {
                            foreach (['Dep' => 4, 'Arr' => 6] as $key => $value) {
                                $res[$key . 'Name'] = node('./td[' . $value . ']');

                                if (isset($this->airportCodes[$res[$key . 'Name']])) {
                                    $res[$key . 'Code'] = $this->airportCodes[$res[$key . 'Name']];
                                } else {
                                    $res[$key . 'Code'] = TRIP_CODE_UNKNOWN;
                                }
                            }

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $dateStr = '';

                            if (!$this->year) {
                                return null;
                            }

                            if (preg_match('#(\d+)\s+(\w+)#', node('./td[2]'), $m)) {
                                $dateStr = $m[1] . ' ' . en($m[2]) . ' ' . $this->year;
                            }

                            foreach (['Dep' => 3, 'Arr' => 5] as $key => $value) {
                                $timeStr = node('./td[' . $value . ']');
                                $res[$key . 'Date'] = strtotime($dateStr . ', ' . $timeStr);

                                if ($dayShift = re('#\s+(\+\d+)#', $timeStr)) {
                                    $res[$key . 'Date'] = strtotime($dayShift . ' day', $res[$key . 'Date']);
                                }
                            }

                            return $res;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            if ($this->airlineName) {
                                return $this->airlineName;
                            }
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node('./td[8]');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->seats[$this->currentSegmentIndex]) && !empty($this->seats[$this->currentSegmentIndex])) {
                                return join(', ', $this->seats[$this->currentSegmentIndex]);
                            }
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->meal) && !empty($this->meal)) {
                                return join(', ', $this->meal);
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
