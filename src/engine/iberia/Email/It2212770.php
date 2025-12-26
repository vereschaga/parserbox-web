<?php

namespace AwardWallet\Engine\iberia\Email;

use AwardWallet\Engine\MonthTranslate;

class It2212770 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]iberia[.]com|no-reply@iberiaexpress\.com#i";

    public $reFrom = "#[@.]iberia[.]com#i";

    public $reProvider = "#[@.]iberia[.]com#i";

    public $mailFiles = "iberia/it-1694393.eml, iberia/it-1694394.eml, iberia/it-2141721.eml, iberia/it-2141724.eml, iberia/it-2212770.eml, iberia/it-2348956.eml, iberia/it-4.eml, iberia/it-5.eml, iberia/it-5474224.eml, iberia/it-6098418.eml";

    private $lang = '';

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return ($this->http->XPath->query("//a[contains(@href, 'iberia.com')]")->length > 0)
            && ($this->http->XPath->query("//*[contains(@alt, 'operated by') or contains(@alt, 'operado por')]/ancestor::tr[1]")->length > 0);
    }

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
                        return reni('(?:
								Reservation code |
								Có?digo de reserva
							)  (\w+)
						');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[
							normalize-space(text()) = 'Passenger' or
							normalize-space(text()) = 'Pasajero'
						]/ancestor::tr[1]/following-sibling::tr/td[1]");

                        return nice($ppl);
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $accs = nodes("//*[
							normalize-space(text()) = 'Passenger' or
							normalize-space(text()) = 'Pasajero'
						]/ancestor::tr[1]/following-sibling::tr/td[2]");
                        $accs = filter(nice($accs));
                        $accs = array_map(function ($el) { return str_replace('Japan Airlines ', '', $el); }, $accs);

                        return $accs;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('(?:
								TOTAL PRICE |
								PRECIO TOTAL
							)  (?: .+? Avios \+)?
							(.? [\d,. ]+ .?) \n
						');

                        if (empty($x)) {
                            $x = node("//text()[contains(.,'TOTAL')]/ancestor::*[1]");
                        }

                        $x = preg_replace('/([\d\.,]+)\s+([\d\.,]+)/', '$1$2', $x);
                        $x = preg_replace(['/\s+/', '/TOTAL/i'], [' ', ''], $x);
                        $cur = trim($this->re('/[ ]*(\b[A-Z]{3}\b|[^\d\(\)\., ]{1,3})[ ]*/', $x));

                        if (empty($cur)) {
                            $cur = $this->http->FindSingleNode("//node()[normalize-space(text())='TOTAL PRICE' or normalize-space(text()) = 'PRECIO TOTAL'][1]/following-sibling::node()[normalize-space(text())][last()]", null, true, '/(?:[A-Z]{3}|[^\d\(\)\., ]{1,3})/u');
                        }

                        $cur = str_replace(['€'], ['EUR'], $cur);

                        return [
                            'TotalCharge' => $this->amount($this->re('/([\d\.,]+)/', $x)),
                            'Currency'    => !empty($cur) ? $cur : null,
                        ];
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re_white('(?:
								TOTAL PRICE |
								PRECIO TOTAL
							)  ([\d.,]+ Avios)
						');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Compra confirmada')) {
                            return 'confirmed';
                        }

                        return null;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[
							contains(@alt, 'operated by') or
							contains(@alt, 'operado por')
						]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $info = node('./td[last()]');
                            $fl = reni('^ (\w+\d+)', $info);

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $q = white('\/ (.+? \n .+?) (?:\n|\s{4,})');
                            $name = ure("/$q/isu", 1);
                            $name = nice($name);
                            $res['DepName'] = $name;

                            if (preg_match("#(.+?)(?:,\s+Terminal (\w+)\b.*|)$#", $name, $m)) {
                                $res['DepName'] = nice($m[1]);

                                if (isset($m[2]) && !empty($m[2])) {
                                    $res['DepartureTerminal'] = $m[2];
                                }
                            }

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $q = white('^ (.+?) $ \d+:\d+');
                            $date = ure("/$q/imu", 1);
                            $date = clear('/\s+de\s+/i', $date, ' ');
                            $date = $this->normalizeDate($date);

                            $time = uberTime(1);
                            $date = totime($date);

                            return strtotime($time, $date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $q = white('\/ (.+? \n .+?) (?:\n|\s{4,})');
                            $name = ure("/$q/isu", 2);
                            $name = nice($name);
                            $res['ArrName'] = $name;

                            if (preg_match("#(.+?)(?:,\s+Terminal (\w+)\b.*|)$#", $name, $m)) {
                                $res['ArrName'] = nice($m[1]);

                                if (isset($m[2]) && !empty($m[2])) {
                                    $res['ArrivalTerminal'] = $m[2];
                                }
                            }

                            return $res;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $q = white('^ (.+?) $ \d+:\d+');
                            $date = ure("/$q/imu", 2);
                            $date = preg_replace("#.+\s{4,}(.+)#", '$1', $date);
                            $date = $this->normalizeDate(clear('/\s+de\s+/i', $date, ' '));

                            $time = uberTime(2);
                            $date = totime($date);

                            return strtotime($time, $date);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $cab = reni('(\w+) $');

                            return reni('confirm', $cab) ? null : $cab;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return reni('Seat: ((?:\d+\w ,?)+) \s+');
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return reni('(\d+) Stop');
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en", "es"];
    }

    private function normalizeDate($date)
    {
//        $this->logger->alert("Date: {$date}");
        $in = [
            '/^(?<year>\d{4})\w(?<month>\d{1,2})\w(?<day>\d{2})\w$/u', // 2019年12月26日
            '/^\w+ (?<day>\d{1,2}) (?<month>\w+) (?<year>\d{4})$/u', // domingo 11 noviembre 2012
        ];
        $out = [
            '$1-$2-$3',
            '$1 $2 $3',
        ];
        $date = preg_replace($in, $out, $date);
        $langs = ['es'];

        foreach ($langs as $lang) {
            if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
                if ($en = MonthTranslate::translate($m[1], $lang)) {
                    $date = str_replace($m[1], $en, $date);
                }
            }
        }
//        $this->logger->alert("Date 2: {$date}");
        return $date;
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", trim($s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
