<?php

namespace AwardWallet\Engine\israel\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ImportantInformation extends \TAccountChecker
{
    public $mailFiles = "israel/it-378082084.eml, israel/it-380687637.eml";

    public $detectFrom = "@service@elal.elalinfo.co.il";

    public $detectBody = [
        'en' => [
            'This is to inform you of a change in your flight schedule',
            'This is to remind you of a change in your flight schedule',
        ],
    ];

    public $detectSubjects = [
        // en
        'Important Information about your flight',
    ];
    public $lang = '';
    public static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation number'))}]",
                null, true, "/{$this->opt($this->t('Reservation number'))}\s+([A-Z\d]{5,7})$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]",
                null, true, "/{$this->opt($this->t('Dear'))}\s+(.+?)\s*,$/"))
        ;

        $xpath = "//text()[{$this->eq($this->t('New flight details'))}]/following::tr[*[1][{$this->eq($this->t('Departure date'))}] and *[2][{$this->eq($this->t('Route'))}]][1]/following-sibling::tr[normalize-space()]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("*[3]", $root, null, "/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d{1,5}\s*$/"))
                ->number($this->http->FindSingleNode("*[3]", $root, null, "/^\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*$/"))
            ;
            $s->departure()
                ->code($this->http->FindSingleNode("*[2]", $root, null, "/^\s*([A-Z]{3})\s*-\s*[A-Z]{3}\s*$/"))
            ;
            $s->arrival()
                ->code($this->http->FindSingleNode("*[2]", $root, null, "/^\s*[A-Z]{3}\s*-\s*([A-Z]{3})\s*$/"))
            ;

            $s->departure()
                ->date($this->normalizeDate($this->http->FindSingleNode("*[1]", $root)
                    . ', ' . $this->http->FindSingleNode("*[4]", $root)));
            $s->arrival()
                ->date($this->normalizeDate($this->http->FindSingleNode("*[5]", $root)
                    . ', ' . $this->http->FindSingleNode("*[6]", $root)));
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".elal.com")]')->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query('//*[' . $this->contains($dBody) . ']')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers["subject"])) {
            return false;
        }

        if ($this->striposAll($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if (strpos($headers['subject'], $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) === true;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s);
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            //21:35 Wednesday, April 18, 2018
            '#^\s*(\d+:\d+)\s+\w+,\s+(\w+)\s+(\d+),\s+(\d{4})\s*$#u',
        ];
        $out = [
            '$3 $2 $4, $1',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime(trim($str));
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
