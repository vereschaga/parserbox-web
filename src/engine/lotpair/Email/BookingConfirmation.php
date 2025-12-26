<?php

namespace AwardWallet\Engine\lotpair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "lotpair/it-140405747.eml";
    public $subjects = [
        // en
        'Booking confirmation for reservation:',
        // pl
        'Potwierdzenie rezerwacji o numerze:',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'statusPhrases'                     => ['Your reservation has been'],
            'statusVariants'                    => ['updated'],
            'adult'                             => ['Adult', 'Child'],
            'Your travel details'               => 'Your travel details',
            'General guidelines for passengers' => 'General guidelines for passengers',

            //            'Booking confirmation' => '',
            //            'Ticket number' => '',
        ],
        "pl" => [
            //            'statusPhrases'  => ['Your reservation has been'],
            //            'statusVariants' => ['updated'],
            'adult'                             => ['Dorosły'],
            'Your travel details'               => 'Szczegóły Twojej podróży',
            'General guidelines for passengers' => 'Wytyczne dla pasażerów',

            'Booking confirmation' => 'Potwierdzenie rezerwacji',
            'Ticket number'        => 'Numer biletu',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@confirmation.lot.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.confirmation.lot.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your travel details']) && !empty($dict['General guidelines for passengers'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Your travel details'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['General guidelines for passengers'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]confirmation\.lot\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        // General
        $travellers = $this->http->FindNodes("//text()[" . $this->eq($this->t('Booking confirmation')) . "]/ancestor::table[1]/following::table[{$this->contains($this->t('adult'))}][1]/descendant::text()[{$this->contains($this->t('adult'))}]/preceding::text()[normalize-space()][1]", null, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");

        if (!empty($travellers)) {
            $f->general()
                ->travellers($travellers, true);
        }

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t('Booking confirmation')) . "]/following::text()[normalize-space()][1]"));

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[.,;:!?]|$)/");

        if ($status) {
            $f->general()->status($status);
        }

        // Issued
        $tickets = array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t('Ticket number')) . "]/ancestor::tr[1]", null, "/{$this->opt($this->t('Ticket number'))}[\s:]*(.*)$/u"));

        if (!empty($tickets)) {
            $f->issued()
                ->tickets($tickets, false);
        }

        // Segments
        $xpathTime = '[contains(translate(normalize-space(),"0123456789","ddddddddd"),"d:dd")]';
        $xpath = "//text()[" . $this->eq($this->t('Your travel details')) . "]/following::table//tr[count(*) > 2 and *[normalize-space()][1]{$xpathTime} and *[normalize-space()][last()]{$xpathTime}]";
        $nodes = $this->http->XPath->query($xpath);
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode('*[normalize-space()][2]', $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $date = $this->http->FindSingleNode("preceding::tr[normalize-space()][1]/preceding::text()[normalize-space()][1]/ancestor::td[1]", $root);

            $depTime = $this->http->FindSingleNode("*[normalize-space()][1]", $root);

            $s->departure()
                ->date((!empty($date) && !empty($depTime)) ? $this->normalizeDate($date . ', ' . $depTime) : null)
                ->code($this->http->FindSingleNode("preceding::tr[1]/descendant::td[normalize-space()][1]", $root));

            $arrTime = $this->http->FindSingleNode("*[normalize-space()][last()]", $root);

            if (stripos($arrTime, '+1') !== false) {
                $arrTime = $this->re("/([\d\:]+)/", $arrTime);
                $arrDate = (!empty($date) && !empty($arrTime)) ? $this->normalizeDate($date . ', ' . $arrTime) : null;

                if (!empty($arrDate)) {
                    $s->arrival()
                        ->date(strtotime('+1 day', $arrDate));
                }
            } else {
                $s->arrival()
                    ->date((!empty($date) && !empty($arrTime)) ? $this->normalizeDate($date . ', ' . $arrTime) : null);
            }

            $s->arrival()
                ->code($this->http->FindSingleNode("preceding::tr[1]/descendant::td[normalize-space()][last()]", $root));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your travel details']) && !empty($dict['General guidelines for passengers'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Your travel details'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['General guidelines for passengers'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function normalizeDate($str)
    {
//        $this->logger->debug('date in = '.print_r( $str,true));

        $in = [
            // 24 kwietnia 2022, 18:20
            "/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4}),\s+(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([^\d\s]+)\s+\d{4}/u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
//        $this->logger->debug('date out = '.print_r( $str,true));

        return strtotime($str);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
