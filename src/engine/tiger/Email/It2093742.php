<?php

namespace AwardWallet\Engine\tiger\Email;

class It2093742 extends \TAccountCheckerExtended
{
    public $mailFiles = "tiger/it-12232470.eml, tiger/it-2093742.eml";
    public $reBody = 'tigerair';
    public $reBody2 = ['Here are your new flight details'];
    public $reFrom = "tigerair.com";
    public $reSubject = ["Important Notification from Tigerair"];

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
                        return re_white('(\w+)  Dear');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[contains(@src, 'passengers.gif')]/ancestor::tr[1]/following-sibling::tr//text()[normalize-space(.)!='']");
                        $ppl = array_map(function ($x) { return clear('/^\s*\d+[.]\s*/', $x); }, $ppl);

                        return nice($ppl);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('We regret to inform you that your flight has been rescheduled')) {
                            return 'updated';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//*[contains(@src, \'departing.gif\')]/ancestor::table[.//img[contains(@src,\'flight-number.gif\')]][1]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = node("(.//*[contains(@src, 'flight-number.gif')]/following::td[1]) [1]");

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $info = node(".//*[contains(@src, 'departing.gif')]/following::td[1]");

                            return re_white('(?:hrs|\s+) ([A-Z]+) $', $info);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $info_dep = node(".//*[contains(@src, 'departing.gif')]/following::td[1]");
                            $info_arr = node(".//*[contains(@src, 'arriving.gif')]/following::td[1]");

                            $dt1 = \DateTime::createFromFormat('D, d M Y H:i*', $info_dep);
                            $dt1 = $dt1 ? $dt1->getTimestamp() : null;

                            if (empty($dt1)) {
                                $dt1 = \DateTime::createFromFormat('D, dMY H:i*', $info_dep);
                                $dt1 = $dt1 ? $dt1->getTimestamp() : null;
                            }
                            $time2 = re_white('(\d+:\d+)', $info_arr);
                            $dt2 = strtotime($time2, $dt1);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $info = node(".//*[contains(@src, 'arriving.gif')]/following::td[1]");

                            return re_white('(?:hrs|\s+) ([A-Z]+) $', $info);
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
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
