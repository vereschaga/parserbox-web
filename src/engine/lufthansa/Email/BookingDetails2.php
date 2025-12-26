<?php

namespace AwardWallet\Engine\lufthansa\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;

class BookingDetails2 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?lufthansa#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]lufthansa#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]lufthansa#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "29.06.2016, 07:49";
    public $crDate = "29.06.2016, 07:17";
    public $xPath = "";
    public $mailFiles = "lufthansa/it-5.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";
    private $date;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getDate());

                    if (preg_match("/Departure (\d+)-(\w+)-(\d{4})\b/", $this->parser->getSubject(), $m)) {
                        $date = strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3]);

                        if (!$date) {
                            $this->date = $date;
                        }
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Reservation code:\s+(\w+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re('#Travel\s+dates\s+for:\s+(.*)#');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#\w+\s+\(\w\)\s+(confirmed)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[contains(., "Departure") and contains(., "Arrival")]/following-sibling::tr[1]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $s = node('./td[1]');

                            if (preg_match('#(\w{2})\s+(\d+)#i', $s, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[3]');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dateStr = str_replace('​', '', node('./td[2]'));
                            $date = EmailDateHelper::parseDateRelative($dateStr, $this->date);
                            $res = [];

                            foreach (['Dep' => 5, 'Arr' => 6] as $key => $value) {
                                $s = re('#\d+:\d+#', str_replace('​', '', node('./td[' . $value . ']')));
                                $res[$key . 'Date'] = strtotime($s, $date);
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node('./td[4]');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $s = node('./td[7]');

                            if (preg_match('#(\w+)\s+\((\w)\)#i', $s, $m)) {
                                return [
                                    'Cabin'        => $m[1],
                                    'BookingClass' => $m[2],
                                ];
                            }
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
