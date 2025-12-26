<?php

namespace AwardWallet\Engine\sportmaster\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReportBonus extends \TAccountChecker
{
    public $mailFiles = "sportmaster/statements/it-90194669.eml";
    public $subjects = [
        '/\w+\, у Вас накопились бонусы\!/u',
    ];

    public $lang = 'ru';

    public static $dictionary = [
        "ru" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@info.sportmaster.ru') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Спортмастер')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('это Ваш электронный отчет о состоянии бонусного счета'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Уровень участия:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.sportmaster\.ru$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Здравствуйте,'))}]", null, true, "/^{$this->opt($this->t('Здравствуйте,'))}\s+(\D+)$/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Выписка по бонусам клубной карты'))}]/following::text()[{$this->contains($this->t('за период'))}][1]", null, true, "/^(\d{12,})\s*{$this->opt($this->t('за период'))}/");

        if (!empty($number)) {
            $st->setNumber($number);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Уровень участия:'))}]/following::text()[contains(normalize-space(), '|')][1]", null, true, "/^\s*(.+)\s\|/u");

        if (!empty($status)) {
            $st->addProperty('Status', $status);
        }

        $pointsExpiring = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Кэшбэк бонусы за покупки'))}]/ancestor::tr[1]/descendant::td[normalize-space()][last()]");

        if (!empty($pointsExpiring)) {
            $st->addProperty('PointsToExpire', $pointsExpiring);
        }

        $dateExpiration = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Кэшбэк бонусы за покупки'))}]/following::text()[normalize-space()][1]", null, true, "/{$this->opt($this->t('Действительны до'))}\s*([\d\.]+)/");

        if (!empty($dateExpiration)) {
            $st->setExpirationDate(strtotime($dateExpiration));
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Кэшбэк бонусы'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");
        $st->setBalance($balance);

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
