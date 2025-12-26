<?php

namespace AwardWallet\Engine\spirit\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Cancellation extends \TAccountChecker
{
    public $mailFiles = "spirit/it-2007419.eml, spirit/it-77816593.eml";

    private $lang = '';
    private $reFrom = [
        '@t.spiritairlines.com',
        '@fly.spirit-airlines.com',
        'booking@fly2.spirit-airlines.com',
    ];
    private $reProvider = ['Spirit Airlines'];
    private $detectLang = [
        'en' => [
            'Please review the contents of this document carefully.',
        ],
    ];
    private $reSubject = [
        'Spirit Airlines Cancellation Confirmation:',
        'Spirit Airlines Cancellation :',
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }
        $f = $email->add()->flight();
        $f->general()->confirmation(
            $this->http->FindSingleNode("//text()[{$this->contains($this->t('YOUR CONFIRMATION CODE'))}]/ancestor::td[1]/following-sibling::td[1]"),
            $this->t('YOUR CONFIRMATION CODE')
        );
        $nodes = $this->http->XPath->query('//tr[./descendant::text()[normalize-space()][1][normalize-space(.) = "NAME"] and contains(string(), "FREE SPIRIT")]/following-sibling::tr/descendant-or-self::tr[not(.//tr)]');

        foreach ($nodes as $n) {
            if ($p = $this->http->FindSingleNode('./td[1]', $n)) {
                $f->general()->traveller($p);
            }

            if ($an = $this->http->FindSingleNode('./td[3]', $n)) {
                $f->program()->account($an, false);
            }
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Itinerary Cancellation'), 'normalize-space()')}]")->length == 1) {
            $f->general()->status($this->t('Cancellation'));
            $f->general()->cancelled();
        }

        $date = $this->http->FindSingleNode("//text()[contains(., 'BOOKING DATE') or (contains(., 'Booking Date'))]/following::text()[normalize-space(.)][1]",
            null, true, "/^\s*\w+,\s+(\w+\s+\d+,\s+\d{4})\b/");
        $date = strtotime($date);

        if (!empty($date)) {
            $f->general()->date($date);
        }

        $this->parseStatement($email);

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

        if ($this->assignLang() && $this->http->XPath->query("//node()[{$this->eq($this->t('Itinerary Cancellation'), 'normalize-space(text())')}]")->length === 1) {
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

    private function parseStatement(Email $email)
    {
        $info = $this->http->FindSingleNode("//text()[contains(., 'Points')]/ancestor::*[contains(., '#') and count(.//text()[contains(., '|')]) >= 2 and descendant::text()[normalize-space()][1][not(contains(., 'Points'))]][1]");

        if (preg_match("/^ *([[:alpha:]][[:alpha:]\- ]+) *\| *(\d[\d, ]*) *Points *\| *\#(\d{5,}) *(?:$|\|)/", $info, $m)) {
            $st = $email->add()->statement();

            $st
                ->setLogin($m[3])
                ->setNumber($m[3])
                ->setBalance(str_replace([',', ' '], '', $m[2]))
            ;

            if (!preg_match("/(\bguest|spirit)/iu", $m[1])) {
                $st
                    ->addProperty("Name", $m[1]);
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
