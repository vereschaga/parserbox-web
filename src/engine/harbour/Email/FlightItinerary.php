<?php

namespace AwardWallet\Engine\harbour\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightItinerary extends \TAccountChecker
{
    public $mailFiles = "harbour/it-466129407.eml, harbour/it-468354663.eml, harbour/it-681385341.eml, harbour/it-686372470.eml, harbour/it-686382513.eml";
    public $subjects = [
        'Flight Itinerary. Thanks for choosing Harbour Air!',
        'Thank you for choosing to take off with Helijet!',
    ];

    public $providerCode;
    public static $detectProviders = [
        'harbour' => [
            'from'              => 'reservation@harbourair.com',
            'subjectUniqueText' => 'Harbour Air',
            'bodyText'          => ['Harbour Air Seaplanes', 'www.harbourair.com'],
        ],
        'helijet' => [
            'from'              => 'passengerservices@helijet.com',
            'subjectUniqueText' => 'Helijet',
            'bodyText'          => ['Helijet Reservations', 'with Helijet.', '@helijet.com'],
        ],
    ];

    public $lang = 'en';
    public static $dictionary = [
        "en" => [
            'Passenger(s) - ' => ['Passenger(s) - ', 'Passengers -'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers['subject'])) {
            return false;
        }

        foreach (self::$detectProviders as $code => $detects) {
            if ((!empty($detects['from']) && stripos($headers['from'], $detects['from']) !== false)
                || (!empty($detects['subjectUniqueText']) && stripos($headers['subject'], $detects['subjectUniqueText']) !== false)
            ) {
                $this->providerCode = $code;
            } else {
                continue;
            }

            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$detectProviders as $code => $detects) {
            if (!empty($detects['bodyText']) && $this->http->XPath->query("//text()[{$this->contains($detects['bodyText'])}]")->length > 0) {
                $this->providerCode = $code;
            } else {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->contains($this->t('Booking #'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Customer Information'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Add to Calendar'))}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]harbourair\.com$/', $from) > 0;
    }

