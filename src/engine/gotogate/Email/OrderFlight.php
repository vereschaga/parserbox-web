<?php

namespace AwardWallet\Engine\gotogate\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderFlight extends \TAccountChecker
{
    public $mailFiles = "gotogate/it-701287439.eml, gotogate/it-714242139.eml, gotogate/it-766472190.eml";
    public $subjects = [
        '/Order\s+[A-Z\d\-]{5,}/',
        '/Your booking under [A-Z\d\-]{5,} has changed/',
        '/Your updated itinerary for order [A-Z\d\-]{5,}/',
    ];

    public static $providers = [
        "gotogate" => [
            "from"       => "gotogate.",
            "detectBody" => ['Gotogate Pty Ltd', 'Gotogate.'],
        ],

        "trip" => [
            "from"       => ".mytrip.com",
            "detectBody" => [
                'Mytrip. ', 'Mytrip / ', ],
        ],
        "fnt" => [
            "from"       => "flightnetwork.com",
            "detectBody" => [
                'Flight Network.',
            ],
        ],
        "flybillet" => [
            "from"       => "@support.flybillet.dk",
            "detectBody" => ["Flybillet."],
        ],
        "supersaver" => [
            "from"       => "@supersaver.nl",
            "detectBody" => ['Supersaver.'],
        ],
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'confFromSubjectRe' => [
                '/Order\s+(?<conf>[A-Z\d\-]{5,})\s*$/i',
                '/Your booking under (?<conf>[A-Z\d\-]{5,}) has changed/i',
            ],
            'Please find the updated flight itinerary for reservation below:' => [
                'Please find the updated flight itinerary for reservation below:',
                'Please find below your updated flight details:',
                'A continuación adjuntamos los datos actualizados del vuelo:', //not error, only this phrases in es
                'Kindly find below the new updated itinerary:',
                'Please find the updated flight itinerary below.',
            ],
            'Ticket numbers' => 'Ticket numbers',
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$providers as $provider) {
            if (isset($headers['from']) && stripos($headers['from'], $provider['from']) !== false) {
                foreach ($this->subjects as $subject) {
                    if (preg_match($subject, $headers['subject'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectProvider()
    {
        foreach (self::$providers as $prov => $provider) {
            foreach ($provider['detectBody'] as $provKey) {
                if ($this->http->XPath->query("//node()[{$this->contains($provKey)}]")->length > 0) {
                    return $prov;
                }
            }
        }

        return null;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (!$this->detectProvider()) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Please find the updated flight itinerary for reservation below:']) && !empty($dict['Ticket numbers'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['Please find the updated flight itinerary for reservation below:'])}]/following::text()[{$this->eq($dict['Ticket numbers'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flightsonbooking\.gotogate\.support$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Please find the updated flight itinerary for reservation below:'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Please find the updated flight itinerary for reservation below:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        // Travel Agency
        $email->obtainTravelAgency();

        foreach ((array) $this->t('confFromSubjectRe') as $re) {
            if (strpos($re, '/') === 0 && preg_match($re, $parser->getSubject(), $m)
            && !empty($m['conf'])
        ) {
                $email->ota()
                ->confirmation($m['conf']);
            }
        }
        $this->ParseFlight($email);

        $code = $this->detectProvider();

        if (!empty($code)) {
            $email->setProviderCode($code);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $segConf = [];
        $ticketsInfo = $this->http->FindNodes("//text()[normalize-space()='Ticket numbers']/ancestor::table[1]/following-sibling::table");
        $traveller = null;

        foreach ($ticketsInfo as $row) {
            if (preg_match("/^\s*(?<pax>\D+\/\D+)\s*$/u", $row, $match)) {
                $traveller = preg_replace("/\s+(?:MRS|MR|MS|MISS|MSTR)\s*$/", "", $match['pax']);
                $traveller = preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", "$2 $1", $traveller);
                $f->general()
                    ->traveller($traveller);
            } elseif (preg_match("/^\s*(?<segConf>[A-Z\d]{6})\s*\W\s+(?<ticket>\d+[\d\-\/]+)\s*$/u", $row, $match)) {
                $f->addTicketNumber($match['ticket'], false, $traveller);

                if (in_array($match['segConf'], $segConf) === false) {
                    $segConf[] = $match['segConf'];
                }
            }
        }

        if (empty($f->getTravellers())) {
            // for error
            $f->general()
                ->traveller(null);
        }

        foreach ($segConf as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Flight']/ancestor::table[contains(normalize-space(), 'Date')][1]/ancestor::table[1]");
        $partSegment = "./preceding::table[2]/ancestor::tr[1][count(*[normalize-space()]) = 2]";

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Flight']/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})$/", $airlineInfo, $m)) {
                $s->airline()
                    ->operator($this->http->FindSingleNode("./descendant::text()[normalize-space()='Operated by']/following::text()[normalize-space()][1][not(contains(normalize-space(), 'Airline reference'))]", $root))
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $s->airline()
                ->confirmation($this->http->FindSingleNode("./descendant::text()[normalize-space()='Airline reference']/following::text()[normalize-space()][1]", $root, true, "/^([A-Z\d]{6})$/"));

            $date = strtotime($this->http->FindSingleNode("./descendant::text()[normalize-space()='Date']/following::text()[normalize-space()][1]", $root, true, "/^(\d+\s*\w+\s*\d{4})$/"));

            $depTime = $this->http->FindSingleNode($partSegment . '/*[1]/descendant::table[3]', $root, true, "/^\s*(\d{1,2}\:\d{2}(?:\s*[ap]m)?\s*(?:\(.*\))?)\s*$/i");

            if (preg_match("/^(?<depTime>\d+\:\d+(?:\s*[ap]m)?)\s*\((?<depDate>\d+\s*\w+)\)$/iu", $depTime, $m)) {
                if (!empty($m['depDate']) && !empty($date)) {
                    $date = EmailDateHelper::parseDateRelative($m['depDate'], $date);
                }
                $depTime = $m['depTime'];
            }

            $s->departure()
                ->code($this->http->FindSingleNode($partSegment . '/*[1]/descendant::table[1]', $root, true, "/^([A-Z]{3})$/"))
                ->date((!empty($date) && !empty($depTime)) ? strtotime($depTime, $date) : null)
                ->name($this->http->FindSingleNode($partSegment . '/*[1]/descendant::table[2]', $root));

            $arrTime = $this->http->FindSingleNode($partSegment . '/*[2]/descendant::table[3]', $root, true, "/^(\d{1,2}\:\d{2}(?:\s*[ap]m)?\s*(?:\(.*\))?)\s*$/i");

            if (preg_match("/^(?<arrTime>\d+\:\d+(?:\s*[ap]m)?)\s*\((?<arrDate>\d+\s*\w+)\)$/iu", $arrTime, $m)) {
                if (!empty($m['arrDate']) && !empty($date)) {
                    $date = EmailDateHelper::parseDateRelative($m['arrDate'], $date);
                }
                $arrTime = $m['arrTime'];
            }

            $s->arrival()
                ->code($this->http->FindSingleNode($partSegment . '/*[2]/descendant::table[1]', $root, true, "/^([A-Z]{3})$/"))
                ->date((!empty($date) && !empty($arrTime)) ? strtotime($arrTime, $date) : null)
                ->name($this->http->FindSingleNode($partSegment . '/*[2]/descendant::table[2]', $root));
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            // Thu 13 Feb, 20:10
            "/^\s*([[:alpha:]]+)\s+(\d{1,2})\s*([[:alpha:]]+)\s*,\s*(\d{1,2}:\d{2}(?: *[ap]m)?)\s*$/u",
        ];
        $out = [
            "$1, $2 $3 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
