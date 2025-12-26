<?php

namespace AwardWallet\Engine\petco\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Statement2024 extends \TAccountChecker
{
    public $mailFiles = "petco/statements/it-645378564.eml, petco/statements/it-646076070.eml";

    private $detectFrom = 'petco@e.petco.com';

    private $detectUniqueSubject = [
        // only subjects with provider name or rewards program name
        'Your Monthly Vital Care Rewards Statement',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]petco.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) !== false) {
            return true;
        }

        foreach ($this->detectUniqueSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.petco.com')]")->length > 2
            && $this->http->XPath->query("//text()[contains(., ', your ')]/following::text()[normalize-space()][2][contains(., 'Vital Care statement is here!')]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $patterns = [
            'travellerName' => '[[:alpha:]][-[:alpha:] ]*[[:alpha:]]',
        ];

        $name = $this->http->FindSingleNode("(//text()[contains(., ', your ')][following::text()[normalize-space()][2][contains(., 'Vital Care statement is here!')]])[1]",
            null, true, "/^[ ]*({$patterns['travellerName']}), your\s*$/u");

        $st->addProperty('Name', $name);

        $userEmail = $this->http->FindSingleNode("//text()[normalize-space() = 'This email was sent to:' or normalize-space() = 'This email was sent to']/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\S+@\S+\.[[:alpha:]]+)\s*$/");

        $st->setLogin($userEmail);

        $balance = $this->http->FindSingleNode("//text()[{$this->eq('Vital Care points')}]/preceding::text()[normalize-space()][1]",
            null, true, "/^\s*(\d[,. \d ]*?)\s*$/");
        $st->setBalance($this->normalizeAmount($balance));

        $balanceDate = $this->http->FindSingleNode("//text()[{$this->starts(["*Your account information as of"])}]",
            null, true, "/^\s*\*Your account information as of\s+(.{6,}?)[. ]*$/");
        $st->parseBalanceDate($balanceDate);

        $pointsNext = $this->http->FindSingleNode("//td[not(.//td)][{$this->contains('points away from your next reward!')}][1]",
            null, true, "/\b(\d[,. \d ]*?) points away from your next reward!/");

        if ($pointsNext !== null) {
            $st->addProperty('PointsToNextReward', $this->normalizeAmount($pointsNext));
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return [];
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }
}
