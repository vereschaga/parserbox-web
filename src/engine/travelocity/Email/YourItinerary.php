<?php

namespace AwardWallet\Engine\travelocity\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourItinerary extends \TAccountChecker
{
    public $mailFiles = "travelocity/it-62898988.eml, travelocity/it-2011843.eml, travelocity/it-2135098.eml";

    private $lang = '';
    private $reFrom = ['.travelocity.com'];
    private $reProvider = ['Travelocity'];
    private $reSubject = [
        'Your flight itinerary to ',
        'Your flight itinerary is canceled',
    ];
    private $reBody = [
        'en' => [
            'Per your request, your flight has been changed.',
            'Per your request, your flight is canceled.',
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->parseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();
        $confOta = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Travelocity Itinerary No.:'))}]/following::text()[normalize-space()][1]",
            null, true, '/^[A-Z\d]{5,}$/');
        $f->ota()->confirmation($confOta);
        $f->general()->noConfirmation();

        $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Traveler:'))}]/following::text()[normalize-space()][1]",
            null, true, '/^[[:alpha:]\s]+$/');
        $f->general()->traveller($traveller);

        $ticket = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Airline Ticket No.:'))}]/following::text()[normalize-space()][1]",
            null, true, '/^[\w\s]+$/');
        $f->issued()->ticket($ticket, false);

        $xpath = "//text()[{$this->contains($this->t('Airline confirmation code:'))}]/ancestor::tr[{$this->contains($this->t('Flight '))}][1]";
        $this->logger->debug($xpath);
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[1]", $root, null,
                '/^.+?\d{4}$/');
            $this->logger->debug($date);

            $str = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Flight '))}]/ancestor::td[1]",
                $root, null, '/^.+?\d{2,4}$/');
            // AirTran Airways Flight 1182
            if (preg_match('/:\s*([[:alpha:]].+?) Flight (\d{2,4})/', $str, $m)
                || preg_match('/^([[:alpha:]].+?) Flight (\d{2,4})/', $str, $m)) {
                $s->airline()->name($m[1]);
                $s->airline()->number($m[2]);
            }
            $s->airline()->operator($this->http->FindSingleNode(".//text()[{$this->contains($this->t('Operated by'))}]/ancestor::td[1]",
                $root, null, "/{$this->opt($this->t('Operated by'))}\s+(.+)/"), false, true);
            $this->logger->debug($str);

            $s->setConfirmation($this->http->FindSingleNode(".//text()[{$this->contains($this->t('Airline confirmation code:'))}]/following::text()[normalize-space()][1]",
                $root, true, '/^[A-Z\d]{5,}$/'));

            // Depart
            $time = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Depart '))}]/ancestor::td[1]",
                $root, null, '/\s+(\d+:\d+.+)/');
            $s->departure()->date2("$date, $time");

            $str = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Depart '))}]/ancestor::td[1]/following-sibling::td[1]",
                $root);
            $this->logger->debug($str);
            // Milwaukee, WI - MKE
            if (preg_match('/^(.+?) - ([A-Z]{3})/', $str, $m)) {
                $s->departure()->name($m[1]);
                $s->departure()->code($m[2]);
            }

            // Arrive
            $time = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Arrive '))}]/ancestor::td[1]",
                $root, null, '/\s+(\d+:\d+.+)/');
            $s->arrival()->date2("$date, $time");

            $str = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Arrive '))}]/ancestor::td[1]/following-sibling::td[1]",
                $root);
            // Milwaukee, WI - MKE
            if (preg_match('/^(.+?) - ([A-Z]{3})/', $str, $m)) {
                $s->arrival()->name($m[1]);
                $s->arrival()->code($m[2]);
            }
        }

        // Cancelled
        if ($this->http->FindSingleNode("//text()[{$this->contains($this->t('Per your request, your flight is canceled. If your card used for the purchase was charged'))}]")) {
            $f->general()->status('canceled');
            $f->general()->cancelled();
        }
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            $this->logger->error($this->http->XPath->query("//text()[{$this->contains($value)}]")->length);

            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $this->t($field);

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
