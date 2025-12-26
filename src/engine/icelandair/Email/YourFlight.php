<?php

namespace AwardWallet\Engine\icelandair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "icelandair/it-617690324.eml";
    public $subjects = [
        'Check in is now open for your flight',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];
    private $date;
    private $subject;

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.icelandair.is') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Icelandair')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Check in now'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Before your journey'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.icelandair\.is$/', $from) > 0;
    }

    public function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking:'))}\s*([A-Z\d]{6})/"));

        if (preg_match("/\d{1,4}\,\s*(\w+)[!]$/", $this->subject, $m)) {
            $f->general()
                ->traveller($m[1], false);
        }

        $flightInfo = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'FLIGHT')]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{2,4})\n(?<date>.+)\n(?<depTime>[\d\:]+\s*A?P?M)\n(?<arrTime>[\d\:]+\s*A?P?M)$/", $flightInfo, $m)) {
            $depCode = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'FROM')]/ancestor::table[1]", null, true, "/{$this->opt($this->t('FROM'))}\s*([A-Z]{3})$/");
            $arrCode = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'TO')]/ancestor::table[1]", null, true, "/{$this->opt($this->t('TO'))}\s*([A-Z]{3})$/");

            $s = $f->addSegment();

            $s->airline()
                ->name($m['aName'])
                ->number($m['fNumber']);

            $s->departure()
                ->date($this->normalizeDate(trim($m['date']) . ', ' . trim($m['depTime'])))
                ->code($depCode);

            $s->arrival()
                ->date($this->normalizeDate($m['date'] . ', ' . $m['arrTime']))
                ->code($arrCode);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $this->subject = $parser->getSubject();

        $this->parseFlight($email);

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

    private function normalizeDate($str)
    {
        $year = date('Y', $this->date);

        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^(\d+\s*\w+)\,\s*([\d\:]+)\s*A?P?M$#u", //04 Dec, 18:30 PM
        ];
        $out = [
            "$1 $year, $2",
        ];
        $str = preg_replace($in, $out, $str);
        $this->logger->debug('$str = ' . print_r($str, true));

        if (preg_match("#\d+\s+(\w+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
