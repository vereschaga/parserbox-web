<?php

namespace AwardWallet\Engine\national\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class RentalAgreement extends \TAccountChecker
{
    public $mailFiles = "national/it-623492025.eml";

    public $lang = '';

    public static $dictionary = [
        'de' => [ // rentacar/it-59207681.eml
            'Dates & Times' => ['Daten und Zeiten'],
            'Location'      => ['Standort'],
            'confNumber'    => ['MV-Nr.:', 'MV-Nr. :'],
            'Renter:'       => ['Mieter', 'Mieter:', 'Mieter :'],
            'Make / Model:' => ['Marke / Modell:', 'Marke/Modell:'],
        ],
        'fr' => [ // rentacar/it-72511686.eml
            'Dates & Times' => ['Dates et heures'],
            'Location'      => ['Agence'],
            'confNumber'    => ['N° RA:', 'N° RA :'],
            'Renter:'       => ['Locataire', 'Locataire:', 'Locataire :'],
            'Make / Model:' => ['Marque / Modèle:', 'Marque/Modèle:'],
        ],
        'es' => [ // rentacar/it-73024136.eml
            'Dates & Times' => ['Fechas y Horas'],
            'Location'      => ['Oficina'],
            'confNumber'    => ['Núm. de contrato de alquiler:', 'Núm. de contrato de alquiler :'],
            'Renter:'       => ['Arrendatario', 'Arrendatario:', 'Arrendatario :'],
            'Make / Model:' => ['Marca / Modelo:', 'Marca/Modelo:'],
        ],
        'en' => [ // it-623492025.eml
            'Dates & Times' => ['Dates & Times'],
            'Location'      => ['Location'],
            'confNumber'    => ['RA#:', 'RA# :'],
            'Renter:'       => ['Renter', 'Renter:', 'Renter :'],
            'Make / Model:' => ['Make / Model:', 'Make/Model:'],
        ],
    ];

    public static $providers = [
        'alamo' => [
            'Thanks for choosing Alamo',
            'Vielen Dank, dass Sie sich für Alamo entschieden haben',
            'Merci d’avoir choisi Alamo',
            "Merci d'avoir choisi Alamo",
            'Gracias por elegir Alamo',
        ],
        'rentacar' => [
            'Thanks for choosing Enterprise',
            'Vielen Dank, dass Sie sich für Enterprise entschieden haben',
            'Merci d’avoir choisi Enterprise',
            "Merci d'avoir choisi Enterprise",
            'Gracias por elegir Enterprise',
        ],
        'national' => [
            'Thanks for choosing National',
            'Vielen Dank, dass Sie sich für National entschieden haben',
            'Merci d’avoir choisi National',
            "Merci d'avoir choisi National",
            'Gracias por elegir National',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@nationalcar.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['subject']) && preg_match('/(?:National|Enterprise|Alamo) Rental Agreement/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return self::getProvider($parser->getHeaders(), $this->http) && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (strpos($textPdf, 'RENTAL DATE') !== false && strpos($textPdf, 'RETURN LOCATION') !== false) {
                $this->logger->debug('Found PDF-attachment! Go to parser RentalAgreementPdf');

                return $email;
            }
        }

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('RentalAgreement' . ucfirst($this->lang));
        $email->setProviderCode(self::getProvider($parser->getHeaders(), $this->http));

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $r = $email->add()->rental();

        $xpathTableSummary = "//tr/*[normalize-space()][1][ descendant::text()[normalize-space()][1][{$this->eq($this->t('confNumber'))}] and descendant::text()[normalize-space()][2][{$this->eq($this->t('Renter:'))}] ]";
        $td1Text = $this->htmlToText($this->http->FindHTMLByXpath($xpathTableSummary));
        $td2Text = $this->htmlToText($this->http->FindHTMLByXpath($xpathTableSummary . "/following-sibling::*[normalize-space()][1]"));

        if ($td1Text && $td2Text) {
            // rentacar/it-59207681.eml
            $tableSummary = [
                preg_split("/([ ]*\n+[ ]*)+/", $td1Text),
                preg_split("/([ ]*\n+[ ]*)+/", $td2Text),
            ];
        } else {
            // it-623492025.eml
            $tableSummary = [];
        }

        if (count($tableSummary) === 2) {
            $r->general()->confirmation($tableSummary[1][0], preg_replace('/^(.+?)[\s:：]*$/u', '$1', $tableSummary[0][0]));
            $traveller = count($tableSummary[1]) === 2 ? $tableSummary[1][1] : null;
        }

