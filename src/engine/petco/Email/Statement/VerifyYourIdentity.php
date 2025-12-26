<?php

namespace AwardWallet\Engine\petco\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class VerifyYourIdentity extends \TAccountChecker
{
    public $mailFiles = "petco/statements/it-562224146.eml";

    private $detectFrom = 'no-reply@account.petco.com';

    private $detectSubject = [
        'Verify your identity',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]petco.com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(., 'account in Petco')]")->length === 0
            && $this->http->XPath->query("//a[contains(@href, '.petco.com')]")->length === 0
        ) {
            return false;
        }

        if ($this->http->XPath->query("//text()[normalize-space() = 'Verify your identity']")->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $code = $this->http->FindSingleNode("//text()[normalize-space() = 'Your code is:']/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{6})\s*$/");

        if (!empty($code)) {
            $otc = $email->add()->oneTimeCode();

            $otc->setCode($code);
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
}
