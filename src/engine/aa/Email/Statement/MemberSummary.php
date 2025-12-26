<?php

namespace AwardWallet\Engine\aa\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MemberSummary extends \TAccountChecker
{
	public $mailFiles = "aa/statements/it-860003311.eml, aa/statements/it-861004843.eml";
    public $subjects = [
        'Thanks for booking: Here are your trip details + travel tips to prepare',
        'prepare for your upcoming trip to'
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Thanks for choosing American' => ['Thanks for choosing American', "Let's get ready to travel"],
            'Manage your account' => 'Manage your account',
            'Your AAdvantage' => 'Your AAdvantage',
            'AAdvantage' => ['AAdvantage', 'ConciergeKey']
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'info.ms.aa.com') !== false) {
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
        if (stripos($parser->getHeader('from'), 'info.ms.aa.com') === 0
            && $this->http->XPath->query("//img/@src[{$this->contains(['aa.com'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Thanks for choosing American']) && $this->http->XPath->query("//*[{$this->contains($dict['Thanks for choosing American'])}]")->length > 0
                && !empty($dict['Your AAdvantage']) && $this->http->XPath->query("//*[{$this->contains($dict['Your AAdvantage'])}]")->length > 0
                && !empty($dict['Manage your account']) && $this->http->XPath->query("//*[{$this->contains($dict['Manage your account'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.ms\.aa\.com$/', $from) > 0;
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
        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Thanks for choosing American'))}]/preceding::td[normalize-space()][1][.//a]/descendant::text()[normalize-space()][1][{$this->contains($this->t('AAdvantage'))}]/ancestor::*[1]");
            if ($status !== null){
                $st = $email->add()->statement();
                $st->addProperty('Status', preg_replace("/(?:AAdvantage|\®)/", "", $status));

                $login = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Thanks for choosing American'))}]/preceding::td[normalize-space()][1]//a");

                if ($login !== null && preg_match("/^([A-Z0-9]{3,})\*+$/", $login, $m)){
                    $st->setLogin($m[1])->masked('right');
                    $st->setNumber($m[1])->masked('right');
                } else {
                    $st->setLogin(null);
                }

                $st->setBalance(str_replace(",", "", $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your AAdvantage'))}]/following::text()[{$this->eq($this->t('Award miles'))}]/ancestor::tr[1]", null, true, "/^\s*(\d[\d, ]*)\s*{$this->opt($this->t('Award miles'))}$/")));
                $st->addProperty('ElitePoints', str_replace(",", "", $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your AAdvantage'))}]/following::text()[{$this->eq($this->t('Loyalty Points'))}]/ancestor::tr[1]", null, true, "/^\s*(\d[\d, ]*)\s*{$this->opt($this->t('Loyalty Points'))}$/")));

                $st->setBalanceDate(strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your AAdvantage'))}]/ancestor::td[2]/descendant::tr[normalize-space()][{$this->starts($this->t('As of'))}]", null, true, "/^{$this->t('As of')}\s*(\d{1,2}\/\d{1,2}\/\d{4})$/")));
            }
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
