<?php

namespace AwardWallet\Engine\taj\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WelcomeTo extends \TAccountChecker
{
    public $mailFiles = "taj/statements/it-98469379.eml, taj/statements/it-98469384.eml, taj/statements/it-99026869.eml";
    public $subjects = [
        "/^Welcome to Taj InnerCircle$/",
        "/Password changed/",
        "/Your One Time Password/",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'detects' => [
                'One time password',
                'Your password is',
                'Taj InnerCircle membership number is',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@tajhotels.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Taj InnerCircle')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('detects'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('DISCLAIMER:'))}]")->length > 0) {
            return true;
        }

        if (stripos($parser->getPlainBody(), 'Taj InnerCircle') !== false
            && stripos($parser->getPlainBody(), 'DISCLAIMER:') !== false
            && (
                stripos($parser->getPlainBody(), 'One time password') !== false
                || stripos($parser->getPlainBody(), 'Your password is') !== false
                || stripos($parser->getPlainBody(), 'Taj InnerCircle membership number is') !== false
            )
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tajhotels.com$/', $from) > 0;
    }

    public function ParseHTML(Email $email)
    {
        //it-99026869
        $this->logger->error('ParseHTML');

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true, "/{$this->opt($this->t('Dear'))}\s*([[:alpha:]][-.'’[:alpha:] ]*[[:alpha:]])\,/");

        if (!empty($name)) {
            $st->addProperty('Name', $name);

            $number = $this->http->FindSingleNode("//text()[contains(normalize-space(),'membership number is')]/ancestor::*[1]", null, true, "/{$this->opt($this->t('membership number is'))}\s*(\d+)/u");

            if (!empty($number)) {
                $st->setNumber($number);
            }

            $st->setNoBalance(true);
        } else {
            $st->setMembership(true);
        }

        $code = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'One time password:')]", null, true, "/{$this->opt($this->t('One time password:'))}\s*(\d+)/");

        if (!empty($code)) {
            $otс = $email->add()->oneTimeCode();
            $otс->setCode($code);
        }
    }

    public function ParseText(Email $email, $body)
    {
        //it-98469384
        $this->logger->error('ParseText');

        $st = $email->add()->statement();

        $name = $this->re("/{$this->opt($this->t('Dear'))}\s*([[:alpha:]][-.'’[:alpha:] ]*[[:alpha:]])\,/", $body);

        if (!empty($name)) {
            $st->addProperty('Name', $name);
            $st->setNoBalance(true);
        } else {
            $st->setMembership(true);
        }

        if (!empty($code = $this->re("/One time password:\s*(\d+)/", $body))) {
            $otс = $email->add()->oneTimeCode();
            $otс->setCode($code);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getPlainBody();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Taj InnerCircle')]")->length > 0) {
            $this->ParseHTML($email);
        } elseif (!empty($this->re("/({$this->opt($this->t('detects'))})/", $body))) {
            $this->ParseText($email, $body);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
