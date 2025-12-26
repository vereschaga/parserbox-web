<?php

namespace AwardWallet\Engine\tripbam\Email;

class TripBAMFoundARateForYou extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#tripBAM\s+found\s+a\s+rate\s+for\s+you#i', 'us', ''],
    ];
    public $reFrom = [
        ['#testnotifications@tripbam\.com]#i', 'us', ''],
    ];
    public $reProvider = [
        ['#tripbam#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "15.01.2015, 11:59";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "tripbam/it-2220591.eml, tripbam/it-2222356.eml, tripbam/it-2351737.eml, tripbam/it-2353667.eml, tripbam/it-2356629.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $html = clear("#<META http-equiv=\"Content\-Type\"[^>]+>#i", $this->html());

                    $text = $this->setDocument("source", $html);

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelCategory" => function ($text = '', $node = null, $it = null) {
                        return HOTEL_CATEGORY_SHOP;
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return [
                            'ConfirmationNumber' => CONFNO_UNKNOWN,
                            'ExtProperties'      => [
                                'Segment_id' => re('#Segment_id\s*=\s*(\d+)#i'),
                                'Member_id'  => re('#Member_id\s*=\s*(\d+)#i'),
                            ],
                        ];
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $s = implode("\n", nodes('(//text()[normalize-space(.) = "Room Desc:"]/ancestor::td[3])[last()]//text()'));

                        if (preg_match('#^\s*(.*?/night)\s+at\s+([^\n]+)\s+((?s).*?)\s+Room\s+Desc#is', $s, $m)) {
                            $res['Rate'] = cost($m[1]);
                            $res['Currency'] = currency($m[1]);
                            $res['HotelName'] = $m[2];
                            $res['Address'] = nice($m[3], ',');

                            if (preg_match('#(.*),\s+(.*?),\s+(\w+)\s+\((\w+)\)\s+(\d+)#i', $res['Address'], $mm)) {
                                $da['AddressLine'] = $mm[1];
                                $da['CityName'] = $mm[2];
                                $da['StateProv'] = $mm[3];
                                $da['Country'] = $mm[4];
                                $da['PostalCode'] = $mm[5];
                                $res['DetailedAddress'] = $da;
                            }

                            return $res;
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $emailDateStr = orval(
                            re('#Sent:\s+(.*)#i'),
                            $this->parser->getHeader('date')
                        );

                        if (!$emailDateStr) {
                            return null;
                        }
                        $emailDate = strtotime($emailDateStr);

                        if (!$emailDate) {
                            return null;
                        }

                        if (preg_match('#(?:on|for)\s+(\w+\s+\d+)\s+-\s+(\w+\s+\d+)\s+\(\d+\s+night#i', $text, $m)) {
                            $ci = strtotime($m[1] . ', ' . date('Y', $emailDate));
                            $co = strtotime($m[2] . ', ' . date('Y', $emailDate));

                            if ($ci and $co) {
                                if ($ci < $emailDate) {
                                    $ci = strtotime('+1 year', $ci);
                                    $co = strtotime('+1 year', $co);
                                }

                                if ($co < $ci) {
                                    $co = strtotime('+1 year', $co);
                                }
                            }

                            return [
                                'CheckInDate'  => $ci,
                                'CheckOutDate' => $co,
                            ];
                        }
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node('(//text()[normalize-space(.) = "Room Desc:"]/ancestor::td[3])[last()]//td[contains(., "Cancellation:") and not(.//td)]/following-sibling::td[1]');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return node('(//text()[normalize-space(.) = "Room Desc:"]/ancestor::td[3])[last()]//td[contains(., "Room Desc:") and not(.//td)]/following-sibling::td[1]');
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return node('(//text()[normalize-space(.) = "Room Desc:"]/ancestor::td[3])[last()]//td[contains(., "Rate Desc:") and not(.//td)]/following-sibling::td[1]');
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
