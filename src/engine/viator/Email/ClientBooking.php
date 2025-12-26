<?php

namespace AwardWallet\Engine\viator\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ClientBooking extends \TAccountChecker
{
    public $mailFiles = "viator/it-519542925.eml, viator/it-521192541.eml";

    public $detectSubject = [
        // en
        'Your client\'s Viator booking',
        'Your client has canceled Viator booking',
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'detectBody' => [
                'Your client\'s reservation is confirmed.',
                "had to cancel your client's booking.",
            ],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHtml($email);

        return $email;
    }

    public function assignLang(): bool
    {
        foreach (self::$dictionary as $dict) {
            if (!empty($dict['detectBody'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['detectBody'])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'viator')]/@href")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['detectBody'])
                && $this->http->XPath->query("//node()[{$this->eq($dict['detectBody'])}]")->length > 0) {
                return true;
            }
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.viator.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], '.viator.com') === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Booking is canceled'))}]")->length > 0) {
            $ev = $email->add()->event();

            $email->setSentToVendor(true);

            // General
            $ev->general()
                ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference number:'))}]",
                    null, true, "/{$this->opt($this->t('Booking reference number:'))}\s*([A-Z\-\d]{5,})\s*$/"))
                ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Itinerary number:'))}]/following::text()[normalize-space()][1]",
                    null, true, "/^\s*([A-Z\-\d]{5,})\s*$/"))
                ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Lead traveler:'))}]/following::text()[normalize-space()][1]",
                    null, true, "/^\s*([A-Za-z\- ]+)\s*$/"), true)
                ->status('Cancelled')
                ->cancelled()
            ;

            // Place
            $ev->place()
                ->name($this->http->FindSingleNode("//tr[not(.//tr)][preceding::tr[not(.//tr)][normalize-space()][1][{$this->starts($this->t('Lead traveler:'))}]][following::tr[not(.//tr)][normalize-space()][1][{$this->starts($this->t('Booking reference number:'))}]]"));

            // Booked
            $ev->booked()
                ->start(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Travel date:'))}]/following::text()[normalize-space()][1]",
                    null, true, "/^\s*(.*\d{4}.*)\s*$/")))
                ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Travelers:'))}]/following::text()[normalize-space()][1]",
                    null, true, "/\(\s*(\d+) [[:alpha:]]{3,}.*\)\s*$/u"))
            ;
            $kids = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Travelers:'))}]/following::text()[normalize-space()][1]",
                null, true, "/\(\s*\d+ [[:alpha:]]{3,}[^,]*,\s*(\d+) [[:alpha:]]{3,}.*\)\s*$/u");

            if (!empty($kids)) {
                $ev->booked()
                    ->kids($kids);
            }
        }
        $rule = "[preceding::text()[{$this->eq($this->t('detectBody'))}] and following::text()[{$this->eq($this->t('Important Information'))}]]";

        if ($this->http->XPath->query("//a{$rule}")->length === 0) {
            if (
                $this->http->XPath->query("//*[{$this->starts($this->t('Travelers:'))}][.//text()[{$this->starts($this->t('Price:'))}]][.//text()[{$this->starts($this->t('Travel Date:'))}]]")->length > 0
            ) {
                $email->setIsJunk(true);
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            //            "#^\s*[^\s\d]+,\s+([^\s\d]+)\s+(\d+),\s+(\d{4})$#", // Thu, Jul 19, 2018
            //            "#^\w+[,]\s*(\w+)\s*(\d{1,2})[,]\s*(\d{4})\s*at\s*([\d:]+\s*(?:AM|PM))?$#", // Saturday, February 29, 2020 at 01:00 PM
            //            "#^\s*(\w+)\s*(\d{1,2})[,]\s*(\d{4})\s*at\s*(\d+:\d+)(?::\d+)\s*(AM|PM)(?:\s*[A-Z]{3,4})?\s*$#", // January 30, 2021 at 1:25:00 PM EST
            //            "#^\w+\.\,\s*(\w+)\.\s*(\d+)\,\s*(\d{4})\,\s*([\d\:]+)$#", //Mi., Nov. 24, 2021, 09:00
        ];
        $out = [
            //            "$2 $1 $3",
            //            "$2 $1 $3, $4",
            //            "$2 $1 $3, $4 $5",
            //            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
