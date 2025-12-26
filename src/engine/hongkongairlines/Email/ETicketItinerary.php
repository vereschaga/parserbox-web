<?php

namespace AwardWallet\Engine\hongkongairlines\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketItinerary extends \TAccountChecker
{
    public $mailFiles = "hongkongairlines/it-22333619.eml, hongkongairlines/it-25324540.eml";

    private $detectFrom = ["@hkairlines.com", ".hkairlines.com"];
    private $detectSubject = [
        'please refer to eTicket Itinerary and receipt',
        'please refer to eTicket Itinerary & receipt',
    ];

    private $detectCompany = [
        'Hong Kong Airlines',
    ];

    private $detectBody = [
        'en' => [
            'Flight Itinerary', 'Flight Confirmation',
        ],
    ];

    private $lang = 'en';
    private static $dictionary = [
        'en' => [
            'confNumber' => [
                'Order Number', 'Order Number:', 'Order Number :',
                'Order Number：', 'Order Number ：',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $detectFrom) {
            if (stripos($from, $detectFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        $finded = false;

        foreach ($this->detectCompany as $dCompany) {
            if (stripos($body, $dCompany) !== false) {
                $finded = true;
            }
        }

        if ($finded == false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $found = false;

        foreach ($this->detectFrom as $detectFrom) {
            if (stripos($headers['from'], $detectFrom) !== false) {
                $found = true;
            }
        }

        if (!$found) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function flight(Email $email): void
    {
        $f = $email->add()->flight();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $f->general()->travellers($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger Information")) . "]/ancestor::div[2]//td[" . $this->eq($this->t("E-Ticket Number")) . "]/ancestor::tr[1]/preceding-sibling::tr[1]"));

        // Program
        $account = array_unique(array_filter($this->http->FindNodes("//td[" . $this->eq($this->t("Frequent Flyer")) . "]/following-sibling::td[1]", null, "#:\s*(\d+)\s*$#")));

        if (!empty($account)) {
            $f->program()
                ->accounts($account, false);
        }

        // Issued
        $tickets = array_unique(array_filter($this->http->FindNodes("//td[" . $this->eq($this->t("E-Ticket Number")) . "]/following-sibling::td[1]", null, "#^\s*([\d\-]{6,})\s*$#")));

        if (!empty($tickets)) {
            $f->issued()
                ->tickets($tickets, false);
        }

        // Price
        $total = implode(" ", $this->http->FindNodes('//text()[' . $this->eq($this->t("Total Amount")) . ']/ancestor::td[1]//text()'));

        if (!empty($total) && (preg_match("#Total Amount\s+(?<curr>[A-Z]{3})\s*(?<total>\d[\d,. ]*)(\s+|$)#", $total, $m) || preg_match("#Total Amount\s+(?<total>\d[\d,. ]*)\s*(?<curr>[A-Z]{3})(?:\s+|$)#", $total, $m))) {
            $m['total'] = str_replace([',', ' '], '', $m['total']);

            if (is_numeric($m['total'])) {
                $f->price()
                    ->total((float) $m['total'])
                    ->currency($m['curr']);
            }
        }

        $xpath = "//text()[" . $this->eq(['Departure', 'Return']) . "]/ancestor::table[1]";
        //		$this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("descendant::text()[normalize-space()][last()]", $root, true, "/^.{2,}\b\d{4}$/")));

            $nextTable = './following::table[1]';

            // HX162  |  A320-200 (32S)  |  FlexiPlus (K)
            $node = implode("\n", $this->http->FindNodes($nextTable . "/descendant::tr[1]//text()[normalize-space()]", $root));

            if (preg_match("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)(?:[,\| ]|$)/", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            } else {
                $s->airline()
                    ->noName()
                    ->noNumber()
                ;
            }

            if (preg_match("/^[^\|]*\|\s*([^\|]+)\s*\|[^\|]*$/", $node, $m)) {
                $s->extra()->aircraft($m[1]);
            }

            // Extra
            if (preg_match("/[\|,]\s*([\w ]+)\s*\(\s*([A-Z]{1,2})\s*\)/", $node, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2])
                ;
            }
            $node = implode("\n", $this->http->FindNodes($nextTable . "/descendant::tr[1]/following-sibling::tr[1]/td[2]//text()[normalize-space()]", $root));

            if (preg_match("#^\s*(\d+h\d+m)\s*(\d+)#", $node, $m)) {
                $s->extra()
                    ->duration($m[1])
                    ->stops($m[2]);
            }

            $reRoute = '#^\s*(?<time>\d+:\d+)\s*(?<anotherDay>[ +]\d)?\s+(?<code>[A-Z]{3})\s+(?<name>[\s\S]+)#';

            // Sanya Fenghuang International Airport (SYX),   Terminal - -
            $patterns['nameTerminal'] = "/^(?<name>.{2,}?)[,\s]+Terminal[-\s]*(?<terminal>.*)$/i";

            // Departure
            $node = implode("\n", $this->http->FindNodes($nextTable . "/descendant::tr[1]/following-sibling::tr[1]/td[1]//text()[normalize-space()]", $root));

            if ($date && preg_match($reRoute, $node, $m)) {
                $m['name'] = preg_replace('/\s+/', ' ', $m['name']);

                if (preg_match($patterns['nameTerminal'], $m['name'], $m2)) {
                    $s->departure()->name($m2['name']);

                    if (!empty(trim($m2['terminal'], '- '))) {
                        $s->departure()->terminal($m2['terminal']);
                    }
                } else {
                    $s->departure()->name($m['name']);
                }

                $s->departure()
                    ->code($m['code'])
                    ->date(strtotime($m['time'], $date));

                if (!empty($m['anotherDay']) && !empty($s->getDepDate())) {
                    $s->departure()->date(strtotime($m['anotherDay'] . ' day', $s->getDepDate()));
                }
            }

            // Arrival
            $node = implode("\n", $this->http->FindNodes($nextTable . "/descendant::tr[1]/following-sibling::tr[1]/td[3]//text()[normalize-space()]", $root));

            if ($date && preg_match($reRoute, $node, $m)) {
                $m['name'] = preg_replace('/\s+/', ' ', $m['name']);

                if (preg_match($patterns['nameTerminal'], $m['name'], $m2)) {
                    $s->arrival()->name($m2['name']);

                    if (!empty(trim($m2['terminal'], '- '))) {
                        $s->arrival()->terminal($m2['terminal']);
                    }
                } else {
                    $s->arrival()->name($m['name']);
                }

                $s->arrival()
                    ->code($m['code'])
                    ->date(strtotime($m['time'], $date));

                if (!empty($m['anotherDay']) && !empty($s->getArrDate())) {
                    $s->arrival()->date(strtotime($m['anotherDay'] . ' day', $s->getArrDate()));
                }
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function normalizeDate(?string $str): string
    {
        $in = [
            "/^\s*[-[:alpha:]]+\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*$/u", // Fri 31 Aug 2018
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
