<?php

namespace AwardWallet\Engine\virgintrains\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "virgintrains/it-694496846.eml, virgintrains/it-694789361.eml, virgintrains/it-698154142.eml, virgintrains/it-699072751.eml, virgintrains/it-699425638.eml, virgintrains/it-699428153.eml, virgintrains/it-703007118.eml";

    public $detectFrom = "info@comms.virgintrainsticketing.com";
    public $detectSubject = [
        // en
        'Booking Confirmation: B-VIRGINTT-',
    ];
    public $detectBody = [
        'en' => [
            'You’re all set for your trip',
        ],
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Journey price' => ['Product Sale', 'Journey price'],
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]virgintrainsticketing\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], ' B-VIRGINTT-') === false
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
            $this->http->XPath->query("//a/@href[{$this->contains(['.virgintrainsticketing.com/'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Virgin Trains Ticketing Support Hub.', 'booking with Virgin Trains Ticketing'])}]")->length === 0
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
        // $this->assignLang();
        // if (empty($this->lang)) {
        //     $this->logger->debug("can't determine a language");
        //     return $email;
        // }
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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Seat Reservations"])
                && $this->http->XPath->query("//*[{$this->contains($dict['Seat Reservations'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $email->obtainTravelAgency();

        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(B-VIRGINTT-[A-Z\d]+)\s*$/");
        $email->ota()
            ->confirmation($conf, $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference'))}]"));
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your ticket collection number:'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*([A-Z\d]{5,})\s*$/");

        if (!empty($conf)) {
            $email->ota()
                ->confirmation($conf, trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your ticket collection number:'))}]"), ':'));
        }

        $t = $email->add()->train();

        // General
        $t->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hey '))}]",
                null, true, "/^\s*{$this->opt($this->t('Hey '))}\s*(\D+)\,\s*$/"), false)
        ;

        // Program
        $earn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('This trip has earned you:'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d[\d,. ]*\s*Virgin Points)/");

        if (!empty($earn)) {
            $t->program()
                ->earnedAwards($earn);
        }

        //Price
        $total = $this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t('Total paid:'))}]",
            null, true, "/{$this->opt($this->t('Total paid:'))}\s*(.+)/");

        if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $total, $m)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$/", $total, $m)
        ) {
            $currencySign = $m['currency'];
            $currency = $this->currency($m['currency']);

            $t->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
            $costs = $this->http->FindNodes("//td[not(.//td)][{$this->eq($this->t('Journey price'))}]/following-sibling::td[normalize-space()][1]",
                null, "/^\s*{$this->opt($currencySign)}?\s*(\d[\d,. ]*)\s*{$this->opt($currencySign)}?\s*$/");
            $cost = 0.0;

            foreach ($costs as $costStr) {
                $cost += PriceHelper::parse($costStr, $currency);
            }

            $t->price()
                ->cost($cost)
            ;

            $fee = $this->http->FindSingleNode("//td[not(.//td)][{$this->eq($this->t('Booking fee'))}]/following-sibling::td[normalize-space()][1]",
                null, true, "/^\s*{$this->opt($currencySign)}?\s*(\d[\d,. ]*)\s*{$this->opt($currencySign)}?\s*$/");

            if (!empty($fee)) {
                $t->price()
                    ->fee($this->http->FindSingleNode("//td[not(.//td)][{$this->eq($this->t('Booking fee'))}]"), PriceHelper::parse($fee, $currency));
            }
        }

        $spent = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Virgin Points discount'))}]",
            null, true, "/^\s*(\d[\d,. ]*\s+Virgin Points) discount\s*$/");

        if (!empty($spent)) {
            $t->price()
                ->spentAwards($spent);
        }

        // Segments
        $timeXpath = "starts-with(translate(normalize-space(.),'0123456789','dddddddddd'),'dd:dd')";
        $xpath = "//tr[descendant::text()[normalize-space()][1][{$timeXpath}]][count(.//text()[{$timeXpath}]) = 1][count(.//img) > 1][following-sibling::*[2][descendant::text()[normalize-space()][1][{$timeXpath}]]]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $depName = $arrName = null;
            $s = $t->addSegment();

            $date = $this->http->FindSingleNode("ancestor::*[1]/preceding::text()[normalize-space()][not(ancestor::*/@style[{$this->contains(['background-color:#e5efff', 'background-color: #e5efff'])}])][1]/ancestor::*[count(.//text()[normalize-space()]) > 1][1]/descendant::text()[normalize-space()][1]",
                $root, true, "/^\s*(?:[[:alpha:]]+ ?:)?(.*\d.*)/");

            if (empty($date)) {
                $date = $this->http->FindSingleNode("ancestor::*[1]/preceding::text()[normalize-space()][not(ancestor::*/@style[{$this->contains(['background-color:#e5efff', 'background-color: #e5efff'])}])][1]/ancestor::*[count(.//text()[normalize-space()]) > 1][1]/descendant::text()[normalize-space()][2]",
                    $root, true, "/.*\d.*/");
            }
            $date = $this->normalizeDate($date);

            // Departure
            $depart = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<time>\d{2}:\d{2}(?:\s*[ap]m)?)\s+(?<name>.+?)\s*$/", $depart, $m)) {
                $depName = $m['name'];
                $s->departure()
                    ->date($date ? strtotime($m['time'], $date) : null)
                    ->name($m['name'] . ', United Kingdom')
                    ->geoTip('uk')
                ;
            }
            $info = implode("\n", $this->http->FindNodes("following-sibling::*[1]//text()[normalize-space()]", $root));
            // Arrival
            $arrive = implode("\n", $this->http->FindNodes("following-sibling::*[2]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<time>\d{2}:\d{2}(?:\s*[ap]m)?)\s+(?<name>.+)\s*$/", $arrive, $m)) {
                $arrName = $m['name'];
                $s->arrival()
                    ->date($date ? strtotime($m['time'], $date) : null)
                    ->name($m['name'] . ', United Kingdom')
                    ->geoTip('uk')
                ;
            }

            // Extra
            $s->extra()
                ->noNumber();

            if (!empty($depName) && !empty($arrName)) {
                $route = $depName . ' to ' . $arrName;
                $seatsTexts = implode("\n", $this->http->FindNodes("//text()[{$this->eq($route)}]/following::text()[normalize-space()][1]/ancestor::*[not({$this->contains($route)})][last()]//text()[normalize-space()]"));

                if (preg_match_all("/\b{$this->opt($this->t('Coach'))}\s*([A-Z\d]{1,4})\s*(?:,|\n|$)/", $seatsTexts, $m)) {
                    $s->extra()
                        ->car(implode(', ', array_unique($m[1])));
                }

                if (preg_match_all("/\b{$this->opt($this->t('Seat'))}\s*([A-Z\d]{1,4})\s*(?:,|\n|$)/", $seatsTexts, $m)) {
                    $s->extra()
                        ->seats($m[1]);
                }
            }
        }

        return true;
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
        if (empty($date)) {
            return null;
        }

        $in = [
            // Sun 7 Jul 2024
            '/^\s*[[:alpha:]]+\s+(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*$/ui',
        ];
        $out = [
            '$1 $2 $3',
        ];

        $date = preg_replace($in, $out, $date);

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
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }

    private function currency($s)
    {
        $s = trim($s);

        if (preg_match("/^\s*([A-Z]{3})\s*$/", $s, $m)) {
            return $s;
        }
        $sym = [
            '€'=> 'EUR',
            '£'=> 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
