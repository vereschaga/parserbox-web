<?php

namespace AwardWallet\Engine\rapidrewards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class SimpleAd extends \TAccountChecker
{
    public $mailFiles = "rapidrewards/statements/it-68665121.eml, rapidrewards/statements/it-78266552.eml, rapidrewards/statements/it-79107973.eml, rapidrewards/statements/st-10143434.eml";

    private $subjects = [
        'en' => [
            'Your travel has been reserved with points',
            'Your account balance has been adjusted',
            'Your Rapid Rewards account has been updated',
            'Your password will need to be reset',
            'Please update your Rapid Rewards password.',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".southwest.com/") or contains(@href,"luv.gotogate.at") or contains(@href,"fly.gotogate.at")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Southwest Airlines Co. All Rights Reserved") or contains(.,"Southwest.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query('//*[contains(.,"RR#")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]southwest\.com/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('SimpleAd');
        $st = $email->createStatement();

        if (!empty($this->http->FindSingleNode("//text()[contains(., 'Update your password today.')]"))) {
            $st
                ->setMembership(true)
                ->setNoBalance(true);

            return $email;
        }

        $roots = $this->http->XPath->query('//text()[contains(.,"RR#")]/ancestor::tr[1]');

        if ($roots->length === 0) {
            return $email;
        }
        $root = $roots->item(0);

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $name = $balance = $number = null;

        $headerRow = $this->http->FindSingleNode('.', $root);

        if (
                preg_match("/Hi[, ]+(?<name>{$patterns['travellerName']})[ ]*\|?\s*\(?(?<balance>\d[,.\'\d]*)\)?[ ]+points?.+RR#[ ]*(?<number>\d+)$/u", $headerRow, $m)
             || preg_match("/Hi[, ]+(?<name>{$patterns['travellerName']})[ ]*\W*\s*RR#[ ]*(?<number>\d+)$/u", $headerRow, $m)
             || preg_match("/^RR#[ ]*(?<number>\d+)$/", $headerRow, $m)
        ) {
            /*
             * Variants:
                Hi Alex  137,406 points  |  RR# 20680184884

                RR# 20680184884

                Hannah | 0 points | RR# 2056761283

                Hi, Jessica Caplinger | RR# 22141194423
            */

            if (!empty($m['name'])) {
                $name = $m['name'];
            }

            if (isset($m['balance']) && strlen($m['balance']) > 0) {
                $balance = $m['balance'];
            }

            $number = $m['number'];
        }

        if (!$name) {
            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Dear')]", null, true, "/Dear[, ]+({$patterns['travellerName']})(?:\s*[,.;:!?]|$)/u");
        }

        $st->setNumber($number)
            ->setLogin($number);

        $st->addProperty('Name', $name);

        if ($balance !== null) {
            $st->setBalance($this->normalizeAmount($balance));
        } elseif ($name !== null || $number !== null) {
            $st->setNoBalance(true);
        }
        $date = strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Details as of')]", null, true, "/^\s*Details as of (\d{1,2}\/\d{1,2}\/\d{2})\s*$/u"));

        if (!empty($date)) {
            $st->setBalanceDate($date);
        }

        $points = $this->http->FindSingleNode('//tr[not(.//tr) and contains(normalize-space(),"Tier Qualifying Points/Flights only")]/preceding-sibling::tr[contains(.,"point") and contains(.,"flight")]');

        if (preg_match('/(\d[,.\'\d]*) \/ \d[,.\'\d]* points?[^\/]+(\d[,.\'\d]*) \/ \d[,.\'\d]* flights?/i', $points, $m)) {
            $st->addProperty('TierPoints', $this->normalizeAmount($m[1]))
                ->addProperty('TierFlights', $this->normalizeAmount($m[2]));
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
}
