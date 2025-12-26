<?php

namespace AwardWallet\Engine\withlocals\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CancelledBooking extends \TAccountChecker
{
    public $mailFiles = "withlocals/it-678302965.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = "@withlocals.com";
    private $detectSubject = [
        // en
        'Cancellation confirmation for booking',
    ];
    private $detectBody = [
        'en' => [
            'successfully processed your request to cancel the booking',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]withlocals\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Withlocals') === false
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
            $this->http->XPath->query("//a[{$this->contains(['.withlocals.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//img[{$this->contains(['.withlocals.com'], '@src')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Withlocals BV - '])}]")->length === 0
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
        $event = $email->add()->event();

        $event->type()->event();

        // General
        $event->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('cancel the booking'))}]",
                null, true, "/{$this->opt($this->t('cancel the booking'))}\s+([\da-z]{5,})-[a-z\d\-]+\./"))
            ->status('Cancelled')
            ->cancelled()
        ;
        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi '))}]",
            null, true, "/^\s*{$this->opt($this->t('Hi '))}\s*(\D+?)[\W\s]*,\s*$/");

        if (!empty($traveller)) {
            $event->general()
                ->traveller($traveller, false);
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
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
