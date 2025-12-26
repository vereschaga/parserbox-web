<?php

namespace AwardWallet\Engine\tamair\Email;

class BookingConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#TAM\s+Linhas\s+Aéreas\s+S.A.#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#no-reply@tam\.com\.br#i";
    public $reProvider = "#tam\.com\.br#i";
    public $xPath = "";
    public $mailFiles = "tamair/it-1.eml, tamair/it-1676684.eml, tamair/it-1748015.eml, tamair/it-1897649.eml, tamair/it-3120232.eml, tamair/it-3187220.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#\n\s*(?:Your\s+order\s+code\s+is|Booking\s+reference\s*:)\s*([\w\-]+)#'),
                            re('#([\w\-]+)\s+Keep\s+this\s+number#i'),
                            re('#Additional reservation services\s+([A-Z\d]{5,7})\s+Your ticket will be#')
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengerInfoNodes = nodes('//td[contains(., "E-ticket") and not(.//td)]');
                        $passengers = [];

                        foreach ($passengerInfoNodes as $n) {
                            $passengers[] = re('#(.*)\s*E-ticket#i', $n);
                        }

                        return $passengers;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = node('//td[contains(., "Total:") and not(.//td)]/following-sibling::td[last()]');

                        if (empty($subj)) {
                            $subj = node('//td[contains(., "Total:") and not(.//td)]', null, "#Total:\s+(.+)#");
                        }

                        if ($subj) {
                            return [
                                'TotalCharge' => cost($subj),
                                'Currency'    => currency($subj),
                            ];
                        }
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//td[contains(., "Total travelers:") and not(.//td)]/following-sibling::td[last()]';

                        return cost(node($xpath));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//td[contains(., "Total fees:") and not(.//td)]/following-sibling::td[last()]';

                        return cost(node($xpath));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Your\s+order\s+was\s+(\w+)#'),
                            re('#(Updated)\s+reservation#i')
                        );
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[normalize-space(text())='Arrival:']/ancestor::tr[2]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            re("#([A-Z\d]{2})(\d+)#", cell("Flight Number:", +1, 0));

                            return [
                                'AirlineName'  => re(1),
                                'FlightNumber' => re(2),
                            ];
                        },
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepCode'=> TRIP_CODE_UNKNOWN,
                                'ArrCode'=> TRIP_CODE_UNKNOWN,
                            ];
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return cell(["Outbound:", "Departure:"], +1, 0);
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return cell("Arrival:", +1, 0);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $subj = node("./preceding-sibling::tr[contains(., 'Bound')][1]");

                            if ($year = re('#\d+\s+\w+\s+(\d{4})#', $subj)) {
                                $dateStr = re("#(\d+\s+\w+)#", node("./td[1]")) . ' ' . $year;

                                foreach (['Dep' => ['Outbound', 'Departure'], 'Arr' => 'Arrival'] as $key => $value) {
                                    if (!is_array($value)) {
                                        $value = [$value];
                                    }
                                    $value = implode(' or ', array_map(function ($s) { return 'contains(., "' . $s . ':")'; }, $value));
                                    $xpath = './/td[(' . $value . ') and not(.//td)]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]';
                                    $timeStr = node($xpath);
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $timeStr);
                                }

                                return $res;
                            }
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Aircraft\s*:\s*([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Cabin\s*:\s*([^\n]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*(?:Duration|Total journey duration)[:\s]+([^\n]+)#i");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seats = [];

                            if (empty($it["DepName"]) || empty($it["ArrName"])) {
                                return [];
                            }
                            $nodes = $this->http->XPath->query("//text()[contains(normalize-space(),'Seat pre-reservation')]/ancestor::tr[1]/following-sibling::tr[contains(.,'" . explode(',', $it["DepName"])[0] . "') and contains(.,'" . explode(',', $it["ArrName"])[0] . "')]");

                            foreach ($nodes as $root) {
                                if (preg_match("#" . explode(',', $it["DepName"])[0] . '.+' . explode(',', $it["ArrName"])[0] . "#", $root->nodeValue, $m)) {
                                    for ($i = 2; $i < 15; $i++) {
                                        if ($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][{$i}][contains(.,'From:')]", $root)
                                                || empty($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][{$i}]", $root))) {
                                            break;
                                        } else {
                                            $seats[] = $this->http->FindSingleNode("./following-sibling::tr[{$i}]/td[3]", $root, true, "#^(\d{1,3}[A-Z])(\s+|$)#");
                                        }
                                    }
                                }
                            }

                            return array_filter($seats);
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
}
