<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HolidayCancellation extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-60170578.eml, easyjet/it-755590052.eml";
    public static $dictionary = [
        "en" => [
            'we’ve had to cancel your upcoming holiday to' => [
                'we’ve had to cancel your upcoming holiday to',
                ', we’re sorry that you’ve chosen to cancel your holiday',
                'We’re sorry that you’ve cancelled your holiday',
            ],
        ],
    ];

    private $detectFrom = 'easyjet.com';

    private $detectSubject = [
        'en' => 'easyJet holidays booking',
    ];

    private $detectBody = [
        'en' => [
            'had to cancel your upcoming holiday',
            'you’ve chosen to cancel your holiday',
            'We’re sorry that you’ve cancelled your holiday',
        ],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $body) {
            if ($this->http->XPath->query('//*[' . $this->contains($body) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
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
        if (stripos($headers['from'], $this->detectFrom) !== false) {
            return true;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[' . $this->contains(['tripcase'], '@href') . '] | //img[' . $this->contains(['tripcase'], '@src') . ']')->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query('//*[' . $this->contains($detectBody) . ']')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function parseHtml(Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Holiday reference")) . "]/following::text()[normalize-space()][1]"));

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();
        $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "][1]", null, true, "#^" . $this->opt($this->t("Dear ")) . "\s*(.+?),#u");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("we’ve had to cancel your upcoming holiday to")) . "][1]", null, true,
                "#^\s*([[:alpha:]\- ]+)" . $this->opt($this->t("we’ve had to cancel your upcoming holiday to")) . "#u");
        }

        if (!empty($traveller)) {
            $f->general()
                ->traveller($traveller, false);
        }

        if ($this->http->XPath->query("//node()[" . $this->contains($this->t("we’ve had to cancel your upcoming holiday to")) . "]")->length > 0) {
            $f->general()
                ->status('Cancelled')
                ->cancelled();
        }
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

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode('|', array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
