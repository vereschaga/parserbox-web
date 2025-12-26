<?php

namespace AwardWallet\Engine\lastminute\Email;

class It3946450 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?flugplanaenderungen@de.customer-travel-care.com#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#flugplanaenderungen@de.customer-travel-care.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]lastminute\W#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "20.06.2016, 06:29";
    public $crDate = "17.06.2016, 14:00";
    public $xPath = "";
    public $mailFiles = "lastminute/it-3946450.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

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
                        return re('#Ihrer Buchung (\d+)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $nodes = xpath('//text()[contains(normalize-space(.), "NEUE FLUGDETAILS:")]/ancestor::tr[3]/following-sibling::tr[last()]//text()[contains(., "Fluggesellschaft")]/ancestor::tr[1]/following-sibling::tr');

                        return $nodes;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $s = node('./td[4]');

                            if (preg_match('#^(\w{2})\s+(\d+)\s+(.*)$#i', $s, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                    'Cabin'        => $m[3],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $dateStr = str_replace('/', '.', re('#\d+/\d+/\d+#i', node('./td[2]')));

                            foreach (['Dep' => 5, 'Arr' => 6] as $key => $value) {
                                $s = node('./td[' . $value . ']');

                                if (preg_match('#(\d+:\d+)\s+(.*)#i', $s, $m)) {
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[1]);
                                    $res[$key . 'Name'] = $m[2];
                                }

                                if ($key == 'Arr'
                                        and isset($res['DepDate'])
                                        and isset($res['ArrDate'])
                                        and $res['DepDate'] > $res['ArrDate']) {
                                    $res['ArrDate'] = strtotime('+1 day', $res['ArrDate']);
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
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
        return ["de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
