<?php

namespace AwardWallet\Engine\sonder\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "sonder/it-48949941.eml, sonder/it-83212667.eml, sonder/it-587818291-fr.eml, sonder/it-591992815.eml, sonder/it-605087998.eml";

    public $lang = '';

    public static $dict = [
        'fr' => [
            'confNumber'          => ['Code de confirmation:', 'Code de confirmation :'],
            // 'Address' => '',
            // 'View on map' => '',
            'checkIn'             => "L'arrivée",
            'checkOut'            => 'Départ',
            'Cancellation policy' => ['Tarif économique', 'Tarif Flex'],
        ],
        'en' => [
            'confNumber' => ['Confirmation code:', 'Confirmation code :'],
            // 'Address' => '',
            'View on map'         => ['View on map', 'Get directions'],
            'checkIn'             => 'Check-in',
            'checkOut'            => ['Check-out', 'Checkout'],
            'Cancellation policy' => [
                'Cancellation policy', 'Cancelation policy',
                'Modify or cancell your reservation', 'Modify or cancel your reservation',
            ],
        ],
    ];
    private $subjects = [
        'fr' => ['Confirmation de réservation pour'],
        'en' => ['Your reservation for', 'Your access details for', 'Reservation confirmation for'],
    ];
    private $body = [
        'fr' => ['Votre réservation est confirmée'],
        'en' => [
            'Get ready for check-in',
            "Here's what you need to know",
            'Here are your stay details for',
            'Your reservation is confirmed',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@sonder.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return preg_match('/Your .{3,} stay details/', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".sonder.com/") or contains(@href,".sonder-mail.com/") or contains(@href,"www.sonder.com") or contains(@href,"links.sonder-mail.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and (contains(normalize-space(),"Sonder, Inc") or contains(normalize-space(),"Sonder Holdings Inc"))]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHotel($email);
        $email->setType('YourReservation' . ucfirst($this->lang));

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

    private function parseHotel(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        ];

        $xpathHotelName_lonely = "//*[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->eq($this->t('View on map'))}] ]/ancestor-or-self::*[ preceding-sibling::*[normalize-space() or descendant::img] ][1]/preceding-sibling::*[normalize-space() and not(descendant::img)]";

        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]*([-A-Z\d]{7,16})$/", $confirmation, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ' :'));
        } elseif (empty($confirmation) && $this->http->XPath->query("//*[{$this->contains($this->t('confNumber'))}]")->length === 0
            && $this->http->XPath->query($xpathHotelName_lonely)->length === 1
        ) {
            // it-605087998.eml
            $h->general()->noConfirmation();
        }

        // it-48949941.eml
        $addressTexts = $this->http->FindNodes("//tr[{$this->eq($this->t('Address'))}]/following-sibling::tr//td[not({$this->contains($this->t('View on map'))})][normalize-space()]");

        if (count($addressTexts) === 0) {
            // it-591992815.eml
            $addressTexts = $this->http->FindNodes("//*[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->eq($this->t('View on map'))}] ]/*[normalize-space()][1]/descendant::text()[normalize-space() and not({$this->eq($this->t('Address'))})]");
        }

        $xpathBeforeConfNoRows = "//text()[{$this->starts($this->t('confNumber'))}]/ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()]";

        if (count($addressTexts) > 0) {
            if ($this->http->XPath->query($xpathBeforeConfNoRows)->length > 0) {
                // it-591992815.eml
                $h->hotel()->name($this->http->FindSingleNode($xpathBeforeConfNoRows . '[1]'));
            } else {
                // it-605087998.eml
                $h->hotel()->name($this->http->FindSingleNode($xpathHotelName_lonely));
            }
            $h->hotel()->address(implode(', ', $addressTexts));
        } elseif (count($addressTexts) === 0) {
            // it-83212667.eml, it-587818291-fr.eml
            $h->hotel()
                ->name($this->http->FindSingleNode($xpathBeforeConfNoRows . '[2]'))
                ->address($this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/preceding::text()[normalize-space()][1]"));
        }

        $h->hotel()->house();

        $xpath = "//tr[ *[2][{$this->eq($this->t('checkOut'))}] ]";

        $checkInText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('checkIn'))}]/ancestor::*[ descendant::text()[{$this->contains($this->t('Anytime after'))}] ][1]"));
        $checkOutText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('checkOut'))}]/ancestor::*[ descendant::text()[{$this->contains($this->t('By'))}] ][1]"));

        if (preg_match("/^\s*{$this->opt($this->t('checkIn'))}[ ]*(?:\n[ ]*)+(?<date>.{3,}?)[ ]*(?:\n[ ]*)+{$this->opt($this->t('Anytime after'))}[ ]+(?<time>{$patterns['time']})/u", $checkInText, $m)) {
            // it-591992815.eml
            $dateCheckInVal = $m['date'];
            $timeCheckIn = $m['time'];
        } else {
            // it-48949941.eml
            $dateCheckInVal = $this->http->FindSingleNode("{$xpath}/following-sibling::tr[normalize-space()][2]/*[1]");
            $timeCheckIn = $this->http->FindSingleNode("{$xpath}/following-sibling::tr[normalize-space()][1]/*[1]", null, true, "/^{$patterns['time']}/");
        }

        if (preg_match("/^\s*{$this->opt($this->t('checkOut'))}[ ]*(?:\n[ ]*)+(?<date>.{3,}?)[ ]*(?:\n[ ]*)+{$this->opt($this->t('By'))}[ ]+(?<time>{$patterns['time']})/u", $checkOutText, $m)) {
            $dateCheckOutVal = $m['date'];
            $timeCheckOut = $m['time'];
        } else {
            $dateCheckOutVal = $this->http->FindSingleNode("{$xpath}/following-sibling::tr[normalize-space()][2]/*[2]");
            $timeCheckOut = $this->http->FindSingleNode("{$xpath}/following-sibling::tr[normalize-space()][1]/*[2]", null, true, "/^{$patterns['time']}/");
        }

        $dateCheckIn = strtotime($this->normalizeDate($dateCheckInVal));
        $dateCheckOut = strtotime($this->normalizeDate($dateCheckOutVal));
        $h->booked()
            ->checkIn(strtotime($timeCheckIn, $dateCheckIn))
            ->checkOut(strtotime($timeCheckOut, $dateCheckOut))
        ;

        $cancellationPolicy = $this->http->FindSingleNode("//tr[*[{$this->eq($this->t('Cancellation policy'))}]]/following-sibling::tr[normalize-space()][1][not(contains(.,'%{'))]");
        $h->setCancellation($cancellationPolicy, false, true);
        $this->detectDeadLine($h);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/if canceled within (\d+ hours) of booking/', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1]);
        }
    }

    private function assignLang(): bool
    {
        foreach ($this->body as $lang => $phrase) {
            if ($this->http->XPath->query("//text()[{$this->contains($phrase)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})$/u', $text, $m)) {
            // 17 novembre 2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/\b([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})$/u', $text, $m)) {
            // Saturday January 18, 2020
            $month = $m[1];
            $day = $m[2];
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

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
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
