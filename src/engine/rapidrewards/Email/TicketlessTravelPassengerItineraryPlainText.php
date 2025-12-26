<?php

namespace AwardWallet\Engine\rapidrewards\Email;

class TicketlessTravelPassengerItineraryPlainText extends \TAccountCheckerExtended
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
    public $reFrom = "#SouthwestAirlines@mail\.southwest\.com#i";
    public $reProvider = "#southwest\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "rapidrewards/it-2135478.eml, rapidrewards/it-2168673.eml";
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
                        return re('#CONFIRMATION\s+NUMBER\s+\*+\s+([\w\-]+)#i');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return explode("\n", re('#PASSENGER\(S\)\s+\*+\s+(.*)\s+\*+\s+ITINERARY#i'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $r = '#(?:\w+\s+\d+\s+.*\s+to\s+.*\s+|Change\s+planes\s+to\s+)Flight\s+.*\s+Depart\s+.*\s+arrive\s+.*#i';

                        if (preg_match_all($r, $text, $m)) {
                            return $m[0];
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re('#Flight\s+(\d+)#');
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            if (strpos($this->text(), "This e-mail contains Southwest Airlines Ticketless") !== false) {
                                return 'WN';
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $year = re('#\d{4}#i', $this->parser->getHeader('date'));

                            if (!$year) {
                                return null;
                            }
                            $dateStr = re('#(\w+\s+\d+)\s+-#i');

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

                                if (preg_match($r, $text, $m)) {
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
