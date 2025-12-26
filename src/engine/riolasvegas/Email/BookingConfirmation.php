<?php

namespace AwardWallet\Engine\riolasvegas\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "riolasvegas/it-724931731.eml, riolasvegas/it-727404072.eml, riolasvegas/it-733407436.eml";

    public $htmlType = 'td';
    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking Information'  => 'Booking Information',
            'BOOKING CANCELLATION' => ['BOOKING CANCELLATION', 'received your cancellation notice', 'Your cancellation number is:'],
            'Subtotal:'            => ['Subtotal:', 'Room Rates:'],
            'Tax and Fees:'        => ['Tax and Fees:', 'Tax:'],
        ],
    ];

    private $detectFrom = "resemailsender@riolasvegas.com";
    private $detectSubject = [
        // en
        'Rio Las Vegas Booking Confirmation:',
        'Rio Hotel and Casino Booking Cancellation:',
        'Rio Las Vegas Pre-Stay Booking Confirmation:',
        'Rio Hotel and Casino Post-Stay, Booking Confirmation:',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]riolasvegas\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Rio Las Vegas') === false
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
            $this->http->XPath->query("//a[{$this->contains(['riolasvegas.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Rio Hotel and Casino'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Booking Information']) && $this->http->XPath->query("//*[{$this->contains($dict['Booking Information'])}]")->length > 0) {
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
        return count(self::$dictionary) * 2;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Booking Information"])) {
                if ($this->http->XPath->query("//*[{$this->eq($dict['Booking Information'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        if (empty($this->nextTd($this->t('Room Type:'))) && !empty($this->http->FindSingleNode("(//div[not(.//div)][{$this->starts($this->t('Room Type:'))}][not(ancestor::tr[{$this->starts($this->t('Room Type:'))}])])[1]"))) {
            $this->htmlType = 'div';
        }
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->nextTd($this->t('Booking Reference:')))
            ->traveller($this->nextTd($this->t('Name:')))
            ->cancellation($this->http->FindSingleNode("//*[{$this->eq($this->t('Cancellation Policy'))}]/following-sibling::*[normalize-space()][1]"), true, true)
        ;

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('BOOKING CANCELLATION'))}]")->length) {
            $h->general()
                ->status('cancelled')
                ->cancelled();

            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your cancellation number is:'))}]",
                null, true, "/{$this->opt($this->t('Your cancellation number is:'))}\s*(\d{5,})\s*\./");

            if (!empty($number)) {
                $h->general()
                    ->cancellationNumber($number);
            }
        }

        // Hotel
        $h->hotel()
            ->name($this->nextTd($this->t('Hotel:')))
        ;
        $info = implode("\n", $this->http->FindNodes("(//text()[{$this->starts($this->t('Total:'))}]/following::text()[{$this->eq('Rio Hotel & Casino')}])[1]/ancestor::*[not({$this->eq('Rio Hotel & Casino')})][1]//text()[normalize-space()]"));

        if (preg_match("/^\s*Rio Hotel & Casino\n(.*\d+.*)\n\s*([\d\-]{5,})\s*$/", $info, $m)
            && strlen(preg_replace("/\D+/", '', $m[2])) > 5
        ) {
            $h->hotel()
                ->address($m[1])
                ->phone($m[2]);
        } elseif (preg_match("/^\s*Rio Hotel & Casino\n(.*\d+.*,.*)\s*$/", $info, $m)) {
            $h->hotel()
                ->address($m[1]);
        }

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->nextTd($this->t('Arrival Date:'))))
            ->checkOut(strtotime($this->nextTd($this->t('Departure Date:'))))
        ;

        // Rooms
        $room = $h->addRoom();
        $room
            ->setType($this->nextTd($this->t('Room Type:')))
            ->setRateType($this->nextTd($this->t('Rate:')), true, true)
        ;
        $nights = $this->nextTd($this->t('Number of Nights:'), "(\d+)");
        $rates = $this->http->FindNodes("//*[{$this->eq($this->t('Daily Rates'))}]/following-sibling::*[1]/descendant::tr[not(.//tr)]/td[2]");

        if ($nights == count($rates)) {
            $room->setRates($rates);
        }

        // Price
        $currency = $this->currency(preg_replace('/\s*\d[\d,. ]*\s*/', '', $this->nextTd($this->t('Total:'))));
        $h->price()
            ->cost(PriceHelper::parse($this->nextTd($this->t('Subtotal:'), '\D{0,5}\s*(\d[\d,. ]*?)\s*\D{0,5}'), $currency))
            // no need collect "Resort Fee:", it is included in the tax
            // no info about "Add Ons:", maybe it is included in the tax maybe not
            ->tax(PriceHelper::parse($this->nextTd($this->t('Tax and Fees:'), '\D{0,5}\s*(\d[\d,. ]*?)\s*\D{0,5}'), $currency))
            ->total(PriceHelper::parse($this->nextTd($this->t('Total:'), '\D{0,5}\s*(\d[\d,. ]*?)\s*\D{0,5}'), $currency))
            ->currency($currency)
        ;

        return true;
    }

    private function nextTd($field, $regexpPart = null)
    {
        if ($this->htmlType == 'div') {
            if (!empty($regexpPart)) {
                $regexp = "/^\s*{$this->opt($field)}\s*{$regexpPart}\s*$/";
            } else {
                $regexp = "/^\s*{$this->opt($field)}\s*(.+)\s*$/";
            }
            $result = $this->http->FindSingleNode("(//div[not(.//div)][{$this->starts($field)}])[1]",
                null, true, $regexp);
        }

        if (!empty($result)) {
            return $result;
        }

        if (!empty($regexpPart)) {
            $regexp = "/^\s*{$regexpPart}\s*$/";
        } else {
            $regexp = null;
        }

        return $this->http->FindSingleNode("//tr[not(.//tr)][*[normalize-space()][1][{$this->eq($field)}]]/*[normalize-space()][2]",
            null, true, $regexp);
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

    private function currency($s)
    {
        $s = trim($s);

        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $s;
        }
        $sym = [
            '$' => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
