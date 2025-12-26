<?php

namespace AwardWallet\Engine\thaiair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "thaiair/it-407095716.eml, thaiair/it-407122131.eml";
    public $subjects = [
        'OTP Code for Royal Orchid Plus Online Service : Member ID',
    ];

    public $lang = '';

    public $detectLang = [
        "th" => ["รหัส OTP"],
        "en" => ["OTP Code"],
    ];

    public static $dictionary = [
        "en" => [
        ],
        "th" => [
            'Thai Airways International Public Company Limited' => 'บริษัท การบินไทย จำกัด (มหาชน)',
            'Your One Time Password (OTP) has been requested.'  => 'ตามที่ท่านได้แจ้งความประสงค์ขอ One Time Password (OTP)',
            'OTP Code'                                          => 'รหัส OTP',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@thaiairways.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        return $this->http->XPath->query("//text()[{$this->contains($this->t('Thai Airways International Public Company Limited'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your One Time Password (OTP) has been requested.'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('OTP Code'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]thaiairways\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $code = $this->http->FindSingleNode("//text()[{$this->starts($this->t('OTP Code'))}]", null, true, "/^{$this->opt($this->t('OTP Code'))}[\s\:]+(\d{4})$/");

        if (!empty($code)) {
            $c = $email->add()->oneTimeCode();
            $c->setCode($code);
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(), 'Subject:')]/following::text()[contains(normalize-space(), 'OTP Code for Royal Orchid Plus Online Service : Member ID')]")->length > 0) {
            $email->setIsJunk(true);
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

    private function assignLang()
    {
        foreach ($this->detectLang as $key => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $key;

                    return true;
                }
            }
        }

        return false;
    }
}
