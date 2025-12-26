<?php

namespace AwardWallet\Engine\ninja\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderModified extends \TAccountChecker
{
    public $mailFiles = "ninja/it-719112405.eml";
    public $subjects = [
        'Order modified: Ticket order',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Tickets modified:'    => 'Tickets modified:',
            'Ticket information :' => 'Ticket information :',
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
        if ($this->http->XPath->query("//a[contains(@href, 'rail.ninja')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Tickets modified:']) && !empty($dict['Ticket information :'])
                && $this->http->XPath->query("//text()[{$this->starts($dict['Tickets modified:'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->eq($dict['Ticket information :'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]rail\.ninja$/', $from) > 0;
    }

    public function ParseRail(Email $email): void
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Tickets modified:'))}][1]", null, true,
                "/{$this->opt($this->t('Tickets modified:'))}\s*(RN[\d\-]+)/"));

        // Trains
        $t = $email->add()->train();

        $t->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}][1]", null, true,
                "/^\s*{$this->opt($this->t('Dear '))}\s*([[:alpha:] \-]+)\s*,\s*$/"), false)
        ;

        $xpathNoDisplay = 'ancestor-or-self::*[contains(translate(@style," ",""),"display:none")]';

        $xpath = "//text()[{$this->eq($this->t('Ticket information :'))}]/following::text()[normalize-space()][not($xpathNoDisplay)][1]/ancestor::*[not({$this->contains($this->t('Ticket information :'))})][1]";

        $text = $this->http->FindSingleNode($xpath);
        $segments = array_filter($this->split("/\b(Train #)/", $text));
        // $this->logger->debug('$segments = '.print_r( $segments,true));

        foreach ($segments as $sText) {
            $s = $t->addSegment();

            if (preg_match("/Train #(\S+?) (.+?)--(.+?)\.\s*{$this->opt($this->t('Departure date/time:'))}/", $sText, $m)) {
                if (preg_match("/\d+/", $m[1])) {
                    $s->extra()
                        ->number($m[1]);
                } else {
                    $s->extra()
                        ->noNumber();
                }
                $s->departure()
                    ->name($m[2]);
                $s->arrival()
                    ->name($m[3]);
            }

            if (preg_match("/{$this->opt($this->t('Departure date/time:'))}\s*(.+?)\. /", $sText, $m)) {
                $s->departure()
                    ->date(strtotime($m[1]));
            }

            if (preg_match("/{$this->opt($this->t('Arrival date/time:'))}\s*(.+?)\. /", $sText, $m)) {
                $s->arrival()
                    ->date(strtotime($m[1]));
            }

            if (preg_match("/{$this->opt($this->t('Ticket class:'))}\s*(.+?)\s*(?:\(|\. )/", $sText, $m)) {
                $s->extra()
                    ->cabin($m[1]);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
