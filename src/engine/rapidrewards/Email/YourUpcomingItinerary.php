<?php

namespace AwardWallet\Engine\rapidrewards\Email;

class YourUpcomingItinerary extends \TAccountCheckerExtended
{
    public $rePlain = "#Your\s+friends\s+at\s+Southwest\s+Airlines#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#SouthwestAirlines@luv\.southwest\.com#i";
    public $reProvider = "#southwest\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "rapidrewards/it-2140806.eml, rapidrewards/it-2141704.eml, rapidrewards/it-2168682.eml, rapidrewards/it-3.eml, rapidrewards/it-4.eml, rapidrewards/it-5.eml, rapidrewards/it-6.eml";
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
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Confirmation\s+Number:\s+([\w\-]+)#i'),
                            CONFNO_UNKNOWN
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes('//tr[normalize-space(.) = "Passenger(s)"]/following-sibling::tr[string-length(normalize-space(.)) > 1 and following-sibling::tr[contains(., "Flight Information")]]');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $reservations = xpath('//tr[contains(., "Flight") and contains(., "Flight Information") and not(.//tr)]/following-sibling::tr[string-length(normalize-space(.)) > 1]');

                        if ($reservations->length == 0) {
                            $this->reservationRegexp = '#(\d+)\s+(\w+\s+\d+)?\s+Depart\s+(.*?)\s+at\s+(\d+:\d+\s+(?:am|pm))\s+Arrive\s+in\s+(.*?)\s+at\s+(\d+:\d+\s+(?:am|pm))#i';

                            if (preg_match_all($this->reservationRegexp, $text, $m)) {
                                $reservations = $m[0];
                            } else {
                                $reservations = null;
                            }
                        }

                        return $reservations;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return !isset($this->reservationRegexp) ? re('#^\s*\d+\s*$#', node('./td[1]')) : re('#^(\d+)#i');
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            if (strpos($this->text(), "Your friends at Southwest Airlines") !== false) {
                                return 'WN';
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $year = re('#\d{4}#i', $this->parser->getHeader('date'));

                            if (!$year) {
                                return null;
                            }
                            $dateStr = !isset($this->reservationRegexp) ? re('#^\w+\s+\d+$#i', node('./td[2]')) : re('#\d+\s+(\w+\s+\d+)\s+Depar#i');

                            if (!$dateStr and isset($this->prevDateStr)) {
                                $dateStr = $this->prevDateStr;
                            }

                            if (!$dateStr) {
                                return null;
                            }
                            $this->prevDateStr = $dateStr;
                            $dateStr .= ', ' . $year;
                            $res = null;

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive in'] as $key => $value) {
                                $r = '#' . $value . '\s*(.*?)\s+at\s+(\d+:\d+\s*(?:am|pm))#i';
                                $subj = isset($this->reservationRegexp) ? $text : node('./td[3]');

                                if (preg_match($r, $subj, $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[2]);
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
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
