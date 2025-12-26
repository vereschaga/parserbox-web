<?php

namespace AwardWallet\Engine\lufthansa\Email;

class Confirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Thank\s+you\s+for\s+your\s+online\s+booking|Gracias\s+por\s+utilizar\s+el\s+servicio\s+online\s+de\s+reservas#i', 'us', '1000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#noreply@milesandmore\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#milesandmore\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en, es";
    public $typesCount = "2";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "13.04.2015, 13:21";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "lufthansa/it-1665834.eml, lufthansa/it-1665929.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = re('#\d{4}#i', $this->parser->getHeader('date'));
                    $xpath = "//text()[contains(., 'Preferencias de los pasajeros')]/ancestor::tr[1]/following-sibling::tr[position() > 1]";
                    $preferencesNodes = $this->http->XPath->query($xpath);
                    $this->seats = [];
                    $this->meal = [];

                    foreach ($preferencesNodes as $n) {
                        $flight = re('#\d+#', node('./td[2]', $n));
                        $this->seats[$flight][] = node('./td[3]', $n);
                        $this->meal[$flight][] = node('./td[4]', $n);
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Your\s+Booking\s+Code|Su\s+código\s+de\s+reserva):\s+([\w\-]+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#(?:Passenger|Pasajero)\s+\d+\s+(.*)#', $text, $m)) {
                            return $m[1];
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//tr[contains(., 'Departure') and contains(., 'Arrival') or contains(., 'Salida') and contains(., 'Llegada')]/following-sibling::tr[1]";

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $this->currentFlightNumber = null;
                            $subj = node('./td[4]');

                            if (preg_match('#(\w+?)(\d+)#', $subj, $m)) {
                                $this->currentFlightNumber = $m[2];

                                return ['AirlineName' => $m[1], 'FlightNumber' => $m[2]];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $dateStr = node('./td[1]');

                            if ($this->year and preg_match('#(\d+)\s+(\w+)#', $dateStr, $m)) {
                                $dateStr = $m[1] . ' ' . en($m[2]) . ' ' . $this->year;
                            }

                            foreach (['Dep' => 2, 'Arr' => 3] as $key => $value) {
                                $subj = node('./td[' . $value . ']');
                                $regex = '#';
                                $regex .= '(?P<Time>\d+:\d+)\s+';
                                $regex .= '(?P<DayShift>[+\-]\d+\s+)?';
                                $regex .= '(?P<Name>.*)';
                                $regex .= '#';

                                if (preg_match($regex, $subj, $m)) {
                                    $res[$key . 'Name'] = $m['Name'];

                                    if ($dateStr) {
                                        $datetimeStr = $dateStr;

                                        if (isset($m['DayShift']) && $m['DayShift']) {
                                            $datetimeStr .= ' ' . $m['DayShift'] . ' day';
                                        }
                                        $datetimeStr .= ', ' . $m['Time'];
                                        $res[$key . 'Date'] = strtotime($datetimeStr);
                                    }
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node('./td[6]');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->seats[$this->currentFlightNumber]) && $this->seats[$this->currentFlightNumber]) {
                                return implode(', ', $this->seats[$this->currentFlightNumber]);
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return node('./td[5]');
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->meal[$this->currentFlightNumber]) && $this->meal[$this->currentFlightNumber]) {
                                return implode(', ', $this->meal[$this->currentFlightNumber]);
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
        return ["en", "es"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
