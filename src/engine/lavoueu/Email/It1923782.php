<?php

namespace AwardWallet\Engine\lavoueu\Email;

class It1923782 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*De\s*:[^\n]*?@lavoueuviagens[.]com[.]br#i";
    public $rePlainRange = "1500";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $reFrom = "#@lavoueuviagens[.]com[.]br#i";
    public $reProvider = "#@lavoueuviagens[.]com[.]br#i";
    public $xPath = "";
    public $mailFiles = "lavoueu/it-1923782.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $pref = 'Código\s*da\s*reserva\s*[(]\s*ou\s*localizador\s*[)]\s*:';

                        return re("#$pref\s*([\w-]+)#is");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = cell('Nome do passageiro:', +1);

                        return [nice($name)];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $tot = node("//*[contains(text(), 'Faturado')]/ancestor-or-self::td[1]/following-sibling::td[last()]");

                        return total($tot);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//span/img[contains(@src, 'global/airlines')]/ancestor-or-self::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $num = node('td[2]');

                            return nice($num);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $code = node('td[3]');

                            return re('/(\w+)/', $code);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $dt = node('td[4]');
                            $dt = \DateTime::createFromFormat('d/m H:i Y', $dt . " " . date("Y", $this->date));

                            if (!$dt) {
                                return;
                            }

                            return $dt->getTimestamp();
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $code = node('td[5]');

                            return re('/(\w+)/', $code);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $dt = node('td[6]');
                            $dt = \DateTime::createFromFormat('d/m H:i Y', $dt . " " . date("Y", $this->date));

                            if (!$dt) {
                                return;
                            }

                            return $dt->getTimestamp();
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            $air = node('td[1]//img[1]/@alt');

                            return nice($air);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $cls = node('td[9]');

                            return re('/(\w{1,2})/', $cls);
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $stops = node('td[8]');

                            return re('/(\d+)/', $stops);
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
        return ["pt"];
    }
}
