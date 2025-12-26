<?php

namespace AwardWallet\Engine\golair\Email;

class It3378847 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Seu número Smiles é#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = [
        ['Smiles e Prata:#i', 'blank', '/1'],
    ];
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]smiles[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]smiles[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "23.12.2015, 11:48";
    public $crDate = "22.12.2015, 15:03";
    public $xPath = "";
    public $mailFiles = "golair/it-3378847.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $date = $this->parser->getHeader('date');
                    $this->anchor = strtotime($date);

                    $text = $this->setDocument('application/pdf', 'complex');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('CÓDIGO DA RESERVA (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = reni('Fraquia de bagagem :  (.+?)  Check-in');

                        return [$name];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Total : (\w.+?) \n');

                        return total($x);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $q = white('\s+ \w+ \s+ [A-Z0-9]{2} \s+ \d+ \s+ [A-Z]{3} \s+');

                        return splitter("/($q)/su");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return reni('\w+ \w{2} (\d+)');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\s+ ([A-Z]{3}) \s+');
                            $code = ure("/$q/su", 1);

                            return $code;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('/Partindo às:[\s\n]*(\d{2}:\d{2})[\s\n]*\([^\d\s]{3},\s+([^\d\s]{3}\s+\d{1,2})\)/u', $text, $matches)) {
                                $time = $matches[1];
                                $date = $matches[2];
                            }
                            $date = en($date);

                            $dt = strtotime($date, $this->anchor);

                            if ($time) {
                                $dt = strtotime($time, $dt);
                            }

                            if ($dt < $this->anchor) {
                                $dt = strtotime('+1 year', $dt);
                            }

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\s+ ([A-Z]{3}) \s+');
                            $code = ure("/$q/su", 2);

                            return $code;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('/Chegando às:[\s\n]*(\d{2}:\d{2})[\s\n]*\([^\d\s]{3},\s+([^\d\s]{3}\s+\d{1,2})\)/u', $text, $matches)) {
                                $time = $matches[1];
                                $date = $matches[2];
                            }
                            $date = en($date);

                            $dt = strtotime($date, $this->anchor);

                            if ($time) {
                                $dt = strtotime($time, $dt);
                            }

                            if ($dt < $this->anchor) {
                                $dt = strtotime('+1 year', $dt);
                            }
                            $this->anchor = $dt;

                            return $dt;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            $air = reni('\w+ (\w{2})');

                            return [
                                'AirlineName'   => $air,
                                'FlightLocator' => reni("$air - (\w+)", $this->text()),
                            ];
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return reni('(\w+) Class\b');
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
        return ['pt'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
