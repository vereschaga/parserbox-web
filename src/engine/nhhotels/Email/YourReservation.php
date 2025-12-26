<?php

namespace AwardWallet\Engine\nhhotels\Email;

class YourReservation extends \TAccountCheckerExtended
{
    public $reBody = 'www.nh-hotels.com';
    public $reBody2 = 'Reservation number';
    public $reBody3 = 'Boekingsnummer';
    public $reBody4 = 'Buchungsnummer';

    public $rePDF = "";

    public $reSubject = 'Your reservation';
    public $reSubject2 = 'Uw reservering';
    public $reSubject3 = 'NH Hotels';
    public $reSubject4 = 'NH ';
    public $reSubject5 = 'Ihre Reservierung für';

    public $reFrom = '#nh@nh-hotels\.com#i';

    public $fnLanguage = "";
    public $langSupported = "en, de";
    public $typesCount = "2";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "22.01.2015, 09:26";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "nhhotels/it-1.eml, nhhotels/it-1736442.eml, nhhotels/it-1746282.eml, nhhotels/it-1747046.eml, nhhotels/it-1907992.eml, nhhotels/it-2352011.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:reservation\s*number|Buchungsnummer)\s*[:]?\s*([\w\-]+)#i');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[@alt='Hotel image']/ancestor::tr[1]//strong"));

                        if (!$text) {
                            $text = text(xpath("//*[contains(text(), 'Hotel information') or contains(text(), 'Hotelinformationen')]/ancestor::tr[1]//strong"));
                        }

                        return preg_replace('/\s+/', ' ', $text);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $arr = [
                            'CheckIn' => [
                                'arrival',
                                'Anreise',
                                'Check-in',
                            ],
                            'CheckOut' => [
                                'departure',
                                'Abreise',
                                'Check-out',
                            ],
                        ];
                        $result = [];

                        foreach ($arr as $key => $value) {
                            $s = re('#(?:' . implode('|', $value) . ')\s*:?\s+(\d+/\d+/\d{4})#i');
                            $datetime = \DateTime::createFromFormat('d/m/Y', $s);

                            if ($datetime) {
                                $datetime->setTime(0, 0);
                                $result[$key . 'Date'] = $datetime->getTimestamp();
                            }
                        }

                        return $result;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $text = text(xpath("//*[contains(text(), 'Hotel information') or contains(text(), 'Hotelinformationen')]/ancestor::tr[1]/td[2]/font[2]"));

                        return re("#\s*(.*)\s*(?:telephone|Telefon)#im");
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $phone = re('#(?:telephone|Telefon)\.?\s*(.*?)\s*[-]?\s*fax[.]?#im');

                        return preg_replace('/[.]/', '-', $phone);
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        $fax = re('#fax[.]?\s*(.*?)\s*\s*e-?mail[.]?#im');

                        return preg_replace('/[.]/', '-', $fax);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return nice([re('#(?:dear\s*mr\.?\s*/\s*mr?s\.?|Sehr\s+geehrte/r\s+Frau\s+/\s+Herr)\s*([\w ]*)\s*,#i')]);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        // $text = re('#reservation\s*details(.*)final\s*price#ims');
                        $g = re("#(\d+)\s*(?:adult|Erwachsene)\.?#i", $text);

                        if (!$g and preg_match_all('#Guest\s*:\s*.*#i', $text, $m)) {
                            $g = count($m[0]);
                        }

                        return $g;
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        //$text = re('#reservation\s*details(.*)final\s*price#ims');
                        return re("#(?:room[s]?|zimmer)\s*[.]?\s*(\d+)#i", $text);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        //$text = re('#reservation\s*details(.*)final\s*price#ims');
                        $rate = re('#rate\s*?(?:vat|MWST)\s+(.*)#i');

                        return $rate ? $rate . ' / night' : '';
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        //return re('#Stornierungen und Änderungen sind nicht möglich#i');
                        $xpath = '//tr[(contains(., "Reservation Guarantee Policy") or contains(., "Reservierungsbedingungen")) and not(.//tr)]/following-sibling::tr[1]';

                        return nice(orval(node($xpath), re('#Cancellations not allowed#i')));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        //$text = re('#reservation\s*details(.*)final\s*price#ims');
                        return re("#type?[:]?\s*(\w+\s*(?:room|zimmer))#i");
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        //$text = re('#reservation\s*details(.*)final\s*price#ims');
                        $x = '//text()[contains(., "Rooms 1")]/ancestor::td[1]//img[contains(@src, "blt.gif")]/following-sibling::text()[not(contains(., "Guest")) and not(contains(., "adults"))]';
                        $s = implode('. ', nodes($x));

                        if ($s) {
                            return $s;
                        }
                        $variants = '(?:Room|Zimmer)';

                        return nice(re('#(?:' . $variants . '\s+\d+|\d+\s+' . $variants . ').*?:\s+(.*)#i', $text));
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $r = '#(?:final\s*price|Endpreis|total\s+price\s+of\s+your\s+stay)\s+(.*?)(?:\+(.*))?\s*=\s*(.*)#i';

                        if (preg_match($r, $text, $m)) {
                            array_walk($m, function (&$value, $key) { if ($key >= 1) { $value = re('#[\d\.,]+\s*\w+#i', $value); } });

                            return [
                                'Cost'     => cost($m[1]),
                                'Taxes'    => cost($m[2]),
                                'Total'    => cost($m[3]),
                                'Currency' => currency($m[3]),
                            ];
                        }
                    },
                ],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reFrom, $from);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return (strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false)
               || (strpos($body, $this->reBody) !== false && strpos($body, $this->reBody3) !== false)
               || (strpos($body, $this->reBody) !== false && strpos($body, $this->reBody4) !== false);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (strpos($headers["subject"], $this->reSubject) !== false && strpos($headers["subject"], $this->reSubject3) !== false)
               || (strpos($headers["subject"], $this->reSubject2) !== false && strpos($headers["subject"], $this->reSubject3) !== false)
               || (strpos($headers["subject"], $this->reSubject4) !== false && strpos($headers["subject"], $this->reSubject5) !== false);
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en", "de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
