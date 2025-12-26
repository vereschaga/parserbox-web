<?php

namespace AwardWallet\Engine\tigerairtw\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightInformation extends \TAccountChecker
{
    public $mailFiles = "tigerairtw/it-245836749.eml, tigerairtw/it-248007583.eml";

    private $detectFrom = ".tigerairtw.com";
    private $detectSubject = [
        'travel in 2 Days! Seize the last chance to buy discounted baggage allowance!'
    ];

    private $detectBody = [
        'en' => [
            'Flight Information',
        ],
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers["subject"], 'Tigerair Taiwan') === false
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
            $this->http->XPath->query("//a[{$this->contains(['.tigerairtw.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['choosing Tigerair Taiwan'])}]")->length === 0
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

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t("Booking Reference"))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t("Hi,"))}]", null, true,
                "/{$this->opt($this->t("Hi,"))}\s*(?:(?:Ms|Mr|Mrst|Mrs|Dr)\s+)?(.+)/i"))
        ;

        // Segments
        $xpath = "//text()[{$this->starts($this->t("Departure time"))}]";
        $nodes = $this->http->XPath->query($xpath);
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $node = $this->http->FindSingleNode("preceding::td[not(.//td)][normalize-space()][1]", $root);
            if (preg_match("/^{$this->opt($this->t("Flight number"))}\s*:\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5})\s*$/", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $node = implode("\n", $this->http->FindNodes("preceding::td[not(.//td)][normalize-space()][2]//text()[normalize-space()]", $root));
            if (preg_match("/^\s*(?<dCode>[A-Z]{3})\s+(?<dName>.+)\s*\n\s*\W\s*\n\s*(?<aCode>[A-Z]{3})\s+(?<aName>.+)\s*$/us", $node, $m)) {
                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName']);

                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName']);
            }

            $s->departure()
                ->terminal($this->http->FindSingleNode("following::td[not(.//td)][normalize-space()][2][{$this->starts($this->t("Check-in Terminal"))}]", $root, null,
                    "/{$this->opt($this->t("Check-in Terminal"))}\s*:\s*(.+)/"), true, true);

            $s->departure()
                ->date($this->normalizeDate($this->http->FindSingleNode("ancestor::td[1]", $root, null,
                    "/^\s*{$this->opt($this->t("Departure time"))}\s*:\s*(.+)\s*$/")));

            $s->arrival()
                ->date($this->normalizeDate($this->http->FindSingleNode("following::td[not(.//td)][normalize-space()][1]", $root, null,
                    "/^\s*{$this->opt($this->t("Arrival time"))}\s*:\s*(.+)\s*$/")));
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

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }


    private function eq($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }


    private function starts($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }


    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
//            // 31 January 2023 (Tue), 15:00
            '/^\s*(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*\([[:alpha:]]+\)[\s,]*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }
//        $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }


    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
                return preg_quote($s, $delimiter);
            }, $field)) . ')';
    }
}