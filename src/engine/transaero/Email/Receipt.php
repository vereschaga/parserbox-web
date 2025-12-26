<?php

namespace AwardWallet\Engine\transaero\Email;

class Receipt extends \TAccountCheckerExtended
{
    public $reFrom = "#donotreply@transaero\.ru#i";
    public $reProvider = "#transaero\.ru#i";
    public $rePlain = "#TRANSAERO AIRLINES#i";
    public $typesCount = "1";
    public $langSupported = "ru";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "transaero/it-1604286.eml, transaero/it-1604289.eml";
    public $pdfRequired = "0";

    private $date = 0;

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
                        return re("#НОМЕР\sБРОНИ\s+-\s+([\w\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#ФАМИЛИЯ\s+ИМЯ:\s+(.*)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = re("#БИЛЕТ\sВСЕГО\s+(.*)#");

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        //$regex = '#ДАТА\s+РЕЙС\s+АЭРОПОРТ\s+ВЫЛЕТА\s+ВРЕМЯ\s+АЭРОПОРТ\s+ПРИБЫТИЯ\s+КЛАСС\s+БАГАЖ\n(.*\n.*\n)\s*(.*\n.*\n)#';
                        $regex = '#ДАТА\s+РЕЙС\s+АЭРОПОРТ\s+ВЫЛЕТА\s+ВРЕМЯ\s+АЭРОПОРТ\s+ПРИБЫТИЯ\s+КЛАСС\s+БАГАЖ\n(.*)\nRESTRICTIONS#s';
                        $subj = re($regex);
                        $regex = '#.*\n.*\n\s*#';

                        if (preg_match_all($regex, $subj, $m)) {
                            return $m[0];
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $regex = '#';
                            $regex .= '(?P<DepDay>\d+)(?P<DepMonth>[^\d\s]+)\s+';
                            $regex .= '\w+\s+(?P<FlightNumber>\d+)\s+';
                            $regex .= '(?P<DepCode>\w{3})-(?P<DepName>[^\d]+)\s+';
                            $regex .= '(?P<DepHour>\d{2})(?P<DepMin>\d{2})\s+';
                            $regex .= '(?P<ArrCode>\w{3})-(?P<ArrName>[^\d]+)\s+';
                            $regex .= '(?P<BookingClass>\w)\s+';
                            $regex .= '.*\n';
                            $regex .= '\s+(?P<DepTerminal>TERMINAL\s+\w)\s+(?P<ArrTerminal>TERMINAL\s+\w)\s+';
                            $regex .= 'ARRIVAL:\s*(?P<ArrHour>\d{2})(?P<ArrMin>\d{2})\s*';
                            $regex .= '(?:(?P<ArrDay>\d+)(?P<ArrMonth>[^\d\s]+))?\s*';
                            $regex .= '#';

                            if (preg_match($regex, $text, $m)) {
                                $keys = ['FlightNumber', 'DepCode', 'DepName', 'ArrCode', 'ArrName', 'BookingClass'];
                                $res = array_merge($res, array_intersect_key($m, array_flip($keys)));

                                $res = array_map('trim', $res);
                                array_walk($res, function (&$value, $key) { $value = preg_replace('#\s+#', ' ', $value); });

                                if (!isset($m['ArrDay'])) {
                                    $m['ArrDay'] = $m['DepDay'];
                                }

                                if (!isset($m['ArrMonth'])) {
                                    $m['ArrMonth'] = $m['DepMonth'];
                                }

                                foreach (['Dep', 'Arr'] as $prefix) {
                                    if (isset($m[$prefix . 'Terminal'])) {
                                        $res[$prefix . 'Name'] .= ' (' . $m[$prefix . 'Terminal'] . ')';
                                    }

                                    $str = $m[$prefix . 'Day'] . ' ' . $m[$prefix . 'Month'] . ', ' . $m[$prefix . 'Hour'] . ':' . $m[$prefix . 'Min'];
                                    $res[$prefix . 'Date'] = strtotime($str, $this->date);
                                }
                            }

                            return $res;
                        },
                    ],
                ],
            ],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('Date'));
        $result = parent::ParsePlanEmail($parser);

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["ru"];
    }
}
