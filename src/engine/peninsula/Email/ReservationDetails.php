<?php

namespace AwardWallet\Engine\peninsula\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationDetails extends \TAccountChecker
{
    public $mailFiles = "peninsula/it-231054786.eml, peninsula/it-234594913.eml, peninsula/it-248862073.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'confNumber'                        => 'Confirmation Number:',
            'Hotel Information'                 => ['Hotel Information', 'HOTEL INFORMATION'],
            "Cancellations must be received by" => ["Cancellations must be received by", "The hotel must receive any cancellation"],
            'guestName'                         => ['Guest Name:', 'Guest Names:'],
        ],
    ];

    private $detectFrom = '@peninsula.com';
    private $detectSubject = [
        // en
        ' - Confirmation for ',
    ];
    private $detectBody = [
        'en' => [
            'Reservation Details', 'CONFIRMATION OF YOUR RESERVATION AT THE PENINSULA',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'The Peninsula ') === false
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
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['.peninsula.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Confirmation of your reservation at the Peninsula'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
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

    private function parseEmailHtml(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $h = $email->add()->hotel();

        // General

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('confNumber'))}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[normalize-space()][1][{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $travellers = [];
        // Guest Names: Mr David Rogero & Mrs Maureen Rogero (children Sadie, Ryan, Elle & Lucy)
        $travellersVal = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('guestName'))}] ]/*[normalize-space()][2]");
        $travellersVal = preg_replace('/\s*\([^)(]+\)/', '', $travellersVal);

        if ($travellersVal) {
            $travellerValues = preg_split("/(?:\s*(?:[,;&]+|\bAnd\b)\s*)+/i", $travellersVal);
            $travellerValues = array_filter(preg_replace("/^(?:Mr|Ms|Mrs|Miss|Mstr|Dr)[.\s]+/i", '', $travellerValues));

            foreach ($travellerValues as $tVal) {
                if (preg_match("/^{$patterns['travellerName']}$/u", $tVal)) {
                    $travellers[] = $tVal;
                } else {
                    $travellers = [];

                    break;
                }
            }
        }
        $h->general()->travellers($travellers);

        $cancellation = $this->nextSibling($this->t("Cancellation Policy:"));

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t("Cancellations must be received by"))}]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Cancellations must be received by"))}]");
        }

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        // Hotel
        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t("Hotel Information"))}]/following::text()[normalize-space()][1]/ancestor::td[1][not({$this->contains($this->t("Hotel Information"))})]//text()[normalize-space()]"));

        if (preg_match("/^(?<name>.{2,})\n(?<address>(?:.+\n)+?)\s*{$this->opt($this->t('Tel'))}\s*:\s*(?<tel>{$patterns['phone']})\n\s*{$this->opt($this->t('Fax'))}\s*:\s*(?<fax>{$patterns['phone']})(?:\n|$)/", $hotelInfo, $m)
            || preg_match("/^(?<name>.{2,})\n(?<address>(?:.+\n*){1,2}\D{10,})$/", $hotelInfo, $m)
        ) {
            /*  The Peninsula New York
                700 Fifth Ave at 55th St
                New York, 10019, United States
                Tel: 1-212-9562888
                Fax: 1-212-9033949
            */
            $h->hotel()
                ->name($m['name'])
                ->address(str_replace("\n", ', ', $m['address']));

            if (isset($m['tel']) && !empty($m['tel'])) {
                $h->hotel()
                    ->phone($m['tel']);
            }

            if (isset($m['tel']) && !empty($m['tel'])) {
                $h->hotel()
                    ->fax($m['fax']);
            }
        }

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->nextSibling($this->t("Arrival Date:"), "/(.+?)(?:-.*|$)/")))
            ->checkOut(strtotime($this->nextSibling($this->t("Departure Date:"), "/(.+?)(?:(?:-|–).*)?$/")))
            ->guests($this->nextSibling($this->t("Number of Guests:")))
        ;

        $checkInTime = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('the check-in time is')) . ']',
            null, false, "/{$this->opt($this->t('the check-in time is'))}\s+({$patterns['time']})/i");

        if (!empty($h->getCheckInDate()) && $checkInTime) {
            $h->booked()->checkIn(strtotime($this->normalizeTime($checkInTime), $h->getCheckInDate()));
        }
        $checkOutTime = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('and check-out time is')) . ']',
            null, false, "/{$this->opt($this->t('and check-out time is'))}\s+({$patterns['time']})/i");

        if (!empty($h->getCheckOutDate()) && $checkOutTime) {
            $h->booked()->checkOut(strtotime($this->normalizeTime($checkOutTime), $h->getCheckOutDate()));
        }

        // Room
        $r = $h->addRoom();
        $r
            ->setType($this->nextSibling($this->t("Room Type:")));
        // rate
        $realNights = 0;

        if (!empty($h->getCheckOutDate()) && !empty($h->getCheckInDate())) {
            $realNights = date_diff(date_create('@' . strtotime('00:00', $h->getCheckInDate())),
                date_create('@' . strtotime('00:00', $h->getCheckOutDate())))->format('%a');
        }

        $dailyRate = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Room Rate:'))}] ]/*[normalize-space()][2]"));

        if ($realNights > 0 && preg_match("/^\s*(?<ratetype>.+)\n\n\s*(?<rates>(?:.+\n)+?)\n+(?:(.*\n){1,})?\s*Total:(?<total>.+)/", $dailyRate, $m)
        ) {
            $r->setRateType($m['ratetype']);

            $rates = [];
            $freeNights = null;
            $dailyRateRows = preg_split('/\s*\n\s*/', trim($m['rates']));

            foreach ($dailyRateRows as $drRow) {
                if (preg_match("/^.+\d{4}\s+(?<currency>[^\d)(]{1,5}?)[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*$/", $drRow, $matches)) {
                    // Thursday, June 16, 2022    CHF 0.00
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;

                    $rateAmount = PriceHelper::parse($matches['amount'], $currencyCode);
                    $rates[] = $matches['currency'] . ' ' . $rateAmount;

                    if ($rateAmount == 0) {
                        ++$freeNights;
                    }
                } else {
                    $freeNights = null;

                    break;
                }
            }

            if (count($rates) == $realNights) {
                $r->setRates($rates);
            } elseif (!empty($dailyRateRows)) {
                $r->setRate(implode('; ', $dailyRateRows));
            }

            if ($freeNights !== null) {
                $h->setFreeNights($freeNights);
            }

            if (preg_match("/^\s*(?<currency>[^\d)(]{1,5}?)[ ]*(?<amount>\d[,.\'\d ]*?)\s*$/", $m['total'], $matches)) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;

                $h->price()
                    ->total(PriceHelper::parse($matches['amount'], $currencyCode))
                    ->currency($matches['currency']);
            }
        } elseif (preg_match("/^\s*(?<ratetype>\D+)\n\s*(?<rate>.+ per night)\n+\s*Total:(?<total>.+)/", $dailyRate, $m)) {
            $r->setRateType(preg_replace("/\s*\n\s*/", ', ', trim($m['ratetype'])));

            $r->setRate($m['rate']);

            if (preg_match("/^\s*(?<currency>[^\d)(]{1,5}?)[ ]*(?<amount>\d[,.\'\d ]*?)\s*$/", $m['total'], $matches)) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;

                $h->price()
                    ->total(PriceHelper::parse($matches['amount'], $currencyCode))
                    ->currency($matches['currency']);
            }
        } elseif (preg_match("/^\s*(?<ratetype>\D+)\n\s*(?<rate>.+ per night)\n+/", $dailyRate, $m)) {
            $r->setRateType(preg_replace("/\s*\n\s*/", ', ', trim($m['ratetype'])));

            $r->setRate($m['rate']);
        }

        $this->detectDeadLine($h);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function nextSibling($field, $regexp = null): ?string
    {
        return $this->http->FindSingleNode("(//*[{$this->eq($field)}]/following-sibling::*[normalize-space()][1])[1]", null, true, $regexp);
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

    private function normalizeTime($string): string
    {
        if (preg_match('/^12\s*noon$/i', $string)
            || preg_match('/^\s*noon\s*$/i', $string)) {
            return '12:00';
        }

        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $string, $m) && (int) $m[2] > 12) {
            $string = $m[1];
        } // 21:51 PM    ->    21:51
        $string = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $string); // 00:25 AM    ->    00:25

        return $string;
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/The hotel must receive any cancellation by (?<time>\d+A?P?M) local time 1 day prior to arrival to avoid 1 night penalty charge./u', $cancellationText, $m)) {
            $h->booked()->deadlineRelative('1 day', $m[1]);
        }
    }
}
