<?php

namespace AwardWallet\Engine\qmiles\Email;

use PlancakeEmailParser;

class It1895230 extends \TAccountCheckerExtended
{
    public $reFrom = "#@qatarairways[.]com#i";

    public $reProvider = "#[@.]qatarairways[.]com#i";

    public $mailFiles = "qmiles/it-1895230.eml, qmiles/it-1895232.eml, qmiles/it-1898223.eml, qmiles/it-1904879.eml, qmiles/it-6804082.eml";

    private $reBodyPDF = [
        'BOARDING PASS',
    ];

    private $patterns = [
        'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?[-\/] ?)?\d{1,3}', // 175-2345005149-23  |  1752345005149/23
        'ffNumber' => '[-A-Z\d\/ ]*\d[-A-Z\d\/]*', // QR 240770516  |  G3-117067742/SMIL
        'cabinValues' => '(?:ECONOMY|BUSINESS)',
        'seat' => '\d+[A-Z]', // 25J
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'complex');

                    return xpath("//div[contains(@id, 'page')]");
                },

                "#^\s*BOARDING\s*PASS#i" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $subject = $this->parser->getSubject();
                        return preg_match("/ for booking ref[.\s]*(?-i)([A-Z\d]{5,8})(?:\s+for\s|\s*$)/i", $subject, $m) || preg_match("/予約コード[:：\s]+([A-Z\d]{5,8})\s*$/", $subject, $m) ? $m[1] : CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = node('(.//b)[2]');

                        return [$this->normalizeTraveller(nice($name))];
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return [$this->http->FindSingleNode("(//p[starts-with(normalize-space(),'E-Ticket No')]/following-sibling::p[not(contains(normalize-space(),'Frequent Flyer'))][1])[1]", null, true, "/^{$this->patterns['eTicket']}$/")];
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $ffNumber = $this->http->FindSingleNode("(//p[starts-with(normalize-space(),'Frequent Flyer')]/following-sibling::p[not({$this->eq($it['TicketNumbers'])})][1])[1]", null, true, "/^{$this->patterns['ffNumber']}$/");

                        return $ffNumber ? [$ffNumber] : null;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath(".");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = node("(.//*[contains(text(), 'Class Of Travel')]/following::p[2])[1]");

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $name = node(".//*[contains(text(), 'Departure From')]/following::p[4]");

                            return nice($name);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node(".//*[contains(text(), 'Departure Time')]/following::p[3]");
                            $time = node(".//*[contains(text(), 'Departure Time')]/following::p[4]");

                            if (strpos($date, ':') !== false) {
                                $date = node(".//*[contains(text(), 'Departure Time')]/following::p[2]");
                                $time = node(".//*[contains(text(), 'Departure Time')]/following::p[3]");
                            }

                            $dt = "$date, $time";
                            $dt = uberDateTime($dt);

                            return strtotime($dt);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $name = node(".//*[contains(text(), 'Departure From')]/following::p[5]");

                            return nice($name);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $class = node("descendant::text()[contains(normalize-space(),'Departure From')]/preceding::p[not(contains(.,'Zone'))][3]", $node, true, "/^{$this->patterns['cabinValues']}$/i");

                            return nice($class);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seat = node("descendant::text()[contains(normalize-space(),'Departure From')]/preceding::p[not(contains(.,'Zone'))][2]", $node, true, "/^{$this->patterns['seat']}$/");

                            return $seat !== null ? [nice($seat)] : null;
                        },
                    ],
                ],

                "#^\s*Online\s*Check-in\s*Confirmation#i" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $conf = node(".//*[contains(text(), 'Booking Reference')]/following::p[2]");

                        return nice($conf);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = node("(.//b)[2]");

                        return [$this->normalizeTraveller(nice($name))];
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $ffNumber = $this->http->FindSingleNode("(//p[starts-with(normalize-space(),'Frequent Flyer')]/following-sibling::p[not({$this->eq($it['RecordLocator'])})][1])[1]", null, true, "/^{$this->patterns['ffNumber']}$/");

                        return $ffNumber ? [$ffNumber] : null;
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return [$this->http->FindSingleNode("(//p[starts-with(normalize-space(),'ETicket No')]/preceding-sibling::p[not({$this->eq($it['AccountNumbers'])})][1])[1]", null, true, "/^{$this->patterns['eTicket']}$/")];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = node(".//*[contains(text(), 'Departure From')]/preceding::p[3]");

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $name = node(".//*[contains(text(), 'Departure From')]/following::p[4]");

                            return nice($name);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node(".//*[contains(text(), 'Departure Time')]/following::p[3]");
                            $time = node(".//*[contains(text(), 'Departure Time')]/following::p[4]");

                            $dt = "$date, $time";
                            $dt = uberDateTime($dt);

                            return strtotime($dt);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $name = node(".//*[contains(text(), 'Departure From')]/following::p[5]");

                            return nice($name);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $class = node("descendant::text()[contains(normalize-space(),'Departure From')]/preceding::p[2]", $node, true, "/^{$this->patterns['cabinValues']}$/i");

                            return nice($class);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $seat = node("descendant::text()[contains(normalize-space(),'Departure From')]/preceding::p[1]", $node, true, "/^{$this->patterns['seat']}$/");

                            return $seat !== null ? [nice($seat)] : null;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    // return $it;
                    return uniteAirSegments($it);
                },
            ],
        ];
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $detectProv = stripos($parser->getCleanFrom(), '@qatarairways.com') !== false
            || $this->http->XPath->query("//text()[{$this->eq('Your Qatar Airways Team')}]");

        $pdfs = $parser->searchAttachmentByName('.*\.pdf');
        $text = '';

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf) || !$detectProv && stripos($textPdf, 'Qatar Airways') === false) {
                continue;
            }

            $text .= $textPdf;
        }

        foreach ($this->reBodyPDF as $re) {
            if (stripos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }
    
    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MASTER|MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/is",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/is",
            '/^([^\/]+?)(?:\s*[\/]+\s*)+([^\/]+)$/',
        ], [
            '$1',
            '$1',
            '$2 $1',
        ], $s);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }
}
