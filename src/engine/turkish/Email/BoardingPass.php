<?php

namespace AwardWallet\Engine\turkish\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "turkish/it-10163190.eml, turkish/it-103415620.eml, turkish/it-17953612.eml";

    private $lang = 'en';

    private $reFrom = 'smartmobile@mail.turkishairlines.com';

    private $reSubject = [
        'en' => 'Boarding Pass',
    ];

    private $reBody = 'Turkish Airlines';
    private $reBody2 = [
        'de' => 'BOARDING-ZEIT',
        'en' => 'BOARDING PASS', // last
    ];

    private static $dict = [
        'en' => [],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->reBody2 as $lang => $re) {
            if (stripos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Turkish Airlines') !== false
            || stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'Turkish Airlines') === false && stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $xpath = "//text()[contains(normalize-space(), 'BOARDING PASS')])[1]/ancestor::table[1]";

        if ($this->http->XPath->query("(//text()[contains(normalize-space(), 'BOARDING PASS')])[1]/ancestor::table[2]//text()[contains(normalize-space(), 'PASSENGER:')]")->length > 0) {
            $xpath = "//text()[contains(normalize-space(), 'BOARDING PASS')])[1]/ancestor::table[2]";
        }

        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("({$xpath}//text()[contains(normalize-space(), 'PASSENGER')]/ancestor::td[1]//descendant::text()[normalize-space()!=''][2]"));

        $ticket = $this->http->FindSingleNode("({$xpath}//text()[contains(normalize-space(), 'E-TICKET')]/ancestor::td[1]//descendant::text()[normalize-space()!=''][2]");

        if (!empty($ticket)) {
            $f->issued()
                ->ticket($ticket, false);
        }

        $accounts = array_filter([
            $this->http->FindSingleNode("({$xpath}//text()[contains(normalize-space(), 'FREQUENT FLYER')]/ancestor::td[1]//descendant::text()[normalize-space()!=''][2]"), ]);

        if (count($accounts) > 0) {
            $f->setAccountNumbers($accounts, false);
        }

        $s = $f->addSegment();
        $node = $this->http->FindSingleNode("({$xpath}//text()[contains(normalize-space(), 'DEPARTS FROM')]/ancestor::td[1]//descendant::text()[normalize-space()!=''][2]");

        if (preg_match("#(.+)\(([A-Z]{3})\)#", $node, $m)) {
            $s->departure()
                ->name(trim($m[1]))
                ->code($m[2]);
        }

        $node = $this->http->FindSingleNode("({$xpath}//text()[contains(normalize-space(), 'DEPARTS FROM')]/ancestor::td[1]//descendant::text()[contains(normalize-space(), 'Terminal')]");

        if (!empty($node)) {
            $s->departure()
                ->terminal(trim(str_ireplace('Terminal', '', $node)));
        }
        $s->departure()
            ->date($this->normalizeDate(implode(" ", $this->http->FindNodes("({$xpath}//text()[contains(normalize-space(), 'DEPARTS FROM')]/ancestor::td[1]//descendant::text()[normalize-space() and not(contains(normalize-space(), 'Terminal'))][position() > 2]"))));

        $node = $this->http->FindSingleNode("({$xpath}//text()[contains(normalize-space(), 'ARRIVAL')]/ancestor::td[1]//descendant::text()[normalize-space()!=''][2]");

        if (preg_match("#(.+)\(([A-Z]{3})\)#", $node, $m)) {
            $s->arrival()
                ->name(trim($m[1]))
                ->code($m[2]);
        }

        $node = $this->http->FindSingleNode("({$xpath}//text()[contains(normalize-space(), 'ARRIVAL')]/ancestor::td[1]//descendant::text()[contains(normalize-space(), 'Terminal')]");

        if (!empty($node)) {
            $s->arrival()
                ->terminal(trim(str_ireplace('Terminal', '', $node)));
        }
        $s->arrival()
            ->date($this->normalizeDate(implode(" ", $this->http->FindNodes("({$xpath}//text()[contains(normalize-space(), 'ARRIVAL')]/ancestor::td[1]//descendant::text()[normalize-space() and not(contains(normalize-space(), 'Terminal'))][position() > 2]"))));

        $node = $this->http->FindSingleNode("({$xpath}//text()[contains(normalize-space(), 'FLIGHT')]/ancestor::td[1]//descendant::text()[normalize-space()!=''][2]");

        if (preg_match("#^([A-Z\d]{2})(\d{1,5})$#", $node, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
        }

        $seat = $this->http->FindSingleNode("({$xpath}//text()[contains(normalize-space(), 'SEAT')]/ancestor::td[1]//descendant::text()[normalize-space()!=''][2]", null, true, "#^(\d{1,3}[A-Z])$#");

        if (!empty($seat)) {
            $s->extra()
                ->seat($seat);
        }

        $class = $this->http->FindSingleNode("({$xpath}//text()[contains(normalize-space(), 'CLASS')]/ancestor::td[1]//descendant::text()[normalize-space()!=''][2]");

        if (preg_match("#^([A-Z]{1,2})$#", $class)) {
            $s->extra()
                ->bookingCode($class);
        } elseif (preg_match("#^(.+?)\s*\(([A-Z]{1,2})\)\s*$#", $class, $m)) {
            $s->extra()
                ->cabin($m[1])
                ->bookingCode($m[2]);
        }

        return true;
    }

    private function t($str)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$str])) {
            return $str;
        }

        return self::$dict[$this->lang][$str];
    }

    private function normalizeDate($str)
    {
        $in = [
            '/^\s*(\d{1,2})-(\d{1,2})-(\d{4})\s+(\d{1,2}:\d{2})(\s+\d+:\d+)?\s*$/', // 06-12-2017 12:00
            '/^\s*(\d{1,2})[. ]+([^\d\s\.\,]+)\s+(\d{4})\s+(\d{1,2}:\d{2})\s*$/', // 26. Juli 2018 19:15
        ];
        $out = [
            '$1.$2.$3 $4',
            '$1 $2 $3 $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match('/\d+\s+([^\d\s]+)\s+\d{4}/', $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
