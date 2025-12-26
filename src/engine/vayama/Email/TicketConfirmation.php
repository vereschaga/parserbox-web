<?php

namespace AwardWallet\Engine\vayama\Email;

class TicketConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#Vayama\s+Ticket\s+Confirmation/Receipt|Dear\s+valued\s+Vayama\s+customer#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = [
        "Vayama Ticket Confirmation/Receipt",
        "Major schedule change notification",
    ];
    public $reBody = [
        "Vayama Ticket Confirmation/Receipt",
        "Your Vayama Trip Id",
        "Dear valued Vayama customer",
    ];
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#noreply@vayama\.com#i";
    public $reProvider = "#[@.]vayama.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "vayama/it-1.eml, vayama/it-1653088.eml, vayama/it-1653113.eml, vayama/it-1955378.eml, vayama/it-1956698.eml, vayama/it-1956703.eml, vayama/it-2.eml, vayama/it-5221934.eml, vayama/it-5530538.eml";
    public $pdfRequired = "";

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'Vayama')]")->length < 1) {
            return false;
        }
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $xpath = "//text()[contains(., 'Travelers')]/ancestor::tr[1]/following-sibling::tr/td[2]//tr";
                    $this->passengers = nodes($xpath);
                    array_walk($this->passengers, function (&$value, $key) { $value = re('#\d+\.\s+(.*?)\s*\(.*\)#', $value); });

                    $recordLocatorNodes = $this->http->XPath->query("//*[contains(text(),'Your Airline Confirmation') or contains(text(), 'Your Airline Reservation Numbers')]/ancestor::tr[1]/following-sibling::tr");
                    $this->recordLocators = [];

                    foreach ($recordLocatorNodes as $rln) {
                        $airline = $this->http->FindSingleNode('./td[1]', $rln);
                        $recordLocator = $this->http->FindSingleNode('./td[2]', $rln);
                        $this->recordLocators[$airline] = $recordLocator;
                    }

                    $xpath = "//text()[contains(., 'Your Itinerary')]/ancestor::tr[1]/following-sibling::tr//text()[normalize-space(.) = 'Flight']/ancestor::tr[1]";
                    $reservations = $this->http->XPath->query($xpath);

                    return $reservations;
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#^(.*?)(?:\s*operated\s+by.*)?$#', node('./td[1]'));

                        return (isset($this->recordLocators[$subj])) ? $this->recordLocators[$subj] : CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[normalize-space(.) = 'Total']/ancestor::td[1]/following-sibling::td";
                        $total = join(' ', nodes($xpath));
                        $xpath = "//text()[normalize-space(.) = 'Taxes and Fees' or normalize-space(.) = 'Taxes and agent-imposed Fees']";
                        $tax = join(' ', nodes($xpath . '/ancestor::td[1]/following-sibling::td'));
                        $baseFare = join(' ', nodes($xpath . '/ancestor::tr[1]/preceding-sibling::tr[1]/td[position() > 1]'));

                        if (count($this->recordLocators) == 1) {
                            return [
                                'TotalCharge' => cost($total),
                                'Currency'    => currency($total),
                                'BaseFare'    => cost($baseFare),
                                'Tax'         => cost($tax),
                            ];
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w{2})\s*(\d+)#', node('./td[3]'), $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = [];

                            foreach (['Dep' => 'Depart', 'Arr' => 'Arrive'] as $key => $value) {
                                $subj = null;
                                $count = count(nodes("./following-sibling::tr[contains(., '" . $value . "')][1]/td[normalize-space(.)]"));

                                if ($count == 2) {
                                    $xpath = "./following-sibling::tr[contains(., '" . $value . "')][1]/td[normalize-space(.)][2]";
                                    $subj = node($xpath);
                                } elseif ($count == 3) {
                                    $xpath = "./following-sibling::tr[contains(., '" . $value . "')][1]/td[normalize-space(.)][3]";
                                    $subj = node($xpath);
                                }

                                if (preg_match('#(.*)\s+\((\w+)\)#', $subj, $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = $m[2];
                                }

                                if ($count == 2) {
                                    $xpath = "./following-sibling::tr[contains(., '" . $value . "')][1]/following-sibling::tr[1]";
                                    $subj = node($xpath);
                                } elseif ($count == 3) {
                                    $xpath = "./following-sibling::tr[contains(., '" . $value . "')][1]/td[2]";
                                    $subj = node($xpath);
                                }

                                if (preg_match('#(\d+)-(\w+)-(\d+)#', $subj, $m)) {
                                    $dateStr = $m[1] . ' ' . $m[2] . ' ' . (strlen($m[3]) == 2 ? '20' . $m[3] : $m[3]);

                                    if (preg_match('#(\d+):(\d+)([ap])#i', $subj, $m)) {
                                        if ($m[1] == '00') {
                                            $m[1] = '12';
                                        }
                                        $dateStr .= ', ' . $m[1] . ':' . $m[2] . ' ' . $m[3] . 'm';
                                    }
                                    $res[$key . 'Date'] = strtotime($dateStr);
                                }
                            }

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $xpath = "./following-sibling::tr[contains(., 'Aircraft')][1]/td[position() > 1]";

                            return node($xpath);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $xpath = "./following-sibling::tr[contains(., 'Flight Time')][1]/td[position() > 1]";
                            $subj = node($xpath);

                            if (preg_match('#(.*)\s+\|\s+(.*)#', $subj, $m)) {
                                return ['Duration' => $m[1], 'Cabin' => $m[2]];
                            }
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $xpath = "./following-sibling::tr[contains(., 'Stops')][1]/td[position() > 1]";
                            $subj = node($xpath);

                            if ($subj == 'nonstop') {
                                return 0;
                            } else {
                                return (int) $subj;
                            }
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) > 1) {
                        foreach ($itNew as &$i) {
                            unset($i['BaseFare']);
                            unset($i['TotalCharge']);
                            unset($i['Currency']);
                            unset($i['Tax']);
                        }
                    }

                    return $itNew;
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