        if (count($tableSummary) === 0) {
            $confirmation = $this->http->FindSingleNode("//*[{$this->eq($this->t('confNumber'))}]/following::*[not(.//tr) and normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
            $confirmationTitle = $this->http->FindSingleNode("//*[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $r->general()->confirmation($confirmation, $confirmationTitle);
        }

        $traveller = $traveller ?? $this->http->FindSingleNode("//*[{$this->eq($this->t('Renter:'))}]/following::*[not(.//tr) and normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");
        $r->general()->traveller($traveller, true);

        $xpathMainTable = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Dates & Times'))}] and *[normalize-space()][2][{$this->eq($this->t('Location'))}] ]/ancestor-or-self::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]/descendant-or-self::*[ *[normalize-space()][2] ][1]";

        $datePickup = $dateDropoff = $timePickup = $timeDropoff = null;

        $datePickupVal = $this->http->FindSingleNode($xpathMainTable . "/*[normalize-space()][1]/*[normalize-space()][1]");
        $dateDropoffVal = $this->http->FindSingleNode($xpathMainTable . "/*[normalize-space()][2]/*[normalize-space()][1]");

        if (preg_match($pattern = "/^(?<date>.{3,}\b\d{4}\b)[,\s]+(?<time>{$patterns['time']})$/", $datePickupVal, $m)) {
            $datePickup = strtotime($this->normalizeDate($m['date']));
            $timePickup = $m['time'];
        }

        if (preg_match($pattern, $dateDropoffVal, $m)) {
            $dateDropoff = strtotime($this->normalizeDate($m['date']));
            $timeDropoff = $m['time'];
        }

        if ($datePickup && $timePickup) {
            $r->pickup()->date(strtotime($timePickup, $datePickup));
        }

        if ($dateDropoff && $timeDropoff) {
            $r->dropoff()->date(strtotime($timeDropoff, $dateDropoff));
        }

        $locationPickup = $this->htmlToText($this->http->FindHTMLByXpath($xpathMainTable . "/*[normalize-space()][1]/*[normalize-space()][2]"));
        $locationDropoff = $this->htmlToText($this->http->FindHTMLByXpath($xpathMainTable . "/*[normalize-space()][2]/*[normalize-space()][2]"));

        if (preg_match($pattern = "/^\s*(?<location>[\s\S]{3,}?)[ ]*\n+[ ]*(?<phone>{$patterns['phone']})[ ]*(?:\n|$)/", $locationPickup, $m)) {
            $r->pickup()->location(preg_replace('/\s+/', ' ', $m['location']))->phone($m['phone']);
        } else {
            $r->pickup()->location(preg_replace('/\s+/', ' ', $locationPickup));
        }

        if (preg_match($pattern, $locationDropoff, $m)) {
            $r->dropoff()->location(preg_replace('/\s+/', ' ', $m['location']))->phone($m['phone']);
        } else {
            $r->pickup()->location(preg_replace('/\s+/', ' ', $locationDropoff));
        }

        $carModel = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][{$this->eq($this->t('Make / Model:'))}] ]/*[normalize-space()][2]", null, true, '/^[^\/]+\/\s*([^\/]+)$/');
        $r->car()->model($carModel);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['alamo', 'rentacar', 'national'];
    }

    public static function getProvider(array $headers, \HttpBrowser $http): string
    {
        // used in parser national/RentalAgreementPdf

        if (!array_key_exists('from', $headers)) {
            $headers['from'] = '';
        }

        if (!array_key_exists('subject', $headers)) {
            $headers['subject'] = '';
        }

        if (stripos($headers['from'], '@alamo.com') !== false || stripos($headers['from'], '@goalamo.com') !== false
            || stripos($headers['subject'], 'Alamo Rental Agreement') !== false
            || stripos($headers['subject'], 'Contrat de location Alamo') !== false
            || $http->XPath->query("//*[" . self::contains(self::$providers['alamo']) . "]")->length > 0
        ) {
            return 'alamo';
        }

        if (stripos($headers['from'], '@enterprise.com') !== false || stripos($headers['from'], '@erac.com') !== false
            || stripos($headers['subject'], 'Enterprise Rental Agreement') !== false
            || stripos($headers['subject'], 'Contrat de location Enterprise') !== false
            || $http->XPath->query("//*[" . self::contains(self::$providers['rentacar']) . "]")->length > 0
        ) {
            return 'rentacar';
        }

        if (stripos($headers['from'], '@nationalcar.com') !== false
            || stripos($headers['subject'], 'National Rental Agreement') !== false
            || stripos($headers['subject'], 'Contrat de location National') !== false
            || $http->XPath->query("//*[" . self::contains(self::$providers['national']) . "]")->length > 0
        ) {
            return 'national';
        }

        return '';
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Dates & Times']) || empty($phrases['Location'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->eq($phrases['Dates & Times'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->eq($phrases['Location'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private static function contains($field, string $node = ''): string
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^[-[:alpha:]]{2,}[,.\s]+([[:alpha:]]{3,})\s+(\d{1,2})[,.\s]+(\d{4})$/u', $text, $m)) {
            // Mon, December 18, 2023
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^[-[:alpha:]]{2,}[,.\s]+(\d{1,2})(?:\s+de)?[.\s]+([[:alpha:]]{3,})\s+(?:de\s+)?(\d{4})$/u', $text, $m)) {
            // Freitag, 20. März 2020    |    miércoles 16 de diciembre de 2020
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
