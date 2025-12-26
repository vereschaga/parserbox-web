<?php

namespace AwardWallet\Engine\rovia\Email;

class It2101098 extends \TAccountCheckerExtended
{
    public $mailFiles = "rovia/it-1698267.eml, rovia/it-2101098.eml";

    private $detects = [
        'Thank you for choosing Rovia',
        'Please use the Airline Reference # when contacting the airline directly, not the Rovia Trip ID',
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $ppl = nodes("//*[contains(text(), 'Passenger Name')]/ancestor::tr[1]/following-sibling::tr/*[1]");
                    $this->ppl = $ppl;

                    return xpath("//*[normalize-space(text()) = 'Departs']/ancestor::tr[1]/following-sibling::tr[ ./*[9] ]");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "TripNumber" => function ($text = '', $node = null, $it = null) {
                        return $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Rovia Trip Id:")]', null, true, '/^[^:]+:\s*(\d{5,})$/');
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $conf = $this->getField("airline_ref");

                        if ('-' === $conf) {
                            return CONFNO_UNKNOWN;
                        }

                        return nice($conf);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->ppl;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re_white('- (\d+)', $this->getField('flight'));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $info = $this->getField('departs');
                            $res = re_white('\( (\w+) \)', $info);

                            if (empty($res) && preg_match('/([A-Z]{3}),\s*(.+)/', $info, $m)) {
                                $res = [
                                    'DepCode' => $m[1],
                                    'DepName' => $m[2],
                                ];
                            }

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dt = $this->getField('takes_off');

                            return strtotime($dt);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $info = $this->getField('arrives');
                            $res = re_white('\( (\w+) \)', $info);

                            if (empty($res) && preg_match('/([A-Z]{3}),\s*(.+)/', $info, $m)) {
                                $res = [
                                    'ArrCode' => $m[1],
                                    'ArrName' => $m[2],
                                ];
                            }

                            return $res;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $dt = $this->getField('lands');

                            return strtotime($dt);
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            $info = $this->getField('flight');
                            $air = re_white('(.*?) -', $info);

                            return nice($air);
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $info = $this->getField('stop_s');

                            return re_white('(\d+)', $info);
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
                },
            ],
        ];
    }

    public function getField($str)
    {
        $str = strtolower(preg_replace("#\s+#", "_", trim(preg_replace("#\W+#", " ", $str))));

        // get table info
        if (!isset($this->tableHeaders)) {
            $this->tableHeaders = [];
            $xpath = xpath("//*[normalize-space(text())='Departs']/../*");

            foreach ($xpath as $root) {
                $name = strtolower(preg_replace("#\s+#", "_", trim(preg_replace("#\W+#", " ", node(".", $root)))));
                $col = count(nodes("./preceding-sibling::*", $root)) + 1;
                $this->tableHeaders[$name] = $col;
            }
        }

        if (!isset($this->tableHeaders[$str])) {
            return null;
        }

        return node("./*[" . $this->tableHeaders[$str] . "]");
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Rovia Air') !== false
            || stripos($from, '@rovia.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && stripos($headers['subject'], 'Rovia trip') === false) {
            return false;
        }

        return stripos($headers['subject'], 'Order status email:') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $detect) {
            if (stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }
}
