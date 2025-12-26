<?php

namespace AwardWallet\Engine\germanwings\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Delayed extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Your Delayed Flight:' => 'Your Delayed Flight:',
        ],
    ];

    private $detectFrom = "info@schedule.eurowings.com";
    private $detectSubject = [
        // en
        //  Your flight EW9467 will be delayed
        'will be delayed',
    ];
    private $detectBody = [
        'en' => [
            'We are very sorry for the delay ',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]eurowings\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        if ($this->http->XPath->query("//*[{$this->contains(['Eurowings GmbH'])}]")->length === 0
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
            if (!empty($dict["Your Delayed Flight:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Your Delayed Flight:'])}]")->length > 0
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
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Code:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"));

        // Segments
        $xpath = "//text()[{$this->contains($this->t(': Delayed'))}]/ancestor::tr[preceding-sibling::tr[normalize-space()][1][count(descendant::td[not(.//td)][normalize-space()]) = 2]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineText = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+)\s*:/u", $airlineText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $date = $this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][1]",
                $root, true, "/^\s*(.+?)\s*\|/");

            $s->departure()
                ->code($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][2][count(descendant::td[not(.//td)][normalize-space()]) = 2]/descendant::td[not(.//td)][normalize-space()][1]", $root))
                ->name($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][3][count(descendant::td[not(.//td)][normalize-space()]) = 2]/descendant::td[not(.//td)][normalize-space()][1]", $root))
                ->date($this->normalizeDate($date . ', '
                    . $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][1]", $root)))
            ;

            $s->arrival()
                ->code($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][2][count(descendant::td[not(.//td)][normalize-space()]) = 2]/descendant::td[not(.//td)][normalize-space()][2]", $root))
                ->name($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][3][count(descendant::td[not(.//td)][normalize-space()]) = 2]/descendant::td[not(.//td)][normalize-space()][2]", $root))
                ->date($this->normalizeDate($date . ', '
                    . $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][2]", $root)))
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            //            // Fr. 09.6.2023, 21:15
            '/^\s*[[:alpha:]\-]+[.?]\s+(\d{1,2})\.(\d{1,2})\.(\d{4}),\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$3-$2-$1, $4',
        ];

        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('date replace = ' . print_r( $date, true));

        if (preg_match("/^\s*\d{4}-\d{1,2}-\d{1,2},\s*\d{1,2}:\d{2}(?:\s*[ap]m)?\s*$/", $date)) {
            return strtotime($date);
        }

        // $this->logger->debug('date end = ' . print_r($date, true));

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
}
