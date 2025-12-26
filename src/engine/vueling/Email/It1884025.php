<?php

namespace AwardWallet\Engine\vueling\Email;

class It1884025 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@vueling[.]com#i";
    public $reFrom = "#@vueling[.]com#i";
    public $reProvider = "#[@.]vueling[.]com#i";
    public $mailFiles = "vueling/it-1884025.eml, vueling/it-3515562.eml, vueling/it-5091240.eml, vueling/it-5688272.eml, vueling/it-7389619.eml, vueling/it-7518081.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'complex');

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $conf = node("//div[@id = 'page1-div']/p[last() - 4]", null, false, '#(\w+)#'); // yes by numbers here =\

                        return nice($conf);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengers = [];
                        $nodes = $this->http->XPath->query('//div[starts-with(@id,"page") and contains(@id,"-div")]//text()[contains(.,"/") and string-length(normalize-space(.))>7 and string-length(normalize-space(.))<11][1]//following::text()[normalize-space(.)!=""][1]');

                        foreach ($nodes as $node) {
                            $passengers[] = $this->http->FindSingleNode('.', $node);
                        }

                        return array_unique($passengers);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//div[starts-with(@id,"page") and contains(@id,"-div")]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $nodes = $this->http->XPath->query('p[position()>4]', $node);

                            foreach ($nodes as $p) {
                                $text = node('.', $p);

                                if (preg_match('/[A-Z]{2}\d+/', $text)) {
                                    return uberAir($text);
                                }
                            }

                            return false;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#(?:Origin|Origen|Origine|Abflugort|Vertrekplaats|Origem):\s*([A-Z]+)#i");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $time = null;
                            $nodes = $this->http->XPath->query('p', $node);

                            foreach ($nodes as $p) {
                                $text = node('.', $p);

                                if (preg_match('/(\d{2}:\d{2})/', $text, $matches)) {
                                    $time = $matches[1];

                                    break;
                                }
                            }

                            if (preg_match('#(\d{1,2}/\d{1,2}/\d{4})#', node('.//text()[contains(.,"/") and string-length(normalize-space(.))>7 and string-length(normalize-space(.))<11][1]'), $matches)) {
                                $dt = \DateTime::createFromFormat('d/m/Y', $matches[1]);
                                $dt->modify($time);

                                return $dt->getTimestamp();
                            }

                            return null;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#(?:Destination|Destino|Destinazione|Zielort|Bestemming|Destino):\s*([A-Z]+)#i");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $nodes = $this->http->XPath->query('p[position()>6]', $node);

                            foreach ($nodes as $p) {
                                $text = trim(node('.', $p));

                                if (preg_match('/^[A-Z\d]{2,3}$/', $text)) {
                                    return $text;
                                }
                            }

                            return false;
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#(?:Your\s*flight\s*takes|Tu\s+vuelo\s+tiene\s+una\s+duración\s+de|Ihr\s+Flug\s+hat\s+eine\s+Dauer\s+von|Je\s+vlucht\s+duurt|O\s+teu\s+voo\s+tem\s+uma\s+duração\s+de)\s*(.+?)[.]#i");
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
        return ['en', 'es', 'fr', 'de', 'pt'];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (stripos($body, 'vueling.com') !== false) {
            return true;
        }

        return parent::detectEmailByBody($parser);
    }
}
