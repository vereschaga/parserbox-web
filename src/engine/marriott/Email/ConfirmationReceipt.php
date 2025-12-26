<?php

namespace AwardWallet\Engine\marriott\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationReceipt extends \TAccountChecker
{
    public $mailFiles = "marriott/it-47811172.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Confirmation Number'],
            'checkIn'    => ['Check-In'],
        ],
    ];

    private $subjects = [
        'en' => ['Confirmation Receipt'],
    ];

    private $detectors = [
        'en' => ['Summary Confirmation', 'Transaction Date', 'Arrival Information'],
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Marriott Vacation Club Destinations') !== false
            || stripos($from, '@vacationclub.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'Marriott Vacation Club Destinations') === false
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
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".marriottvacationclub.com/") or contains(@href,"owners.marriottvacationclub.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Marriott Vacation Club Int") or contains(normalize-space(),"Marriott International, Inc")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match("/Marriott Vacation Club/", $parser->getSubject())) {
            $email->setProviderCode('marriottvacationclub');
        }

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHotel($email);
        $email->setType('ConfirmationReceipt' . ucfirst($this->lang));

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
        return ['marriott', 'marriottvacationclub'];
    }

    private function parseHotel(Email $email)
    {
        $xpathBold = '(self::b or self::strong)';

        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $transactionDate = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Transaction Date'))}]/following::text()[normalize-space()][1]");
        $h->general()->date2($transactionDate);

        $xpathHotel = "//td[ not(.//td) and count(*[normalize-space()])=4 and *[normalize-space()][3][descendant::img] and *[normalize-space()][4][{$this->starts($this->t('Room Type'))}] ]";

        $hotelName = $this->http->FindSingleNode($xpathHotel . "/*[normalize-space()][1]");
        $address = $this->http->FindSingleNode($xpathHotel . "/*[normalize-space()][2]");
        $phone = $this->http->FindSingleNode($xpathHotel . "/*[normalize-space()][3]", null, true, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
        $roomType = $this->http->FindSingleNode($xpathHotel . "/*[normalize-space()][4]", null, true, "/{$this->opt($this->t('Room Type'))}[:\s]+(.+)/");

        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone);

        if ($roomType) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $checkIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-In'))}]/following::text()[normalize-space()][1]");
        $h->booked()->checkIn2($checkIn);

        $checkOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-Out'))}]/following::text()[normalize-space()][1]");
        $h->booked()->checkOut2($checkOut);

        $guestCount = $this->http->FindSingleNode("//text()[{$this->starts($this->t('# Of Guests'))}]/following::text()[normalize-space()][1]", null, true, '/^\d{1,3}$/');
        $h->booked()->guests($guestCount);

        $xpathGuests = "//text()[{$this->eq($this->t('Primary Guest'))} or {$this->eq($this->t('Additional Guests'))}]";

        $guestNames = array_filter($this->http->FindNodes($xpathGuests . "/following::text()[{$this->starts($this->t('Name'))} and ancestor::*[{$xpathBold}]]/following::text()[normalize-space()][1][not(ancestor::*[{$xpathBold}])]", null, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u'));

        if (count($guestNames)) {
            $h->general()->travellers($guestNames);
        }

        $accountNumbers = array_filter($this->http->FindNodes($xpathGuests . "/following::text()[{$this->starts($this->t('Marriott Rewards Number'))}]/following::text()[normalize-space()][1]", null, '/^[-A-Z\d]{5,}$/'));

        if (count($accountNumbers)) {
            $h->program()->accounts($accountNumbers, false);
        }

        $appliedPoints = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Points Applied to Reservation'))}]/following::text()[normalize-space()][1]", null, true, '/^\d.*/');

        if ($appliedPoints) {
            $h->price()->spentAwards($appliedPoints);
        }

        $cancellationTexts = $this->http->FindNodes("//text()[{$this->starts($this->t('Modifications and cancellation policies'))}]/ancestor::td[1]/descendant::*[normalize-space() and (self::p or self::li) and not(.//*[self::p or self::li])][not({$this->starts($this->t('Modifications and cancellation policies'))})]");
        $cancellationTexts = array_map(function ($item) {
            return preg_replace('/([^,.:;?!])$/', '$1.', $item);
        }, $cancellationTexts);
        $cancellation = implode(' ', $cancellationTexts);
        $h->general()->cancellation($cancellation);
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['checkIn'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
