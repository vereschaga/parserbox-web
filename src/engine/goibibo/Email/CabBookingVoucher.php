<?php

namespace AwardWallet\Engine\goibibo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class CabBookingVoucher extends \TAccountChecker
{
    public $mailFiles = "goibibo/it-71364004.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'pickUp'  => ['Pickup At'],
            'carType' => ['Booked Car Type'],
        ],
    ];

    private $subjects = [
        'en' => ['ETicket for Car Booking ID'],
    ];

    private $detectors = [
        'en' => ['Trip Details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@goibibo.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".goibibo.com/") or contains(@href,"www.goibibo.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@goibibo.com")]')->length === 0
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
        $email->setType('CabBookingVoucher' . ucfirst($this->lang));

        $this->parseTransfer($email);

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

    private function parseTransfer(Email $email): void
    {
        $xpathP = '(self::p or self::div)';

        $train = $email->add()->transfer();

        $confirmation = $this->http->FindSingleNode("//*[{$this->eq($this->t('Booking ID'))}]/following-sibling::*[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//*[{$this->eq($this->t('Booking ID'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($confirmation, $confirmationTitle);
            $train->general()->noConfirmation();
        }

        $s = $train->addSegment();

        $xpathRoute = "//tr[ count(*)=2 and *[1][normalize-space()='' and descendant::img[contains(@src,'/trip-start-end.')]] and *[2][normalize-space()] ]/*[2]/descendant-or-self::*[count(*[normalize-space()])=2]";

        $startPoint = $this->http->FindSingleNode($xpathRoute . "/*[normalize-space()][1]");
        $s->departure()->address($startPoint);

        $endPoint = $this->http->FindSingleNode($xpathRoute . "/*[normalize-space()][2]");
        $s->arrival()->address($endPoint);

        $xpathCustomer = "//tr[{$this->eq($this->t('Customer Details'))}]/following-sibling::tr[normalize-space()][1]";

        $traveller = $this->http->FindSingleNode($xpathCustomer . "/descendant::*[{$xpathP} and {$this->eq($this->t('Name'))}]/following-sibling::*[{$xpathP} and normalize-space()]", null, true, "/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u");
        $train->general()->traveller($traveller);

        $xpathTrip = "//tr[{$this->eq($this->t('Trip Details'))}]/following-sibling::tr[normalize-space()][1]";

        $datePickup = $this->http->FindSingleNode($xpathTrip . "/descendant::*[{$xpathP} and {$this->eq($this->t('pickUp'))}]/following-sibling::*[{$xpathP} and normalize-space()]", null, true, "/^(.{6,}?)(?:\s*[hrs]+)$/i");
        $s->departure()->date2(preg_replace('/^(.{5,}\d)\s*[hrs]+$/i', '$1', $datePickup));

        $carType = $this->http->FindSingleNode($xpathTrip . "/descendant::*[{$xpathP} and {$this->eq($this->t('Booked Car Type'))}]/following-sibling::*[{$xpathP} and normalize-space()]");
        $s->extra()->type($carType);

        if (!empty($s->getDepDate())) {
            $s->arrival()->noDate();
        }

        $xpathFare = "//tr[{$this->eq($this->t('Fare Details'))}]/following-sibling::tr[normalize-space()][1]";

        $totalPrice = $this->http->FindSingleNode($xpathFare . "/descendant::tr[ count(*)=2 and *[1][{$this->eq($this->t('Total Booking Amount'))}] ]/*[2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // Rs 4109.0
            $currency = $this->normalizeCurrency($m['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $train->price()->currency($currency)->total(PriceHelper::parse($m['amount'], $currencyCode));

            $totalFare = $this->http->FindSingleNode($xpathTrip . "/descendant::*[{$xpathP} and {$this->eq($this->t('Total Fare'))}]/following-sibling::*[{$xpathP} and normalize-space()]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalFare, $matches)) {
                $train->price()->cost(PriceHelper::parse($matches['amount'], $currencyCode));
            }
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
            if (!is_string($lang) || empty($phrases['pickUp']) || empty($phrases['carType'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['pickUp'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['carType'])}]")->length > 0
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

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            // do not add unused currency!
            'INR' => ['Rs'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
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
