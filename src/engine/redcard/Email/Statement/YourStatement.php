<?php

namespace AwardWallet\Engine\redcard\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourStatement extends \TAccountChecker
{
    public $mailFiles = "redcard/statements/it-139678708.eml, redcard/statements/it-147103447.eml";
    public $subjects = [
        'Your RedCard statement is available', 'Your Manage My RedCard One Time Passcode',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) || strpos($headers['subject'], 'RedCard') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'The RedCard credit cards are issued by TD Bank')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Your statement'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('is available online'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Statement Closing Date:'))}]")->length > 0
                || $this->findOneTimeCode()->length === 1
            ;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]myredcard\.target\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('YourStatement' . ucfirst($this->lang));

        $st = $email->add()->statement();

        $name = null;
        $names = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hello'))}]", null, "/^{$this->opt($this->t('Hello'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($names)) === 1) {
            $name = array_shift($names);
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('For RedCard ending in'))}]", null, true, "/{$this->opt($this->t('For RedCard ending in'))}\s*(\d{4})$/");

        if ($number !== null) {
            $st->setNumber($number)->masked();
        }

        $passcode = null;
        $otcRoots = $this->findOneTimeCode();

        if ($otcRoots->length === 1) {
            $otcRoot = $otcRoots->item(0);
            $passcode = $this->http->FindSingleNode('.', $otcRoot, true, "/:[ ]*(\d{3,})$/");

            if ($passcode !== null) {
                // it-147103447.eml
                $code = $email->add()->oneTimeCode();
                $code->setCode($passcode);
            }
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Statement Balance:'))}]/ancestor::tr[1]/descendant::td[2]");

        if ($balance !== null) {
            $st->setBalance($balance);
        } elseif ($passcode !== null) {
            $st->setNoBalance(true);
        }

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

    private function findOneTimeCode(): \DOMNodeList
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(),'Please use this One Time Passcode to verify your identity')]");
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
}
