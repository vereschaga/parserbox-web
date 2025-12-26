<?php

namespace AwardWallet\Engine\mileageplus\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsing My Account page from site
class SavedStatement2021 extends \TAccountChecker
{
    public $mailFiles = "mileageplus/it-415316863.eml, mileageplus/it-416349210.eml, mileageplus/statements/it-127457458.eml, mileageplus/statements/it-208196888.eml, mileageplus/statements/it-854757141.eml, mileageplus/statements/it-880659259.eml";

    public $lang = '';

    public $detectLang = [
        'en' => ['ACCOUNT'],
        'de' => ['KONTO'],
    ];

    public static $dictionary = [
        "en" => [
            'Your Premier progress' => ['Your Premier progress', 'Premier progress'],
        ],
        "de" => [
            'Hello,'                           => 'HI,',
            'Upcoming trips'                   => 'Bevorstehende Reisen',
            'Your Premier progress'            => 'Ihr Premier-Fortschritt',
            'My United'                        => 'Mein United',
            'MILES'                            => 'MEILEN',
            'Premier qualifying flights (PQF)' => 'Premier Qualifying Flights (PQF)',
            'Premier qualifying points (PQP)'  => 'Premier Qualifying Points (PQP)',
            'Lifetime flight miles'            => 'Unbegrenzte Meilengültigkeit',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $st = $email->createStatement();

        $number = '';

        if (($number = $this->nextText('ACCOUNT NUMBER', '/^[A-Z]{2,3}\d{5,10}$/'))
        || ($number = $this->nextText('MileagePlus Number', '/^[A-Z]{2,3}\d{5,10}$/'))) {
            $st->addProperty('Number', $number);
            $st->addProperty('Login', $number);
        }
        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello,'))}]",
            null, false, "/{$this->opt($this->t('Hello,'))}\s+(.+)/");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $miles = str_replace(',', '', $this->nextText($this->t('MILES'), '/^\d[\d,]*$/'));

        if ($miles == null) {
            $miles = str_replace(',', '', $this->http->FindSingleNode("//text()[{$this->eq($this->t('View My United'))}]/following::text()[{$this->eq($this->t('MILES'))}][1]/following::text()[normalize-space()][1]", null, true, '/^\d[\d,]*$/'));
        }
        $st->setBalance($miles);

        $value = $this->nextText('PLUSPOINTS', '/^\d[\d,]*$/');

        if (empty($value)) {
            $value = $this->http->FindSingleNode("//text()[{$this->eq($this->t('View My United'))}]/following::text()[{$this->eq($this->t('PLUSPOINTS'))}][1]/following::text()[normalize-space()][1]", null, true, '/^([\d\.\,]+)\s*\(/');
        }

        if (empty($value) && empty($this->http->FindSingleNode("(//*[contains(., 'PLUSPOINTS')])[1]"))) {
        } else {
            $st->addProperty('PlusPoints', $value);
        }

        $valueTB = $this->nextText('TRAVELBANK', '/^\$\d[\d,.]*$/');

        if ($valueTB === null) {
            $valueTB = $this->nextText('TravelBank', '/^\$*\d[\d,.]*\s*(?:[A-Z]{3})?$/');
        }

        if ($valueTB !== null) {
            $subAccountData = [
                'Code'        => 'mileageplusTravelBank',
                'DisplayName' => 'TravelBank',
            ];

            if (preg_match("/^\s*(?<currency>\D{1,3})\s*(?<balance>[\d\.\,]+)\s*$/", $valueTB, $m)
            || preg_match("/^\s*(?<balance>[\d\.\,]+)\s*(?<currency>\D{1,3})\s*$/", $valueTB, $m)) {
                $subAccountData['Balance'] = $m['balance'];
                $subAccountData['Currency'] = $m['currency'];
            }

            $expirationDate = $this->http->FindSingleNode("//text()[{$this->contains($this->t('expire'))} and {$this->contains($valueTB)}]/ancestor::div[1]", null, true, "/{$this->opt($this->t('expire'))}\s*(\d+\/\d+\/\d{4})$/");

            if (!empty($expirationDate)) {
                $subAccountData['ExpirationDate'] = strtotime($expirationDate);
            }

            //Roma said
            if (!empty(intval($subAccountData['Balance']))) {
                $st->addSubAccount($subAccountData);
            }
        }

        $value = $this->nextText($this->t('Premier qualifying flights (PQF)'), '/^\d+$/');
        $st->addProperty('EliteFlights', $value);

        $value = $this->nextText($this->t('Premier qualifying points (PQP)'), '/^\d+[\d,]*$/');
        $st->addProperty('ElitePoints', $value);

        $value = $this->nextText($this->t('Lifetime flight miles'), '/^\d+[\d,\.]*$/', '[not(ancestor::button)]');
        $st->addProperty('LifetimeMiles', $value);

        $value = $this->http->FindSingleNode("//text()[{$this->eq($this->t('United Club passes'))}]/following::text()[normalize-space()][1][following::text()[normalize-space()][1][{$this->contains($this->t('Expiration'))}]]");

        if ($value !== null) {
            $st->addProperty('UnitedClubPasses', $value);
        }

        $value = $this->http->FindSingleNode("//text()[normalize-space()='ACCOUNT NUMBER' or normalize-space()='MileagePlus Number']/following::text()[normalize-space()][position() < 5][starts-with(., 'Star Alliance')]",
            null, true, "/^Star Alliance (\w+)$/");

        if (!empty($value)) {
            $st->addProperty('StarAllianceStatus', $value);
        }

        $status = $this->http->FindSingleNode("//*[contains(@class, 'mileagePlusIcon')]/@data-test-id", null, true, "/^.*(?:Platinum|Gold|Global Services|Member|1K|Silver).*$/");

        if (!empty($status)) {
            $st->addProperty('MemberStatus', $status);
        }
        $pooledMiles = $this->http->FindSingleNode("//text()[{$this->contains($this->t('pooled miles'))}]",
            null, true, "/^\s*\|?\s*(\d[\d,]*)\s*{$this->opt($this->t('pooled miles'))}$/");

        if (!empty($pooledMiles)) {
            $st->addProperty('PooledMiles', str_replace(',', '', $pooledMiles));
        }
    }

    public function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }
    }

    public function nextText($name, $regex = null, $cond = null)
    {
        return $this->http->FindSingleNode('//text()[normalize-space(.) = "' . $name . '"]/following::text()[normalize-space()]' . $cond . '[1]', null, true, $regex);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        return $this->http->XPath->query("//*[descendant::text()[normalize-space()][1][" . $this->eq(['ACCOUNT NUMBER', 'ACCOUNT']) . "]][following-sibling::*[descendant::text()[normalize-space()][1][normalize-space()= 'MILES']]]/ancestor::*[descendant::text()[normalize-space()][1][" . $this->eq(['ACCOUNT NUMBER', 'ACCOUNT']) . "]]/following-sibling::*[normalize-space()][1][descendant::text()[normalize-space()][1][normalize-space()= 'Premier progress']]")->length > 0
            || ($this->http->XPath->query("//text()[{$this->contains($this->t('My United'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Upcoming trips'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Your Premier progress'))}]")->length > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
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
}
