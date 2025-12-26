<?php

namespace AwardWallet\Engine\vueling\Email;

class It1658068 extends \TAccountCheckerExtended
{
    public $reFrom = "#vueling#i";
    public $reProvider = "#vueling#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?vueling#i";
    public $typesCount = "1";
    public $langSupported = "en, es";
    public $reSubject = "";
    public $reHtml = "";
    public $xPath = "";
    public $mailFiles = "vueling/it-1658068.eml";
    public $pdfRequired = "0";
    public $date = null;

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
                        return re("#Número de confirmación:\s*([\w\d]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//strong[contains(text(), 'Nombre')]/ancestor::tr[1]/following-sibling::tr[position() > 1]/td[1]");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        re('#Importe total.*?([\d,.]+)\s*(\w+)#ims');

                        return ["TotalCharge" => re(1), "Currency" => re(2)];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Estado:\s*(.*?)\n#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(en(re("#Fecha de reserva:.*?,\s*(.*)#")));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#Número de vuelo[:]*\s*([\d|\w]+)#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = node("//strong[contains(text(), 'Nombre')]/ancestor::tr[1]/following-sibling::tr[2]/td[2]", null, false, "#\((\w+)#");

                            if (empty($res)) {
                                $res = node("//strong[contains(text(), 'De')]", null, false, '#\((\w{3})\)#');
                            }

                            return $res;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = node('//td[normalize-space(string())="Ida"]/ancestor::table[1]/following-sibling::table[1]/descendant::tr[1]/following-sibling::tr[1]/td[1]');

                            if (empty($res)) {
                                $res = node("//strong[contains(text(), 'De')]", null, false, '#De (\w+) \(\w{3}\)#');
                            }

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dep = totime(en(node('//td[normalize-space(string())="Ida"]/ancestor::table[1]/following-sibling::table[1]/descendant::tr[1]/following-sibling::tr[4]/td[1]', null, false, '/.*?,\s*(.*)/ims')) . ' ' . node('//td[normalize-space(string())="Ida"]/ancestor::table[1]/following-sibling::table[1]/descendant::tr[1]/following-sibling::tr[3]/td[1]', null, false, '/(.*?)\sh/ims'));
                            $arr = totime(en(node('//td[normalize-space(string())="Ida"]/ancestor::table[1]/following-sibling::table[1]/descendant::tr[1]/following-sibling::tr[4]/td[1]', null, false, '/.*?,\s*(.*)/ims')) . ' ' . node('//td[normalize-space(string())="Ida"]/ancestor::table[1]/following-sibling::table[1]/descendant::tr[1]/following-sibling::tr[3]/td[2]', null, false, '/(.*?)\sh/ims'));

                            if ($dep > $arr) {
                                $arr = strtotime("+1 day", $arr);
                            }
                            $res = ["DepDate" => $dep, "ArrDate" => $arr];

                            if (empty($res) || $res["DepDate"] === false) {
                                $this->date = $this->normalizeDate(node("//text()[contains(normalize-space(.), 'Terminal:')]/preceding-sibling::text()[normalize-space(.)!=''][4]", null, false, '#: \S+ (\d{2} \w+ \d+)#'));
                                $res['DepDate'] = strtotime($this->date . ' ' . node("//text()[contains(normalize-space(.), 'Terminal:')]/preceding-sibling::text()[normalize-space(.)!=''][3]", null, false, '#\w+:\s+(\d{2}:\d{2})#'));
                                $res['ArrDate'] = strtotime($this->date . ' ' . node("//text()[contains(normalize-space(.), 'Terminal:')]/preceding-sibling::text()[normalize-space(.)!=''][2]", null, false, '#\w+:\s+(\d{2}:\d{2})#'));
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $res = node("//strong[contains(text(), 'Nombre')]/ancestor::tr[1]/following-sibling::tr[2]/td[2]", null, false, "#\(\w+-(\w+)#");

                            if (empty($res)) {
                                $res = node("//strong[contains(text(), 'De')]/following-sibling::*[1]", null, false, '#\((\w{3})\)#');
                            }

                            return $res;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $res = node('//td[normalize-space(string())="Ida"]/ancestor::table[1]/following-sibling::table[1]/descendant::tr[1]/following-sibling::tr[1]/td[2]');

                            if (empty($res)) {
                                $res = node("//strong[contains(text(), 'De')]/following-sibling::*[1]", null, false, '#a ([\w\S]+) \(\w{3}\)#');
                            }

                            return $res;
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
        return ['en', 'es'];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\s+(\w+)\s+(\d+)$#",
        ];
        $out = [
            "$2 $1 20$3",
        ];

        return en(preg_replace($in, $out, $str));
    }
}
