<?php

namespace AwardWallet\Engine\aeroplan\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingSummaryVacations extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-738768570.eml, aeroplan/it-738830597.eml, aeroplan/it-749277776.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            // 'Your booking number is:' => '',
            'Date of Birth:' => ['Date of Birth:', 'Date of birth:'],
            // 'Aeroplan Number:'        => '',
            'Hotel & Room' => ['Hotel & Room', 'Hotel & room'],
            // 'Check-in:'               => '',
            // 'Check-out:'              => '',
            // 'Operated by'             => '',
            // 'Price Details'           => '',
            // 'taxes and fees'          => '',
            // // 'EXTRAS' => '',
            // 'Subtotal'        => '',
            // 'Aeroplan points' => '',
            // 'TOTAL'           => '',
        ],
        'fr' => [
            'Your booking number is:' => 'Votre numéro de réservation est le',
            'Date of Birth:'          => 'Date de naissance :',
            'Aeroplan Number:'        => 'Numéro Aéroplan :',
            'Hotel & Room'            => ['Hotel & Chambre', 'Hôtel et chambre(s)'],
            'Check-in:'               => 'Enregistrement :',
            'Check-out:'              => 'Départ :',
            'Operated by'             => 'Opéré par',
            'Price Details'           => 'Détails du prix',
            'taxes and fees'          => 'taxes et frais',
            // 'EXTRAS' => '',
            'Subtotal'        => 'Sous-total',
            'Aeroplan points' => 'points Aéroplan',
            'TOTAL'           => 'TOTAL',
        ],
    ];

    private $detectSubject = [
        // en
        'Your reservation with Air Canada Vacations - Booking Number:',
        // fr
        'Votre réservation avec Vacances Air Canada – Numéro de réservation:',
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.](?:aircanadavacations|vacancesaircanada)\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (
            stripos($headers["from"], 'noreply@aircanadavacations.com') === false
            && stripos($headers["from"], 'nepasrepondre@vacancesaircanada.com') === false
            && strpos($headers["subject"], 'Air Canada Vacations') === false
            && strpos($headers["subject"], 'Vacances Air Canada') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.aircanada.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['with Air Canada Vacations', 'by Air Canada Vacations'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Hotel & Room'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Hotel & Room'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Hotel & Room"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Hotel & Room'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking number is:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{5,})\s*$/"));

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price Details'))}]/following::tr[not(.//tr)][count(*) = 2][*[1][{$this->eq($this->t('TOTAL'))}]]/*[2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $email->price()
                ->total(PriceHelper::parse($m['amount']))
                ->currency($m['currency']);
        }
        $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price Details'))}]/following::tr[not(.//tr)][count(*) = 1][*[1][{$this->contains($this->t('taxes and fees'))}]]/following-sibling::*[1]/*[2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $tax, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $tax, $m)
        ) {
            $email->price()
                ->tax(PriceHelper::parse($m['amount']));
        }
        $spentAwards = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price Details'))}]/following::tr[not(.//tr)][count(*) = 2][*[1][{$this->contains($this->t('Aeroplan points'))}]]/*[1]",
            null, true, "/^\s*(\d+[\d, ]*\s*{$this->opt($this->t('Aeroplan points'))})/u");

        if (!empty($spentAwards)) {
            $email->price()
                ->spentAwards($spentAwards);
        }

        $end = 'TOTAL';

        if (!empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Price Details'))}]/following::text()[{$this->eq($this->t('Subtotal'))}]"))) {
            $end = 'Subtotal';
        }
        $feesNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Price Details'))}]/following::tr[not(.//tr)][count(*) = 2][preceding::text()[{$this->eq($this->t('EXTRAS'))}]][following::text()[{$this->eq($this->t($end))}]]");

        foreach ($feesNodes as $fRoot) {
            $name = $this->http->FindSingleNode("preceding-sibling::*[normalize-space()][2][count(*[normalize-space()]) = 1]/*[1]", $fRoot);
            $value = $this->http->FindSingleNode("*[2]", $fRoot);

            if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
            ) {
                $email->price()
                    ->fee($name, PriceHelper::parse($m['amount']));
            }
        }

        $travellers = $this->http->FindNodes("//text()[{$this->starts($this->t('Date of Birth:'))}]/preceding::text()[normalize-space()][1]");
        $aNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Aeroplan Number:'))}]/ancestor-or-self::node()[not({$this->eq($this->t('Aeroplan Number:'))})][1]");

        foreach ($aNodes as $aRoot) {
            $account = $this->re("/^\s*{$this->opt($this->t('Aeroplan Number:'))}\s*(\d{5,})\s*$/", $aRoot->nodeValue);

            if (!empty($account) && !in_array($account, array_column($email->getTravelAgency()->getAccountNumbers(), 0))) {
                $email->ota()
                    ->account($account, false,
                        $this->http->FindSingleNode("preceding::text()[{$this->starts($this->t('Date of Birth:'))}][1]/preceding::text()[normalize-space()][1]",
                            $aRoot),
                        trim($this->re("/^\s*({$this->opt($this->t('Aeroplan Number:'))})\s*\d{5,}\s*$/",
                            $aRoot->nodeValue), ' :')
                    );
            }
        }

        // Hotels
        $xpath = "//text()[{$this->starts($this->t('Check-in:'))}]/ancestor::*[{$this->contains($this->t('Check-out:'))}][not(.//text()[{$this->eq($this->t('Hotel & Room'))}])][last()]";
        $hNodes = $this->http->XPath->query($xpath);

        foreach ($hNodes as $hRoot) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->noConfirmation()
                ->travellers($travellers, true);

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode(".//*[{$this->starts($this->t('Check-in:'))}][following-sibling::*[{$this->starts($this->t('Check-out:'))}]]/preceding-sibling::*[normalize-space()][position() > 1][last()]",
                    $hRoot, true, "/^\s*(.+?)(?:\s+\d[\d.]*)?\s*$/"))
                ->noAddress();

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Check-in:'))}]/ancestor::*[not({$this->eq($this->t('Check-in:'))})][1] | .//text()[{$this->starts($this->t('Check-in:'))}][not({$this->eq($this->t('Check-in:'))})]", null, true,
                    "/^\s*{$this->opt($this->t('Check-in:'))}\s*(.+)/")))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Check-out:'))}]/ancestor::*[not({$this->eq($this->t('Check-out:'))})][1] | .//text()[{$this->starts($this->t('Check-out:'))}][not({$this->eq($this->t('Check-out:'))})]", null, true,
                    "/^\s*{$this->opt($this->t('Check-out:'))}\s*(.+)/")))
            ;

            // Rooms
            $h->addRoom()
                ->setType($this->http->FindSingleNode(".//*[{$this->starts($this->t('Check-in:'))}][following-sibling::*[{$this->starts($this->t('Check-out:'))}]]/preceding-sibling::*[normalize-space()][1]", $hRoot));
        }

        // Flights
        $xpath = "//*[@class[{$this->contains('flight-line')}]]/ancestor::tr[1]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $fNodes = $this->http->XPath->query($xpath);

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers($travellers, true);

        foreach ($fNodes as $fRoot) {
            $s = $f->addSegment();

            // Airline
            $node = $this->http->FindSingleNode("following::text()[normalize-space()][1]/ancestor::tr[1]", $fRoot);

            if (preg_match("/{$this->opt($this->t('Operated by'))}\s*(?<operator>\S[^,]+?)\s*,\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+)\s*,\s*(?<cabin>\S[^,]+?)\s*$/", $node, $m)
                || preg_match("/^(?<operator>\S[^,]+?)\s*,\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+)\s*,\s*(?<cabin>\S[^,]+?)\s*$/", $node, $m)
            ) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                    ->operator($m['operator']);

                $s->extra()
                    ->cabin($m['cabin'])
                ;
            }

            $re = "/^\s*(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s+(?<date>[\S\s]+)\s*$/u";

            // Departure
            $departure = implode("\n", $this->http->FindNodes("*[normalize-space()][1]//text()[normalize-space()]", $fRoot));

            if (preg_match($re, $departure, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']));
            }

            // Arrival
            $arrival = implode("\n", $this->http->FindNodes("*[normalize-space()][2]//text()[normalize-space()]", $fRoot));

            if (preg_match($re, $arrival, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']));
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        $in = [
            // 29 août 2024
            '/^\s*(\d{1,2})\s+([[:alpha:]]+)\.?\s+(\d{4})\s*$/ui',
            // 9 août 2024  10:15 AM
            '/^\s*(\d{1,2})\s+([[:alpha:]]+)\.?\s+(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
