<?php

namespace AwardWallet\Engine\tboh\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HotelInvoice extends \TAccountChecker
{
    public $mailFiles = "tboh/it-566717621.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'hotelName'   => ['Hotel Name'],
            'checkIn'     => ['Check In:', 'Check In :'],
            'checkOut'    => ['Check Out:', 'Check Out :'],
            'invoiceDate' => ['Invoice Date:', 'Invoice Date :'],
        ],
    ];

    private $subjects = [
        'en' => ['HOTEL INVOICE'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@tbo\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true
            && !preg_match('/[, ]TBOH (?i)Conf No/', $headers['subject'])
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
            && $this->http->XPath->query('//*[contains(.,"97144357520") or contains(.,"@tboholidays.com")]')->length === 0
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
        $email->setType('HotelInvoice' . ucfirst($this->lang));

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('invoiceDate'))}]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()]", null, true, '/^[-A-z\d]{5,}$/');
        $email->ota()->confirmation($otaConfirmation);

        $checkIn = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('checkIn'))}]/following::text()[normalize-space()][1]", null, true, $pattern = "/^\d{1,2}[- .][[:alpha:]]+[- .]\d{2,4}$/u"));
        $checkOut = strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('checkOut'))}]/following::text()[normalize-space()][1]", null, true, $pattern));

        $costValues = $taxValues = [];
        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Net Amount Payable'))}]", null, true, "/^{$this->opt($this->t('Net Amount Payable'))}\s*\(\s*([^\-\d)(]+?)\s*\)\s*[:]+$/");
        $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;

        $hotels = $this->http->XPath->query("//*[ *[1][{$this->eq($this->t('hotelName'))}] and *[2][{$this->eq($this->t('Room Name'))}] ]/following-sibling::*[normalize-space() and not({$this->contains($this->t('checkIn'))})]");

        foreach ($hotels as $root) {
            $h = $email->add()->hotel();

            $hotelName = $this->http->FindSingleNode("*[1]", $root);
            $h->hotel()->name($hotelName);

            $room = $h->addRoom();
            $roomName = $this->http->FindSingleNode("*[2]", $root);
            $room->setType($roomName);

            $guestName = $this->http->FindSingleNode("*[3]", $root, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");
            $h->general()->traveller($guestName, true);

            $roomsCount = $this->http->FindSingleNode("*[4]", $root, true, '/^\d{1,3}$/');
            $h->booked()
                ->rooms($roomsCount)
                ->checkIn($checkIn)
                ->checkOut($checkOut)
            ;

            $rate = $this->http->FindSingleNode("*[6]", $root, true, '/^\d[,.‘\'\d ]*$/u');
            $costValues[] = $rate !== null ? PriceHelper::parse($rate, $currencyCode) : null;

            $tax = $this->http->FindSingleNode("*[7]", $root, true, '/^\d[,.‘\'\d ]*$/u');
            $taxValues[] = $tax !== null ? PriceHelper::parse($tax, $currencyCode) : null;

            if ($hotelName && !empty($checkIn) && !empty($checkOut)) {
                $h->hotel()->noAddress();
                $h->general()->noConfirmation();
            }
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Net Amount Payable'))}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // 1808.43
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            if (count($costValues) > 0 && !in_array(null, $costValues, true)) {
                $email->price()->cost(array_sum($costValues));
            }

            if (count($taxValues) > 0 && !in_array(null, $taxValues, true)) {
                $email->price()->tax(array_sum($taxValues));
            }
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
            if (!is_string($lang) || empty($phrases['hotelName']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->eq($phrases['hotelName'])}]")->length > 0
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
}
