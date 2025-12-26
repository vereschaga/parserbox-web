<?php

namespace AwardWallet\Engine\sportmaster\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BalanceOnly extends \TAccountChecker
{
    public $mailFiles = "sportmaster/statements/it-94732063.eml, sportmaster/statements/it-545346378.eml";
    public $subjects = [
        '/\D+\, не оставляйте ваши товары\!/u',
        '/\D+\, ещё думаете насчёт покупки\?/u',
        '/\D+\, есть повод вернуться\!/u',
        '/(?:^|[:🎁]+[ ]*)([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])[ ]*,[ ]*(?:дарим \d[,.\'\d ]* бонусов на онлайн-покупки|\d[,.\'\d ]* бонусов на онлайн-покупку уже в вашем распоряжении)/iu',
    ];

    public $lang = 'ru';

    public static $dictionary = [
        "ru" => [
            'бонусов' => ['бонусов', 'бонус'],
        ],
    ];

    private $format = 0;

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (preg_match($subject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".sportmaster.ru/") or contains(@href,"www.sportmaster.ru") or contains(@href,"info.sportmaster.ru")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"Спортмастер")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:info|personal)\.sportmaster\.ru$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('Root-node not found!');

            return $email;
        }
        $email->setType('BalanceOnly' . $this->format . ucfirst($this->lang));
        $root = $roots->item(0);

        $st = $email->add()->statement();

        $balance = $this->http->FindSingleNode(".", $root, true, "/(?:^|[^\d\s]\s*)(\d[,.\'\d ]*)\s*{$this->opt($this->t('бонусов'))}$/i");
        $st->setBalance($balance);

        $name = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('здравствуйте'))}]", null, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])[,\s]+{$this->opt($this->t('здравствуйте'))}(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $name = array_shift($travellerNames);
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
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

    private function findRoot(): \DOMNodeList
    {
        $this->format = 1; // it-545346378.eml
        $nodes = $this->http->XPath->query("//tr[count(*)=3 and count(*[normalize-space()])=1 and *[1]/descendant::img]/*[3][ descendant::text()[{$this->eq($this->t('бонусов'))}] ]");

        if ($nodes->length === 0) {
            $this->format = 2; // it-94732063.eml
            $nodes = $this->http->XPath->query("//tr[ count(*)=3 and *[2][normalize-space()=''] and *[3][{$this->eq($this->t('Личный кабинет'))}] ]/*[1][{$this->starts($this->t('На счёте'))}]");
        }

        return $nodes;
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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
