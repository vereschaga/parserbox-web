<?php

namespace AwardWallet\Engine\kiwi\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingFlight extends \TAccountChecker
{
    public $mailFiles = "kiwi/it-821676351.eml, onbusiness/statements/it-64389900.eml";
    public $subjects = [
        ': We got your payment and we’re on it!',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    private $year;

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@kiwi.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'www.kiwi.com')]")->length === 0
        && $this->http->XPath->query("//text()[contains(normalize-space(), 'Kiwi.com app')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Manage my booking'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking status'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('View booking'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Edit passenger details'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]kiwi\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match("/Fwd:\s+/", $parser->getSubject())) {
            $this->year = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Date:')]", null, true, "/(\d{4})\s+/");
        } elseif (empty($this->year)) {
            $this->year = date("Y", strtotime($parser->getHeader('date')));
        }

        $this->logger->error($this->year);

        if (empty($this->year)) {
            $this->logger->error('NO YEAR!!!');
        }

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Booking number']/following::text()[normalize-space()][1]", null, true, "/^([\d\s]+)$/");
        $f->general()
            ->confirmation(str_replace(' ', '', $confirmation))
            ->travellers(array_unique($this->http->FindNodes("//text()[contains(normalize-space(), '×')]/ancestor::table[3]/descendant::tr/descendant::table[1][not(contains(normalize-space(), '×'))]")))
            ->status($this->http->FindSingleNode("//text()[normalize-space()='Booking status']/following::text()[normalize-space()][1]"));

        $nodes = $this->http->XPath->query("//text()[contains(normalize-space(), 'Edit passenger details')]/following::table[1]/descendant::text()[contains(normalize-space(), ':')]/ancestor::table[3][not(contains(normalize-space(), 'Reserved seats:'))]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode("./descendant::table[1]/following-sibling::table[1]", $root);
            $this->logger->error($airlineInfo);

            if (preg_match("/^(?<duration>(?:\d+h)?\s*(\d+m)?)\s+.*\s+(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{2,4})/", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $s->extra()
                    ->duration($m['duration']);
            }

            $depInfo = $this->http->FindSingleNode("./descendant::table[1]", $root);
            $this->logger->debug($depInfo);

            if (preg_match("/^(?<depTime>[\d\:]+)\s*(?:\(.+\))?\s+(?<date>\w+\,\s+\d+\.\d+)\.\s*(?<depName1>.+)\s+[•]\s+(?<depCode>[A-Z]{3})\s*(?<depName2>.+)$/u", $depInfo, $m)) {
                $s->departure()
                    ->name($m['depName1'] . ', ' . $m['depName2'])
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['date'] . '.' . $this->year . ', ' . $m['depTime']));
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::table[1]/following-sibling::table[not(contains(normalize-space(), 'Operating carrier:'))][2]", $root);
            $this->logger->debug($arrInfo);

            if (preg_match("/^(?<arrTime>[\d\:]+)\s*(?:\(.+\))?\s+(?<date>\w+\,\s+\d+\.\d+)\.\s*(?<arrName1>.+)\s+[•]\s+(?<arrCode>[A-Z]{3})\s*(?<arrName2>.+)$/u", $arrInfo, $m)) {
                $s->arrival()
                    ->name($m['arrName1'] . ', ' . $m['arrName2'])
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['date'] . '.' . $this->year . ', ' . $m['arrTime']));
            }

            $seats = explode(", ", $this->http->FindSingleNode("./following::tr[1][starts-with(normalize-space(), 'Reserved seats:')]", $root, true, "/{$this->opt($this->t('Reserved seats:'))}\s+(\d+\-[A-Z].*)/"));

            if (count($seats) > 0) {
                $s->extra()
                    ->seats(preg_replace("/\-/", "", $seats));
            }
        }
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            //Wed, 30.10.2024, 23:35
            "#^(\w+\,\s+\d+\.\d+\.\d{4}\,\s+\d+\:\d+)$#i",
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
