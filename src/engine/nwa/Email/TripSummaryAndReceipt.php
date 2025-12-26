<?php

namespace AwardWallet\Engine\nwa\Email;

class TripSummaryAndReceipt extends \TAccountCheckerExtended
{
    public $reFrom = "#webuser@lists\.nwa\.com#i";
    public $reProvider = "#lists\.nwa\.com#i";
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+Northwest\s+Airlines#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Northwest\s+Airlines\s+Trip\s+Summary\s+and\s+Receipt#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "nwa/it-1735035.eml, nwa/it-1735038.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = re('#Date:\s+\w+\s+\d+,\s+(\d{4})#');
                    $this->confNos = [];
                    $regex = '#(.*)\s+Confirmation\s+Number:\s+([\w\-]+)#';

                    if (preg_match_all($regex, $text, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            $this->confNos[substr(nice($m[1]), 0, 2)] = $m[2];
                        }
                    }
                    $xpath = '//tr[contains(., "Passenger Name") and contains(., "Number") and not(.//tr)]/following-sibling::tr[string-length(normalize-space(.)) > 1]';
                    $passengerInfoNodes = $this->http->XPath->query($xpath);

                    foreach ($passengerInfoNodes as $n) {
                        $this->passengers[] = node('./td[1]', $n);
                        $this->accountNumbers[] = node('./td[3]', $n);
                    }
                    $this->accountNumbers = implode(',', $this->accountNumbers);
                    $xpath = '//tr[contains(., "Departs:") and not(.//tr)]/ancestor::table[2]';

                    return $this->http->XPath->query($xpath);
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return $this->confNos[re('#Flight:\s+(\w{2})#')];
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return $this->accountNumbers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re('#Flight:\s+\w+\s+(\d+)#');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;
                            $dateStr = re('#Date:\s+\w+,\s+(\w+\s+\d+)#');

                            foreach (['Dep' => 'Departs', 'Arr' => 'Arrives'] as $key => $value) {
                                $subj = re('#' . $value . ':\s+(.*)#');

                                if (preg_match('#(.*)\s+\((\w+)\)\s+at\s+(\d+:\d+\s*(?:am|pm)?)#i', $subj, $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = $m[2];
                                    $res[$key . 'Date'] = strtotime($dateStr . ' ' . $this->year . ', ' . $m[3]);
                                }
                            }

                            return $res;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re('#Operated\s+by\s+(.*)#');
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re('#Aircraft:\s+(.*)#');
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return re('#Approximate\s+Miles:\s+(.*)#');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Class\s+of\s+Service:\s+(.*)\s+\((\w)\)#', $node->nodeValue, $m)) {
                                return [
                                    'Cabin'        => $m[1],
                                    'BookingClass' => $m[2],
                                ];
                            }
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re('#Seat:\s+(.*)#');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re('#Flight\s+Duration:\s+(.*)#');
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re('#Meal\s+Service:\s+(.*)#');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
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
