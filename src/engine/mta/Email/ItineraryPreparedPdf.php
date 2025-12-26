<?php

namespace AwardWallet\Engine\mta\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ItineraryPreparedPdf extends \TAccountChecker
{
    public $mailFiles = "mta/it-143805925.eml, mta/it-20249831.eml, mta/it-20250034.eml";

    public $reFrom = ["MTA Travel", "mtatravel.com.au"];

    public $reBody = [
        'en' => ['Itinerary Prepared for', 'Your Travel Consultant'],
    ];
    public $lang = '';
    public $pdfNamePattern = "(?:Itinerary|.*Hotel.*Voucher).*pdf";
    public static $dict = [
        'en' => [
            'Itinerary Prepared by' => ['Itinerary Prepared by', 'Itinerary Powered by'],
        ],
    ];

    private $patterns = [
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
    ];

    private $pax = [];
    private $accounts = [];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    }

                    if (!$this->parseEmail($text, $email)) {
                        return null;
                    }
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (((stripos($text, 'MTA Travel') !== false) || (stripos($text, 'mtatravel.com.au') !== false)
                    || $this->http->XPath->query("//text()[contains(., 'mtatravel.com')]")->length > 0
                    || (stripos(implode('', $parser->getFrom()), 'mtatravel.com.au') !== false)
                )
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function splitter($regular, $text): array
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmail($textPDF, Email $email): bool
    {
        if (!preg_match("#^ *{$this->opt($this->t('Itinerary Prepared by'))} .+? {5,}Page \d+#m", $textPDF)) {
            $this->logger->debug("other format");

            return false;
        }
        $textPDF = preg_replace("#\n *{$this->opt($this->t('Itinerary Prepared by'))} .+? {5,}Page \d+ *#m",
            '', $textPDF);

        $email->ota()
            ->confirmation($this->re("# {3,}.+ {$this->t('Booking File')}[: ]+([\w\/\-]{5,})#", $textPDF),
                $this->re("# {3,}(.+ {$this->t('Booking File')})[: ]+[\w\/\-]{5,}#", $textPDF))
            ->phone($this->re("#Your Travel Consultant:.+? ({$this->patterns['phone']})\n#", $textPDF));

        if (preg_match("#{$this->opt($this->t('Itinerary Prepared for'))}[\s:]+(.+?)\n *[A-Z][a-z][a-z ]+?:#s", $textPDF,
            $m)) {
            $p = array_filter(array_map("trim", explode("\n", $m[1])));

            foreach ($p as $v) {
                $this->pax[] = $this->re("#^([A-Z ]+?)(?: {3,}|$)#", $v);

                if (preg_match("# {3,}([A-Z\d][A-Z]|[A-Z][A-Z\d]) - .+? ([A-Z\d]{5,})$#", $v, $mm)) {
                    $this->accounts[$mm[1]][] = $mm[2];
                }
            }
        }

        $arr = $this->splitter("#(\w.+\n *\w+, \d+ \w+ \d{4})#", $textPDF);

        foreach ($arr as $root) {
            if (preg_match("#^ *Flight:#", $root)) {
                $this->parseFlight($root, $email);
            } elseif (preg_match("#^ *Hotel:#", $root)) {
                $this->parseHotel($root, $email);
            } elseif (preg_match("#^ *Surface:#", $root)) {
                continue;
            } else {
                $this->logger->info("unknown type or reservation");

                return false;
            }
        }

        return true;
    }

    private function parseFlight($textPDF, Email $email): void
    {
        $r = $email->add()->flight();

        $confNo = $this->re("#Confirmation Number: ([A-Z\d]{3,})#", $textPDF);

        if (empty($confNo) && !empty($this->re("#(Confirmation Number: )#", $textPDF))) {
            $r->general()
                ->noConfirmation();
        } else {
            $r->general()->confirmation($confNo);
        }
        $r->general()->travellers($this->pax);
        $date = strtotime($this->re("#^.+\n *(\w+, \d+ \w+ \d{4})#", $textPDF));
        $r->general()->status($this->re("# {5,}Status: +(.+)#", $textPDF));

        if (preg_match_all("#Ticket Numbers.+\s+([\d\S]{7,})#", $textPDF, $ticketMatches)) {
            $r->issued()->tickets($ticketMatches[1], false);
        }

        $s = $r->addSegment();

        if (preg_match("#^ *Flight:.+?\(([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\)#", $textPDF, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2])
                ->operator($this->re("# {5,}Airline: +(.+)#", $textPDF));

            if (isset($this->accounts[$m[1]])) {
                $r->program()->accounts($this->accounts[$m[1]], false);
            }
        }

        if (preg_match("#at (\d+:\d+\s*(?:(?i)[ap]m)?) +Depart:(.+)?\s+at#s", $textPDF, $m)) {
            if (preg_match("#Terminal: +(.+)#", $m[2], $v)) {
                $s->departure()->terminal($v[1]);
                $m[2] = preg_replace("#Terminal: +(.+)#", '', $m[2]);
            }
            $s->departure()
                ->name($this->nice($m[2]))
                ->date(strtotime($m[1], $date));
        }

        if (preg_match("#at (\d+:\d+\s*(?:(?i)[ap]m)?)(?: \((.+?)\))? +Arrive:(.+)?\s+Airline#s", $textPDF, $m)) {
            if (preg_match("#Terminal: +(.+)#", $m[3], $v)) {
                $s->arrival()->terminal($v[1]);
                $m[3] = preg_replace("#Terminal: +(.+)#", '', $m[3]);
            }

            if (isset($m[2]) && !empty($m[2])) {
                $m[1] = $m[2] . ', ' . $m[1];
            }
            $s->arrival()
                ->name($this->nice($m[3]))
                ->date(strtotime($m[1], $date));
        }

        if (preg_match("# {5,}Equipment: +\(([A-Z]{3}) \- ([A-Z]{3})\) (.+)#", $textPDF, $m)) {
            $s->departure()->code($m[1]);
            $s->arrival()->code($m[2]);
            $s->extra()->aircraft($m[3]);
        }

        if (preg_match("# {5,}Class: +(.+?)(?: *\(([A-Z]{1,2})\))?\n#", $textPDF, $m)) {
            $s->extra()->cabin($m[1]);

            if (isset($m[2]) && !empty($m[2])) {
                $s->extra()->bookingCode($m[2]);
            }
        }
        $s->extra()->duration($this->re("# {5,}Flight Time: +(.+)#", $textPDF));
        $node = $this->re("# {5,}Seat.*?: +(.+)#", $textPDF);

        if (preg_match_all("#\b(\d+[A-Z])\b#", $node, $m)) {
            $s->extra()->seats($m[1]);
        }

        if (preg_match("# {5,}OnBoard: +Meal#", $textPDF)) {
            $s->extra()->meal('Meal');
        }
    }

    private function parseHotel($textPDF, Email $email): void
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->re("#Confirmation Number: ([A-Z\d]{3,})#", $textPDF))
            ->travellers($this->pax)
        ;

        // Hotel
        $hotelName = $this->re("#Hotel[ ]*[:]+[ ]*(.{2,75}?) Confirmation Number[ ]*:#", $textPDF);
        $address = $this->re("#Address[ ]*[:]+[ ]*([^:\n]{3,}(?:\n[^:\n]+){0,5}?)(?:\n+[ ]*[[:alpha:]][- [:alpha:]]+:|\s*$)#", $textPDF);
        $phone = null;

        if (preg_match("/^\s*([\s\S]+?)\n[ ]*({$this->patterns['phone']})\s*$/", $address, $m)) {
            $address = preg_replace('/\s+/', ' ', $m[1]);
            $phone = $m[2];
        } else {
            $address = preg_replace('/\s+/', ' ', $address);
        }

        $h->hotel()->name($hotelName)->address($address)->phone($phone, false, true);

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->re("#Check in: *(\S.+?)(?: {2,}|\n)#", $textPDF)))
            ->checkOut(strtotime($this->re("#Check out: *(\S.+?)(?: {2,}|\n)#", $textPDF)))
        ;

        $rate = $this->re("#\s+Rate: *(\S.+?) *- *Approx Total:#", $textPDF);

        if (!empty($rate)) {
            $h->addRoom()
                ->setRate($rate);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function nice($str): string
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
