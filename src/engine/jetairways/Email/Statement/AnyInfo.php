<?php

namespace AwardWallet\Engine\jetairways\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class AnyInfo extends \TAccountChecker
{
    public $mailFiles = "jetairways/statements/it-85025689.eml, jetairways/statements/it-85025690.eml, jetairways/statements/it-85025918.eml, jetairways/statements/it-86652838.eml, jetairways/statements/it-85685064.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]intermiles\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match("/Alert[ ]*:[ ]*\d[,.\'\d ]* InterMiles credited to your account/i", $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".intermiles.com/") or contains(@href,"alerts.intermiles.com") or contains(@href,"updates.intermiles.com") or contains(@href,"delivery.intermiles.com") or contains(@href,"rewardstore.intermiles.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for using InterMiles") or contains(normalize-space(),"Warm Regards, Team InterMiles") or contains(normalize-space(),"Kind regards, InterMiles Team") or contains(normalize-space(),"Warm Regards, The InterMiles Team") or contains(.,"www.Intermiles.com") or contains(.,"@intermiles.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $st = $email->add()->statement();

        $name = $number = $balance = null;

        $name = $this->http->FindSingleNode("//text()[{$this->starts(['Dear', 'Hello'])}]", null, true, "/^{$this->opt(['Dear', 'Hello'])}\s+(?:(?:Mr|Ms|Mrs)\.\s*)?({$patterns['travellerName']})(?:[ ]*[,:;!?]|$)/u");

        if (preg_match('/^Member$/i', $name)) {
            $name = null;
        }

        $numbers = array_filter($this->http->FindNodes("//text()[{$this->contains(['Membership Number:', 'InterMiles No.', 'Your InterMiles Membership Account', 'your InterMiles Membership Account', 'Your InterMiles membership number'])}]/following::text()[normalize-space()][1]", null, "/^['\s]*([-A-Z\d]{5,})['\s]*$/"));

        if (count(array_unique($numbers)) === 1) {
            $number = array_shift($numbers);
        }

        if (!$number) {
            $number = $this->http->FindSingleNode("//text()[{$this->contains('login with your membership number')}]", null, true, "/{$this->opt('login with your membership number')}[:\s]+([-A-Z\d]{5,})(?:[ ,.;!?]|$)/");
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->eq('InterMiles Balance')}]/following::text()[normalize-space()][1]", null, true, "/^\d[,.\'\d ]*$/");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if ($number) {
            $st->setNumber($number)->setLogin($number);
        }

        if ($balance !== null) {
            $st->setBalance($this->normalizeAmount($balance));
        } elseif ($name || $number) {
            $st->setNoBalance(true);
        } elseif ($this->isMembership()) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        $singlePhrases = [
            'Membership Number:',
            'You can now login to your InterMiles Membership Account',
            'Your InterMiles membership number',
            'login with your membership number:',
            'Your InterMiles Account Summary',
            'Thank you for your order placed on the InterMiles Reward Store',
            'Take advantage of your InterMiles HDFC Bank Signature Credit Credit Card',
            'Thank you for participating in the InterMiles',
            'We are pleased to inform you that your Tier status has been upgraded to',
        ];

        return $this->http->XPath->query("//*[contains(normalize-space(),'Dear Member,') and {$this->contains(['Thank you for using InterMiles', 'is the OTP to verify your initiated action'])}]
            | //*[contains(normalize-space(),'Your InterMiles Membership Account') and contains(normalize-space(),'was just used to login')]
            | //*[{$this->contains(['Your Voucher request has been processed', 'Your request for cancellation of your booking'])} and {$this->contains(['InterMiles redeemed', 'Total Miles Redeemed'])}]
            | //*[{$this->contains($singlePhrases)}]")->length > 0;
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
