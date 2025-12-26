<?php

namespace AwardWallet\Engine\mirage\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It3108337 extends \TAccountChecker
{
    public $mailFiles = "mirage/it-105455222.eml, mirage/it-3108337.eml, mirage/it-52719588.eml, mirage/it-55580759.eml, mirage/it-55610872.eml, mirage/it-55660309.eml";

    private $langDetectors = [
        'en' => [
            'Room Confirmation #',
            'Room Confirmation#',
            'CONFIRMATION',
            'Cancelled Reservation',
            'Thanks for choosing MGM Resorts International for your upcoming trip to Las Vegas',
        ],
    ];

    private $lang = '';

    private static $dict = [
        'en' => [
            'Privacy Policy'        => ['Privacy Policy', 'Privacy', 'PRIVACY POLICY'],
            'Confirmation Number'   => ['Confirmation Number', 'CONFIRMATION NUMBER'],
            'Cancelled Reservation' => ['Cancelled Reservation', 'CANCELLED RESERVATION'],
            'Hello'                 => ['Dear', 'Hello'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Park MGM Las Vegas') !== false
            || stripos($from, '@parkmgm.com') !== false
            || stripos($from, '@lv.mgmgrand.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!empty($headers['from']) && self::detectEmailFromProvider($headers['from']) !== true && !empty($headers['subject']) && !preg_match('/\bMGM\b/', $headers['subject'])) {
            return false;
        }

        return !empty($headers['subject']) && (stripos($headers['subject'], 'Reservation Confirmation') !== false
        || stripos($headers['subject'], 'Confirmed: Room Reservation') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Copyright MGM Resorts") or contains(.,"@lv.mgmgrand.com") or contains(.,"MGMResorts.com") or contains(.,"MGMRESORTS.COM")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".mgmresorts.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseEmail($email);
        $email->setType('ReservationConfirmation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $patterns = [
            'confNumber' => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
            'phone'      => '[+)(\d][-.\s\d)(]{5,}[\d)(]', // +377 (93) 15 48 52    |    713.680.2992
        ];

        // travellers
        $traveller = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Hello')) . ']', null, true, '/' . $this->opt($this->t('Hello')) . '\s+(.+?)(?:,|$)/s');

        $xpath = "//text()[{$this->starts($this->t('Confirmation Number'))} or {$this->starts($this->t('Cancelled Reservation'))}]/ancestor::*[.//img][1]";
        $reservations = $this->http->XPath->query($xpath);
        // it-105455222
        if ($reservations->length > 1 && $this->http->XPath->query("//text()[{$this->starts($this->t('Confirmation Number'))}]")->length > 0 && $this->http->XPath->query("//text()[{$this->starts($this->t('Cancelled Reservation'))}]")->length > 0) {
            $xpath = "//text()[{$this->starts($this->t('Cancelled Reservation'))}]/ancestor::*[.//img][1]";
            $reservations = $this->http->XPath->query($xpath);
        }

        $this->logger->debug("[XPATH]\n" . $xpath);

        foreach ($reservations as $root) {
            $h = $email->add()->hotel();

            $h->general()->traveller($traveller, false);

            if (!empty($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Reservation Cancellation'))}]"))
                || !empty($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Cancelled Reservation'))}]", $root))) {
                $h->general()
                    ->cancelled()
                    ->status('Cancelled');
            }

            if ((!empty($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Reservation Confirmation'))}]"))
                || !empty($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Confirmation'))}]", $root))
                    || !empty($this->http->FindSingleNode('(//text()[' . $this->eq($this->t('CONFIRMATION')) . '])[1]')))
                && $h->getCancelled() !== true
            ) {
                $h->general()
                    ->status('Confirmed');
            }
            // checkInDate
            // checkOutDate
            $datesText = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Confirmation Number'))}]/preceding::text()[normalize-space(.)!=''][1]", $root);

            if (preg_match('/^([^\d\W]{3,})\s*(\d{1,2})\s*-\s*(\d{1,2})\s*,\s*(\d{2,4})$/u', $datesText, $m)) {
                // OCT 11 - 12, 2015
                $h->booked()
                    ->checkIn2($m[2] . ' ' . $m[1] . ' ' . $m[4])
                    ->checkOut2($m[3] . ' ' . $m[1] . ' ' . $m[4]);
            } elseif (preg_match('/^([^\d\W]{3,})\s*(\d{1,2})\s*-\s*([^\d\W]{3,})\s*(\d{1,2})\s*,\s*(\d{2,4})$/u',
                $datesText, $m)) {
                // JUN 24 - JUL 03, 2019
                $h->booked()
                    ->checkIn2($m[2] . ' ' . $m[1] . ' ' . $m[5])
                    ->checkOut2($m[4] . ' ' . $m[3] . ' ' . $m[5]);
            } else {
                $in = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Arrival Date'))}]/following::text()[string-length(normalize-space()) > 3][1]",
                    $root, false, "/^:?\s*(.+)/");
                $out = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure Date'))}]/following::text()[string-length(normalize-space()) > 3][1]",
                    $root, false, "/^:?\s*(.+)/");

                if (!empty($in) && !empty($out)) {
                    $h->booked()
                        ->checkIn2($in)
                        ->checkOut2($out);
                }
            }

            // confirmation number
            $confirmationNumber = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Confirmation Number'))}]/ancestor::td[1]", $root);

            if (empty($confirmationNumber)) {
                $confirmationNumber = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Confirmation Number'))}]/ancestor::td[1]", $root);
            }

            if (empty($confirmationNumber)) {
                $confirmationNumber = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Cancelled Reservation'))}]/ancestor::td[1]",
                    $root, true, '/' . $this->opt($this->t('Cancelled Reservation')) . '[:\s]*(' . $patterns['confNumber'] . ')/');
            }

            if (empty($confirmationNumber)) {
                $confirmationNumber = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Cancelled Reservation'))}]/ancestor::td[1]",
                    $root, true, '/' . $this->opt($this->t('Cancelled Reservation')) . '[:\s]*(' . $patterns['confNumber'] . ')/');
            }

            if (preg_match('/(' . $this->opt($this->t('Confirmation Number')) . ')[:\s]*(' . $patterns['confNumber'] . ')\s*$/',
                $confirmationNumber, $matches)) {
                $h->general()->confirmation($matches[2], $matches[1]);
            } elseif (preg_match('/^\s*(' . $patterns['confNumber'] . ')\s*/', $confirmationNumber, $matches)) {
                $h->general()->confirmation($matches[1]);
            }

            if (
                ($refund = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(.), 'Your reservation deposit is refundable')][1]", $root))
                && preg_match('/(\w+) (\d{1,2}), (\d{4} \d{1,2}:\d{2} [AP]M)/i', $refund, $m)
            ) {
                $h->booked()->deadline(strtotime($m[2] . ' ' . $m[1] . ' ' . $m[3]));
                $h->general()->cancellation($refund);
            }

            // r.type
            // hotelName
            $h1Texts = $this->http->FindNodes("./descendant::text()[{$this->contains($this->t('Night Stay'))}]/ancestor::td[{$this->contains($this->t('Reservations Phone Number'))}][not({$this->contains($this->t('Confirmation Number'))} or {$this->contains($this->t('Cancelled Reservation'))})][1]/descendant::text()[normalize-space(.)!='']", $root);

            if (empty($h1Texts)) {//it-52719588.eml
                $h1Texts = $this->http->FindNodes("./descendant::text()[{$this->contains($this->t('Night Stay'))}]/ancestor::td[1]/descendant::text()[normalize-space(.)!='']", $root);
            }
            $h1Text = implode("\n", $h1Texts);

            if (preg_match('/^(?:([^\n]+?)\n+)?([^\n]+?)\s+\d+\s+' . $this->opt($this->t('Night Stay')) . '/', $h1Text,
                $matches)) {
                if (!empty($matches[1])) {
                    $r = $h->addRoom();
                    $r->setType($matches[1]);
                } elseif (preg_match('/^([^\n]+)\n\d+\s+' . $this->opt($this->t('Night Stay')) . '[ ]*\|[ ]*(.+)/', $h1Text,
                    $matches)) {
                    $h->hotel()->name($matches[2]);
                    $r = $h->addRoom();
                    $r->setType($matches[1]);
                } else {
                    $type = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Night Stay'))}]/ancestor::td[1]/preceding::text()[normalize-space(.)!=''][1][not({$this->contains($this->t('Confirmation Number'))})]", $root);

                    if (!empty($type)) {
                        $h->addRoom()->setType($type);
                    }
                }
                $h->hotel()->name(preg_replace('/\s+/', ' ', $matches[2]));
            } elseif (preg_match('/^\d+\s+' . $this->opt($this->t('Night Stay')) . '[ ]*\|[ ]*(.+)$/', $h1Text,
                $matches)) {
                $h->hotel()->name($matches[1]);
                $r = $h->addRoom();
                $r->setType($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Night Stay'))}]/ancestor::td[1]/following::text()[normalize-space(.)!=''][1]", $root));
            }

            // phone
            $phone = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Reservations Phone Number'))}]/following::text()[normalize-space(.)!=''][1]",
                $root, true, '/^(' . $patterns['phone'] . ')/');
            $h->hotel()->phone($phone, false, true);

            // p.currencyCode
            // p.total
            $payment = $this->http->FindSingleNode("./descendant::td[not(.//td) and {$this->starts($this->t('Reservation Total'))}]/following-sibling::td[last()]", $root);

            if (preg_match('/^(?<currency>[^\d)(]+)\s*(?<amount>\d[,.\'\d]*)/', $payment, $matches)) {
                // $167.80
                $h->price()
                    ->currency($this->normalizeCurrency($matches['currency']))
                    ->total($this->normalizeAmount($matches['amount']));
            }
            $cost = $this->http->FindSingleNode("./descendant::td[not(.//td) and {$this->starts($this->t('Room Rate and Estimated Tax'))}]/following-sibling::td[normalize-space()][last()]", $root);

            if (preg_match('/^(?<currency>[^\d)(]+)\s*(?<amount>\d[,.\'\d]*)/', $cost, $matches)) {
                // $167.80
                $h->price()
                    ->currency($this->normalizeCurrency($matches['currency']))
                    ->cost($this->normalizeAmount($matches['amount']));
            }
            $tax = $this->http->FindSingleNode("./descendant::td[not(.//td) and {$this->starts($this->t('Resort Fee And Tax'))}]/following-sibling::td[normalize-space()][last()]", $root);

            if (preg_match('/^(?<currency>[^\d)(]+)\s*(?<amount>\d[,.\'\d]*)/', $tax, $matches)) {
                // $167.80
                $h->price()
                    ->currency($this->normalizeCurrency($matches['currency']))
                    ->tax($this->normalizeAmount($matches['amount']));
            }

            // address
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Privacy Policy'))}]/preceding::tr[string-length(normalize-space(.))>2][1]");
            $hotelName = preg_quote($h->getHotelName());

            if (empty($hotelName)) {
                $hotelName = trim($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you for considering')]", null, true, "/Thank you for considering\s*(.+)\s*as your resort destination/"));
                $h->hotel()
                    ->name($hotelName);
            }
            $address = preg_replace("/^\s*{$hotelName}\s*\,\s*/i", '', $address);

            if (!empty($address)) {
                $h->hotel()->address($address);
            } elseif (empty($this->http->FindSingleNode('(//text()[' . $this->eq($this->t('Privacy Policy')) . '])[1]'))) {
                $h->hotel()->noAddress();
            }
        }
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
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
