<?php

namespace AwardWallet\Engine\ufly\Email;

class It1674064 extends \TAccountCheckerExtended
{
    public $reFrom = "#@suncountry\.#i";
    public $reProvider = "#@suncountry\.#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@suncountry#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "ufly/it-1674064.eml, ufly/it-76722157.eml";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        return cell('Confirmation:', +1);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = array_filter(nodes("//tr[ *[2][normalize-space()='Traveler Name'] ]/ancestor::table[1]/tbody/tr/*[2]", null, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u"));

                        return array_values(array_unique($names));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//tr[ count(*)=2 and *[1][normalize-space()='Flight Date:'] ]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("following-sibling::tr[normalize-space()][1][ *[1][normalize-space()='Flight#:'] ]/*[2]", $node));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $info = node("preceding-sibling::tr[normalize-space()][1][starts-with(normalize-space(),'AIRFARE:')]", $node);

                            if (preg_match_all('/(?:\(Terminal[ ]*(?<t>[-A-z\d ]+)\)[ ]*)?\((?<c>[A-Z]{3})\)/', $info, $ms)) {
                                $result = [
                                    'DepCode' => $ms['c'][0] ?? TRIP_CODE_UNKNOWN,
                                    'ArrCode' => $ms['c'][1] ?? TRIP_CODE_UNKNOWN,
                                ];

                                if (isset($ms['t'][0]) && !empty($ms['t'][0])) {
                                    $result['DepartureTerminal'] = $ms['t'][0];
                                }

                                if (isset($ms['t'][1]) && !empty($ms['t'][1])) {
                                    $result['ArrivalTerminal'] = $ms['t'][1];
                                }

                                return $result;
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node('*[2]', $node);

                            $time1 = node("following-sibling::tr[normalize-space()][3][ *[1][normalize-space()='Departure:'] ]/*[2]", $node);
                            $time1 = re('/(\d+:\d+\s*(?:am|pm))/i', $time1);
                            $time2 = node("following-sibling::tr[normalize-space()][4][ *[1][normalize-space()='Arrival:'] ]/*[2]", $node);
                            $time2 = re('/(\d+:\d+\s*(?:am|pm))/i', $time2);

                            $dt1 = "$date $time1";
                            $dt2 = "$date $time2";
                            $dt1 = totime(uberDateTime($dt1));
                            $dt2 = totime(uberDateTime($dt2));

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node("following-sibling::tr[normalize-space()][2][ *[1][normalize-space()='Flying:'] ]/*[2]", $node);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (preg_match_all('/seat\s*(\d+\w)/i', node("following-sibling::tr[normalize-space()][5]", $node), $ms)) {
                                return $ms[1];
                            }
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $x = re("/(\d+|Non)[-\s]*stop/i", node("following-sibling::tr[normalize-space()][3][ *[1][normalize-space()='Departure:'] ]/*[2]", $node));

                            if ($x !== null && strcasecmp($x, 'Non') === 0) {
                                return 0;
                            }

                            if ($x !== null) {
                                return intval($x);
                            }
                        },
                    ],

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $result = [];
                        $xpathPrice = "//text()[normalize-space()='PRICING DETAILS:']";
                        $totalPrice = $this->http->FindSingleNode($xpathPrice . "/following::tr/*[normalize-space()='Total']/following-sibling::*[normalize-space()][last()]");

                        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
                            // $1,861.60
                            $result['Currency'] = $m['currency'];
                            $result['TotalCharge'] = $this->normalizeAmount($m['amount']);

                            $baseFare = $this->http->FindSingleNode($xpathPrice . "/following::tr/*[starts-with(normalize-space(),'Airfare,')]/following-sibling::*[normalize-space()][last()]");

                            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseFare, $matches)) {
                                $result['BaseFare'] = $this->normalizeAmount($matches['amount']);
                            }

                            $feeRows = $this->http->XPath->query($xpathPrice . "/following::tr[ preceding-sibling::tr[*[starts-with(normalize-space(),'Airfare,')]] and following-sibling::tr[*[normalize-space()='Total']] ]");

                            foreach ($feeRows as $feeRow) {
                                $feeCharge = $this->http->FindSingleNode('*[last()]', $feeRow);

                                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $matches)) {
                                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow);
                                    $result['Fees'][] = ['Name' => $feeName, 'Charge' => $this->normalizeAmount($matches['amount'])];
                                }
                            }
                        }

                        return $result;
                    },
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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
