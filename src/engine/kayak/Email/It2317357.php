<?php

namespace AwardWallet\Engine\kayak\Email;

use PlancakeEmailParser;

class It2317357 extends \TAccountCheckerExtended
{
    public $mailFiles = "kayak/it-10016696.eml, kayak/it-2317357.eml, kayak/it-2997208.eml";

    private $subjects = [
        'Thanks for booking. Your flight with',
        'Thanks for booking. Your flight on Icelandair is processing via',
    ];

    private $detects = [
        'you can view the details of this flight on KAYAK',
        'Thanks for booking your flight to',
        'Thanks for booking',
    ];

    private $from = '/[@\.]kayak\.com/i';

    private $prov = 'kayak';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->locators = [];
                    $this->dates = [];

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all("#\n\s*([^\n]+?)\s+Record\s+Locator\s*:\s*([\w-]+)#s", $text, $m, PREG_SET_ORDER)) {
                            foreach ($m as $locs) {
                                $this->locators[$locs[1]] = $locs[2];
                            }
                        }

                        if (!($lctr = re("#\sTrip\s+ID\s*:\s*([\w-]+)#i")) && count($this->locators) < 2) {
                            $lctr = re("#\sRecord\s+Locator\s*:\s*([\w-]+)#s");
                        }

                        return $lctr ? $lctr : TRIP_CODE_UNKNOWN;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if ("#Thanks\s+for\s+booking\s+your\s+flight#") {
                            return "booked";
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $re = xpath("//img[contains(@src, '/images/air/')]/ancestor::tr[1][contains(., ':') and contains(./td[3], 'Flight ')]");

                        if ($re->length === 0) {
                            $re = xpath("//img[contains(@src, '/images/air/')]/ancestor::tr[contains(., ':') and contains(., 'Flight ')][1]");
                        }

                        if ($re->length === 0) {
                            $re = xpath("//img[contains(@src, '/provider-logos/airlines/v/AC')]/ancestor::tr[contains(., ':') and contains(., 'Flight ') and (not(contains(normalize-space(), 'Access')))][2]");
                        }

                        if ($re->length === 0) {
                            $re = xpath("//img[contains(@src, '/provider-logos/airlines/')]/ancestor::tr[contains(., ':') and contains(., 'Flight ')][1]");
                        }

                        return $re;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $data = [
                                'AirlineName'  => re("#^(.*?)\s*,\s*Flight\s+(\d+)#"),
                                'FlightNumber' => re(2),
                            ];

                            if (isset($this->locators[$data['AirlineName']])) {
                                $data['FlightLocator'] = $this->locators[$data['AirlineName']];
                            }

                            return $data;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dateRaw = node("ancestor::tr[1]/preceding-sibling::tr[(contains(., 'Flight ') or contains(., 'Depart ') or contains(., 'Return ')) and contains(., ' - ')][1]");

                            if (!$dateRaw) {
                                $dateRaw = node("preceding-sibling::tr[(contains(., 'Flight ') or contains(., 'Depart ') or contains(., 'Return ')) and contains(., ' - ')][1]");
                            }
                            $date = uberDate($dateRaw);
                            $key1 = null;

                            if (re("#Flight\s+(\d+)\s*\-\s*\w+,\s*#i", $dateRaw)) {
                                $key1 = 'f' . re(1);

                                if (!isset($this->dates[$key1])) {
                                    $this->dates[$key1] = totime($date);
                                } else {
                                    $date = date("d F Y", $this->dates[$key1]);
                                }
                            }
                            $dep = $date . ', ' . re("#Take\-off\s*:\s*\w+\s*(\d+:\d+)\s*([apm]+)#i") . ' ' . re(2) . 'm';
                            $timearr = re("#Landing\s*:\s*\w+\s*(\d+:\d+)\s*([apm]+)#i", node("following-sibling::tr[1]"));

                            if (empty($timearr)) {
                                $timearr = re("#Landing\s*:\s*\w+\s*(\d+:\d+)\s*([apm]+)#i");
                            }
                            $arr = $date . ', ' . $timearr . ' ' . re(2) . 'm';

                            correctDates($dep, $arr);

                            if (isset($key1)) {
                                $this->dates[$key1] = $arr;
                            }

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $s = re("#\(([A-Z]{3})\)#", node("following-sibling::tr[1][contains(.,'Landing')]"));

                            if (empty($s)) {
                                $s = re("#Landing.+?\(([A-Z]{3})\)#", node("descendant::tr[contains(.,'Landing')]"));
                            }

                            return $s;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $s = nice(re("#([^,]+)#", node('descendant::table[1]/descendant::tr[1]/td[string-length(normalize-space(.))>1][1]/text()[string-length(normalize-space(.))>1][1]')));

                            if (empty($s)) {
                                $s = nice(re("#([^,]+)#", node('following-sibling::tr[1]/td[string-length(normalize-space(.))>1][1]')));
                            }

                            return $s;
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = !empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'], $headers['from'])) {
            if (!preg_match($this->from, $headers['from'])) {
                return false;
            }

            foreach ($this->subjects as $subject) {
                if (false !== stripos($headers['subject'], $subject)) {
                    return true;
                }
            }
        }

        return false;
    }
}
