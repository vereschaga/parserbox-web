<?php

namespace AwardWallet\Engine\skywards\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightChanged extends \TAccountChecker
{
    public $mailFiles = "skywards/it-10483417.eml, skywards/it-65501843.eml";
    private $lang = '';
    private $reFrom = ['emirates.com'];
    private $reProvider = ['Emirates'];
    private $reSubject = [
        'The departure time has changed for your flight to',
        'Important changes to your Emirates flight',
    ];
    private $reBody = [
        'en' => [
            'The departure time of your flight has been changed as follows',
            'Important changes to your Emirates flight',
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseFlight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
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

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()->confirmation($this->http->FindSingleNode("//node()[contains(text(), 'Emirates Booking Reference') or contains(text(), 'Emirates booking reference')]/ancestor::tr[1]", null, true, '/Emirates Booking Reference\s*:\s*([A-Z\d]{5,9})/i'));

        $traveller = $this->http->FindSingleNode("//span[contains(text(), 'Dear') and not(.//span)]/following-sibling::span[1]", null, true, '/(.+?),/');

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//span[contains(text(), 'Dear') and not(contains(text(),'customer'))]", null, true, '/Dear (.+?),/');
        }

        if (isset($traveller)) {
            $f->general()->traveller($traveller);
        }

        $xpath = "//tr[(contains(., 'Flight Number') or contains(., 'Flight number')) and not(.//tr)]/ancestor::*[1][preceding::table[1][not(contains(normalize-space(.), 'Old flight details:'))]]/following-sibling::*[1]/tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->debug("[XPATH]: {$xpath}");

            return [];
        }

        foreach ($roots as $root) {
            $s = $f->addSegment();

            if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $this->getNode($root), $m)) {
                $s->airline()->name($m[1]);
                $s->airline()->number($m[2]);
            }

            // Dubai International Airport (DXB) Terminal 3
            // Auckland International Airport (AKL) Terminal INTERNATIONAL
            $re = '/(.+)\s+\(([A-Z]{3})\)\s*(?:Terminal\s+(\w+))?/';

            foreach ([
                'departure' => $this->getNode($root, 2),
                'arrival' => $this->getNode($root, 3),
            ] as $name => $value) {
                if (preg_match($re, $value, $m)) {
                    $s->$name()->name($m[1]);
                    $s->$name()->code($m[2]);

                    if (!empty($m[3])) {
                        $s->$name()->terminal($m[3]);
                    }
                }
            }

            foreach ([
                'departure' => $this->getNode($root, 4),
                'arrival' => $this->getNode($root, 5),
            ] as $name => $value) {
                // 06:00 Sunday 24 Dec
                if (preg_match('/(?<time>\d+:\d+)\s*(?<week>[[:alpha:]]+)\s*(?<date>\d{1,2} \w+)/i', $value, $m)) {
                    $numberOfDay = WeekTranslate::number1($m['week']);
                    $s->$name()->date(strtotime($m['time'], EmailDateHelper::parseDateUsingWeekDay($m['date'], $numberOfDay)));
                } // Thursday 3 Dec 10:05
                elseif (preg_match('/(?<week>[[:alpha:]]+) (?<date>\d{1,2} \w{3}) (?<time>\d{1,2}:\d{2})/', $value, $m)) {
                    $numberOfDay = WeekTranslate::number1($m['week']);
                    $s->$name()->date(strtotime($m['time'],
                        EmailDateHelper::parseDateUsingWeekDay($m['date'], $numberOfDay)));
                }
                elseif (preg_match('/(\d{1,2} \w+ \d{2,4} \d{1,2}:\d{2})/', $value, $m)) {
                    $s->$name()->date(strtotime($m[1]));
                }
            }

            $cabin = $this->getNode($root, 6);

            if (preg_match('/^[A-z]{4,15}$/', $cabin)) {
                $s->extra()->cabin($cabin);
            }
        }

        return $email;
    }

    private function getNode(\DOMNode $root, int $td = 1, string $re = null): ?string
    {
        return $this->http->FindSingleNode("td[{$td}]", $root, true, $re);
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $this->t($field);

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
