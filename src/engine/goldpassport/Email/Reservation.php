<?php

namespace AwardWallet\Engine\goldpassport\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "goldpassport/it-30008044.eml";
    private $subjects = [
        'en' => ['Hyatt Regency'],
    ];
    private $langDetectors = [
        'en' => ['# of Kids'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'Room Type Booked:'             => ['Room Type Booked:', 'Room:'],
            'Total Including Taxes & Fees:' => ['Total Including Taxes & Fees:', 'Total After Tax:'],
        ],
    ];

    private $emailSubject;

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@hyatt.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from'], $headers['subject']) && self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Hyatt Reservation') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"@hyatt.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".hyatt.com/")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $this->emailSubject = $parser->getSubject();

        $this->parseEmail($email);
        $email->setType('Reservation' . ucfirst($this->lang));

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
        $xpathFragmentBold = "(self::b or self::strong or contains(@style, 'font-weight:bold') or contains(@style, 'font-weight: bold'))";

        $h = $email->add()->hotel();

        // travellers
        $guestName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Name:'))}]/following::text()[normalize-space(.)][1][ ./ancestor::*[{$xpathFragmentBold} or self::p] ]", null, true, "/^[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]]$/");

        if (empty($guestName)) {
            $guestName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation #:'))}]/preceding::text()[normalize-space(.)][1][ ./ancestor::*[{$xpathFragmentBold} or self::p] ]",
                null, true, "/^[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]]$/");
        }
        $h->general()->traveller($guestName);

        // confirmation number
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation #:'))}]/following::text()[normalize-space(.)][1]", null, true, "/^\s*([A-Z\d]{5,})\s*$/");

        if (!empty($confirmationNumber)) {
            $h->general()->confirmation($confirmationNumber, preg_replace('/\s*:\s*$/', '', $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation #:'))}]")));
        } else {
            $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation #:'))}]");

            if (preg_match("/({$this->opt($this->t('Confirmation #:'))})\s*([A-Z\d]{5,})$/", $confirmationNumber, $m)) {
                $h->general()->confirmation($m[2], preg_replace('/\s*:\s*$/', '', $m[1]));
            }
        }

        // hotelName
        // address
        $hotelTexts = $this->http->FindNodes("//p[ ./preceding-sibling::p[{$this->starts($this->t('Confirmation #:'))}] and ./following-sibling::p[{$this->eq($this->t('View map'))}] ]");

        if (empty($hotelTexts)) {
            $hotelTexts = $this->http->FindNodes("//div[ ./preceding-sibling::div[{$this->starts($this->t('Confirmation #:'))}] and ./following-sibling::div[{$this->eq($this->t('View map'))}] ]");
        }
        $hotelText = implode("\n", $hotelTexts);

        if (preg_match("/^\s*(?<name>[^\n]{3,})\s+(?<address>.{3,})$/s", $hotelText, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace('/\s+/', ' ', $m['address']))
            ;
        } elseif (preg_match("/Hyatt Reservation - (.+) - .+ - \d{1,2}\/\w{2,5}\/\d{4}(?: \| .+)?\s*$/", $this->emailSubject, $m)) {
            $h->hotel()
                ->name($m[1])
                ->noAddress();
        }

        // phone
        $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('View map'))}]/following::text()[normalize-space(.)][position()<5][{$this->starts($this->t('Tel:'))}]", null, true, "/{$this->opt($this->t('Tel:'))}\s*([+)(\d][-.\s\d)(]{5,}[\d)(])$/");

        if (!empty($phone)) {
            $h->hotel()->phone($phone);
        }

        // checkInDate
        // checkOutDate
        $xpath = "//text()[{$this->eq($this->t('# of Rooms:'))}]/ancestor::p[1]/preceding-sibling::p[normalize-space(.)][1]";
        $datesRow = $this->http->FindSingleNode($xpath, null, false, '/^(?:Dates:\s*)?(.+?\s+-\s+.+?)$/');

        if (!$datesRow) {
            $datesRow = $this->http->FindSingleNode("{$xpath}/ancestor::div[1]", null, false, '/^.+?\s+-\s+.+?$/');
        }

        if (!$datesRow) {
            $datesRow = $this->http->FindSingleNode("//text()[{$this->eq($this->t('# of Rooms:'))}]/ancestor::div[1][{$this->starts($this->t('# of Rooms:'))}]/preceding-sibling::div[normalize-space(.)][1]", null, false, '/^.+?\s+-\s+.+?$/');
        }

        if ($datesRow) {
            $dates = preg_split('/\s+-\s+/', $datesRow);

            if (count($dates) === 2) {
                $h->booked()->checkIn2($dates[0])->checkOut2($dates[1]);
            }
        }

        // roomsCount
        $roomsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('# of Rooms:'))}]/following::text()[normalize-space(.)][1][not(./ancestor::*[{$xpathFragmentBold}])]", null, true, "/^\d{1,3}$/");
        $h->booked()->rooms($roomsCount);

        // guestCount
        $guestCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('# of Adults:'))}]/following::text()[normalize-space(.)][1][not(./ancestor::*[{$xpathFragmentBold}])]", null, true, "/^\d{1,3}$/");
        $h->booked()->guests($guestCount);

        // kidsCount
        $kidsCount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('# of Kids:'))}]/following::text()[normalize-space(.)][1][not(./ancestor::*[{$xpathFragmentBold}])]", null, true, "/^\d{1,3}$/");
        $h->booked()->kids($kidsCount);

        $r = $h->addRoom();

        // r.type
        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type Booked:'))}]/following::text()[normalize-space(.)][1][not(./ancestor::*[{$xpathFragmentBold}])]");
        $r->setType($roomType);

        // r.rate
        $rate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Average Nightly Rate:'))}]/following::text()[normalize-space(.)][1][not(./ancestor::*[{$xpathFragmentBold}])]");
        $r->setRate($rate !== null ? $rate . ' / night' : null);

        // p.total
        $payment = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Including Taxes & Fees:'))}]/following::text()[normalize-space(.)][1][not(./ancestor::*[{$xpathFragmentBold}])]");

        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $payment, $matches)) {
            // 2106.21 USD
            $h->price()
                ->total($this->normalizeAmount(PriceHelper::parse($matches['amount'], $matches['currency'])))
                ->currency($matches['currency'])
            ;

            // p.tax
            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes & Fees:'))}]/following::text()[normalize-space(.)][1][not(./ancestor::*[{$xpathFragmentBold}])]");

            if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*' . preg_quote($matches['currency'], '/') . '\b/', $tax, $m)) {
                $h->price()->tax($this->normalizeAmount(PriceHelper::parse($m['amount'], $matches['currency'])));
            }
        }

        // r.rateType
        $rateType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rate Plan:'))}]/following::text()[normalize-space(.)][1][not(./ancestor::*[{$xpathFragmentBold}])]");
        $r->setRateType($rateType);

        // r.description
        $benefits = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Because you booked through your travel advisor, you are receiving the following benefits:'))}]/following::text()[normalize-space(.)][1][not(./ancestor::*[{$xpathFragmentBold}])]");
        $r->setDescription($benefits, false, true);

        // cancellation
        $cancellationPolicy = $this->http->FindSingleNode("//p[{$this->eq($this->t('CANCELLATION POLICY'))}]/following-sibling::p[normalize-space(.)][1]");
        $h->general()->cancellation($cancellationPolicy, true, true);

        // deadline
        if (
            preg_match("/\bCancell?\s*(?<prior>\d+)\s*d Prior For Refund/i", $cancellationPolicy, $m)
            // 21 Days Prior To Arrival To Avoid Two Night Fee
            || preg_match("/^\s*(?<prior>\d+)\s*Days Prior To Arrival To Avoid Two Night Fee/i", $cancellationPolicy, $m)
            // 14 Days Prior Or 1night Fee Credit Card Req
            || preg_match("/^\s*(?<prior>\d+)\s*Days Prior Or \d+night Fee Credit Card Req/i", $cancellationPolicy, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'] . ' days', '00:00');
        }
    }

    private function normalizeAmount(string $price)
    {
        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
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
