<?php

namespace AwardWallet\Engine\delta\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AwardRedemption extends \TAccountChecker
{
	public $mailFiles = "delta/statements/it-872893178.eml, delta/statements/it-874537571.eml";

    public $subjects = [
        'SkyMiles Award Redemption Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'detectPhrase' => ['An Award Travel transaction has posted to your account.'],
            'Your SkyMiles account' => 'Your SkyMiles account',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 't.delta.com') !== false) {
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
        if (stripos($parser->getHeader('from'), 't.delta.com') === false
            && $this->http->XPath->query("//img/@src[{$this->contains(['delta.com'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['detectPhrase']) && $this->http->XPath->query("//*[{$this->contains($dict['detectPhrase'])}]")->length > 0
                && !empty($dict['Your SkyMiles account']) && $this->http->XPath->query("//*[{$this->contains($dict['Your SkyMiles account'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]t\.delta\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseSummary($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function parseSummary(Email $email)
    {
        $st = $email->add()->statement();

        $st->addProperty('Name', $this->http->FindSingleNode('//text()[starts-with(normalize-space(),"Hello,")]', null, true, "/{$this->opt($this->t('Hello'))}\,\s*([[:alpha:]][-.\'&[:alpha:] ]*[[:alpha:]])$/iu"))
            ->setNumber($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your SkyMiles account'))}]", null, false, "/^{$this->opt($this->t('Your SkyMiles account'))}[ ]+(\b[0-9]+\b)[ ]+/"))
            ->setLogin($login = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your SkyMiles account'))}]", null, false, "/^{$this->opt($this->t('Your SkyMiles account'))}[ ]+(\b[0-9]+\b)[ ]+/"));

        $level = $this->http->FindSingleNode("//a[{$this->eq("#" . $login)}]/following-sibling::a[normalize-space()][1][following::text()[normalize-space()][1][{$this->starts($this->t('Hello,'))}]]");

        if ($level !== null){
            $st->addProperty('Level', str_replace("®", "", $level));
        }

        $st->setNoBalance(true);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s));
            }, $field)) . ')';
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
}
