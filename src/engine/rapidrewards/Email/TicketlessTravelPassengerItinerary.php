<?php

namespace AwardWallet\Engine\rapidrewards\Email;

class TicketlessTravelPassengerItinerary extends \TAccountCheckerExtended
{
    public $rePlain = "#This\s+e-mail\s+contains\s+Southwest\s+Airlines\s+Ticketless\s+Travel\s+information#i";
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
    public $mailFiles = "rapidrewards/it-1.eml, rapidrewards/it-2.eml, rapidrewards/it-2124901.eml, rapidrewards/it-2168677.eml, rapidrewards/it-2204914.eml";
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
                        return re('#Confirmation\s+Number\s*:?\s+([A-Z\d]{6})#i');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return array_values(array_filter(nodes('//tr[contains(., "Passenger(s)") and not(.//tr)]/following-sibling::tr')));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[(contains(., "Depart") or contains(., "Change planes")) and contains(., "Arrive") and not(.//tr)]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return node('./td[last() - 1]');
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            if (!empty(nodes("//node()[contains(normalize-space(.),'This e-mail contains Southwest Airlines Ticketless Travel')]", null))) {
                                return 'WN';
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $year = re('#\d{4}#i', $this->parser->getHeader('date'));

                            if (!$year) {
                                return null;
                            }
                            $dateStr = re('#^\w+\s+(\w+\s+\d+)$#i', node('./td[last() - 2]'));

                            if (!$dateStr and isset($this->prevDateStr)) {
                                $dateStr = $this->prevDateStr;
                            }

                            if (!$dateStr) {
                                return null;
                            }
                            $this->prevDateStr = $dateStr;
                            $dateStr .= ', ' . $year;
                            $res = null;

                            foreach (['Dep' => '(?:Depart|Change\s+planes\s+in)', 'Arr' => 'Arrive in'] as $key => $value) {
                                $r = '#' . $value . '\s*(.*?)\s*\((\w{3})\)\s+(?:departing\s+)?at\s+(\d+:\d+\s*(?:am|pm))#i';

                                if (preg_match($r, node('./td[last()]'), $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = $m[2];
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[3]);
                                }
                            }

                            return $res;
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
