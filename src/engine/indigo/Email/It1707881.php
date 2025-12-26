<?php

namespace AwardWallet\Engine\indigo\Email;

class It1707881 extends \TAccountCheckerExtended
{
    public $mailFiles = "indigo/it-1.eml, indigo/it-10.eml, indigo/it-11.eml, indigo/it-11387062.eml, indigo/it-12.eml, indigo/it-1707881.eml, indigo/it-1711207.eml, indigo/it-1711208.eml, indigo/it-1711210.eml, indigo/it-1966308.eml, indigo/it-1982881.eml, indigo/it-2.eml, indigo/it-2072906.eml, indigo/it-2072911.eml, indigo/it-2072917.eml, indigo/it-2072921.eml, indigo/it-2189142.eml, indigo/it-2189166.eml, indigo/it-4.eml, indigo/it-6788249.eml, indigo/it-6794804.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'NTERGLOBE AVIATION LTD.(INDIGO)') !== false
            && (stripos($body, 'Booking Date') !== false || stripos($parser->getHTMLBody(), 'Payment Status') !== false || stripos($body, 'Date of Booking') !== false)
            // Itinerary2
            && stripos($body, 'Bag drop closes') === false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@goindigo.in') !== false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // set another document if xpath doesn't work
                    if (!nodes("//*[contains(text(), 'Booking Refence') or contains(text(), 'Booking Reference')]/ancestor-or-self::td[1]")) {
                        $this->setDocument("text/html", "html");
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return cell(["Booking Reference:", "Booking Reference", "Booking Refence"], 0, +1);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = rew('IndiGo Passenger \(s\)  (.+?)  IndiGo Flight \(s\)');

                        if (preg_match_all("/(m[rs]\s+[\sa-z]+)/iu", $info, $m)) {
                            $names = nice($m[1]);

                            return $names;
                        }
                        $pass = array_filter(nodes("//text()[contains(normalize-space(), 'Passenger(s) Information')]/ancestor::tr[1][contains(normalize-space(), 'Baggage Details')]/following-sibling::tr/td[1]", null, "#^\s*\d+\s*\.\s*(.+)$#s"));

                        if (!empty($pass)) {
                            return array_values($pass);
                        }

                        return null;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $total = $this->http->FindSingleNode("(//text()[" . $this->eq(["Total Payment", "Total Fare", "Amount To Pay", "Total Price"]) . "]/ancestor-or-self::td[1]/following-sibling::td[normalize-space()][1])[last()]");

                        if (preg_match("#^\D+$#", $total)) {
                            $total = $this->http->FindSingleNode("(//text()[" . $this->eq(["Total Payment", "Total Fare", "Amount To Pay", "Total Price"]) . "]/ancestor-or-self::td[1]/following-sibling::td[normalize-space()][2])[last()]");
                        }

                        return cost($total);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $base = $this->http->FindSingleNode("(//text()[" . $this->starts(["Base Fare", "Airfare Charges"]) . "]/ancestor-or-self::td[1]/following-sibling::td[normalize-space()][1])[last()]");

                        if (preg_match("#^\D+$#", $base)) {
                            $base = $this->http->FindSingleNode("(//text()[" . $this->starts(["Base Fare", "Airfare Charges"]) . "]/ancestor-or-self::td[1]/following-sibling::td[normalize-space()][2])[last()]");
                        }

                        return cost($base);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        $curr = currency($this->http->FindSingleNode("//text()[" . $this->eq(["Total Payment", "Total Fare", "Amount To Pay"]) . "]/ancestor-or-self::td[1]/following-sibling::td[normalize-space()][1]"));

                        if (!empty($curr)) {
                            return $curr;
                        } else {
                            return 'INR';
                        }
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Service Tax", +2, 0));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if ($str = node("//text()[normalize-space(.)='Status' or normalize-space(.)='Status:']/ancestor::tr[1]/following-sibling::tr[1]/td[2]")) {
                            $res['Status'] = $str;

                            if (stripos($str, "CANCELLED") !== false) {
                                $res['Cancelled'] = true;
                            }

                            return $res;
                        }

