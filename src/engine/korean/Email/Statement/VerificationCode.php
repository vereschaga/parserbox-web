<?php

namespace AwardWallet\Engine\korean\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class VerificationCode extends \TAccountChecker
{
    public $mailFiles = "korean/statements/it-759551365.eml, korean/statements/it-764749151.eml";

    public $detectFrom = "no-reply@koreanair.com";
    public $detectSubject = [
        // en
        '[Korean Air] Verification code for checking email',
        // pt
        '[Korean Air] Código de verificação para confirmar e-mail',
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
            'textBeforeCode' =>
                'Please enter the following verification code in the checking email page.',
        ],
        'pt' => [
            'textBeforeCode' =>
                'Insira o código de verificação a seguir na página de confirmação de e-mail.',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]koreanair\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Korean Air') === false
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
            $this->http->XPath->query("//a[{$this->contains(['koreanair.'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['www.koreanair.com', 'Korean Air app'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['textBeforeCode']) && $this->http->XPath->query("//*[{$this->eq($dict['textBeforeCode'])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $otc = $email->add()->oneTimeCode();

        $code = $this->http->FindSingleNode("//*[{$this->eq($this->t('textBeforeCode'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{6,})\s*$/");

        if (!empty($code)) {
            $otc->setCode($code);
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['textBeforeCode']) && $this->http->XPath->query("//*[{$this->eq($dict['textBeforeCode'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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
