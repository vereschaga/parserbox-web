<?php

namespace AwardWallet\Engine\lanpass\Email;

class It3664035 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Da LAN Flüge zwischen#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]lan[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]lan[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "25.03.2016, 12:59";
    public $crDate = "25.03.2016, 12:45";
    public $xPath = "";
    public $mailFiles = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->anchor = 0;

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('Ihr Reservierungscode ist :  (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[contains(text(), 'Passagier(e)')]/following::span[1]"));
                        $q = white('^ (.+?) $');

                        if (preg_match_all("/$q/imu", $info, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Preis  (\S.+?) \n');

                        return total($x);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Gepäckstücke')]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = rew('\b ([A-Z]+\d+) \b');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( ([A-Z]{3}) \)');

                            return ure("/$q/isu", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time = uberTime(1);

                            $date = en($date);
                            $date = orval(strtotime($date), $this->anchor);
                            $dt = strtotime($time, $date);

                            if ($dt < $this->anchor) {
                                $dt = strtotime('+1 day', $dt);
                            }

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( ([A-Z]{3}) \)');

                            return ure("/$q/isu", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(2);
                            $time = uberTime(2);

                            $date = en($date);
                            $date = orval(strtotime($date), $this->anchor);
                            $dt = strtotime($time, $date);

                            if ($dt < $this->anchor) {
                                $dt = strtotime('+1 day', $dt);
                            }
                            $this->anchor = $dt;

                            return $dt;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('(.+?) -', node('./td[5]'));
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return reni('- (\w)', node('./td[5]'));
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
