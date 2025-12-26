<?php

namespace AwardWallet\Engine\preferred\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "preferred/it-17430245.eml, preferred/it-723648412.eml";

    private $detects = [
        "Below we've copied some of the formalities we thought you",
        'Thank you again for choosing to stay with us',
        'We are delighted to confirm your reservation',
        "we've included some suggestions to spice up your stay below",
    ];

    private $lang = 'en';

    private $patterns = [
        'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.  |  3pm
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52  |  (+351) 21 342 09 07  |  713.680.2992
    ];

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        $this->parseEmail($email);
        $email->setType('Hotel' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || !array_key_exists('subject', $headers)
            || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true
        ) {
            return false;
        }

        return stripos($headers['subject'], 'your upcoming stay') !== false
            || stripos($headers['subject'], 'your reservation confirmation') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (0 === $this->http->XPath->query("//img[contains(@src, 'img.revinate.com/image/upload') or @alt='iPreferred']")->length) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if ($this->http->XPath->query('//node()[contains(normalize-space(),"' . $detect . '")]')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:preferredhotelgroup|eaupalmbeach|pulitzeramsterdam)[.]com/i', $from) > 0;
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email): void
    {
        $h = $email->add()->hotel();

        $confirmationNumber = $this->getNode('Confirmation number', '/^[-A-Z\d]{5,35}$/');

        if ($confirmationNumber) {
            $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq('Confirmation number', "translate(.,':*','')")}]", null, true, '/^(.+?)[\s:：]*$/u')
            ?? $this->http->FindSingleNode("//text()[{$this->starts('Confirmation number')}]", null, true, "/^({$this->opt('Confirmation number')})[*:]*:/");
            $h->addConfirmationNumber($confirmationNumber, $confirmationNumberTitle);
        }

        $bookingNumber = $this->getNode('Booking number', '/^[-A-Z\d]{5,35}$/');

        if ($bookingNumber) {
            $bookingNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq('Booking number', "translate(.,':*','')")}]", null, true, '/^(.+?)[\s:：]*$/u')
            ?? $this->http->FindSingleNode("//text()[{$this->starts('Booking number')}]", null, true, "/^({$this->opt('Booking number')})[*:]*:/");
            $h->addConfirmationNumber($bookingNumber, $bookingNumberTitle);
        }

        $hotelName = $this->http->FindSingleNode("//text()[{$this->eq(['View in Browser', 'View In Browser'])}]/following::img[1][normalize-space(@width)='300' or not(@width) or {$this->contains('Exterior', '@alt')}]/@alt", null, true, "/^(.{2,}?)(?:\s+{$this->opt('Exterior')})?$/i")
            ?? $this->http->FindSingleNode("//text()[normalize-space()='General Manager']/following::text()[1]")
        ;

        $address = $this->getNode('Address');

        if (empty($address)) {
            $address = implode(', ', $this->http->FindNodes("//img[contains(@src,'Facebook-Logo')]/preceding::table[1]/descendant::text()[normalize-space()][not(contains(normalize-space(),'TEL.'))]"));
        }

        $phone = $this->getNode('Phone', "/^{$this->patterns['phone']}$/");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//img[contains(@src,'Facebook-Logo')]/preceding::table[1]//descendant::text()[normalize-space()][starts-with(normalize-space(),'TEL.')]", null, false, "/TEL.[:\s]+({$this->patterns['phone']})/");
        }

        if ($hotelName) {
            $contacts = implode("\n", $this->http->FindNodes("//text()[{$this->eq($hotelName)}]/ancestor::td[1]/descendant::text()[normalize-space()]"));

            if (!$address
                && preg_match("/(?:{$this->opt($hotelName)}.*\n+)+([\s\S]{3,}?)\n+.*(?:\w\.com\b|@|{$this->patterns['phone']})/i", $contacts, $m)
            ) {
                $address = preg_replace('/\s+/', ' ', $m[1]);
            }

            if (!$phone
                && preg_match("/{$this->opt($hotelName)}.*\n+[\s\S]{3,}?\n+.*?({$this->patterns['phone']})(?:\n|$)/i", $contacts, $m)
            ) {
                $phone = preg_replace('/\s+/', ' ', $m[1]);
            }
        }

        if (empty($address)) {
            $addressText = implode("\n", $this->http->FindNodes("//img[normalize-space(@alt)='Facebook'][following::img[1][normalize-space(@alt)='Instagram']]/preceding::text()[normalize-space()][1]/ancestor::*[self::p or self::div][position()<3][count(descendant::text()[normalize-space()])>1][1]/descendant::text()[normalize-space()]"));

            if (preg_match("/^((?:.*\n){2,5}).*@.*\n([ \d\(\)\+\-]{5,})(?:\n|$)/", $addressText, $m)
                && strlen(preg_replace("/\D/", '', $m[2])) > 5
            ) {
                $address = preg_replace('/\s+/', ' ', trim($m[1]));
                $phone = $m[2];
            }
        }

        $h->hotel()->name($hotelName)->address($address)->phone($phone);

        $h->booked()
            ->checkIn(strtotime(str_replace(".", '', $this->getNode('Arrival date') . ', ' . $this->getNode(['Check-in time', 'Checkin time']))));

        $h->booked()
            ->checkOut(strtotime(str_replace(".", '', $this->getNode('Departure date') . ', ' . $this->getNode(['Check-out time', 'Checkout time']))));

        if (preg_match('/^\d{1,3}$/', $this->getNode('Number of adults'), $m)) {
            $h->booked()->guests($m[0]);
        }

        if (preg_match('/^\d{1,3}$/', $this->getNode('Number of children'), $m)) {
            $h->booked()->kids($m[0]);
        }

        if (preg_match('/\b(\d{1,3})\s*\/\s*(\d{1,3})\b/', $this->getNode('Adults / children'), $m)) {
            $h->booked()->guests($m[1])->kids($m[2]);
        }

        $h->general()
            ->travellers(array_map("trim", explode(",", preg_replace("# and #", ',', $this->getNode('Guest name')))));

        $roomType = $this->getNode('Room type');
        $roomRate = $this->getNode(['Average Nightly Rate', 'Average nightly rate']) ?? $this->getNode('Rate');

        if ($roomType || $roomRate !== null) {
            $h->addRoom()->setType($roomType, false, true)->setRate($roomRate, false, true);
        }

        $totalPrice = $this->getNode('Total amount', '/^.*\d.*$/') ?? $this->getNode('Total', '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // EUR 2,860.00
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $cancellationTexts = $this->http->FindNodes("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq(['Cancellation Policy', 'Cancellation'], "translate(.,':*','')")}] ]/*[normalize-space()][2]/descendant::text()[normalize-space()][not(ancestor::i)]");
        $cancellation = count($cancellationTexts) > 0 ? implode(' ', $cancellationTexts) : null;

        if (!$cancellation) {
            $cancellation = $this->getNode('Cancellation Policy') ?? $this->getNode('Cancellation');
        }

        $h->general()->cancellation($cancellation, false, true);

        if ($cancellation) {
            $this->detectDeadLine($h, $cancellation);
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText): void
    {
        if (preg_match("#Notice of cancellation should be received .+? \((\d+)\) days prior to arrival date#i",
            $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days', '00:00');
        }

        if (preg_match("/If (?i)you cancell? after (?<hour>{$this->patterns['time']})(?: hotel time)? the day before arrival the forfeiture amount will be one night stay/", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative('1 day', $m['hour']);
        }
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

    private function getNode($s, ?string $re = null): ?string
    {
        $result = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($s, "translate(.,':*','')")}] ]/*[normalize-space()][2]", null, true, $re);

        if ($result === null) {
            $result = $this->http->FindSingleNode("//text()[{$this->starts($s)}]", null, true, "/^{$this->opt($s)}[*\s]*[:]+\s*(.+)$/");

            if ($re !== null) {
                if (preg_match($re, $result, $m)) {
                    $result = count($m) > 1 ? $m[1] : $m[0];
                } else {
                    $result = null;
                }
            }
        }

        if ($result === null) {
            $result = $this->http->FindSingleNode("//text()[{$this->eq($s, "translate(.,':*','')")}]/following::text()[normalize-space()][1]", null, true, $re ?? '/^.*[^:]$/');
        }

        return $result;
    }
}
