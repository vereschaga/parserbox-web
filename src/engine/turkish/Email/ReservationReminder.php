<?php

namespace AwardWallet\Engine\turkish\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationReminder extends \TAccountChecker
{
    public $mailFiles = "turkish/it-155028269.eml, turkish/it-592186622.eml";

    private $detectSubject = [
        // en
        'Reservation Reminder',
        // tr
        'Rezervasyon Hatırlatma',
    ];

    private static $dictionary = [
        'en' => [
            'Due time of ticketing your reservation nearly expires.' => 'Due time of ticketing your reservation nearly expires.',
            //            'Transaction Date:' => '',
            //            'class' => '',
            //            'Passenger' => '',
        ],
        'tr' => [
            'Due time of ticketing your reservation nearly expires.' => 'Rezervasyonunuzu bilete dönüştürmek için size verilen süre sona ermektedir.',
            'Transaction Date:'                                      => 'İşlem Tarihi:',
            'class'                                                  => 'sınıfı',
            'Passenger'                                              => 'Yolcu',
        ],
    ];

    private $lang = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectBody();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'turkishairlines.com')]")->length > 0) {
            return $this->detectBody();
        }

        return false;
    }

    public function detectBody()
    {
        foreach (self::$dictionary as $lang => $detect) {
            if (isset($detect['Due time of ticketing your reservation nearly expires.'])
                && $this->http->XPath->query("//text()[" . $this->contains($detect['Due time of ticketing your reservation nearly expires.']) . "]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'info@thy.com') === false) {
            return false;
        }

        foreach ($this->detectSubject as $sub) {
            if (stripos($headers['subject'], $sub) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thy.com') !== false
            || stripos($from, '@mail.turkishairlines.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Transaction Date:')) . ']/preceding::text()[normalize-space(.)][1]',
            null, true, '/^\s*[A-Z\d]{5,7}\s*$/');

        $f->general()
            ->confirmation($confirmation);

        // Travellers
        $pXpath = "//*[" . $this->eq($this->t("Passenger")) . "]/following-sibling::*[normalize-space()]";
        $pRows = $this->http->XPath->query($pXpath);
        $travellers = [];

        foreach ($pRows as $i => $pRoot) {
            $values = $this->http->FindNodes(".//text()[normalize-space()]", $pRoot);

            if (count($values) == 2 && preg_match("/^\s*([A-Z]{2})\s*$/", $values[0])) {
                $travellers[] = $values[1];
            } elseif ($i > 0) {
                break;
            }
        }

        $travellers = preg_replace("/^\s*(Mr|Ms|Bay|Bayan)\.?\s+/", '', $travellers);

        $f->general()
            ->travellers($travellers, true);

        $relativeDate = $this->normalizeDate($this->http->FindSingleNode('//text()[' . $this->eq($this->t('Transaction Date:')) . ']/following::text()[normalize-space(.)][1]',
            null, true, '/^\s*.*\d{4}.*\s*$/'));

        // Segments
        $xpath = "//tr[td[normalize-space()][1][count(.//text()[normalize-space()]) = 3 and descendant::text()[normalize-space()][1][translate(normalize-space(.),'0123456789','dddddddddd--')='dd:dd']]]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->debug('Segments not found by: ' . $xpath);

            return $email;
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->noName()
                ->noNumber();

            $date = $this->http->FindSingleNode('preceding::tr[normalize-space(.)][1]', $root, null, "/^.+?-.+?,\s*(.+)$/");

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode('td[normalize-space()][1]/descendant::text()[normalize-space()][2]', $root, null, "/^\s*\s*([A-Z]{3})\s*$/"));

            $time = $this->http->FindSingleNode('td[normalize-space()][1]/descendant::text()[normalize-space()][1]', $root, null, "/^\s*(\d{1,2}:\d{2})\s*$/");

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $time, $relativeDate));
            }

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode('td[normalize-space()][3]/descendant::text()[normalize-space()][2]', $root, null, "/^\s*([A-Z]{3})\s*$/"));

            $time = $this->http->FindSingleNode('td[normalize-space()][3]/descendant::text()[normalize-space()][1]', $root, null, "/^\s*(\d{1,2}:\d{2})\s*$/");

            if (!empty($date) && !empty($time)) {
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $time, $relativeDate));
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode('td[normalize-space()][last()]/*[1]', $root), true)
                ->bookingCode($this->http->FindSingleNode('td[normalize-space()][last()]/descendant::text()[normalize-space()][last()]', $root, true, "/^\s*([A-Z]{1,2})\s*" . $this->opt($this->t('class')) . "\s*$/"))
            ;
        }
    }

    private function t($str)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$str])) {
            return $str;
        }

        return self::$dictionary[$this->lang][$str];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate($date, $relativeDate = null)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));

        $year = date('Y', $relativeDate);
        $in = [
            //29 July Friday,  5:50
            "/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+([[:alpha:]]+)\s*,\s*(\d{1,2}:\d{2})\s*$/u",
        ];
        $out = [
            "$3, $1 $2 {$year}, $4",
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([[:alpha:]]{3,})\s+(?:\d{4}|%year%)#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ 20\d{2}\b.*)/u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/^\d+ [[:alpha:]]+ 20\d{2}(,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $date)) {
            return strtotime($date);
        }

        return null;
    }
}
