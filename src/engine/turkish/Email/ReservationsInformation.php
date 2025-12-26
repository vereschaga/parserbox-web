<?php

namespace AwardWallet\Engine\turkish\Email;

class ReservationsInformation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Thank you for choosing Turkish Airlines', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['callcenter@thy.com', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]thy.com#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "29.06.2016, 12:13";
    public $crDate = "27.06.2016, 11:19";
    public $xPath = "";
    public $mailFiles = "turkish/it-3910820.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";
    public $anchor;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $date = $this->parser->getHeader('date');
                    $date = totime($date);
                    $this->anchor = $date;
                    $texts = implode("\n", $this->parser->getRawBody());
                    $texts = preg_replace("#^(--\w{25,32}(--)?)$#m", "\n", $texts);
                    $posBegin1 = stripos($texts, "Content-Type: text/html");
                    $i = 00;

                    while ($posBegin1 !== false && $i < 30) {
                        $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                        $posEnd = stripos($texts, "\n\n", $posBegin);

                        if (preg_match("#filename=.*EN.*\.htm.*base64#s", substr($texts, $posBegin1, $posBegin - $posBegin1), $m)) {
                            $t = substr($texts, $posBegin, $posEnd - $posBegin);
                            $text .= base64_decode($t);
                        } elseif (preg_match("#quoted-printable.*filename=.*EN.*\.htm#s", substr($texts, $posBegin1, $posBegin - $posBegin1), $m)) {
                            $t = substr($texts, $posBegin, $posEnd - $posBegin);
                            $text .= quoted_printable_decode($t);
                        } elseif (preg_match("#filename=.*EN.*\.htm.*#s", substr($texts, $posBegin1, $posBegin - $posBegin1), $m)) {
                            $t = substr($texts, $posBegin, $posEnd - $posBegin);
                            $text .= $t;
                        }
                        $posBegin1 = stripos($texts, "Content-Type: text/html", $posBegin);
                        $i++;
                    }
                    $text = str_replace("&nbsp;", " ", $text);
                    $this->http->setBody($text);

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return node("(//text()[contains(normalize-space(), 'Reservation Code')]/following::text()[normalize-space(.)][1])[1]");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[contains(text(), 'Name and Surname')]/following::tbody[1]/tr/td[1]");

                        return nice($ppl);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//*[contains(text(), "Itinerary")]/ancestor::*/following-sibling::table//*[contains(text(), "Business")]/ancestor::tr[1]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $s = node('./td[3]');

                            if (preg_match('#(\w{2})(\d+)#i', $s, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $s = implode("\n", nodes('./td[1]//text()'));

                            if (preg_match('#(\w{3})\s+(\w{3})$#i', $s, $m)) {
                                return [
                                    'DepCode' => $m[1],
                                    'ArrCode' => $m[2],
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $dateStr = node('./td[2]') . ' ' . date("Y", $this->anchor);

                            foreach (['Dep' => 4, 'Arr' => 5] as $key => $value) {
                                $t = node('./td[' . $value . ']');
                                $res[$key . 'Date'] = strtotime($dateStr . ', ' . $t);
                            }

                            return $res;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node('./td[7]');
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return node('./td[6]');
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
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
