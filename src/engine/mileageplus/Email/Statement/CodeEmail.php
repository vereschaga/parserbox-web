<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CodeEmail extends \TAccountChecker
{
    public $mailFiles = "mileageplus/statements/it-714146685.eml";
    public $detectFrom = 'account-noreply@united.com';
    public $detectSubjects = [
        'Verify your email',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Verify your email'                                        => 'Verify your email',
            'Please confirm your email by clicking on the link below.' =>
                'Please confirm your email by clicking on the link below.',
            'Confirm my email' => 'Confirm my email',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains('United Airlines, Inc')}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Verify your email']) && !empty($dict['Please confirm your email by clicking on the link below.'])
                && !empty($dict['Confirm my email'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Verify your email'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Please confirm your email by clicking on the link below.'])}]")->length > 0
                && $this->http->XPath->query("//a[{$this->eq($dict['Confirm my email'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]united\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $code = $this->http->FindSingleNode("//a[{$this->eq($this->t('Confirm my email'))}]/@href[contains(., '//notification.united.com/')]");

        if (!empty($code)) {
            $c = $email->add()->oneTimeCode();
            $c->setCodeAttr("/^http\:\/\/notification\.united\.com\/ls\/click\?upn=u\d+\.[A-z\d\-\_]+$/u", 5000);
            $c->setCode($code);
        }

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
        return 0;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
