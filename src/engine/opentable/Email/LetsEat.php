<?php

namespace AwardWallet\Engine\opentable\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class LetsEat extends \TAccountChecker
{
    public $mailFiles = "opentable/it-142441957.eml";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = '';

    private $emailDate;
    private $detectFrom = 'opentable.com';
    private $detectSubject = [
        // en
        'Let\'s eat! I reserved a table for',
    ];

    private $detectBody = [
        "en" => [
            'Let\'s eat! I reserved a table for',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        if (empty($body)) {
            $body = $parser->getPlainBody();
        } else {
            $body = preg_replace("/<br[^>]*>/", "\n", $body);
            $body = preg_replace(["/></", "/(<(?:td|p)(?: |>))/"], ["> <", "\n"."$1"], $body);
            $body = htmlspecialchars_decode(strip_tags($body));
        }

        $this->detectBody();

        $this->emailDate = strtotime("-1 day", strtotime($parser->getDate()));

        $this->parseHtml($email, $body ?? '');

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
            $body = $this->http->Response['body'];
        }

        if ($this->http->XPath->query('//a[contains(@href,".opentable.com")]')->length === 0
            && stripos($body, '.opentable.com') === false) {
            return false;
        }

        return $this->detectBody($body);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email, string $text)
    {
        $r = $email->add()->event();

        // General
        $r->general()
            ->noConfirmation();

        // Place
        $r->place()
            ->type(Event::TYPE_RESTAURANT)
            ->name($this->re("/a table for \d+ at (.+?):\s*http/", $text))
        ;
        if (preg_match("/Address:(?<address>[\s\S]+?)(?<phone>\n\s*[\(\) \-+\d]+\d+[\(\) \-+\d]+\s*)?\bMap:/", $text, $m)) {
            if (strlen(preg_replace("/\D/", '', $m['phone'])) > 5) {
                $r->place()->phone(trim($m['phone']));
            } else {
                $m['address'] = $m['address']."\n".$m['phone'];
            }
            $r->place()
                ->address(preg_replace("/\s*\n\s*/", ", ", trim($m['address'])));
        }

        // Booked
        $r->booked()
            ->start($this->normalizeDate($this->re("/When:\s*(.+)/", $text)))
            ->noEnd()
            ->guests($this->re("/reserved a table for (\d+) at /", $text))
        ;

        return $email;
    }

    private function detectBody($body = '')
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }
        if (empty($body)) {
            return false;
        }
        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($body, $dBody) !== false
                ) {
                    $this->lang = $lang;

                    return true;
                }
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

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->emailDate);
        $in = [
            // Thursday, 28 April at 19:45
            '/^\s*(\w+),\s*(\d+)\s+(\w+)\s+at\s+(\d+:\d+(?:\s*[ap]m)?)\s*$/iu',
            // Friday, March 4 at 8:30 PM
            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s+at\s+(\d+:\d+(?:\s*[ap]m)?)\s*$/iu',
        ];
        $out = [
            '$1, $2 $3 ' . $year . ' $4',
            '$1, $3 $2 ' . $year . ' $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)){
            if ($en = MonthTranslate::translate($m[1], $this->lang))
                $date = str_replace($m[1], $en, $date);
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }
        return $date;

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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);
        if (isset($m[$c])) {
            return $m[$c];
        }
        return null;
    }
}
