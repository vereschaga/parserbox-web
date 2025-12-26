<?php

namespace AwardWallet\Engine\reurope\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ProductDetails extends \TAccountChecker
{
    public $mailFiles = "reurope/it-107383365.eml, reurope/it-143178331.eml, reurope/it-321909454.eml, reurope/it-653403366.eml"; // +2 bcdtravel(html)[en]

    private $detectSubjects = [
        // en
        'Your Rail Europe confirmation: Booking reference',
    ];

    private $lang = '';
    private static $dictionary = [
        'en' => [
            'Product details' => ['Product details', 'New Trip Summary'],
            'Travelers:'      => ['Travelers:', 'Traveler(s):'],
            'Total'           => ['Total', 'TOTAL PRICE'],
            'Trip duration:'  => 'Trip duration:',
            'Segment '        => 'Segment ',
            'seats'           => ['seats', 'seat(s) :'],
            'Outbound:'       => ['Outbound:', 'Outbound'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@raileurope.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && stripos($headers['subject'], 'Rail Europe') === false) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".raileurope.com")]')->length === 0
            && $this->http->XPath->query('//text()[contains(normalize-space(),"Rail Europe")]')->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseEmail($email);

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

    private function parseEmail(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();

        $confirmation = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Booking reference:')) . ']/following::text()[normalize-space(.)][1]', null, true, '/^\s*([A-Z\d]{5,})\s*$/');

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Booking reference:')) . ']',
                null, true, '/' . $this->opt($this->t('Booking reference:')) . '\s*([A-Z\d]{5,})\s*$/');
        }

        if (!empty($confirmation)) {
            $email->ota()->confirmation($confirmation);
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('TOTAL PRICE')) . "]/ancestor::*[self::td or self::th][" . $this->starts($this->t('TOTAL PRICE')) . "]/following-sibling::*[1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $email->price()
                ->total(PriceHelper::cost($m['amount']))
                ->currency($this->normalizeCurrency($m['curr']))
            ;

            // p.fees
            $feeRows = $this->http->XPath->query("//text()[{$this->eq($this->t('TOTAL PRICE'))}]/preceding::text()[contains(normalize-space(), 'Booking') and contains(normalize-space(), 'fee')]/ancestor::tr[2]");

            foreach ($feeRows as $feeRow) {
                $feeName = $this->http->FindSingleNode('./td[normalize-space(.)][1]/descendant::text()[normalize-space()][1]', $feeRow);
                $feeChargeTexts = $this->http->FindNodes('./td[normalize-space(.)][last()]/descendant::text()[normalize-space(.)]', $feeRow);
                $feeChargeText = implode(' ', $feeChargeTexts);

                if (preg_match('/^' . preg_quote($m['curr'], '/') . '\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1.$2', $feeChargeText), $m)) {
                    $email->price()->fee($feeName, $this->normalizeAmount($m['amount']));
                }
            }
        }

        $xpath = "//text()[{$this->eq($this->t('Trip duration:'))}]/ancestor::*/following-sibling::*[{$this->starts($this->t('Segment '))}]";
        $trains = $this->http->XPath->query($xpath);

        if ($trains->length === 0) {
            $this->logger->debug('Trains not found!');
        } else {
            $this->logger->debug('Trains count: ' . $trains->length);
            $this->logger->debug('Trains [xpath]: ' . $xpath);
        }

        foreach ($trains as $key => $root) {
            $t = $email->add()->train();

            // General

            $confText = $this->http->FindSingleNode("preceding::*[self::td or self::th][contains(., '>')][1]/following::*[self::td or self::th][not(.//td)][1][starts-with(normalize-space(), 'PNR')]",
                $root);

            if (preg_match_all("/([A-z\d\-]{5,})/", $confText, $m)) {
                if (isset($m[1][$key]) && !empty($m[1][$key])) {
                    $t->general()
                        ->confirmation($m[1][$key]);
                } else {
                    $t->general()
                        ->confirmation($m[1][0]);
                }
            }

            $travellers = [];
            $travellerText = implode("\n", $this->http->FindNodes("preceding-sibling::*[{$this->contains($this->t('Travelers:'))}]/descendant-or-self::tr[*[normalize-space()][1][{$this->contains($this->t('Travelers:'))}] and *[normalize-space()][2]]/*[2]//text()[normalize-space()]", $root));

            if (preg_match("/^([[:alpha:]\- ,\']+(?:\s*,\s*[[:alpha:]\- ,]+)*)(?:\n|$)/", $travellerText, $m)) {
                $travellers = preg_split("/\s*,\s*/", $m[1]);
            }

            $t->general()
                ->travellers($travellers, true)
            ;

            // Price
            $route = $this->http->FindSingleNode("preceding::*[self::td or self::th][contains(., '>')][1]", $root, true, "/^\s*(?:\([^)(]+\)\s*)?(.+)/");

            if (!empty($route)) {
                $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('TOTAL PRICE')) . "]/preceding::tr[count(*[normalize-space()]) = 2 and *[1][" . $this->eq($route) . "]]/*[2]");

                if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
                    $t->price()
                        ->total(PriceHelper::cost($m['amount']))
                        ->currency($this->normalizeCurrency($m['curr']))
                    ;
                }

                if (empty($confText)) {
                    $confText = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Billing information')) . "]/preceding::tr[count(*[normalize-space()]) = 2 and *[1][" . $this->eq($route) . "]][following::*[self::td or self::th][not(.//td) and not(.//th)][5][" . $this->eq($this->t("Ticket references:")) . "]]/following::*[self::td or self::th][not(.//td) and not(.//th)][6]",
                        null, true, "/^\s*([A-Z\d]{5,})\s*$/");
                    $t->general()
                        ->confirmation($confText);
                }
            }

            $date = strtotime($this->http->FindSingleNode("(./preceding-sibling::*[normalize-space()][2]/descendant::text()[normalize-space()])[2]",
                $root, true, "/(.+) - \d{1,2}:\d{2}/"));

            if (empty($date)) {
                $date = strtotime($this->http->FindSingleNode("./preceding::text()[{$this->eq($this->t('Outbound:'))}][1]/following::text()[normalize-space()][1]",
                    $root, true, "/(.+) - \d{1,2}:\d{2}/"));
            }

            $segmentRows = $this->http->FindNodes(".//text()[normalize-space()]", $root);

            if (count($segmentRows) == 3) {
                $segmentRows = array_merge($segmentRows, $this->http->FindNodes("following-sibling::*//text()[normalize-space()]", $root));
            }
            $segmentText = implode("\n", $segmentRows);

            $s = $t->addSegment();

            $regexp = "/^[^:]+:\s*\n\s*(?<dTime>\d{1,2}:\d{2}.+)\n(?<dName>.+)\n(?<aTime>\d{1,2}:\d{2}.+)\n(?<aName>.+)\n(?<service>[\w\s\-\(\)]+?)?\s*(?<number>[\dA-Z]{1,6}|Connections)?\s*,\s+(?<cabin>.+)/";

            if (preg_match($regexp, $segmentText, $m)) {
                // Departure
                $s->departure()
                    ->name($m['dName'] . ', Europe') //because geoTag don't work
                    ->geoTip('Europe')
                    ->date(!empty($date) ? strtotime($m['dTime'], $date) : null)
                ;
                // Arrival
                $s->arrival()
                    ->name($m['aName'] . ', Europe') //because geoTag don't work
                    ->geoTip('Europe')
                    ->date(!empty($date) ? strtotime($m['aTime'], $date) : null)
                ;

                // Extra
                if (!empty($m['service']) && !empty($m['cabin'])) {
                    $s->extra()
                        ->service($m['service'])
                        ->cabin(trim(preg_replace("/\s*" . $this->opt($this->t('class')) . "\s*/i", ' ', $m['cabin'])))
                    ;
                }

                if ($m['number'] == 'Connections' || empty($m['number'])) {
                    $s->extra()
                        ->noNumber();
                } else {
                    $s->extra()
                        ->number($m['number']);
                }
            }

            if (preg_match("/\s*{$this->opt($this->t("Coach"))}\s*(?<car>\w+)\s*\,?\s*\n*{$this->opt($this->t("seats"))}(?<seats>[\d\,\sA-Z]+)/", $segmentText, $m)) {
                $s->extra()
                    ->car($m['car'])
                    ->seats(array_map('trim', explode(',', $m['seats'])))
                ;
            }
            /*
             *
                        if (!empty($startTrip) && !empty($endTrip)) {
                            $priceTexts = $this->http->FindNodes("//text()[starts-with(normalize-space(),'{$startTrip}') and ./following::text()[normalize-space()!=''][1][contains(.,'{$endTrip}')]]/ancestor::table[starts-with(normalize-space(),'{$startTrip}')][1]/ancestor::td[./following-sibling::td][1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]");
                            $price = implode(' ', $priceTexts);

                            if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $price), $matches)) {
                                // €59 90
                                $t->price()
                                    ->currency($this->normalizeCurrency($matches['currency']))
                                    ->total($this->normalizeAmount($matches['amount']));
                            }
                        }
                        */
        }

        // p.currencyCode
        // p.total
        $totalPaymentTexts = $this->http->FindNodes('//text()[' . $this->eq($this->t('Total')) . ']/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)]');
        $totalPaymentText = implode(' ', $totalPaymentTexts);
        // $610    |    $361 95

        if (preg_match('/^(?<currency>\D+)\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1,$2', $totalPaymentText), $matches)) {
            $email->price()
                ->currency($this->normalizeCurrency($matches['currency']))
                ->total($this->normalizeAmount($matches['amount']));
            // p.fees
            $feeRows = $this->http->XPath->query('//text()[' . $this->eq($this->t('Total')) . ']/ancestor::table[ ./preceding-sibling::table[normalize-space(.)] ][1]/preceding-sibling::table[normalize-space(.)][1]/descendant::tr[ not(.//tr) and ./td[normalize-space(.)][2] ]');

            foreach ($feeRows as $feeRow) {
                $feeName = $this->http->FindSingleNode('./td[normalize-space(.)][1]', $feeRow);
                $feeChargeTexts = $this->http->FindNodes('./td[normalize-space(.)][last()]/descendant::text()[normalize-space(.)]', $feeRow);
                $feeChargeText = implode(' ', $feeChargeTexts);

                if (preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d]*)/', preg_replace('/(\d)\s+(\d)/', '$1.$2', $feeChargeText), $m)) {
                    $email->price()->fee($feeName, $this->normalizeAmount($m['amount']));
                }
            }
        }
    }

    private function assignLang(): bool
    {
        $bg = ["background:rgb(229,250,255)", 'background:#E5FAFF', 'background-color:rgb(229,250,255)'];

        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['Product details'])) {
                if ($this->http->XPath->query("//*[self::td or self::th or self::p][" . $this->eq($dict['Product details']) . "]/following::*[self::td or self::th][not(.//td) and not(.//th)][not(ancestor::*[{$this->contains($bg, '@style')}])][normalize-space()][position() < 3][contains(., '>')]")->length > 0
                    || $this->http->XPath->query("//*[self::td or self::th][" . $this->eq($dict['Product details']) . "]/following::*[normalize-space()][1][.//img[contains(@src, 'info-icon.png')]]/following::*[self::td or self::th][not(.//td) and not(.//th)][normalize-space()][position() < 3][contains(., '>')]")->length > 0
                    || $this->http->XPath->query("//text()[" . $this->eq($dict['Product details']) . "]/following::img[contains(@src, 'ellipse-1.png')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }

            if (!empty($dict['Trip duration:']) && !empty($dict['Segment '])) {
                if ($this->http->XPath->query("//text()[{$this->eq($dict['Trip duration:'])}]/ancestor::*/following-sibling::*[{$this->starts($dict['Segment '])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($phrase)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
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

    private function normalizeDate(string $string)
    {
        $string = preg_replace('/^\s*(\d{1,2}\/\d{1,2}\/)(\d{2})\s*$/', '${1}' . '20$2', $string);

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $m)) { // 08/21/2018
            if ($this->usDateFormat === true || (int) $m[2] > 12) {
                return $m[2] . '.' . $m[1] . '.' . $m[3];
            } else {
                return str_replace('/', '.', $string); // 31/03/2018
            }
        }

        return $string;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
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
