<?php

namespace AwardWallet\Engine\national\Email;

use AwardWallet\Engine\MonthTranslate;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "national/it-6667145.eml";

    protected $lang = '';

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'National Car Rental') !== false
            || stripos($from, '@nationalcar.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'National Car Rental México') !== false
            || stripos($headers['from'], 'reservaciones@nationalcar.com.mx') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//img[contains(@src,"//nationalcar.com.mx") and contains(@src,"logo")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//nationalcar.com.mx")]')->length === 0;
        $condition3 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Gracias por elegir a National")]')->length === 0;

        if ($condition1 && $condition2 && $condition3) {
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
            'emailType' => 'YourReservation_' . $this->lang,
        ];
    }

    public static function getEmailLanguages()
    {
        return ['es'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/(\d{1,2})(?:\s+de)?\s+([^\d\s]{3,})\s*,\s*(\d{4})\s+(\d{1,2}:\d{2}\s*[AP]M)$/i', $string, $matches)) { // 22 de May, 2017 1:30 PM
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
            $time = $matches[4];
        }

        if ($day && $month && $year && $time) {
            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year . ', ' . $time;
        }

        return false;
    }

    protected function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function parseEmail()
    {
        $patterns = [
            'hours' => '/^\s*[^:]{2,}[^:]*:[^:]*\d/',
        ];

        $it = [];
        $it['Kind'] = 'L';

        $reservationNumber = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"El número de tu reservación es")]/following::text()[normalize-space(.)][1]', null, true, '/^([-\d\s]+)$/');
        $it['Number'] = str_replace(' ', '', $reservationNumber);
        $it['RenterName'] = $this->http->FindSingleNode('//td[normalize-space(.)="Nombre"]/following-sibling::td[normalize-space(.)][1]');

        $xpathFragment1 = '//tr[not(.//tr) and ./td[normalize-space(.)="Entrega"]]/following-sibling::tr[normalize-space(.)]';
        $locationPickup = $this->http->FindSingleNode($xpathFragment1 . '[1][count(./descendant::img)<2]');
        $datePickupTexts = $this->http->FindNodes($xpathFragment1 . '[2]/descendant::text()[normalize-space(.)]');
        $datePickup = implode(' ', $datePickupTexts);
        $it['PickupDatetime'] = strtotime($this->normalizeDate($datePickup));
        $xpathFragmentTel = '/descendant::text()[starts-with(normalize-space(.),"Tel:")]';
        $xpathFragmentAddress = '[./descendant::text()[normalize-space(.)="Dirección:"]]' . $xpathFragmentTel . '/preceding-sibling::text()[normalize-space(.)]';
        $addressPickupTexts = $this->http->FindNodes($xpathFragment1 . $xpathFragmentAddress);
        $addressPickupValues = array_values(array_filter($addressPickupTexts));

        if ($locationPickup && !empty($addressPickupValues[0])) {
            $it['PickupLocation'] = $locationPickup . ', ' . implode(', ', $addressPickupValues);
        } elseif (!empty($addressPickupValues[0])) {
            $it['PickupLocation'] = implode(', ', $addressPickupValues);
        } elseif ($locationPickup) {
            $it['PickupLocation'] = $locationPickup;
        }
        $it['PickupPhone'] = $this->http->FindSingleNode($xpathFragment1 . $xpathFragmentTel, null, true, '/^[^:]+:\s*([-.\d\s]+)/');
        $hoursPickup = '';
        $hoursPickupTexts = $this->http->FindNodes($xpathFragment1 . '/descendant::text()[normalize-space(.)="Horas de operación:"]/following-sibling::text()[normalize-space(.)]');

        foreach ($hoursPickupTexts as $hoursPickupText) {
            if (preg_match($patterns['hours'], $hoursPickupText)) {
                $hoursPickup .= ', ' . $hoursPickupText;
            }
        }

        if (!empty($hoursPickup)) {
            $it['PickupHours'] = trim($hoursPickup, ', ');
        }

        $xpathFragment2 = '//tr[not(.//tr) and ./td[normalize-space(.)="Devolución"]]/following-sibling::tr[normalize-space(.)]';
        $locationDropoff = $this->http->FindSingleNode($xpathFragment2 . '[1][count(./descendant::img)<2]');
        $dateDropoffTexts = $this->http->FindNodes($xpathFragment2 . '[2]/descendant::text()[normalize-space(.)]');
        $dateDropoff = implode(' ', $dateDropoffTexts);
        $it['DropoffDatetime'] = strtotime($this->normalizeDate($dateDropoff));
        $addressDropoffTexts = $this->http->FindNodes($xpathFragment2 . $xpathFragmentAddress);
        $addressDropoffValues = array_values(array_filter($addressDropoffTexts));

        if ($locationDropoff && !empty($addressDropoffValues[0])) {
            $it['DropoffLocation'] = $locationDropoff . ', ' . implode(', ', $addressDropoffValues);
        } elseif (!empty($addressDropoffValues[0])) {
            $it['DropoffLocation'] = implode(', ', $addressDropoffValues);
        } elseif ($locationDropoff) {
            $it['DropoffLocation'] = $locationDropoff;
        }
        $it['DropoffPhone'] = $this->http->FindSingleNode($xpathFragment2 . $xpathFragmentTel, null, true, '/^[^:]+:\s*([-.\d\s]+)/');
        $hoursDropoff = '';
        $hoursDropoffTexts = $this->http->FindNodes($xpathFragment2 . '/descendant::text()[normalize-space(.)="Horas de operación:"]/following-sibling::text()[normalize-space(.)]');

        foreach ($hoursDropoffTexts as $hoursDropoffText) {
            if (preg_match($patterns['hours'], $hoursDropoffText)) {
                $hoursDropoff .= ', ' . $hoursDropoffText;
            }
        }

        if (!empty($hoursDropoff)) {
            $it['DropoffHours'] = trim($hoursDropoff, ', ');
        }

        $xpathFragment3 = '//tr[not(.//tr) and ./td[normalize-space(.)="Vehículo"]]/following-sibling::tr[normalize-space(.)][1]';
        $it['CarType'] = $this->http->FindSingleNode($xpathFragment3 . '/descendant::td[not(.//td) and count(./descendant::text()[normalize-space(.)])>1]/descendant::text()[normalize-space(.)][1]');
        $carModelTexts = $this->http->FindNodes($xpathFragment3 . '/descendant::td[not(.//td) and count(./descendant::text()[normalize-space(.)])>1]/descendant::text()[normalize-space(.)][position()>1]');
        $it['CarModel'] = implode(' ', $carModelTexts);
        $it['CarImageUrl'] = $this->http->FindSingleNode($xpathFragment3 . '/descendant::td[not(.//td) and count(./descendant::img[@src])=1]/descendant::img[1]/@src');

        $xpathFragment4 = '//tr[not(.//tr) and ./td[normalize-space(.)="Tarifas e Impuestos"]]/following-sibling::tr[normalize-space(.)][1]';
        $total = $this->http->FindSingleNode($xpathFragment4 . '/descendant::td[not(.//td) and starts-with(normalize-space(.),"Total (MXN)")]/following-sibling::td[normalize-space(.)][1]');

        if (preg_match('/^([^\d\s]+)\s*([,.\d]+)/', $total, $matches)) {
            $it['Currency'] = $matches[1];
            $it['TotalCharge'] = $this->normalizePrice($matches[2]);
            $fees = [];
            $feesNames = [
                'Sub-Total Tarifa Base',
                'Sub-Total Protecciones',
                'Sub-Total Extras',
                'I.V.A.',
            ];
            $patternCurrency = str_replace('$', '\$', $it['Currency']);

            foreach ($feesNames as $feesName) {
                $feesValue = $this->http->FindSingleNode($xpathFragment4 . '/descendant::td[not(.//td) and normalize-space(.)="' . $feesName . '"]/following-sibling::td[normalize-space(.)][1]');

                if (preg_match('/^' . $patternCurrency . '\s*([,.\d]+)/', $feesValue, $m)) {
                    $fees[] = ['Name' => $feesName, 'Charge' => $this->normalizePrice($m[1])];
                }
            }

            if (count($fees)) {
                $it['Fees'] = $fees;
            }
        }

        return $it;
    }

    protected function assignLang()
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"El número de tu reservación") and contains(normalize-space(.),"Devolución")]')->length > 0) {
            $this->lang = 'es';

            return true;
        }

        return false;
    }
}
