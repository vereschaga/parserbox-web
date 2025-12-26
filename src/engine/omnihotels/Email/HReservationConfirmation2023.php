<?php

namespace AwardWallet\Engine\omnihotels\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HReservationConfirmation2023 extends \TAccountChecker
{
    public $mailFiles = "omnihotels/it-456745879.eml, omnihotels/it-459573474.eml, omnihotels/it-453734590.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'status'          => ['Status:', 'Status :'],
            'checkIn'         => ['CHECK IN:'],
            'checkOut'        => ['CHECK OUT:'],
            'statusVariants'  => ['Confirmed', 'Cancelled', 'Canceled'],
            'cancelledStatus' => ['Cancelled', 'Canceled'],
        ],
    ];

    private $subjects = [
        'en' => ['Your Reservation Confirmation for'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]omnihotels\.com$/i', $from) > 0;
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

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".omnihotels.com/") or contains(@href,"em.omnihotels.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Your reservation at Omni")] | //text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Omni Hotels & Resorts")]')->length === 0
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
        $email->setType('HReservationConfirmation2023' . ucfirst($this->lang));

        $this->parseHotel($email);

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

    private function parseHotel(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        $headerRows = $this->http->FindNodes("//*[ tr[normalize-space()][3] ][ tr[normalize-space()][3][{$this->starts($this->t('Phone:'))}] or tr[normalize-space()][position()>2][{$this->starts($this->t('Driving Directions To Hotel'))}] ]/tr[normalize-space()]");

        if (count($headerRows) > 2) {
            $hotelName = $headerRows[0];
            $address = $headerRows[1];
            $phone = preg_match("/^{$this->opt($this->t('Phone:'))}[:\s]*({$patterns['phone']})$/", $headerRows[2], $m) ? $m[1] : null;
            $h->hotel()->name($hotelName)->address($address)->phone($phone, false, true);
        }

        $status = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('status'))}]/following-sibling::*[normalize-space()][1]", null, true, "/^{$this->opt($this->t('statusVariants'))}$/i");
        $h->general()->status($status);

        if (preg_match("/^{$this->opt($this->t('cancelledStatus'))}$/i", $status)) {
            // it-456745879.eml
            $h->general()->cancelled();
        }

        $confirmation = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Confirmation #'))}]/following-sibling::*[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/");

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Confirmation #'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        } else {
            // it-456745879.eml
            $cancellationNo = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('CANCELLATION #'))}]/following-sibling::*[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/");
            $h->general()->cancellationNumber($cancellationNo);
        }

        $dateCheckIn = $dateCheckOut = $timeCheckIn = $timeCheckOut = null;

        // 06/11/2024 (before 11:00 AM)
        $checkInVal = $this->http->FindSingleNode("//*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/tr[normalize-space()][2]");
        $checkOutVal = $this->http->FindSingleNode("//*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->eq($this->t('checkOut'))}] ]/tr[normalize-space()][2]");

        if (preg_match("/^(.{4,}?\b\d{4})(?:\s*\(|$)/", $checkInVal, $m)) {
            $dateCheckIn = strtotime($m[1]);
        }

        if (preg_match("/^(.{4,}?\b\d{4})(?:\s*\(|$)/", $checkOutVal, $m)) {
            $dateCheckOut = strtotime($m[1]);
        }

        if (preg_match("/{$this->opt($this->t('after'))}\s*({$patterns['time']})/i", $checkInVal, $m)) {
            $timeCheckIn = $m[1];
        }

        if (preg_match("/{$this->opt($this->t('before'))}\s*({$patterns['time']})/i", $checkOutVal, $m)) {
            $timeCheckOut = $m[1];
        }

        if ($dateCheckIn && $timeCheckIn) {
            $h->booked()->checkIn(strtotime($timeCheckIn, $dateCheckIn));
        }

        if ($dateCheckOut && $timeCheckOut) {
            $h->booked()->checkOut(strtotime($timeCheckOut, $dateCheckOut));
        }

        $traveller = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('GUEST'))}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");
        $isNameFull = true;

        if (!$traveller) {
            // it-456745879.eml
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Welcome'))}]", null, "/^{$this->opt($this->t('Welcome'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $isNameFull = null;
            }
        }

        $h->general()->traveller($traveller, $isNameFull);

        $occupantsVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('OCCUPANTS'))}] ]/*[normalize-space()][2]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/i", $occupantsVal, $m)) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Children'))}/i", $occupantsVal, $m)) {
            $h->booked()->kids($m[1]);
        }

        $yourStayVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('YOUR STAY'))}] ]/*[normalize-space()][2]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('room'))}/i", $yourStayVal, $m)) {
            $h->booked()->rooms($m[1]);
        }

        $subTotalCurrencies = $subTotalAmounts = [];
        $roomNodes = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('ROOM ∆'), 'translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆")')}] ]");

        foreach ($roomNodes as $rRoot) {
            $room = $h->addRoom();

            $roomType = $this->http->FindSingleNode("*[normalize-space()][2]", $rRoot);
            $room->setType($roomType, false, true);

            $rate = $this->http->FindSingleNode("following::tr[*[normalize-space()][1][not(.//tr)] and count(*[normalize-space()])=2][position()<3][ *[normalize-space()][1][{$this->eq($this->t('RATE'))}] ]/*[normalize-space()][2]", $rRoot);

            if (preg_match('/\d/', $rate) > 0) {
                $room->setRate($rate);
            } else {
                $room->setRateType($rate, false, true);
            }

            $subTotal = $this->http->FindSingleNode("following::tr[*[normalize-space()][1][not(.//tr)] and count(*[normalize-space()])=2][position()<3][ *[normalize-space()][1][{$this->eq($this->t('SUB-TOTAL'))}] ]/*[normalize-space()][2]", $rRoot, true, '/^(?:[^:]+[:]+)?\s*([^:]*\d[^:]*)$/');

            if (preg_match('/^(?<currency>[^\-\d)(]+?)?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})$/u', $subTotal, $m)) {
                $subTotalCurrencies[] = $m['currencyCode'];
                $subTotalAmounts[] = PriceHelper::parse($m['amount'], $m['currencyCode']);
            }
        }

        $grandTotal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('GRAND TOTAL'))}] ]/*[normalize-space()][2]/descendant::tr[not(.//tr) and normalize-space()][1]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})$/u', $grandTotal, $matches)) {
            // $722.84 USD
            if (empty($matches['currency'])) {
                $matches['currency'] = '';
            }
            $h->price()->currency($matches['currencyCode'])->total(PriceHelper::parse($matches['amount'], $matches['currencyCode']));

            if (count(array_unique($subTotalCurrencies)) === 1 && $subTotalCurrencies[0] === $matches['currencyCode']) {
                $h->price()->cost(array_sum($subTotalAmounts));
            }

            $fees = $feeTexts = [];
            $additionalItemsNodes = $this->http->XPath->query("//*[ count(tr[normalize-space()])>1 and tr[{$this->eq($this->t('ADDITIONAL ITEMS'))}] ]/tr[normalize-space() and not({$this->eq($this->t('ADDITIONAL ITEMS'))})]");

            foreach ($additionalItemsNodes as $aiRow) {
                $additionalItems = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $aiRow));
                $feeTexts = array_merge($feeTexts, preg_split("/[ ]*\n+[ ]*/", $additionalItems));
            }

            foreach ($feeTexts as $feeText) {
                if (preg_match("/^(?<name>.{2,}?)\s*[:]+\s*(?<charge>.*\d.*)$/", $feeText, $feeMatches)) {
                    if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currencyCode'], '/') . ')?$/u', $feeMatches['charge'], $m)) {
                        $feeCharge = PriceHelper::parse($m['amount'], $matches['currencyCode']);

                        if (array_key_exists($feeMatches['name'], $fees)) {
                            $fees[$feeMatches['name']][] = $feeCharge;
                        } else {
                            $fees[$feeMatches['name']] = [$feeCharge];
                        }
                    }
                }
            }

            foreach ($fees as $feeName => $feeCharges) {
                $h->price()->fee($feeName, array_sum($feeCharges));
            }
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CANCELLATION POLICY:'))}]/following::text()[normalize-space()][1]");
        $h->general()->cancellation($cancellation, false, true);

        if ($cancellation) {
            if (preg_match("/^Cancell? by (?<time>{$patterns['time']}) on (?<date>.{4,20}\b\d{4}) to avoid\b.{0,16} penalty\./i", $cancellation, $m) // en
            ) {
                $h->booked()->deadline(strtotime($m['time'], strtotime($m['date'])));
            }
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['status']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr/*[{$this->eq($phrases['status'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
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
}