    public function Flight(Email $email)
    {
        $flightNodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Booking #')]/ancestor::*[count(*[starts-with(normalize-space(), 'Booking #')])=1][last()]");

        foreach ($flightNodes as $flightRoot) {
            $f = $email->add()->flight();

            $travellers = $this->http->FindNodes("./descendant::text()[{$this->contains($this->t('Passenger(s) - '))}]/following::table[1]/descendant::text()[string-length()>3]", $flightRoot, "/^(\D+)\,/");

            $f->general()
                ->travellers(array_unique($travellers), true)
                ->confirmation($this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Booking #')]", $flightRoot, true, "/{$this->opt($this->t('Booking #'))}\s*(\d{5,})/"));

            if ($this->http->XPath->query("//text()[normalize-space()='Account']")->length > 0) {
                $accounts = $this->http->FindNodes("//text()[normalize-space()='Account']/ancestor::table[1]/descendant::text()[normalize-space()='HAS #']/following::text()[normalize-space()][1]", null, "/^(\d{5,})$/");
                $f->setAccountNumbers($accounts, false);
            }

            $nodes = $this->http->XPath->query("./descendant::text()[contains(normalize-space(), 'Departure:')]/preceding::text()[normalize-space()][1]", $flightRoot);

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                $airlineInfo = $this->http->FindSingleNode(".", $root);

                if (preg_match("/^(?<airName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*[#](?<number>\d{1,4})$/", $airlineInfo, $m)
                    || preg_match("/^(?<airName>Flight)\s*[#](?<number>\d{1,4})/", $airlineInfo, $m)
                    || preg_match("/^(?<number>\d{1,4})/", $airlineInfo, $m)
                ) {
                    if (empty($m['airName']) || $m['airName'] === 'Flight') {
                        $m['airName'] = $this->providerCode === 'helijet' ? 'JB' : 'YB';
                    }

                    $s->airline()
                        ->name($m['airName'])
                        ->number($m['number']);
                }

                $depDate = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'Booking #')][1]/following::text()[normalize-space()][1]", $root);
                $depTime = $this->http->FindSingleNode("./following::text()[normalize-space()='Departure:'][1]/following::text()[normalize-space()][1]", $root, true, "/^([\d\:]+)/");

                $s->departure()
                    ->date(strtotime($depDate . ', ' . $depTime))
                    ->name($this->http->FindSingleNode("./following::text()[normalize-space()='Departure:'][1]/following::text()[normalize-space()][1]", $root, true, "/^[\d\:]+\s*(.+)/"))
                    ->noCode();

                $arrTime = $this->http->FindSingleNode("./following::text()[normalize-space()='Arrival:'][1]/following::text()[normalize-space()][1]", $root, true, "/^([\d\:]+)/");

                $s->arrival()
                    ->date(strtotime($depDate . ', ' . $arrTime))
                    ->name($this->http->FindSingleNode("./following::text()[normalize-space()='Arrival:'][1]/following::text()[normalize-space()][1]", $root, true, "/^[\d\:]+\s*(.+)/"))
                    ->noCode();

                $s->extra()
                    ->cabin($this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[{$this->contains($this->t('Passenger(s) - '))}][1]", $root, true, "/\-\s*(.+)/"));

                if ($nodes->length === 1) {
                    $s->extra()
                        ->duration($this->http->FindSingleNode("./following::text()[normalize-space()='Arrival:'][1]/following::text()[normalize-space()='Directions'][1]/following::text()[normalize-space()][position() < 5][{$this->contains([' hour', ' minute'])}]",
                            $root, null, "/^\d+.+/"));
                }

                $statusText = $this->http->FindSingleNode("./ancestor::table[1]/descendant::text()[contains(normalize-space(), 'Passenger(s)')][1]/preceding::text()[normalize-space()][1]", $root);

                if (preg_match("/^(?<bookingCode>[A-Z]{2})\s*\-\s*(?<status>.+)$/", $statusText, $m)) {
                    $s->extra()
                        ->bookingCode($m['bookingCode']);
                    $s->setStatus($m['status']);
                }
            }

            $price = $this->http->FindSingleNode(".//text()[contains(normalize-space(), 'Grand Total')][1]/ancestor::tr[1]/descendant::text()[normalize-space()][2]", $flightRoot);

            if (preg_match("/^(?<currency>\D) *(?<total>\d[\d\.\,]*)\s*$/", $price, $m)) {
                $currency = $this->normalizeCurrency($m['currency']);

                $f->price()
                    ->total(PriceHelper::parse($m['total'], $currency))
                    ->currency($currency);
            }

            $feeNodes = $this->http->XPath->query(".//text()[normalize-space()='Air Transportation Charges'][1]/ancestor::tr[1]/following-sibling::tr", $flightRoot);

            if ($feeNodes->length == 0) {
                $feeNodes = $this->http->XPath->query(".//tr[not(.//tr)][{$this->starts($this->t('Invoice #'))}][1]/following-sibling::tr", $flightRoot);
            }

            unset($cost);

            foreach ($feeNodes as $feeRoot) {
                $feeName = $this->http->FindSingleNode("./descendant::td[1]", $feeRoot);
                $feeSumm = $this->http->FindSingleNode("./descendant::td[2]", $feeRoot, true, "/^\s*\D* ?(\d[\d\.\,]*)\s*$/");

                if ($this->http->XPath->query(".//text()[normalize-space()='Air Transportation Charges']", $flightRoot)->length === 0) {
                    if (preg_match('/^.* - .* \$ ?\d[\d,.]*\s*$/', $feeName)) {
                        $cost = isset($cost) ? $cost + $feeSumm : $feeSumm;

                        continue;
                    }
                } elseif (preg_match('/^.* : (?:.+ )?\(\d\)\s*.+\s*$/', $feeName)) {
                    $cost = isset($cost) ? $cost + $feeSumm : $feeSumm;

                    continue;
                }

                if (!empty($feeName) && $feeSumm !== null) {
                    $f->price()
                        ->fee($feeName, $feeSumm);
                }
            }

            if (isset($cost)) {
                $f->price()
                    ->cost($cost);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->Flight($email);

        if (empty($this->providerCode)) {
            foreach (self::$detectProviders as $code => $detects) {
                if ((!empty($detects['from']) && stripos($parser->getCleanFrom(), $detects['from']) !== false)
                    || (!empty($detects['subjectUniqueText']) && stripos($parser->getSubject(), $detects['subjectUniqueText']) !== false)
                    || (!empty($detects['bodyText']) && $this->http->XPath->query("//text()[{$this->contains($detects['bodyText'])}]")->length > 0)
                ) {
                    $this->providerCode = $code;
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailProviders()
    {
        return array_filter(array_keys(self::$detectProviders));
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'CAD' => ['$'],
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
}
