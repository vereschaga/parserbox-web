<?php

namespace AwardWallet\Engine\travelocitybus\Email;

class PlainText extends \TAccountCheckerExtended
{
    public $reFrom = "#tbiztravelcenter@tbiztravel\.com#i";
    public $reProvider = "#tbiztravel\.com#i";
    public $rePlain = "";
    public $rePlainRange = "1000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Booking\s+Confirmation#i";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $xPath = "";
    public $mailFiles = "travelocitybus/it-1.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    $this->passengers = [re('#Name:\s+(.*)#')];

                    $reservations = [];

                    if (preg_match_all('#AIR\s+.*?\n\n#s', $text, $m)) {
                        $reservations = $m[0];
                    }

                    $this->recordLocators = [];
                    $regex = '#Airline\s+Record\s+Locator\s+\#\d\s+\w+-(\w+)\s+\((.*)\)#';

                    if (preg_match_all($regex, $text, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            $this->recordLocators[$m[2]] = $m[1];
                        }
                    }

                    $this->cost['BaseFare'] = cost(re('#Base\s+Airfare\s+\(per\s+person\)\s+(.*)#'));
                    $this->cost['Tax'] = cost(re('#Total\s+Taxes\s+and/or\s+Applicable\s+fees\s+\(per\s+person\)\s+(.*)#'));
                    $totalStr = re('#Total\s+Flight\s+\(per\s+person\)\s+(.*)#');
                    $this->cost['TotalCharge'] = cost($totalStr);
                    $this->cost['Currency'] = currency($totalStr);

                    return $reservations;
                },

                "#AIR#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Flight/Equip.:\s+(.*)\s+(\d+)#', $text, $m)) {
                            $this->currentFlightNumber = $m[2];
                            $this->currentAirlineName = $m[1];

                            if (isset($this->recordLocators[$this->currentAirlineName])) {
                                return ['RecordLocator' => $this->recordLocators[$this->currentAirlineName]];
                            }
                        }
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Status:\s+(.*)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return $this->currentFlightNumber;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = [];

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive'] as $key => $value) {
                                $regex = '#';
                                $regex .= $value . ':\s+';
                                $regex .= '(?P<Name>.*)\s*';
                                $regex .= '\((?P<Code>\w+)\)\s+';
                                $regex .= '\w+,\s+(?P<Date>\w+\s+\d+\s+\d+:\d+)';
                                $regex .= '#i';

                                if (preg_match($regex, $text, $m)) {
                                    foreach (['Name', 'Code', 'Date'] as $field) {
                                        $res[$key . $field] = ($field == 'Date') ? strtotime($m[$field], $this->date) : $m[$field];
                                    }
                                }
                            }

                            return $res;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return $this->currentAirlineName;
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            $subj = re('#Miles:\s*(.*)\s+Class#');

                            if ($subj) {
                                return (float) $subj;
                            }
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Class:\s+(.*)#');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('#Seats\s+Requested:\s+(.*)#');
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $subj = re('#Stops:\s+([^;]+)#');

                            return ($subj == 'non-stop') ? 0 : (int) $subj;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itMod = uniteAirSegments($it);

                    if (count($itMod) == 1) {
                        if ($itMod[0]['Kind'] == 'T') {
                            foreach (['BaseFare', 'Tax', 'TotalCharge', 'Currency'] as $key) {
                                $itMod[0][$key] = $this->cost[$key];
                            }
                        }
                    }

                    return $itMod;
                },
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
}
