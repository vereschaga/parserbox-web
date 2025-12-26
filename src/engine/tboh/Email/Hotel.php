<?php

namespace AwardWallet\Engine\tboh\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "tboh/it-557737209.eml, tboh/it-561496946-pt.eml, tboh/it-549378054-pt.eml, tboh/it-594562514.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],
            // 'Booked By' => '',
            'confNumber'                  => 'Número de Confirmação do Hotel',
            'address'                     => ['Endereço:', 'Endereço :'],
            'otaConfNumber'               => 'Número de Confirmação TBOH',
            'Booking Amount'              => 'Valor da Reserva',
            'checkIn'                     => ['Check-In:', 'Check-In :'],
            'checkOut'                    => ['Check-Out:', 'Check-Out :'],
            'HotelName,City:'             => 'Nome do Hotel,Cidade:',
            'phone'                       => ['Phone No:', 'Phone No :'],
            'fax'                         => ['Fax:', 'Fax :'],
            'Arrival Time (at the hotel)' => 'Hora de Chegada (no hotel)',
            'Room Name'                   => 'Nome do Quarto',
            'Lead Guest Name'             => 'Nome do Hóspede Líder',
            // 'Incl' => '',
            // 'Ameneties' => '',
            // 'Cancelled on or before' => '',
            // 'Cancellation Charge' => '',
        ],
        'en' => [
            'statusPhrases'  => ['your booking with following details has been successfully'],
            'statusVariants' => ['Confirmed'],
            // 'Booked By' => '',
            'confNumber'    => 'Hotel Confirmation Number',
            'address'       => ['Address:', 'Address :'],
            'otaConfNumber' => 'TBOH Confirmation Number',
            // 'Booking Amount' => '',
            'checkIn'  => ['Check In:', 'Check In :'],
            'checkOut' => ['Check Out:', 'Check Out :'],
            // 'HotelName,City:' => '',
            'phone' => ['Phone No:', 'Phone No :'],
            'fax'   => ['Fax:', 'Fax :'],
            // 'Arrival Time (at the hotel)' => '',
            // 'Room Name' => '',
            // 'Lead Guest Name' => '',
            // 'Incl' => '',
            // 'Ameneties' => '',
            // 'Cancelled on or before' => '',
            // 'Cancellation Charge' => '',
        ],
    ];

    private $subjects = [
        // 'pt' => [''],
        'en' => ['Hotel Confirmation', 'Hotel Booking Confirmed'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@tbo\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true
            && strpos($headers['subject'], ':TBOH ') === false
            && strpos($headers['subject'], ': TBOH ') === false
            && strpos($headers['subject'], ' for TBOH ') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//*[contains(normalize-space(),"+9714 - 4357520") or contains(.,"+9714-4357520") or contains(.,"@tboholidays.com")]')->length === 0
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
        $email->setType('Hotel' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $generalText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('checkIn'))}]/ancestor::*[ descendant::text()[{$this->starts($this->t('address'))}] ][1]"));

        if (preg_match("/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i", $generalText, $m)) {
            // it-594562514.eml
            $h->general()->status($m[1]);
        }

        $confirmationNumbers = [];
        $confirmationTitle = null;

        if (preg_match("/^[> ]*({$this->opt($this->t('confNumber'))})[ ]*[:]+[ ]*([-A-Z\d ,|]{5,})$/m", $generalText, $m)) {
            $confirmationNumbers = preg_split('/(\s*[,|]\s*)+/', $m[2]);
            $confirmationTitle = trim($m[1], ': ');
        }

        if (count($confirmationNumbers) === 1) {
            $h->general()->confirmation($confirmationNumbers[0], $confirmationTitle);
        }

        if (preg_match("/^[> ]*({$this->opt($this->t('otaConfNumber'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})$/m", $generalText, $m)) {
            $email->ota()->confirmation($m[2], trim($m[1], ': '));
        }

        $totalPrice = $this->re("/^[> ]*{$this->opt($this->t('Booking Amount'))}[ ]*[:]+[ ]*(.*?\d.*?)(?:[ ]*\(|$)/m", $generalText);

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // BRL2567.88
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $dateCheckIn = strtotime($this->normalizeDate($this->re("/^[> ]*{$this->opt($this->t('checkIn'))}[: ]*(.*\d.*)$/m", $generalText)));

        $timeCheckIn = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('Arrival Time (at the hotel)'))}] ]/following-sibling::tr/*[1]", null, true, "/^{$patterns['time']}/");

        if ($timeCheckIn && $dateCheckIn) {
            $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
        }

        $dateCheckOut = strtotime($this->normalizeDate($this->re("/^[> ]*{$this->opt($this->t('checkOut'))}[: ]*(.*\d.*)$/m", $generalText)));

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $hotelName = $this->re("/^[> ]*{$this->opt($this->t('HotelName,City:'))}[: ]*(.{2,})$/m", $generalText);

        if ($hotelName && strpos($hotelName, ',') !== false
            && strpos($hotelName, ',') === strrpos($hotelName, ',')
        ) {
            $hotelName = $this->re("/^(.{2,}?)\s*,\s*.{2,}$/", $hotelName);
        }

        $address = $this->re("/^[> ]*{$this->opt($this->t('address'))}[: ]*(.{3,})$/m", $generalText);
        $phone = $this->re("/^[> ]*{$this->opt($this->t('phone'))}[: ]*({$patterns['phone']})(?:[ ]*{$this->opt($this->t('fax'))}|$)/m", $generalText);
        $fax = $this->re("/(?:^[> ]*| ){$this->opt($this->t('fax'))}[: ]*({$patterns['phone']})$/m", $generalText);

        $h->hotel()
            ->name($hotelName)
            ->phone($phone, false, true)
            ->fax($fax, false, true);

        if (stripos($address, 'Temple Way ZipCode') !== false || trim($address) === 'ZipCode:') {
            $h->hotel()
                ->noAddress();
        } else {
            $h->hotel()
                ->address($address);
        }

        $roomNames = $travellers = $adultsValues = $childrenValues = [];
        $roomsRows = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Room Name'))}] and *[2][{$this->eq($this->t('Lead Guest Name'))}] ]/following-sibling::tr[normalize-space()]");

        foreach ($roomsRows as $rRow) {
            $guestName = $this->http->FindSingleNode("*[2]", $rRow, true, "/^{$patterns['travellerName']}$/u");
            $adultsVal = $this->http->FindSingleNode("*[3]", $rRow, true, "/^(\d{1,3})(?:\s*\(|$)/u");
            $childrenVal = $this->http->FindSingleNode("*[4]", $rRow, true, "/^(\d{1,3})(?:\s*\(|$)/u");

            if (!$guestName || $adultsVal === null || $childrenVal === null) {
                break;
            }

            $travellers[] = $guestName;
            $adultsValues[] = $adultsVal;
            $childrenValues[] = $childrenVal;

            $roomNameText = $this->htmlToText($this->http->FindHTMLByXpath("*[1]", null, $rRow));
            $roomNames[] = $this->re("/^\s*(.{2,}?)\s*(?:{$this->opt($this->t('Incl'))}[ ]*:|{$this->opt($this->t('Ameneties'))}[ ]*:|$)/", $roomNameText);
        }

        foreach ($roomNames as $i => $rName) {
            $room = $h->addRoom();
            $room->setType($rName);

            if (count($confirmationNumbers) > 1 && count($confirmationNumbers) === count($roomNames)) {
                $room->setConfirmation($confirmationNumbers[$i])->setConfirmationDescription($confirmationTitle);
                $h->general()->noConfirmation();
            }
        }

        if (count($travellers) > 0) {
            $h->general()->travellers($travellers, true);
        }

        if (count($adultsValues) > 0) {
            $h->booked()->guests(array_sum($adultsValues));
        }

        if (count($childrenValues) > 0) {
            $h->booked()->kids(array_sum($childrenValues));
        }

        // it-594562514.eml
        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Last cancellation date'))}]");
        $h->general()->cancellation($cancellation, false, true);

        $cancellationRows = $this->http->XPath->query("//tr[ *[2][{$this->eq($this->t('Cancelled on or before'))}] and *[3][{$this->eq($this->t('Cancellation Charge'))}] ]/following-sibling::tr[ *[3][normalize-space()] ]");

        foreach ($cancellationRows as $cpRow) {
            $charge = $this->http->FindSingleNode('*[3]', $cpRow);

            if (preg_match('/^(?:[^\-\d)(]+?[ ]*)?(\d[,.‘\'\d ]*)$/u', $charge, $m) // USD0.00    |    0.00
                && PriceHelper::parse($m[1]) === "0.00"
            ) {
                $before = $this->http->FindSingleNode('*[2]', $cpRow);
                $h->booked()->deadline2($this->normalizeDate($before));

                break;
            }
        }

        if (count($confirmationNumbers) === 0 && !preg_match("/{$this->opt($this->t('confNumber'))}/i", $generalText)
            && preg_match("/{$this->opt($this->t('Booked By'))}.*\n+(?:.*\n)?[ ]*{$this->opt($this->t('otaConfNumber'))}/i", $generalText)
        ) {
            // it-594562514.eml
            $h->general()->noConfirmation();
        }

        if (!empty($h->getCancellation()) && empty($h->getDeadline())) {
            $this->detectDeadLine($h);
        }

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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['checkIn']) || empty($phrases['address'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['address'])}]")->length > 0
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
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
        if (preg_match('/^(\d{1,2})[- ]+([[:alpha:]]{3,})[- ]+(\d{4})$/u', $text, $m)) {
            // 24-Oct-2023    |    24 Oct 2023
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Last cancellation date\s*\:\s*(\d+)\-(\w+)\-(\d{4})/", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3]));
        }
    }
}
