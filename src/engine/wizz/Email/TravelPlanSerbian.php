<?php

namespace AwardWallet\Engine\wizz\Email;

class TravelPlanSerbian extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#noreply@wizzair.com>\s+wrote:#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#noreply@wizzair.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]wizz#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "sr";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "16.06.2016, 11:45";
    public $crDate = "16.06.2016, 11:13";
    public $xPath = "";
    public $mailFiles = "";
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
                        return cell('Код за потврду лета:', +1);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Контакт име клијента:\s+(.*)\s+Предузеће\s+клијента#s'));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Укупни збир', +1));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Датум\s+резервације:\s+(\d+\.\d+\.\d+)#'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//text()[normalize-space(.) = "ОДЛАЗАК" or normalize-space(.) = "ПОВРАТАК"]/ancestor::tr[1]/following-sibling::tr[1]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $s = node('./preceding-sibling::tr[1]/td[2]');

                            if (preg_match('#:\s+(\w{2})\s+(\d+)#', $s, $m)) {
                                return [
                                    'FlightNumber' => $m[2],
                                    'AirlineName'  => $m[1],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $s = node('./td//tr[contains(., "Полази из:")]/following-sibling::tr[1]/td[1]');
                            $s = re('#\((\w{3})\)#', $s);

                            return $s;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $s = node('./td//tr[contains(., "Полази из:")]/following-sibling::tr[2]/td[1]');

                            return strtotime($s);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $s = node('./td//tr[contains(., "Полази из:")]/following-sibling::tr[1]/td[2]');
                            $s = re('#\((\w{3})\)#', $s);

                            return $s;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $s = node('./td//tr[contains(., "Полази из:")]/following-sibling::tr[2]/td[2]');

                            return strtotime($s);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            static $index = 1;
                            $seat = node('//td[@class="passenger-seat"]/div[' . $index . ']') . "\n";
                            $index++;

                            return trim($seat);
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
        return ["sr"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
