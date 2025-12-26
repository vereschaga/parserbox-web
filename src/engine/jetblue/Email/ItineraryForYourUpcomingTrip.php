<?php

namespace AwardWallet\Engine\jetblue\Email;

class ItineraryForYourUpcomingTrip extends \TAccountCheckerExtended
{
    public $mailFiles = "jetblue/it-1.eml, jetblue/it-1583134.eml, jetblue/it-1657368.eml, jetblue/it-1835026.eml, jetblue/it-2.eml, jetblue/it-2022132.eml, jetblue/it-3.eml, jetblue/it-3009126.eml, jetblue/it-3009127.eml, jetblue/it-3020698.eml, jetblue/it-5.eml, jetblue/it-6913074.eml";
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Your JetBlue Vacations Itinerary', 'blank', ''],
    ];
    public $reFrom = [
        ['#reservations@jetblue\.com#i', 'us', ''],
        ['#jetblueairways@email\.jetblue\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#jetblue\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "4";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "31.08.2015, 10:30";
    public $crDate = "";
    public $xPath = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    /*
    var $rePlain = [
        ['#Scan\s+the\s+barcode\s+at\s+the\s+top\s+of\s+this\s+page\s+to\s+check\s+in\s+(?>for\s+your\s+flight\s+)?at\s+any\s+JetBlue\s+kiosk|'
            . 'Thanks\s+for\s+choosing\s+JetBlue\.\s+Please\s+review\s+this\s+booking\s+confirmation|'
            . 'Your\s+JetBlue\s+Getaways\s+Itinerary|'
            . 'JetBlue\s+Reservations#i','us','/1'],
    ];
    */

    private $detectBody = [
        'Scan the barcode at the top of this page to check in',
        'Scan this barcode to check in at',
        'Please review this booking confirmation',
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $emailDateVariants = [
                        strtotime(re('#.*(?:Date|Sent):[^\n]+\s+(\w+\s+\d+,\s+\d{4})#is', $this->text())),
                        strtotime(re('#\d+\s+\w+\s+\d+#', $this->parser->getHeader('date'))), ];
                    $emailDateVariants = array_filter($emailDateVariants);
                    $this->emailDate = min($emailDateVariants);

                    if (!$this->emailDate) {
                        return null;
                    }
                    $this->emailYear = re('#\d{4}#i', date('Y', $this->emailDate));

                    // its doesn't working now
                    // getting total charge from pages of each passengers (with link)
//                    $this->totalCharge = 0; $this->currency = "";
//                    $dataRaw = re("#For\s+a\s+detailed\s+receipt,\s+select\s+a\s+customer\s+Ticket\s+number\(s\)(.+?)Please\s+click\s+here\s+<http#si", $this->getDocument('plain'));
//                    if (preg_match_all("#[^\n]+\s+<(https?\:[^>]+)>\s+\d+\s*\n#", $dataRaw, $m)) {
//                        $oldBody = $this->http->Response['body'];
//                        foreach ($m[1] as $dataUrl) {
//                            $this->http->GetURL($dataUrl);
//                            $str = text($this->http->Response['body']);
//                            if (re("#\n\s*Total\s+Fare\s+(\D+\s*\d[\d,.]*)#i", $str)) {
//                                $this->totalCharge += cost(nice(re(1)));
//                                $this->currency = currency(nice(re(1)));
//                            }
//                        }
//                        $this->http->SetBody($oldBody);
//                    }

                    return [$text];
                },

                "./tr[normalize-space(.) = 'Your hotel']" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },
                ],

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+(?:flight\s+)?confirmation\s+(?:number|code)\s+is\s+([\w\-]+)#i');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        if (isset($this->totalCharge) && $this->totalCharge > 0) {
                            return ['TotalCharge' => $this->totalCharge, 'Currency' => $this->currency];
                        }
                        $field = xpath("//td[not(.//td) and (normalize-space(.)='Total price' or normalize-space(.)='TOTAL')]/ancestor-or-self::td[count(../td)>1][1]");
                        $fieldIndex = xpath("preceding-sibling::td", $field->item(0))->length + 1;
                        $total = total(node("ancestor::table[1]/following-sibling::table[count(./tbody/tr[1]/td)>1][1]/tbody/tr[1]/td[$fieldIndex]", $field->item(0)));

                        if (empty($total["totalCharge"])) {
                            $total = total(node("ancestor::table[1]/following::table[count(./tbody/tr[1]/td)>1][1]/tbody/tr[1]/td[$fieldIndex]", $field->item(0)));
                        }

                        return $total;
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $field = xpath("//td[not(.//td) and normalize-space(.)='Base price']");
                        $fieldIndex = xpath("preceding-sibling::td", $field->item(0))->length + 1;

                        $cost = cost(node("ancestor::table[1]/following-sibling::table[count(./tbody/tr[1]/td)>1][1]/tbody/tr[1]/td[$fieldIndex]", $field->item(0)));

                        if (empty($cost)) {
                            $cost = cost(node("ancestor::table[1]/following::table[count(./tbody/tr[1]/td)>1][1]/tbody/tr[1]/td[$fieldIndex]", $field->item(0)));
                        }

                        return $cost;
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $field = xpath("//td[not(.//td) and normalize-space(.)='Taxes & fees']");
                        $fieldIndex = xpath("preceding-sibling::td", $field->item(0))->length + 1;
                        $cost = cost(node("ancestor::table[1]/following-sibling::table[count(./tbody/tr[1]/td)>1][1]/tbody/tr[1]/td[$fieldIndex]", $field->item(0)));

                        if (empty($cost)) {
                            $cost = cost(node("ancestor::table[1]/following::table[count(./tbody/tr[1]/td)>1][1]/tbody/tr[1]/td[$fieldIndex]", $field->item(0)));
                        }

                        return $cost;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $segments = xpath('//tr[contains(.,"Departs") and contains(.,"Route") and not(.//tr)]/following-sibling::tr[contains(.,":") and not(contains(.,"Your ticket"))]');

                        if ($segments->length === 0) {
                            $segments = xpath('//tr[contains(.,"Departs") and contains(.,"Route") and not(.//tr)]/ancestor::table[1]/following-sibling::table//tr//img[starts-with(normalize-space(@alt),"jetBlue")]/ancestor::tr[3]');
                        }

                        if ($segments->length === 0) {
                            $segments = xpath('//tr[contains(.,"Departs") and contains(.,"Route") and not(.//tr)]/ancestor::table[1]/following::table//tr//img[starts-with(normalize-space(@alt),"jetBlue")]/ancestor::tr[3]');
                        }

                        return $segments;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $this->currentSegmentTdOffset = nodes('./td[2]//text()[contains(.,":")]') ? 0 : 1;

                            return re('#\d+#i', node('./td[' . (4 + $this->currentSegmentTdOffset) . ']'));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            $this->currentSegmentTdOffset = nodes('./td[2]//text()[contains(.,":")]') ? 0 : 1;

                            if (!empty(node('./td[' . (4 + $this->currentSegmentTdOffset) . ']//img[contains(@src, "/B6.png")]/@src'))) {
                                return 'B6';
                            }

                            return AIRLINE_UNKNOWN;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $regex = '#^(.*?)(?:\s+\((\w{3})\))?\s+to\s*(.*?)(?:\s+\((\w{3})\))?$#is';

                            if (preg_match($regex, node('./td[' . (3 + $this->currentSegmentTdOffset) . ']'), $m)) {
                                return [
                                    'DepName' => nice($m[1]),
                                    'DepCode' => (isset($m[2]) and $m[2]) ? $m[2] : TRIP_CODE_UNKNOWN,
                                    'ArrName' => nice($m[3]),
                                    'ArrCode' => (isset($m[4]) and $m[4]) ? $m[4] : TRIP_CODE_UNKNOWN,
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dateStrs = [];

                            if (preg_match_all('#\w+\s+\d+#', node('./td[' . (1 + $this->currentSegmentTdOffset) . ']'), $m)) {
                                $dateStrs = $m[0];

                                if (count($dateStrs) === 1) {
                                    $dateStrs[1] = $dateStrs[0];
                                }
                            }

                            foreach ($dateStrs as &$d) {
                                $year = $this->emailYear;
                                $date1 = strtotime($d . ', ' . $year);

                                if ($year and $date1 and $date1 < $this->emailDate) {
                                    ++$year;
                                }
                                $d .= ', ' . $year;
                            }

                            $timeRe = '\d+:\d+ *[A-Z.]*';

                            if (preg_match('#(' . $timeRe . ')\s*(' . $timeRe . ')#i', node('./td[' . (2 + $this->currentSegmentTdOffset) . ']'), $m)) {
                                return [
                                    'DepDate' => strtotime($dateStrs[0] . ', ' . $m[1]),
                                    'ArrDate' => strtotime($dateStrs[1] . ', ' . $m[2]),
                                ];
                            }
                        },

                        "Operator" => function ($text = '', $node = null, $it = null) {
                            return node('./td[' . (4 + $this->currentSegmentTdOffset) . ']//img/@alt');
                        },

                        "DepartureTerminal" => function ($text = '', $node = null, $it = null) {
                            return node('./td[last()]', null, true, '/^([A-Z\d]{1})$/');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            // gather together data
                            $fieldTitle = xpath('//td[starts-with(normalize-space(.),"Departs/")][1]/following-sibling::td[normalize-space(.)="Travelers"][1]');

                            if (!$fieldTitle || $fieldTitle->length < 1) {
                                $fieldTitle = xpath('//th[starts-with(normalize-space(.),"Departs/")][1]/following-sibling::th[normalize-space(.)="Travelers"][1]');
                            }

                            if ($fieldTitle && $fieldTitle->length > 0) {
                                if ($fieldIndex = xpath('preceding-sibling::*', $fieldTitle->item(0))) {
                                    $fieldIndex = $fieldIndex->length;
                                    $fields = xpath('td[position()>' . $fieldIndex . ']');

                                    if ($fields && $fields->length > 0) {
                                        $addonFLength = xpath('following-sibling::tr[count(./td)>4][1]/preceding-sibling::tr');

                                        if ($addonFLength->length > 0) {
                                            $addonFLength = $addonFLength->length;
                                        } else {
                                            $addonFLength = 10000;
                                        }
                                        $addonFStartIndex = xpath('preceding-sibling::tr')->length;
                                        $addonFields = xpath('following-sibling::tr[position()<' . ($addonFLength - $addonFStartIndex) . '][count(./td)<4]');

                                        $arData = [text($fields)];

                                        for ($i = 0; $i < $addonFields->length; $i++) {
                                            $arData[] = text($addonFields->item($i));
                                        }
                                        $seats = [];

                                        if (!isset($this->passengers) or !$this->passengers) {
                                            $this->passengers = [];
                                            $this->accountNumbers = [];
                                        }
                                        // parse the collected data
                                        foreach ($arData as $data) {
                                            if (preg_match_all("#^\s*([A-Z][^\n:]+?)\s+((?>[A-Z]\w*\s+)?\d+|N\/A)\s*?$(?>\s*Select\sseat)*#mi", $data, $m)) {
                                                $this->passengers = array_merge($this->passengers, $m[1]);
                                                $this->accountNumbers = array_merge($this->accountNumbers, $m[2]);
                                                $this->accountNumbers = array_filter($this->accountNumbers, function ($val) {
                                                    return !preg_match("#\bN\/A\b#i", $val);
                                                });
                                                $this->accountNumbers = array_map(function ($val) {
                                                    return preg_replace("#\s*?\n\s*#", "", $val);
                                                }, $this->accountNumbers);
                                            }

                                            if (preg_match_all("#\b(\d+[A-Z])\b#", $data, $m)) {
                                                $seats = array_merge($seats, $m[1]);
                                            }
                                        }
                                    }
                                }
                            }

                            return (isset($seats) && is_array($seats) && count($seats)) ? implode(', ', $seats) : null;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $x = '//tr[contains(.,"Your hotel") and not(.//tr)]';

                    if ($it[0]['Kind'] === 'T') {
                        if (isset($this->passengers) and $this->passengers) {
                            $it[0]['Passengers'] = array_values(array_unique($this->passengers));
                        }

                        if (isset($this->accountNumbers) and $this->accountNumbers) {
                            $it[0]['AccountNumbers'] = array_values(array_unique($this->accountNumbers));
                        }
                    }

                    if ($hotelReservation = xpath($x)) {
                        $hotelNodes = xpath('//tr[contains(.,"Check in") and not(.//tr)]/ancestor::table[1]/following-sibling::table[contains(.,":")][1]//tr[count(./td)=4]');

                        if ($hotelNodes->length == 0) {
                            $hotelNodes = xpath('//tr[contains(.,"Check in") and not(.//tr)]/ancestor::table[1]/following::table[contains(.,":")][1]//tr[count(./td)=4]');
                        }

                        if ($hotelNodes->length > 0) {
                            $hotelParentNode = $hotelNodes->item(0);
                            $res['Kind'] = 'R';
                            $res['ConfirmationNumber'] = re('/Your\s+(?:Getaways|Vacation)\s+confirmation\s+number\s+is\s+([-\w]+)/i', $this->text());
                            $dateStrs[] = implode(" ", nodes('./td[2]//table[string-length(normalize-space(.))>1][1]//text()', $hotelParentNode));
                            $dateStrs[] = implode(" ", nodes('./td[2]//table[string-length(normalize-space(.))>1][2]//text()', $hotelParentNode));

                            foreach ($dateStrs as &$d) {
                                $year = $this->emailYear;
                                $monthAndDay = re('#\w+,\s+(\w+\s+\d+)#i', $d);
                                $time = re('#(\d+:\d+\s+[ap]\.\s*\m)#i', $d);
                                $date1 = strtotime($monthAndDay . ', ' . $year);

                                if ($year and $date1 and $date1 < $this->emailDate) {
                                    ++$year;
                                }
                                $d = $monthAndDay . ', ' . $year . ', ' . $time;
                            }
                            $res['CheckInDate'] = strtotime($dateStrs[0]);
                            $res['CheckOutDate'] = strtotime($dateStrs[1]);
                            $subj = implode("\n", nodes('./td[3]//text()', $hotelParentNode));

                            if (preg_match('#\s*(.*)\s+((?s).*)#i', $subj, $m)) {
                                $res['HotelName'] = $m[1];
                                $res['Address'] = nice($m[2], ',');
                            }
                            $roomType = node('./td[4]//td[2]', $hotelParentNode);

                            if (preg_match('/^(.+?),\s+(.+)$/', $roomType, $m)) {
                                $res['RoomType'] = $m[1];
                                $res['RoomTypeDescription'] = $m[2];
                            } elseif ($roomType) {
                                $res['RoomType'] = $roomType;
                            }
                            $res['Guests'] = re('/Adults:\s*(\d{1,3})/i', $this->text());
                            $res['Kids'] = re('/Children:\s*(\d{1,3})/i', $this->text());
                            $res['Rooms'] = re('#Total\s+rooms:\s+(\d+)#i', $this->text());
                            $res['GuestNames'] = [node('./td[4]//tr[count(./td)=2]/td[1]', $hotelParentNode)];
                            $it[] = $res;
                        }
                    }

                    return $it;
                },
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href, "www.jetblue.com") or contains(@href,".jetblue.com")] | //text()[contains(., "JetBlue")]')->length < 5) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query('//*[contains(normalize-space(),"' . $dBody . '")]')->length > 0) {
                return true;
            }
        }

        return false;
    }
}
