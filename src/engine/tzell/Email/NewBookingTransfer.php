<?php

namespace AwardWallet\Engine\tzell\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class NewBookingTransfer extends \TAccountChecker
{
    public $mailFiles = "tzell/it-69274482.eml";
    public $subjects = [
        '/^[\dA-Z\/]+\sNEW\sBOOKING$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tzell.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Drivania Online')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you very much for your reservation'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Pick up'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('STOP'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tzell\.com$/', $from) > 0;
    }

    public function parseTransfer(Email $email)
    {
        $t = $email->add()->transfer();

        $t->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Passenger'))}]/following::text()[normalize-space()][1]"))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Thank you very much for your reservation'))}]/following::text()[contains(normalize-space(), 'NEW BOOKING')]", null, true, "/^([\dA-Z\/]+)\s*{$this->opt($this->t('NEW BOOKING'))}/"),
                $this->t('NEW BOOKING'));

        $s = $t->addSegment();
        $transferDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick up date'))}]/following::text()[normalize-space()][1]");
        $transferTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick up time'))}]/following::text()[normalize-space()][1]");
        $s->departure()
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick up place'))}]/following::text()[normalize-space()][1]"))
            ->date($this->normalizeDate($transferDate . ', ' . $transferTime));

        $s->arrival()
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('End of service'))}]/preceding::text()[{$this->starts($this->t('STOP'))}][1]/following::text()[{$this->eq($this->t('Place'))}][1]/following::text()[normalize-space()][1]"))
            ->noDate();

        $t->price()
            ->currency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total ('))}]", null, true, "/{$this->opt($this->t('Total ('))}([A-Z]{3})\)/"))
            ->total($this->http->FindSingleNode("//text()[{$this->starts($this->t('Total ('))}]/following::text()[normalize-space()][1]"));

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseTransfer($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\w+\,\s+(\w+)\s+(\d+)\,\s+(\d{4})\,\s+([\d\:]+)h$#', //Saturday, Oct 31, 2020, 10:30h
        ];
        $out = [
            '$2 $1 $3, $4',
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
