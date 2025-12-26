<?php

namespace AwardWallet\Engine\lyft\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TransferPdf extends \TAccountChecker
{
    public $mailFiles = "lyft/it-47798444.eml, lyft/it-54733802.eml";

    public $lang = '';

    public static $dict = [
        'en' => [
        ],
    ];
    private $from = [
        '@business.lyftmail.com',
    ];
    private $subjects = [
        'en' => 'Lyft Business Ride Report', 'Lyft Ride Report',
    ];
    private $detectLang = [
        'en' => ['Business Ride Report', 'Business ride report', 'Ride Report', 'Ride report'],
    ];

    public function detectEmailFromProvider($from)
    {
        return $this->stripos($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return $this->stripos($headers['subject'], $this->subjects) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//text()[contains(normalize-space(),"Lyft, Inc")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->alert("Can't determine a language!");

            return $email;
        }

        $pdfs = $parser->searchAttachmentByName('ride_report.*?pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }
            $this->parseTransfer($email, substr($textPdf, 0, 10000));
        }

        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseTransfer(Email $email, string $text): void
    {
        $t = $email->add()->transfer();
        $t->general()->noConfirmation();
        //  kCao Cao                                                  $30.21
        if (preg_match('/^.+?\n\s{2,}([\w\s]+)\s{2,}(.)([\d,.]+)\n/', $text, $m)) {
            $t->general()->traveller($m[1]);
            $t->price()->currency($this->currency($m[2]))
                ->total($m[3]);
        }

        $relativeDate = 0;

        /*
            December 30 2019 -
            January 1 2020
        */
        $patternDate1 = "/"
            . "^[ ]*([[:alpha:]]{3,} \d{1,2} \d{4}) -.*\n"
            . "^[ ]*[[:alpha:]]{3,} \d{1,2} \d{4}(?:[ ]{2}|$)"
            . "/m";

        /*
            October 30 -
            October 31 2019
        */
        $patternDate2 = "/"
            . "^[ ]*([[:alpha:]]{3,} \d{1,2}) -.*\n"
            . "^[ ]*[[:alpha:]]{3,} \d{1,2} (\d{4})(?:[ ]{2}|$)"
            . "/m";

        if (preg_match($patternDate1, $text, $m)) {
            $relativeDate = strtotime($m[1]);
        } elseif (preg_match($patternDate2, $text, $m)) {
            $relativeDate = strtotime($m[1] . ' ' . $m[2]);
        }

        if (empty($relativeDate)) {
            $this->logger->debug('Date relative is wrong!');

            return;
        }

        $items = $this->splitter("#\s{2,}(\w+ \d{1,2}, \d{1,2}:\d{1,2}[AP]M\s{2,}.[\d,.]+\n)#", $text);

        foreach ($items as $item) {
            $s = $t->addSegment();
            /*
                                        October 30, 5:48AM                  $14.90
                October 31 2019         3412 E Salisbury Cir, Orange
                2 selected rides        Airport Way, , CA
                                        Ride purpose:
                                        Expense code:

                Lyft, Inc
            */
            $pattern = "/"
                . "(?<date>[[:alpha:]]{3,} \d{1,2}), (?<time>\d{1,2}:\d{1,2}[AP]M)[ ]{2,}.[\d,.]+\n"
                . "(?:[\w ]+[ ]{5,})?(?<dep>.+)\n"
                . "(?:[\w ]+[ ]{5,})?(?<arr>.+)"
                . "/";

            if (preg_match($pattern, $item, $m)) {
                $date = EmailDateHelper::parseDateRelative($m['time'] . ' ' . $m['date'], $relativeDate, true, $m['time'] . ' ' . $m['date'] . ' %Y%');
                $s->departure()
                    ->date($date)
                    ->address(preg_replace('/^(.{2,}?\S)[ ]{2,}\S.*/', '$1', $m['dep']));
                $s->arrival()
                    ->noDate()
                    ->address(preg_replace('/^(.{2,}?\S)[ ]{2,}\S.*/', '$1', $m['arr']));
            }
        }
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $phrase) {
            if ($this->http->XPath->query("//text()[{$this->contains($phrase)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return false;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function stripos($haystack, $arrayNeedle): bool
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
