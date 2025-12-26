<?php

namespace AwardWallet\Engine\westjet\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class NewTime extends \TAccountChecker
{
    public $mailFiles = "westjet/it-47765397.eml,westjet/it-48934722.eml";
    private static $detectors = [
        'en' => ["We apologize for any inconvenience this change may cause.", "New estimated arrival time:"],
    ];
    private static $dictionary = [
        'en' => [
            "New estimated arrival time:"                          => ["New estimated arrival time:"],
            "Thank you for being our guest"                        => ["Thank you for being our guest"],
            "Hello"                                                => ["Hello"],
            "flight"                                               => ["flight"],
            "from"                                                 => ["from"],
            "to"                                                   => ["to"],
            "at"                                                   => ["at"],
            "is now departing"                                     => ["is now departing"],
            "Visit"                                                => ["Visit"],
            "Important: your WestJet flight's time has changed - " => ["Important: your WestJet flight's time has changed - "],
        ],
    ];
    private $from = "westjet.com";
    private $body = "westjet.com";
    private $subject = [
        "Important: your WestJet flight's time has changed - ",
    ];
    private $lang;
    private $recordLocator;
    private $emailDate;

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], "noreply@notifications.westjet.com") === false) {
            return false;
        }

        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, $this->body) === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $this->emailDate = strtotime($parser->getDate());

        $this->parseEmail($email, $parser->getSubject());

        return $email;
    }

    private function detectBody()
    {
        foreach (self::$detectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $w) {
            if (isset($w["New estimated arrival time:"], $w["Thank you for being our guest"])) {
                if ($this->http->XPath->query("//*[{$this->contains($w['New estimated arrival time:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($w['Thank you for being our guest'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmail(Email $email, $subject)
    {
        if (!$this->detectBody()) {
            return false;
        }

        $r = $email->add()->flight();

        // Passenger
        $passenger = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hello")) . "]", null, true,
            "/" . $this->opt($this->t("Hello")) . "\s+(.+),/");

        if (!empty($passenger)) {
            $r->general()
                ->traveller($passenger, false);
        }

        if (preg_match('/' . $this->opt($this->t("Important: your WestJet flight's time has changed - ")) . '([A-Z\d]{5,6})/',
            $subject, $m)) {
            if (!empty($m[1])) {
                $r->general()
                    ->confirmation($m[1], 'RecordLocator');
            }
        }

        $this->parseSegment($r);

        return $email;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function parseSegment(Flight $r)
    {
        $s = $r->addSegment();

        $segment = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("New estimated arrival time:")) . "]/ancestor::tr[1]",
            null, true);

        if (!empty($segment)) {
            if (preg_match("/(.+?)\s" . $this->opt($this->t("flight")) . "\s([A-Z]{2})(\d+)\s" . $this->opt($this->t("from")) . "\s([A-z].+?)\s\(([A-Z\d]{3})\)\sto\s([A-z].+?)\s\(([A-Z\d]{3})\)\s" . $this->opt($this->t("is now departing")) . "\s([A-z]+),\s([A-z]+\s\d+)\s" . $this->opt($this->t("at")) . "\s(\d{1,2}:\d{2})/",
                $segment, $m)) {
                $s->airline()
                    ->name($m[2])
                    ->number($m[3]);

                $s->departure()
                    ->name($m[4])
                    ->code($m[5]);

                $s->arrival()
                    ->name($m[6])
                    ->code($m[7]);

                $year = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), '@') and contains(., 'WestJet')]", null, true, "/@\s*(\d{4})\D+/");
                if (empty($year)) {
                    $year = date("Y", $this->emailDate);
                }

                $depDate = $this->normalizeDate($m[8], $m[9] . ($year? ' ' . $year : '') . ' ' . $m[10]);

                if (!empty($depDate)) {
                    $s->departure()
                        ->date($depDate);
                }

                $arrTime = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("New estimated arrival time:")) . "]",
                    null,
                    true, "/\d{1,2}:\d{1,2}/");

                if (!empty($arrTime)) {
                    $arrDate = $this->normalizeDate($m[8], $m[9] . ($year? ' ' . $year : '') . ' ' . $arrTime);

                    if (!empty($arrDate)) {
                        $s->arrival()
                            ->date($arrDate);
                    }
                }
            }
        }
    }

    private function normalizeDate($week, $date)
    {
        return EmailDateHelper::parseDateUsingWeekDay($date . ' ' . '', WeekTranslate::number1($week, 'en'));
    }
}
