<?php

namespace AwardWallet\Engine\spg\Email;

class It2164028 extends \TAccountCheckerExtended
{
    public $mailFiles = "spg/it-1821023.eml, spg/it-1821693.eml, spg/it-1828968.eml, spg/it-1907799.eml, spg/it-2135240.eml, spg/it-2164028.eml, spg/it-2164137.eml, spg/it-2236077.eml";

    private $providerCode = '';

    private $columnLeft = null;
    private $columnRight = null;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('plain');

                    if (empty($text)) {
                        return [];
                    }

                    $text = str_replace("\r\n", "\n", $text);

                    $reservationText = $text;
                    $reservationStart = strpos($reservationText, "Reservation Advice");

                    if ($reservationStart !== false) {
                        $reservationText = substr($reservationText, $reservationStart);
                    }
                    $reservationEnd = strrpos($reservationText, "Remarks");

                    if ($reservationEnd !== false) {
                        $reservationText = substr($reservationText, 0, $reservationEnd);
                    }

                    $tablePos = [0];

                    if (preg_match('/^([> ]*Guest Name.+ )Arrival Flight:/m', $reservationText, $m)) {
                        $tablePos[] = mb_strlen($m[1]);
                    }
                    $table = $this->splitCols($reservationText, $tablePos);

                    if (count($table) === 2) {
                        $this->columnLeft = $table[0];
                        $this->columnRight = $table[1];
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Reservation Number\s*:\s*([A-Z\d-]+)#ix");
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (re("#(?:^.+:\s*)?(.*?)\s+Reservation Confirmation\.?$#", $this->parser->getSubject())) {
                            $this->logger->debug('case 1');
                            $name = re(1);

                            if (!rew('your\s*$', $name)) {
                                return $name;
                            }

                            return $name;
                        }

                        $hotels = [
                            [
                                'email' => 'ElementMiamiInternationalAirport@starwoodhotels.com',
                                'hotel' => 'Element Miami International Airport',
                            ],
                            [
                                'email' => 'WMontreal@starwoodhotels.com',
                                'hotel' => 'W Montreal',
                            ],
                            [
                                'email' => '<WMontreal@starwoodhotels.com>',
                                'hotel' => 'W Montreal',
                            ],
                            [
                                'email' => 'Wmontreal@whotels.com',
                                'hotel' => 'W Montreal',
                            ],
                            [
                                'email' => 'TheWestinPrinceton@starwoodhotels.com',
                                'hotel' => 'The Westin Princeton',
                            ],
                            [
                                'email' => 'SheratonSanJose@starwoodhotels.com',
                                'hotel' => 'Sheraton San Jose',
                            ],
                            [
                                'email' => 'ElementLexington@marriott.com',
                                'hotel' => 'Element Lexington',
                            ],
                        ];
                        $from = $this->parser->getHeader('From');
                        $returnTo = isset($this->parser->getHeader('return-path')[1]) ? $this->parser->getHeader('return-path')[1] : null;
                        $fromText = nice(re('#\n\s*From:\s+<?(.*?@.*?)[><]#'));
                        $searchIn = [
                            $from,
                            $returnTo,
                            $fromText,
                        ];

                        foreach ($hotels as $h) {
                            if (in_array($h['email'], $searchIn)) {
                                $this->logger->debug('case 2');

                                return $h['hotel'];
                            }
                        }

                        $this->logger->debug('case 3'); // it-2236077.eml
                        $addr = re("#\n\s*([^\n]+)\s+Phone:#");

                        return [
                            'HotelName' => re("#^[^,]+,\s*([^,]+)#", $addr),
                            'Address'   => $addr,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        re("#\s{2,}Arrival Date\s*:\s*(\d+)\-(\d+)\-(\d{4})#i");
                        $date = re(3) . '-' . re(1) . '-' . re(2);

                        re("#\s{2,}Arrival Time\s*:\s*(\d+:\d+\s*[APM]+)#i");
                        $date .= re(1);

                        return totime($date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        re("#\s{3,}Departure Date\s*:\s*(\d+)\-(\d+)\-(\d{4})#ix");

                        return totime(re(3) . '-' . re(1) . '-' . re(2));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $name = arrayVal($it, 'HotelName');

                        if ($name
                            && preg_match("/^[> ]*{$name}$(?<address>(?:\s+^.{2,}$){1,3})\s+^[> ]*(?:Phone:[ ]*)?(?<phone>[+(\d][-. \d)(]{5,}[\d)])$/im", $text, $m)
                        ) {
                            return [
                                'Address' => preg_replace('/\s+/', ' ', trim($m['address'])),
                                'Phone'   => $m['phone'],
                            ];
                        }

                        return orval(
                            arrayVal($it, 'Address'),
                            $name
                        );
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            arrayVal($it, 'Phone'),
                            re("#\n\s*Phone\s*:\s*([+(\d][-. \d)(]{5,}[\d)])#")
                        );
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        $r = re("#Fax\s*:\s*([+(\d][-. \d)(]{5,}[\d)])#");

                        if (!empty($r)) {
                            return trim($r);
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        if (preg_match("/^[> ]*Guest Name(?:\(s\))?[\s:]+([[:alpha:]][-.\'[:alpha:]\s]*?[[:alpha:]])\s+^[> ]*Company Name/mu", $this->columnLeft, $m)) {
                            return array_unique(preg_split('/[> ]*\n+[> ]*/', $m[1]));
                        }

                        return null;
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Number of Guests\s*:\s*(\d+)#ix");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Number of Rooms\s*:\s*(\d+)#ix");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*Daily Room Rate[:\s]+([^\w\d]+[\d.]+)#"),
                            re("#\n\s*Daily Room Rate[^\n]*?Number of Guests:\s*\d+\s+([A-Z ,.]+)#")
                        );
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#RESERVATIONS ARE HELD[^a-z]*?\n{2,}#s"));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Accommodation\s*:\s*([^\n]*?)\s{2,}#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Status\s*:\s*([^\n^\r]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        re("#\s{2,}Date\s*:\s*(\d+)\-(\d+)\-(\d{4})#");
                        $date = re(3) . '-' . re(1) . '-' . re(2);

                        re("#\s{2,}Time\s*:\s*(\d+:\d+\s*[APM]+)#i");
                        $date .= re(1);

                        return totime($date);
                    },
                ],
            ],
        ];
    }

    public static function getEmailProviders()
    {
        return ['spg', 'marriott'];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@starwoodhotels.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'reservation confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->http->XPath->query('//node()[contains(normalize-space(),"Reservation Advice")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignProvider($parser->getHeaders());
        $result = parent::ParsePlanEmail($parser);
        $result['providerCode'] = $this->providerCode;
        $result['emailType'] = 'ReservationAdvice';

        return $result;
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

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@marriott.com') !== false
            || $this->http->XPath->query('//node()[contains(.,"@marriott.com")]')->length > 0
        ) {
            $this->providerCode = 'marriott';

            return true;
        }

        if (stripos($headers['from'], '@starwoodhotels.com') !== false
            || $this->http->XPath->query('//node()[contains(.,"@starwoodhotels.com")]')->length > 0
        ) {
            $this->providerCode = 'spg';

            return true;
        }

        return false;
    }

    private function rowColsPos($row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