                        return cell("Status:", 0, +1);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(node("//*[contains(text(), 'Booking Date:') or contains(text(), 'Date of Booking:')]/ancestor::tr[1]/following-sibling::tr[1]/td[3]"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Arrives') or contains(text(), 'Arr Time')]/ancestor::tr[1]/following-sibling::tr[count(./td)>2]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return orval(uberAir(node("td[5]")), uberAir(node("td[6]")), uberAir(node("td[7]")));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $pos = count(nodes("./preceding-sibling::tr[contains(normalize-space(), 'From')]/td[starts-with(normalize-space(), 'From')]/preceding-sibling::td"));

                            if (!empty($pos)) {
                                return node("td[" . ($pos + 1) . "]");
                            }

                            return null;
                        },

                        "DepartureTerminal" => function ($text = '', $node = null, $it = null) {
                            $count = count(nodes("./preceding-sibling::tr[last()]/td[contains(normalize-space(), 'Dep Terminal')]/preceding-sibling::td")) + 1;

                            if ($count > 1) {
                                return node("td[{$count}]");
                            }

                            return null;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dep = clear("#\s+#", node("td[1]") . ',' . node("td[2]"));
                            $arrtime = node("td[last()]");

                            if (preg_match("#\d+/\d+/\d+#", $arrtime)) {
                                $arr = $arrtime;
                            } else {
                                $arr = clear("#\s+#", node("td[1]") . ',' . node("td[last()]"));
                            }

                            $depArr = ['Dep' => &$dep, 'Arr' => &$arr];

                            //							correctDates($dep, $arr);
                            array_walk($depArr, function (&$val) {
                                $val = preg_replace("#^\s*(\d{1,2})\s*([^\d\s]+)\s*(\d{2}),#", '$1 $2 20$3, ', $val);
                                $val = preg_replace("#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s+#", '$1.$2.$3, ', $val);

                                if (preg_match('/\b((\d{1,2}):(\d{2})\s*([ap]m)?)\s*$/i', $val, $m)) {
                                    if ((int) $m[2] === 00 || (int) $m[2] === 0) {
                                        return $val = strtotime(str_replace($m[1], $m[2] . ':' . $m[3], $val));
                                    } else {
                                        return $val = strtotime($val);
                                    }
                                }

                                return $val;
                            });

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $pos = count(nodes("./preceding-sibling::tr[contains(normalize-space(), 'From')]/td[starts-with(normalize-space(), 'To')]/preceding-sibling::td"));

                            if (!empty($pos)) {
                                return node("td[" . ($pos + 1) . "]");
                            }

                            return null;
                        },
                    ],
                ],
                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    if (!empty(array_filter($this->http->FindNodes("//text()[contains(normalize-space(), 'Passenger(s) Information')]/ancestor::tr[1][contains(normalize-space(), 'Seats')]/following-sibling::tr/td[5]")))) {
                        $xpath = "//text()[contains(normalize-space(), 'Passenger(s) Information')]/ancestor::tr[1][contains(normalize-space(), 'Seats')]/following-sibling::tr/td[5]";
                        $roots = $this->http->XPath->query($xpath);
                        $seats = [];

                        foreach ($roots as $root) {
                            $route = trim($this->http->FindSingleNode("./preceding::td[2]", $root, true, "#^\s*[A-Z]{3}[^\w\s][A-Z]{3}\s*$#"));

                            if (empty($route)) {
                                return $it;
                            }

                            if (preg_match("#^\s*\d{1,3}[A-Z]\s*$#", $root->nodeValue)) {
                                $seats[$route][] = trim($root->nodeValue);
                            } else {
                                $seats[$route] = !empty($seats[$route]) ? $seats[$route] : [];
                            }
                        }

                        if (count($it[0]['TripSegments']) == count($seats)) {
                            foreach ($it[0]['TripSegments'] as $key => $value) {
                                reset($seats);
                                $it[0]['TripSegments'][$key]['Seats'] = current($seats);

                                if (preg_match("#^\s*([A-Z]{3})[^\w\s]([A-Z]{3})\s*$#", key($seats), $m)) {
                                    $it[0]['TripSegments'][$key]['DepCode'] = trim($m[1]);
                                    $it[0]['TripSegments'][$key]['ArrCode'] = trim($m[2]);
                                }
                                array_shift($seats);
                            }
                        }

                        return $it;
                    }
                },
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
