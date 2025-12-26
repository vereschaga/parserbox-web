<?php

namespace AwardWallet\Engine\lastminute\Email;

class Confirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#When\s+you\s+book\s+a\s+Flight\s+with\s+lastminute\.com|Lastminute\s+Rede\s+atuando\s+como\s+agente|solicite\s+una\s+factura\s+de\s+lastminute\.com#i', 'blank', '/1'],
        ['#Buchungscode\s+der\s+Fluggesellschaft\s*-([^\n]+\s+){1,4}?[^\n]+?\s+›\s+[^\n]+#si', 'blank', '5000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#Ihre\s+Flugbuchung\s+nach.+?ist\s+bestätigt#i', 'blank', ''],
    ];
    public $reFrom = [
        ['#donotreply@lastminute\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#lastminute\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = 'en, pt, de, es';
    public $typesCount = "4";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "28.08.2015, 11:01";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "lastminute/it-1608570.eml, lastminute/it-1723710.eml, lastminute/it-1768071.eml, lastminute/it-3024586.eml, lastminute/it-6719459.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";
    public $dict;
    public $lang;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // dictionary for parsing
                    $this->lang = "en";
                    $this->dict = [
                        'en' => ['RecordLocator' => '(?:Reservation\s+number|Airline\s+Reference)\s*\-', 'Passengers' => 'Passengers',
                            'TotalCharge'        => 'Total\s+charged', 'Tax' => 'Taxes and fees',
                            'FlightNumber'       => ['to', ''], 'Arrives next day' => 'Arrives next day',
                        ],
                        'pt' => ['RecordLocator' => 'Número\s+de\s+reserva\s*[\-:]*', 'Passengers' => 'Passageiros',
                            'TotalCharge'        => 'Preço\s+total', 'Tax' => 'Taxas e franquias',
                            'FlightNumber'       => ['a', ''], 'Arrives next day' => 'NOTTRANSLATED',
                        ],
                        'de' => ['RecordLocator' => 'Buchungscode der Fluggesellschaft\s*\-', 'Passengers' => 'Reisende',
                            'TotalCharge'        => 'Gesamtpreis', 'Tax' => 'Steuern und Gebühren',
                            'FlightNumber'       => ['nach', ''], 'Arrives next day' => 'Kommt am nächsten Tag an',
                        ],
                        'es' => [
                            'RecordLocator'    => 'Número\s+de\s+reserva\s+-',
                            'Passengers'       => 'Pasajeros',
                            'TotalCharge'      => 'Total cobrado',
                            'Tax'              => 'Impuestos y costes',
                            'FlightNumber'     => ['a', 'to'],
                            'Arrives next day' => 'Llega el día siguiente',
                        ],
                    ];

                    foreach ($this->dict as $lang => $dict) {
                        if (re("#\n\s*" . $dict['RecordLocator'] . "\s*[\w-]+#i") && re("#\n\s*" . $dict['Tax'] . "\s*[\w-]+#i")) {
                            $this->lang = $lang;

                            break;
                        }
                    }

                    if (!isset($this->dict[$this->lang])) {
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#" . $this->dict[$this->lang]['RecordLocator'] . "\s*([\w\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#' . $this->dict[$this->lang]['Passengers'] . '\s+(.*)#');

                        return nice(explode(',', $subj));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#' . $this->dict[$this->lang]['TotalCharge'] . '\s+(.*)#');

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#' . $this->dict[$this->lang]['Tax'] . '\s+(.*)#'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $pref1 = "//text()[contains(., '" . $this->dict[$this->lang]['Tax'] . "')]/ancestor::table[2]";
                        $pref2 = "${pref1}/tbody";
                        $suf = "tr[contains(., ':') and position() < last() - 1]";
                        $xpath = "${pref1}/${suf}|${pref2}/${suf}";

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $subj = node('.//table[2]');
                            $regex = '#(?:Departure\s+terminal|Terminal\s+de\s+partida):\s+(\w+)\s*#i';

                            if (preg_match($regex, $subj, $m)) {
                                $depTerminal = $m[1];
                                $subj = preg_replace($regex, '', $subj);
                            }
                            $regex1 = '#';
                            $regex1 .= '(?<AirlineName>.*)\s+';
                            $regex1 .= '\w+?(?<FlightNumber>\d+)\s+';
                            $regex1 .= '(?<DepName>.*)\s+(?:' . $this->dict[$this->lang]['FlightNumber'][0] . '|' . $this->dict[$this->lang]['FlightNumber'][1] . ')\s+';
                            $regex1 .= '(?<ArrName>.*)\s+';
                            $regex1 .= '(?<Cabin>\w+)';
                            $regex1 .= '#ui';

                            $regex2 = '#';
                            $regex2 .= '(?<AirlineName>.*)\s+';
                            $regex2 .= '\w+?(?<FlightNumber>\d+)\s?';
                            $regex2 .= '(?<DepName>.*Airport)\s+(?:' . $this->dict[$this->lang]['FlightNumber'][0] . '|' . $this->dict[$this->lang]['FlightNumber'][1] . ')\s?';
                            $regex2 .= '(?<ArrName>.*Airport)\s?';
                            $regex2 .= '(?<Cabin>.*)';
                            $regex2 .= '#ui';

                            if (preg_match($regex1, $subj, $m) || preg_match($regex2, $subj, $m)) {
                                copyArrayValues($res, $m, ['AirlineName', 'FlightNumber', 'DepName', 'ArrName', 'Cabin']);

                                if (isset($depTerminal)) {
                                    $res['DepName'] .= ' (Terminal ' . $depTerminal . ')';
                                }
                            }

                            return $res;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $subj = node('.//table[1]');
                            $regex = '#';
                            $regex .= '\S+\s+(?<Day>\d+)\s+(?<Month>\w+)\s+(?<Year>\d{2,4})\s?';
                            $regex .= '(?<DepTime>\d{2}:\d{2})\s+-\s+';
                            $regex .= '(?<ArrTime>\d{2}:\d{2})\s*';
                            $regex .= '(?<Duration>.*)?';
                            $regex .= '#';

                            if (preg_match($regex, $subj, $m)) {
                                foreach (['Dep', 'Arr'] as $pref) {
                                    $s = $m['Day'] . ' ' . en($m['Month'], $this->lang) . ' ' . $m['Year'] . ', ' . $m["${pref}Time"];
                                    $m[$pref . 'Date'] = strtotime($s);
                                }
                                copyArrayValues($res, $m, ['DepDate', 'ArrDate']);
                                $res['Duration'] = (isset($m['Duration']) and $m['Duration']) ? $m['Duration'] : null;
                            }

                            if (strpos($subj, $this->dict[$this->lang]['Arrives next day']) !== false) {
                                $res['ArrDate'] = strtotime("+1 day", $res['ArrDate']);
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
        return 4;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'pt', 'de', 'es'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
