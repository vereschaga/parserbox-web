<?php

namespace AwardWallet\Engine\jetblue\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class IsReady extends \TAccountChecker
{
    public $mailFiles = "jetblue/it-62859601.eml, jetblue/it-66534971.eml, jetblue/statements/it-103803203.eml";

    public static $dictionary = [
        'en' => [
            'REDEEMABLE' => ['REDEEMABLE', 'INDIVIDUAL', 'AVAILABLE POINTS'],
            'Hi,'        => ['Hi,', 'Hi'],
        ],
    ];

    private $detectFrom = "jetblueairways@email.jetblue.com";
    private $detectSubjects = [
        "your July TrueBlue statement is ready!",
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if (stripos($headers['subject'], $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('JetBlue'))}]")->length === 0) {
            return false;
        }

        return $this->http->XPath->query("//text()[{$this->contains($this->t('REDEEMABLE'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('TrueBlue'))}]")->count() > 0
            || $this->findRoot()->length === 1
        ;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();
        $root = $roots->length === 1 ? $roots->item(0) : null;
        $rootText = $this->http->FindSingleNode('.', $root);

        $login = $number = $name = $balance = $balanceDate = null;

        if (preg_match("/#\s*(?<number>\d{5,})\s*\|\s*(?<balance>\d[,.\'\d ]*?)\s*pts$/i", $rootText, $m)) {
            // | #2143653376 | 77,222 pts
            $number = $m['number'];
            $balance = $m['balance'];
        }

        $st = $email->add()->statement();

        // Login
        $login = $this->http->FindSingleNode("//text()[normalize-space()= 'This e-mail was sent to']/following::text()[1]", null, true,
            "#^\s*(.+@.+\.[^\d\W]+)\s*$#u");

        $st->setLogin($login);

        // Number
        if ($number === null) {
            $number = $this->http->FindSingleNode("//text()[normalize-space()='TrueBlue']/following::text()[1][starts-with(normalize-space(),'#')]", null, true, "/^\s*#\s*(\d{5,})\s*$/u");
        }
        $st->setNumber($number);

        // Name
        $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hi,")) . "]", null, true,
            "#^\s*" . $this->preg_implode($this->t("Hi,")) . "\s*([^\d\W]+(?: [^\d\W]+){0,4})\s*\.\s*$#u");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hi,")) . "]", null, true,
                "#^\s*(?:Hi,?)\s*(\D+)\.?$#u");
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        // Balance
        if ($balance === null) {
            $balance = $this->http->FindSingleNode("//*[{$this->starts($this->t('REDEEMABLE'))}]/preceding::text()[normalize-space()][1]", null, true, "/^\d[,.\'\d ]*$/");
        }

        if ($balance !== null) {
            $st->setBalance((int) (str_replace(',', '', trim($balance))));
            $balanceDate = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Activity as of')]", null, true, "/Activity as of\s*(.+)/");

            if ($balanceDate) {
                $st->setBalanceDate(strtotime($balanceDate));
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class));

        return $email;
    }

    private function findRoot(): \DOMNodeList
    {
        // it-103803203.eml
        return $this->http->XPath->query("//tr[not(.//tr) and contains(normalize-space(),'#') and contains(normalize-space(),'|') and contains(normalize-space(),'pts')]");
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
