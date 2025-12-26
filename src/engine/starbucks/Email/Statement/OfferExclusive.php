<?php

namespace AwardWallet\Engine\starbucks\Email\Statement;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OfferExclusive extends \TAccountChecker
{
    public $mailFiles = "starbucks/statements/it-65924734.eml, starbucks/statements/it-80096227.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'userEmail' => ['This offer is exclusively for', 'Your address is listed as'],
        ],
    ];

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".starbucks.com/")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Starbucks Coffee Company. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//*[contains(normalize-space(),'This offer is exclusively for') or contains(normalize-space(),'Star Balance as of')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mg\.starbucks\.com$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $login = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('userEmail'))} and not(contains(.,'@'))][last()]/following::text()[normalize-space()][1]", null, true, '/^\S+@\S+$/');
        $st->setLogin($login);

        $balanceText = implode("\n", $this->http->FindNodes("//table[not(.//table) and starts-with(normalize-space(),'You have') and contains(normalize-space(),'Stars')]/descendant::text()[normalize-space()]"));

        if (preg_match("/You have (\d[,.\'\d ]*) Stars/i", $balanceText, $m)) {
            $st->setBalance($this->normalizeAmount($m[1]));

            if (preg_match("/Star Balance as of (\d{1,2}\/\d{1,2})$/im", $balanceText, $m)) {
                $st->setBalanceDate(EmailDateHelper::calculateDateRelative($m[1], $this, $parser, '%D%/%Y%'));
            }
        } elseif ($login) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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
}
