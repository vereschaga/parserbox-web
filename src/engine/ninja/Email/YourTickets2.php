<?php

namespace AwardWallet\Engine\ninja\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourTickets2 extends \TAccountChecker
{
    public $mailFiles = "ninja/it-207113193.eml, ninja/it-300038691.eml, ninja/it-312818445.eml, ninja/it-314496898.eml, ninja/it-544407836.eml, ninja/it-561414567.eml";
    public $subjects = [
        'Your tickets are ready',
    ];

    public $lang = 'en';

    public $trains = [];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@rail.ninja') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'ninja') and contains(normalize-space(), 'My account')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Your train tickets have been'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Tickets from'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rail\.ninja$/', $from) > 0;
    }

    public function ParseRail(Email $email): void
    {
        $xpathNoDisplay = 'ancestor-or-self::*[contains(translate(@style," ",""),"display:none")]';

        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $t = $email->add()->train();

        $t->general()
            ->noConfirmation()
        ;
        $traveller = $this->http->FindSingleNode("//a[contains(normalize-space(), 'My account')]/following::text()[contains(normalize-space(), 'Order ID:')][1]", null, true, "/^(\D+)\s+\|\s*{$this->opt($this->t('Order ID:'))}/");

        if (empty($traveller) && !empty($this->http->FindSingleNode("//a[contains(normalize-space(), 'My account')]/following::text()[contains(normalize-space(), 'Order ID:')][1]", null, true, "/^(\s*\D+\S+@\S+)+\s+\|\s*{$this->opt($this->t('Order ID:'))}/"))) {
        } else {
            $t->general()
                ->traveller($traveller, true);
        }

        $xpath = "//*[ *[1][{$this->eq($this->t('Departure'))}] and *[3][{$this->eq($this->t('Arrival'))}] ][not({$xpathNoDisplay})]/following::*[not(.//tr) and normalize-space()][1]/descendant-or-self::*[ count(*[normalize-space()])=3 and *[normalize-space()][2][{$this->starts($this->t('Train'))}] ]";
        $nodes = $this->http->XPath->query($xpath);
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            // Departure
            $dateDep = $this->normalizeDate($this->http->FindSingleNode("*[normalize-space()][1]", $root));
            $timeDep = $this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][1]/descendant-or-self::*[count(*)>1]/*[self::div or self::td][1]", $root, true, "/^{$patterns['time']}/");
            $s->departure()
                ->name($this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][2]/descendant-or-self::*[count(*[normalize-space()]) > 1]/*[self::div or self::td][1]", $root))
                ->date($dateDep && $timeDep ? strtotime($timeDep, $dateDep) : null)
            ;

            $addressDep = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][3][ following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Ticket class:'))}] ]/descendant-or-self::*[count(*[normalize-space()])>1]/*[normalize-space()][1]", $root);

            if ($addressDep) {
                $s->departure()->address($addressDep);
            }

            // Arrival
            $dateArr = $this->normalizeDate($this->http->FindSingleNode("*[normalize-space()][last()]", $root));
            $timeArr = $this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][1]/descendant-or-self::*[count(*[normalize-space()]) > 1]/*[self::div or self::td][last()]", $root, true, "/^{$patterns['time']}/");

            if ($dateArr && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            } elseif (!$timeArr && $dateArr && $dateArr === $dateDep && $timeDep) {
                // it-300038691.eml
                $s->arrival()->noDate();
            }

            $s->arrival()->name($this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][2]/descendant-or-self::*[count(*[normalize-space()]) > 1]/*[self::div or self::td][last()]", $root));

            $addressArr = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][3][ following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Ticket class:'))}] ]/descendant-or-self::*[count(*[normalize-space()])>1]/*[normalize-space()][last()]", $root);

            if ($addressArr) {
                $s->arrival()->address($addressArr);
            }

            $trains = $this->http->FindSingleNode("*[normalize-space()][2]", $root);

            if (preg_match("/Train\s*[#]\s*(?<name>\D+)?\s*(?<number>\d+(?:[|\/]\d+)?)/", $trains, $match)) {
                $s->extra()
                    ->number($match['number']);

                if (isset($match['name']) && !empty($match['name'])) {
                    $s->extra()
                        ->service($match['name']);
                }
            } elseif (preg_match("/^\s*Train\s*[#]\s*\D*$/", $trains, $match)) {
                $s->extra()
                    ->noNumber();
            }

            $s->extra()->cabin($this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][position()<5]/descendant::text()[normalize-space()][1]/ancestor::*[self::div or self::td][1][{$this->contains($this->t('Ticket class:'))}]", $root, true, "/{$this->opt($this->t('Ticket class:'))}[:\s]*(.+)/"));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//a[contains(normalize-space(), 'My account')]/following::text()[contains(normalize-space(), 'Order ID:')][1]", null, true, "/{$this->opt($this->t('Order ID:'))}\s*(RN[\d\-]+)/"), 'Order ID');

        $this->ParseRail($email);

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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            '/^[-[:alpha:]]+\s*,\s*(\d{1,2})\s*([[:alpha:]]+)\s*,\s*(\d{4})$/u', // Tuesday,6 December, 2022
        ];

        $out = [
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
