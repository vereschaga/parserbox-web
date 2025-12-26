<?php

namespace AwardWallet\Engine\vueling\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VuelingClub extends \TAccountChecker
{
    public $mailFiles = "vueling/statements/it-647190550.eml, vueling/statements/it-649731546.eml, vueling/statements/it-650455471.eml";
    public $subjects = [
        'Blue Monday, yellow attitude',
    ];

    public $lang = '';

    public static $dictionary = [
        "en" => [
        ],
        "es" => [
            'Avios balance as of' => 'Balance de Avios extraído el',
        ],
        "fr" => [
            'Avios balance as of' => 'Solde de Avios au',
        ],
    ];

    public $detectLang = [
        "en" => ["Book now"],
        "es" => ["Reserva ahora"],
        "fr" => ["Réservez maintenant"],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@comms.vueling.com') !== false) {
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

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Vueling Airlines')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->starts($this->t('ID'))}]/ancestor::tr[2]/descendant::text()[{$this->contains($this->t('Avios'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@]comms\.vueling\.com/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $st = $email->add()->statement();

        $statementInfo = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'ID')]/ancestor::tr[2]");

        if (preg_match("/\|\s*(?<balance>\d+)\s*{$this->opt($this->t('Avios'))}\s*{$this->opt($this->t('ID'))}\:?\s*(?<number>\d{10,})$/", $statementInfo, $m)) {
            $st->setNumber($m['number'])
                ->setBalance($m['balance']);
        }

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('ID'))}]/ancestor::tr[2]/preceding::tr[1]");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
        }

        $dateOfBalance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Avios balance as of'))}]", null, true, "/{$this->opt($this->t('Avios balance as of'))}\s*(\d{1,2}\/\d+\/\d{4})\.$/");

        if (!empty($dateOfBalance)) {
            $st->setBalanceDate(strtotime(str_replace('/', '.', $dateOfBalance)));
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
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $item) {
                if ($this->http->XPath->query("//text()[{$this->contains($item)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }
    }
}
