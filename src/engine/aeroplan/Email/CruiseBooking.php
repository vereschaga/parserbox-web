<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class CruiseBooking extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-752685878.eml, aeroplan/it-847944160.eml, aeroplan/it-885849367.eml";

    public $detectSubject = [
        // en
        'Your Cruise Booking Number',
    ];
    public $emailSubject;
    public $lang;
    public $lastArrivalDate = '';
    public static $dictionary = [
        'en' => [
            'Sailing Information' => ['Sailing Information', 'Booking summary'],
            'Cruise Cost'         => ['Cruise Cost', 'Fare'],
            'Cruise Total'        => 'Cruise Total',
            'FeesName'            => ['NCCF', 'Taxes and Fees', 'Options'],
            'Cruise Line:'        => ['Cruise Line:', 'Vendor'],
            'Cruise Ship:'        => ['Cruise Ship:', 'Ship'],
            'Stateroom Category:' => ['Stateroom Category:', 'Category Name'],
            'Deck Name:'          => ['Deck Name:', 'Deck'],
            'Date of Birth'       => ['Date of Birth', 'Country of Citizenship'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.](?:aircanadavacations|vacancesaircanada)\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (
            stripos($headers["from"], 'noreply@aircanadavacations.com') === false
            && stripos($headers["from"], 'nepasrepondre@vacancesaircanada.com') === false
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
            $this->http->XPath->query("//a[{$this->contains(['.aircanada.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Air Canada Vacations'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Sailing Information']) && !empty($dict['Cruise Cost'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Sailing Information'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Cruise Cost'])}]")->length > 0
            ) {
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

        $this->emailSubject = $parser->getSubject();
        $this->parseHtmlCruise($email);

        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Round trip flight from'))}]/following::text()[{$this->eq($this->t('Total Booked Cost'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^\D{1,3}(?<total>[\d\.\,\']+)\s*(?<currency>[A-Z]{3})$/", $total, $m)) {
            $email->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Round trip flight from'))}]")->length > 0) {
            $this->parseHtmlFlight($email);
        }

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Sailing Information"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Sailing Information'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseHtmlCruise(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $email->ota()
            ->confirmation($this->re("/{$this->opt($this->t('Your Cruise Booking Number'))}\s*[\dA-Z]{5,} - (\d{5,})\s*$/", $this->emailSubject));

        // Cruises

        $cr = $email->add()->cruise();

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cruise Cost'))}]/following::tr[not(.//tr)][count(*) = 2][*[1][{$this->eq($this->t('Cruise Total'))}]]/*[2]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
            || preg_match("#^\s*\\$(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
            || preg_match("#^\D{1,3}(?<amount>[\d\.\,\']+)\s*(?<currency>[A-Z]{3})$#", $total, $m)
        ) {
            $cr->price()
                ->total(PriceHelper::parse($m['amount']))
                ->currency($m['currency']);
        }

        $feesNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Cruise Cost'))}]/following::tr[not(.//tr)][*[1][{$this->eq($this->t('FeesName'))}]]");

        foreach ($feesNodes as $fRoot) {
            $name = $this->http->FindSingleNode("*[1]", $fRoot);
            $value = $this->http->FindSingleNode("*[last()]", $fRoot);

            if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $value, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                || preg_match("#^\s*\\$(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $value, $m)
                || preg_match("#^\D{1,3}(?<amount>[\d\.\,\']+)\s*(?<currency>[A-Z]{3})$#", $value, $m)
            ) {
                $cr->price()
                    ->fee($name, PriceHelper::parse($m['amount']));
            }
        }

        // General
        $cr->general()
            ->confirmation($this->re("/{$this->opt($this->t('Your Cruise Booking Number'))}\s*([\dA-Z]{5,}) - \d{5,}\s*$/", $this->emailSubject))
            ->travellers(array_unique(preg_replace("/^\s*(Mr|Ms|Mrs|Miss|Mstr)\s+/", '',
                $this->http->FindNodes("//text()[{$this->starts($this->t('Date of Birth'))}]/preceding::text()[{$this->starts($this->t('Name'))}][1]/ancestor::td[1]",
                null, "/^\s*{$this->opt($this->t('Name'))}[\s:]+([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*(?:\(|$)/"))));

        $cr->details()
            ->description($this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t('Cruise Line:'))}]/preceding::text()[normalize-space()][1][preceding::text()[normalize-space()][1][{$this->eq($this->t('Sailing Information'))}]]")
            ?? $this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t('Cruise Line:'))}]/preceding::text()[normalize-space()='Booking summary']/following::text()[normalize-space()][1]"))
            ->ship($this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t('Cruise Ship:'))}]",
                null, true, "/{$this->opt($this->t('Cruise Ship:'))}\s*(.+)/"))
            ->room($this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t('Cabin Number:'))}]",
                null, true, "/{$this->opt($this->t('Cabin Number:'))}\s*(.+)/")
            ?? $this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t('Cabin'))}]",
                    null, true, "/{$this->opt($this->t('Cabin'))}\s*(.+)/"))
            ->deck($this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t('Deck Name:'))}]",
                null, true, "/{$this->opt($this->t('Deck Name:'))}\s*(?:{$this->opt($this->t('Deck'))}\s*)?(.+)/"), true, true)
            ->roomClass($this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t('Stateroom Category:'))}]",
                null, true, "/{$this->opt($this->t('Stateroom Category:'))}\s*(.+)/"))
        ;

        // Segments
        $xpath = "//tr[*[1][{$this->eq($this->t('Date'))}]][*[2][{$this->eq($this->t('Port'))}]]/ancestor::table[1]/tbody/*";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//tr[*[1][{$this->eq($this->t('Date'))}]][*[2][{$this->eq($this->t('Port'))}]]/ancestor::*/*[not({$this->starts($this->t('Date'))})]";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $date = $this->http->FindSingleNode("*[1]", $root);
            $name = $this->http->FindSingleNode("*[2]", $root);
            $time1 = $this->http->FindSingleNode("*[3]", $root);
            $time2 = $this->http->FindSingleNode("*[4]", $root);

            if ($time1 === '--' && $time2 === '--') {
                continue;
            }

            if (strpos($name, 'TRANSIT ') === 0) {
                continue;
            }

            $s = $cr->addSegment();

            $s->setName($name);

            if ($time1 !== '--') {
                $s->setAshore($this->normalizeDate($date . ', ' . $time1));
            }

            if ($time2 !== '--') {
                $s->setAboard($this->normalizeDate($date . ', ' . $time2));
            }
        }

        return true;
    }

    private function parseHtmlFlight(Email $email)
    {
        $f = $email->add()->flight();

        $year = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Balance Due')]/ancestor::tr[1]", null, true, "/\,\s*(\d{4})\s*\d+\:/");

        $f->general()
            ->noConfirmation()
            ->travellers(array_unique(preg_replace("/^\s*(Mr|Ms|Mrs|Miss|Mstr)\s+/", '',
                $this->http->FindNodes("//text()[{$this->starts($this->t('Date of Birth'))}]/preceding::text()[{$this->starts($this->t('Name'))}][1]/ancestor::td[1]",
                    null, "/^\s*{$this->opt($this->t('Name'))}[\s:]+([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])\s*(?:\(|$)/"))));

        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Round trip flight from'))}]/following::text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[last()]");

        if (preg_match("/^\D{1,3}(?<total>[\d\.\,\']+)\s*(?<currency>[A-Z]{3})$/", $total, $m)
            || preg_match("/^(?<currency>\D{1,3})(?<total>[\d\.\,\']+)$/", $total, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $paymentPath = "//text()[starts-with(normalize-space(), 'Payment Schedule')]/following::text()[{$this->starts($this->t('Round trip flight from'))}]";
            $cost = $this->http->FindSingleNode($paymentPath . "/following::text()[{$this->eq($this->t('Fare'))}]/ancestor::tr[1]/descendant::td[last()]", null, true, "/^\D{1,3}\s*([\d\.\,\']+)/");

            if ($cost !== null) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $tax = $this->http->FindSingleNode($paymentPath . "/following::text()[{$this->eq($this->t('Taxes'))}]/ancestor::tr[1]/descendant::td[last()]", null, true, "/^\D{1,3}\s*([\d\.\,\']+)/");

            if ($tax !== null) {
                $f->price()
                    ->tax(PriceHelper::parse($cost, $currency));
            }
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Departure']/ancestor::tr[1][not(contains(normalize-space(), 'Port'))]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flightInfo = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]/ancestor::tr[1]", $root);

            if (preg_match("/^(?<aName>.+)\s+(?<fNumber>\d{1,5})[\s|]+(?<cabin>.+)[\s|]+Booking Class\:\s+(?<bookingCode>[A-Z])[\s|]+operated by\s+(?<operator>.+)\s+Departure\s+(?<depTime>[\d\:]+\s*A?\.?P?\.?M\.?)[\s|]+(?<depName>.+)\s+\((?<depCode>[A-Z]{3})\)\s+Arrival\s+(?<arrTime>[\d\:]+\s*A?\.?P?\.?M\.?)[\s|]+(?<arrName>.+)\s+\((?<arrCode>[A-Z]{3})\)/i", $flightInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber'])
                    ->operator($m['operator']);

                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode']);

                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode']);

                $waitTime = $this->http->FindSingleNode("./preceding::tr[1]/descendant::td[normalize-space()][2]", $root, true, "/{$this->opt($this->t('Wait time'))}\s+(.+)/");

                if (empty($waitTime)) {
                    $this->lastArrivalDate = '';
                }

                if (!empty($this->lastArrivalDate)) {
                    $this->lastArrivalDate = strtotime('+' . $waitTime, $this->lastArrivalDate);
                    $depDate = date('d.m.Y', $this->lastArrivalDate); //current date after wait time
                    $s->departure()
                        ->date(strtotime($depDate . ', ' . $m['depTime']));
                } else {
                    $depDate = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]/preceding::tr[1]/descendant::td[2]", $root, true, "/^(\w+\,\s*\w+\s*\d+)/");
                    $s->departure()
                        ->date(strtotime($depDate . ' ' . $year . ', ' . $m['depTime']));
                }

                $arrDate = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]/preceding::tr[1]/descendant::td[4]", $root, true, "/^(\w+\,\s*\w+\s*\d+)/");

                if (!empty($arrDate)) {
                    $s->arrival()
                        ->date(strtotime($arrDate . ' ' . $year . ', ' . $m['arrTime']));
                    $this->lastArrivalDate = $s->getArrDate();
                } else {
                    $arrDate = strtotime($depDate . ', ' . $m['arrTime']);

                    if ($s->getDepDate() > $arrDate) {
                        $arrDate = strtotime('+1 day', $arrDate);
                    }
                    $s->arrival()
                        ->date($arrDate);
                }
            }
        }
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        $in = [
            // 29 août 2024
            '/^\s*(\d{1,2})\s+([[:alpha:]]+)\.?\s+(\d{4})\s*$/ui',
            // 9 août 2024  10:15 AM
            '/^\s*(\d{1,2})\s+([[:alpha:]]+)\.?\s+(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
            'CAD' => ['CA$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
