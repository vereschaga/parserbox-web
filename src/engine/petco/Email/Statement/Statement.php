<?php

namespace AwardWallet\Engine\petco\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Statement extends \TAccountChecker
{
    public $mailFiles = "petco/statements/it-562718811.eml, petco/statements/it-78505033.eml, petco/statements/it-78647391.eml, petco/statements/it-78705661.eml, petco/statements/it-85675483.eml";

    private $detectFrom = 'Petco@e.petco.com';

    private $detectUniqueSubject = [
        // only subjects with provider name or rewards program name
        'Your Monthly Pals Rewards Statement',
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
        if ($this->http->XPath->query("//text()[contains(., 'Pals Reward') or contains(., 'Vital Care account')]")->length > 0
            && $this->http->XPath->query("//a[contains(@href, '.petco.com')]")->length > 2) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $name = $this->http->FindSingleNode("//tr[starts-with(normalize-space(),'Hi ')]", null, true, "/^Hi\s+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u")
            ?? $this->http->FindSingleNode("//tr[starts-with(normalize-space(),'Hi')]", null, true, "/^Hi[ ]*,[ ]*({$patterns['travellerName']})(?:\s*[.;:!?]|$)/u");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $userEmail = $this->http->FindSingleNode("//text()[normalize-space() = 'This email was sent to:']/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\S+@\S+\.[[:alpha:]]+)\s*$/");

        $number = $this->http->FindSingleNode("//text()[contains(., 'statement')]/following::text()[normalize-space()][1][{$this->starts(['Account', 'Vital Care'])}]",
            null, true, "/^\s*(?:Account|Vital Care)\s*#?\s*(\d{5,})\s*$/");

        if (!empty($number)) {
            // it-85675483.eml
            $st
                ->setNumber($number)
                ->setLogin($userEmail)
            ;

            $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Current points:')][1]", null, true, "/^Current points:\s*(\d[,.\'\d ]*)$/");

            if ($balance === null) {
                $balance = $this->http->FindSingleNode("//td[{$this->eq(['Current points:', 'Currentpoints:'])}]/following-sibling::td[normalize-space()][1]", null, true, "/^\d[,.\'\d ]*$/");
            }
            $st->setBalance($this->normalizeAmount($balance));

            $balanceDate = $this->http->FindSingleNode("//text()[{$this->starts(["Here’s your account information as of", "Here’s your Vital Care account information as of"])}]",
                null, true, "/Here’s your (?:Vital Care )?account information as of\s+(.{6,}?)[. ]*$/");

            if (empty($balanceDate)) {
                $balanceDate = $this->http->FindSingleNode("//text()[{$this->eq(["Here’s your account information as of", "Here’s your Vital Care account information as of"])}]/following::text()[normalize-space()][1]",
                    null, true, "/^\s*(.{6,}?)[. ]*$/");
            }

            if ($balanceDate) {
                $st->parseBalanceDate($balanceDate);
            }

            $pointsNext = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Points to next reward:')][1]", null, true, "/^Points to next reward:\s*(\d[,.\'\d ]*)$/");

            if ($pointsNext === null) {
                $pointsNext = $this->http->FindSingleNode("//td[normalize-space()='Points to next reward:' or normalize-space()='Points tonext reward:']/following-sibling::td[normalize-space()][1]", null, true, "/^\d[,.\'\d ]*$/");
            }

            if ($pointsNext !== null) {
                $st->addProperty('PointsToNextReward', $this->normalizeAmount($pointsNext));
            }

            return $email;
        }

        $number = $this->http->FindSingleNode("//text()[normalize-space() = 'Pals Rewards number:']/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{5,})\s*$/");

        if (!empty($number)) {
            // it-78705661.eml
            $st
                ->setNumber($number)
                ->setNoBalance(true)
                ->setLogin($userEmail)
            ;

            return $email;
        }

        if (!empty($this->http->FindSingleNode("//text()[contains(., 'points')]/ancestor::*[position()<4][contains(., 'Pals Reward')][1]",
        null, true, "/^\s*[[:alpha:] \-]+, only (\d+ points?)\*? to your next Pals Reward\./"))) {
            // it-78505033.eml
            $st
                ->setMembership(true)
                ->setNoBalance(true)
                ->setLogin($userEmail)
            ;

            return $email;
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
