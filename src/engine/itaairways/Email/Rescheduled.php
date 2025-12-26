<?php

namespace AwardWallet\Engine\itaairways\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Rescheduled extends \TAccountChecker
{
    public $mailFiles = "itaairways/it-360041870.eml";
    public $detectSubject = [
        "ITA Airways info PNR",
    ];

    public $detectProvider = '.ita-airways.com';
    public $detectFrom = 'notice@enews.ita-airways.com';

    public $detectBody = [
        "en" => [
            "has been rescheduled.",
        ],
        "it" => [
            "ha subito una variazione.",
        ],
    ];

    public $lang;
    public $emailSubject;

    public static $dictionary = [
        "en" => [
            "Dear "          => "Dear ",
            "SegmentsStarts" => ["provide you immediate assistance", "The flight is now expected to leave with flight"],
            "SegmentsEnds"   => "no further action is required",
        ],
        "it" => [
            "Dear "          => "Gentile ",
            "SegmentsStarts" => ["per fornirti pronta assistenza", "viaggio identificato per fornirti pronta assistenza"],
            "SegmentsEnds"   => "non occorre ulteriore azione da parte tua",
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '" . $this->detectProvider . "')]")->length == 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
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
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/PNR\s+([A-Z\d]{5,7})$/", $this->subject))
        ;

        $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true,
            "/" . $this->preg_implode($this->t("Dear ")) . "(.+?),/");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[" . $this->eq(preg_replace('/\s+$/', '', $this->t("Dear "))) . "]/ancestor::*[not(" . $this->eq(preg_replace('/\s+$/', '', $this->t("Dear "))) . ")][1]", null, true,
                "/" . $this->preg_implode($this->t("Dear ")) . "(.+?),/");
        }
        $f->general()
            ->traveller($traveller);

        $xpath = "//tr[not(.//tr)][ preceding::node()[{$this->contains($this->t('SegmentsStarts'))}] and following::node()[{$this->contains($this->t('SegmentsEnds'))}] ][normalize-space()]";
        $nodes = $this->http->XPath->query($xpath);

        $row = 1;

        foreach ($nodes as $root) {
            if ($row > 5) {
                $f->addSegment();
                $this->logger->debug('something go wrong');
            }
            $values = $this->http->FindNodes("*[normalize-space()]", $root);
//            $this->logger->debug('$values = '.print_r( $values,true));
            if (preg_match("/^\s*([A-Z]{3})\s*$/", $values[1] ?? null)
                && preg_match("/^\s*([A-Z]{3})\s*$/", $values[2] ?? null)
            ) {
                $row = 1;
            }

            if ($row == 1) {
                $date = $values[0] ?? null;
                $s = $f->addSegment();
                $s->departure()
                    ->code($this->re("/^\s*([A-Z]{3})\s*$/", $values[1] ?? null));
                $s->arrival()
                    ->code($this->re("/^\s*([A-Z]{3})\s*$/", $values[2] ?? null));
                $row++;

                continue;
            }

            if ($row == 2) {
                $s->airline()
                    ->name($this->re("/^\s*([A-Z\d]{2})\s*$/", $values[0] ?? null));
                $s->departure()
                    ->name($values[1] ?? null);
                $s->arrival()
                    ->name($values[2] ?? null);
                $row++;

                continue;
            }

            if ($row == 3) {
                $s->airline()
                    ->number($this->re("/^\s*(\d+)\s*$/", $values[0] ?? null));
                $s->departure()
                    ->date($this->normalizeDate($date . ', ' . $values[1] ?? null));
                $s->arrival()
                    ->date($this->normalizeDate($date . ', ' . $values[2] ?? null));
                $row++;

                continue;
            }
        }

        return true;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        if (preg_match("/^\s*(\d{1,2}+)\\/(\d{2})\\/(\d{4})\s*,\s*(\d{1,2}:\d{1,2}(?:\s*[ap]m)?)\s*$/iu", $str, $m)) {
            return strtotime($m[1] . '.' . $m[2] . '.' . $m[3] . ', ' . $m[4]);
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
