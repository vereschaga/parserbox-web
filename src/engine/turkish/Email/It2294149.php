<?php

namespace AwardWallet\Engine\turkish\Email;

class It2294149 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]thy[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]thy[.]com#i";
    public $reProvider = "#[@.]thy[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "19.01.2015, 10:37";
    public $crDate = "25.12.2014, 10:08";
    public $xPath = "";
    public $mailFiles = "turkish/it-2294149.eml, turkish/it-2340341.eml, turkish/it-8764376.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $date = orval(
                        reni('Date: (\d+ - \d+ - \d+)'),
                        $this->parser->getHeader('date')
                    );
                    $date = totime($date);
                    $this->anchor = $date;
                    $texts = implode("\n", $this->parser->getRawBody());
                    $texts = preg_replace("#^(--\w{25,32}(--)?)$#m", "\n", $texts);
                    $posBegin1 = stripos($texts, "Content-Type: text/html");
                    $i = 0;

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

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        $tn = nodes("//*[contains(text(), 'Name and Surname')]/following::tbody[1]/tr/td[2]");
                        $tn = array_filter($tn);
                        $tickets = [];

                        foreach ($tn as $value) {
                            $tickets = array_merge($tickets, array_filter(explode(" ", $value)));
                        }

                        return $tickets;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Ticketed Segments')]/following::table[1]//*[
							contains(text(), 'Economy') or
							contains(text(), 'Tarife')
						]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = node('./td[3]');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('
								(?P<DepCode> \w{3})
								(?P<ArrCode> \w{3})
							');
                            $res = re2dict($q, node('.'));

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $date = date_carry($date, $this->anchor);

                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $dt1 = strtotime($time1, $date);
                            $dt2 = date_carry($time2, $dt1);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return nice(node('./td[7]'));
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return nice(node('./td[6]'));
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
