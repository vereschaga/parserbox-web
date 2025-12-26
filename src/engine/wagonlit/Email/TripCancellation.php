<?php

namespace AwardWallet\Engine\wagonlit\Email;

class TripCancellation extends \TAccountChecker
{
    public $mailFiles = "wagonlit/it-20177992.eml, wagonlit/it-8076821.eml";

    protected $langDetectors = [
        'en' => ['Trip locator:', 'Trip locator :', 'Trip Locator:', 'Trip Locator :'],
        'nl' => ['Boekingsreferentie:', 'Boekingsreferentie :'],
    ];

    protected $lang = '';

    protected static $dict = [
        'en' => [
            'Travelers' => ['Travelers', 'Traveler'],
        ],
        'nl' => [
            'Trip locator:'          => 'Boekingsreferentie:',
            'Date:'                  => 'Datum:',
            'Travelers'              => ['Reizigers', 'Reiziger'],
            'YOUR TRIP'              => 'UW REIS',
            'IS NOW FULLY CANCELLED' => 'IS NU VOLLEDIG GEANNULEERD',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'CWT Service Center') !== false
            || preg_match('/[.@]carlsonwagonlit\.com/i', $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['from'], 'CWT Service Center') !== false
            || stripos($headers['from'], 'info@reservation.carlsonwagonlit.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"CWT is commit") or contains(normalize-space(.),"CWT’s Travel")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.carlsonwagonlit.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignLang();
        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => 'TripCancellation_' . $this->lang,
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    protected function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function parseEmail()
    {
        $it = [];
        $it['Kind'] = 'T';

        if ($this->http->XPath->query("//text()[({$this->contains($this->t('YOUR TRIP'))}) and ({$this->contains($this->t('IS NOW FULLY CANCELLED'))})]")->length > 0) {
            $it['Status'] = 'CANCELLED';
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('YOUR RESERVATION DOES NOT CONTAIN ANY TRIP PLANS'))}]")->length > 0) {
            $it['Status'] = $this->t('DOES NOT CONTAIN ANY TRIP PLANS');
        } else {
            return false;
        }

        $it['Cancelled'] = true;

        $xpathFragment1 = "//text()[{$this->eq($this->t('Trip locator:'))}]";

        $it['RecordLocator'] = $this->http->FindSingleNode($xpathFragment1 . '/following::text()[normalize-space(.)][1]', null, true, '/^([A-Z\d]{5,})$/');

        $date = $this->http->FindSingleNode($xpathFragment1 . "/following::text()[{$this->eq($this->t('Date:'))}][1]/following::text()[normalize-space(.)][1]", null, true, '/^(\d{1,2}\s*[^\d\s]{3,}\s*\d{2,4})$/');

        if ($date) {
            $it['ReservationDate'] = strtotime($date);
        }

        $traveler = $this->http->FindSingleNode('//td[' . $this->eq($this->t('Travelers')) . ']/following-sibling::td[normalize-space(.)][1]', null, true, '/^([^}{]{3,})$/');

        if ($traveler) {
            $it['Passengers'] = [$traveler];
        }

        return $it;
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
