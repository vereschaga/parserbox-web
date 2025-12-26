<?php

namespace AwardWallet\Engine\opentable\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class GotAnotherPoints extends \TAccountChecker
{
    public $mailFiles = "opentable/it-39685971.eml, opentable/it-40132439.eml";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = '';

    private $detectFrom = 'opentable.com';
    private $detectSubject = [
        "en" => "Your reservation at",
    ];

    private $detectBody = [
        'en'=> ['Your reservation details'],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        $this->detectBody();

        // Travel Agency
        $email->obtainTravelAgency();
        $awards = array_sum(array_filter(str_replace(',', '', $this->http->FindNodes("//text()[" . $this->eq("Book again") . "]/following::td[" . $this->starts("+") . " and " . $this->contains("points") . "][1]", null, "#\+\s*([\d,]+)\s*points\b#"))));

        if (!empty($awards)) {
            $email->ota()
                ->earnedAwards($awards . ' points');
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers["subject"], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if ($this->http->XPath->query('//a[contains(@href,".opentable.com")]')->length === 0) {
            return false;
        }

        return $this->detectBody();
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
        $xpath = "//text()[" . $this->eq("Book again") . "]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->event();

            // General
            $r->general()
                ->noConfirmation();

            // Place
            $r->place()
                ->type(Event::TYPE_RESTAURANT)
                ->name($this->http->FindSingleNode("./ancestor::*[1]/tr[normalize-space()][1]", $root))
                ->address($this->http->FindSingleNode("./ancestor::*[1]/tr[normalize-space()][2]", $root))
            ;

            // Booked
            $date = $this->http->FindSingleNode("./ancestor::*[1]/tr[normalize-space()][3]", $root);
            $time = $this->http->FindSingleNode("./ancestor::*[1]/tr[normalize-space()][4]", $root);
            $r->booked()
                ->start(strtotime($date . ', ' . $time))
                ->noEnd();
        }

        return $email;
    }

    private function detectBody()
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//        $this->logger->alert($str);
        $in = [
            //Sunday, May 14, 2017 at 7:15 pma
            //            "#[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+at\s+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)[Aa]?$#",
            //Saturday, 26 May 2018 at 8:00 pm
            //            "#[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4})\s+at\s+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)[Aa]?$#",
        ];
        $out = [
            //            "$2 $1 $3, $4",
            //            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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
}
