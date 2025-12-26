<?php

namespace AwardWallet\Engine\uber\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ActivityCanceled extends \TAccountChecker
{
    public $mailFiles = "uber/it-591426505.eml";

    public $emailDate;
    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Booking Reference:' => 'Booking Reference:',
        ],
    ];

    private $detectFrom = "noreply@uber.com";
    private $detectSubject = [
        // en
        'Your booking has been canceled',
    ];
    private $detectBody = [
        'en' => [
            'following activity has been canceled',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]uber\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.uber.'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains([' Uber Technologies'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->emailDate = strtotime($parser->getDate());

        if ($this->detectEmailByHeaders($parser->getHeaders()) !== true) {
            $date = $this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->contains($this->detectSubject)}][contains(., ':')]/preceding::text()[1][contains(., ':')])[last()]",
                null, true, "/^.+?:(.+)/"));

            if (!empty($date)) {
                $this->emailDate = $date;
            } else {
                $this->emailDate = strtotime("-1 month", $this->emailDate);
            }
        }

        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Booking Reference:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Booking Reference:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $event = $email->add()->event();

        $event->type()->event();

        // General
        $event->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference:'))}]",
                null, true, "/{$this->opt($this->t('Booking Reference:'))}\s*([A-Z\d]+[-A-Z\d]+)\s*$/"))
            ->status('Cancelled')
            ->cancelled()
        ;

        //Place
        $text = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Booking Reference:'))}]/ancestor::*[{$this->contains($this->t('has been canceled:'))}][1]//text()[normalize-space()]"));

        if (preg_match("/{$this->opt($this->t('has been canceled:'))}\s*(?<name>.+)\n(?<date>.+)\n(?<guests>\d+ Adult)/", $text, $m)) {
            $event->place()
                ->name($m['name']);

            $event->booked()
                ->start($this->normalizeDate($m['date']))
                ->guests($this->re("/(\d+) Adult/", $m['guests']))
            ;
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date in = '.print_r( $date,true));
        $year = date("Y", $this->emailDate);

        $in = [
            // Wed, Aug 23, 2023 at 11:00 AM
            '/^\s*[[:alpha:]]+,\s*([[:alpha:]]+)\s+(\d+),\s*(\d{4})\s+at\s+(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/i',
            //Nov 21, 12:00 PM
            '/^\s*([[:alpha:]]+)\s*(\d+)\,\s*(\d{1,2}:\d{2}\s*[AP]M)\s*$/i',
        ];
        // $year - for date without year and with week
        // %year% - for date without year and without week
        $out = [
            '$2 $1 $3, $4',
            '$2 $1 %year%, $3',
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date out = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (!empty($this->emailDate) && $this->emailDate > strtotime('01.01.2000') && strpos($date, '%year%') !== false
            && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s*,\s(?<time>\d{1,2}:\d{2}.*))?$/', $date, $m)) {
            // $this->logger->debug('$date (no week, no year) = '.print_r( $m['date'],true));
            $date = EmailDateHelper::parseDateRelative($m['date'], $this->emailDate);

            if (!empty($date) && !empty($m['time'])) {
                return strtotime($m['time'], $date);
            }

            return $date;
        } elseif ($year > 2000 && preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $date, $m)) {
            // $this->logger->debug('$date (week no year) = '.print_r( $string,true));
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}(,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $date)) {
            // $this->logger->debug('$date (year) = '.print_r( $str,true));
            return strtotime($date);
        } else {
            return null;
        }

        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
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
