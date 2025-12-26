<?php

namespace AwardWallet\Engine\wellsfargo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservations extends \TAccountChecker
{
    public $mailFiles = "wellsfargo/it-2108953.eml, wellsfargo/it-2108956.eml, wellsfargo/it-2109377.eml, wellsfargo/it-113259575.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber' => ['Reservation number'],
            'checkIn'    => ['Check in'],
        ],
    ];

    private $subjects = [
        'en' => ['Rewards Travel Confirmation'],
    ];

    private $detectors = [
        'en' => ['Room details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.wellsfargo.com') !== false
            || stripos($from, 'mywellsfargorewards.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'Wells Fargo') === false
            && strpos($headers['subject'], 'Go Far®') === false
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
        if ($this->http->XPath->query('//a[contains(@href,".wellsfargoemail.com/") or contains(@href,"connect.wellsfargoemail.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for your recent Go Far Rewards") or contains(normalize-space(),"Thank you for your recent Wells Fargo Rewards")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('Reservations' . ucfirst($this->lang));

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
        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->eq($this->t('confNumber'))}] ]/*[2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->eq($this->t('confNumber'))}] ]/*[1]");
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $hotelName = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][normalize-space()='Hotel name'] ]/*[2]");
        $address = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][normalize-space()='Address'] ]/*[2]");
        $h->hotel()->name($hotelName)->address($address);

        $checkIn = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->eq($this->t('checkIn'))}] ]/*[2]");

        if (preg_match("/^(?<date>.{6,}?)\s+at\s+(?<time>\d.+)/i", $checkIn, $m)) {
            $checkIn = $m['date'] . ' ' . $m['time'];
        }

        $checkOut = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][normalize-space()='Check out'] ]/*[2]");

        if (preg_match("/^(?<date>.{6,}?)\s+at\s+(?<time>\d.+)/i", $checkOut, $m)) {
            $checkOut = $m['date'] . ' ' . $m['time'];
        }

        $h->booked()->checkIn2($checkIn)->checkOut2($checkOut);

        $xpathRoom = "//tr[ count(*)=2 and *[1][normalize-space()='Check out'] ]/following-sibling::tr[normalize-space()][1][ *[1][contains(normalize-space(),'room')] ]";

        $roomCount = $this->http->FindSingleNode($xpathRoom . "/*[1]", null, true, '/^(\d{1,3})\s*room/');
        $h->booked()->rooms($roomCount);

        $roomType = $this->http->FindSingleNode($xpathRoom . "/*[2]", null, true, '/^(.+?)[\s:：]*$/u');

        $room = $h->addRoom();

        $room->setType($roomType);

        $numberOfGuests = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][normalize-space()='Number of guests'] ]/*[2]");

        if (preg_match("/\b(\d{1,3})\s*Adult/i", $numberOfGuests, $m)) {
            $h->booked()->guests($m[1]);
        }

        $guestName = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][normalize-space()='Guest name'] ]/*[2]", null, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");
        $h->general()->traveller($guestName);

        $cancellation = $this->http->FindSingleNode("//tr[normalize-space()='Cancellation information']/following-sibling::tr[normalize-space()][1]/descendant::p[normalize-space()][1]");
        $h->general()->cancellation($cancellation);

        $h->booked()->parseNonRefundable("All real-time hotel redemptions are non-refundable and non-exchangeable.");

        $rewardsApplied = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][normalize-space()='Rewards applied'] and not(following::tr[count(*)=2 and *[1][normalize-space()='Rewards applied']]) ]/*[2]", null, true, "/^\d.+/");

        if ($rewardsApplied) {
            $h->price()->spentAwards($rewardsApplied);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][normalize-space()='Total charge to payment card'] ]/*[2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $571.95
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount']));
        }
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

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
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

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
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
}
