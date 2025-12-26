<?php

namespace AwardWallet\Engine\cheapflightsfares\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight2 extends \TAccountChecker
{
	public $mailFiles = "cheapflightsfares/it-841285586.eml, cheapflightsfares/it-842274015.eml";
    public $subjects = [
        'E-ticket Confirmation From',
        'E-Ticket Confirmation From'
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Flight Price' => ['Flight Price', 'Total Flight Price'],
            'Discount' => ['Discount', 'Price Lock', 'Coupon Amount'],
            'Total Amount' => ['Total Amount', 'Total charge equivalent in USD'],
        ],
    ];

    private $providerCode;
    private static $detectsProvider = [
        'cheapflightsfares' => [
            'from'           => 'cheapflightsfares.com',
            'bodyHtml'       => [
                'Greetings from Cheapflightsfares',
                'Thank you for choosing Cheapflightsfares',
                'Greetings from faresonfleek',
                'Greetings from Lookbyfare',
                'Greetings from Lookupfare'
            ],
        ],
        'travelopick' => [
            'from'           => 'travelopick.com',
            'bodyHtml'       => [
                'Greetings from Travelopick',
                'Greetings from TraveloPick',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectsProvider as $code => $detect) {
            if (isset($headers['from']) && stripos($headers['from'], $detect['from']) !== false) {
                $this->providerCode = $code;

                foreach ($this->subjects as $subject) {
                    if (stripos($headers['subject'], $subject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$detectsProvider as $code => $detect) {
            $detectedProvider = false;
            if (!empty($detect['bodyHtml'])) {
                foreach ($detect['bodyHtml'] as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && $this->http->XPath->query("//node()[{$this->contains($search)}]")->length > 0)
                    ) {
                        $this->providerCode = $code;
                        $detectedProvider = true;
                        break;
                    }
                }
            }

            if ($detectedProvider === false) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->contains($this->t('This is regarding your Bookings Reference Number'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Travelers Details'))}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectsProvider as $code => $detect) {
            if (strpos($from, $detect['from']) !== false){
                return true;
            };
        }
        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email): Email
    {
        if ($this->providerCode !== null){
            $email->setProviderCode($this->providerCode);
        }

        $this->Flight2($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight2(Email $email)
    {
        $f = $email->add()->flight();

        $f->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('This is regarding your Bookings Reference Number'))}]/following::text()[normalize-space()][1]", null, false, "/^([\dA-Z\-]+)$/"), $this->t('Booking Reference Number'));

        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Airline Confirmation Number(s)'))}]/following::text()[normalize-space()][1]", null, false, "/^([\dA-Z\-\/\s\,]+)$/");
        $confNumbers = preg_split("/\,/", $confirmationNumber);
        foreach ($confNumbers as $number){
            $airCode = null;

            preg_match_all("/\b([A-Z0-9]{2})[\/\s]+([A-Z0-9]{5,7})\b|\b([A-Z0-9]{5,7})\b/", $number, $m, PREG_SET_ORDER);

            foreach ($m as $match) {
                if (!empty($match[1])) {
                    $airCode = $match[1];
                    $confNumber = $match[2];
                } else {
                    $confNumber = $match[3];
                }
                $f->general()
                    ->confirmation($confNumber, $airCode);
            }
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Airline Confirmation Number(s)'))}]")->length == 0){
            $f->general()
                ->noConfirmation();
        }

        $travellersInfo = $this->http->XPath->query("//text()[{$this->eq($this->t('Travelers Details'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]/descendant::tr[normalize-space()][not({$this->contains($this->t('Ticket Number'))})]");

        foreach ($travellersInfo as $travellerNode){
            $f->addTraveller($travellerName = $this->http->FindSingleNode("./descendant::td[2]/descendant::text()[normalize-space()][1]", $travellerNode, false, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u"), true);

            $ticketNumber = $this->http->FindSingleNode("./descendant::td[3]/descendant::text()[normalize-space()][1]", $travellerNode, false, "/^([\d\-\/]+)$/");

            if ($ticketNumber !== null){
                foreach (preg_split("/\//", $ticketNumber) as $value){
                    $f->addTicketNumber($value, false, $travellerName);
                }
            }
        }

        $segmentNodes = $this->http->XPath->query("//img[contains(@src, '/stop2.png')]/ancestor::tr[1]");
        $segmentNum = 2;
        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::td[normalize-space()][1]/descendant::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][2]", $root, false, "/^([\dA-Z]{2})\s*\d{1,4}$/"))
                ->number($this->http->FindSingleNode("./descendant::td[normalize-space()][1]/descendant::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][2]", $root, false, "/^[\dA-Z]{2}\s*(\d{1,4})$/"));

            $depDate = $this->http->FindSingleNode("./descendant::td[normalize-space()][4]/descendant::text()[normalize-space()][1]", $root, false, "/^(\w+\,\s*[\d\w]+\s*[\d\w]+\,\s*\d{4})$/");

            $depTime = $this->http->FindSingleNode("./descendant::td[normalize-space()][4]/descendant::text()[normalize-space()][2]", $root, false, "/^(\d{1,2}\:\d{2}\s*[Aa]?[Pp]?[Mm]?)$/");

            if ($depDate !== null && $depTime !== null){
                $s->departure()
                    ->date($this->normalizeDate($depDate . ' ' . $depTime));
            }

            $arrDate = $this->http->FindSingleNode("./descendant::td[normalize-space()][5]/descendant::text()[normalize-space()][1]", $root, false, "/^(\w+\,\s*[\d\w]+\s*[\d\w]+\,\s*\d{4})$/");

            $arrTime = $this->http->FindSingleNode("./descendant::td[normalize-space()][5]/descendant::text()[normalize-space()][2]", $root, false, "/^(\d{1,2}\:\d{2}\s*[Aa]?[Pp]?[Mm]?)$/");

            if ($arrDate !== null &&  $arrTime !== null){
                $s->arrival()
                    ->date($this->normalizeDate($arrDate . ' ' . $arrTime));
            }

            $depCity = preg_replace("/[\(\)]/", '', $this->http->FindSingleNode("./descendant::td[normalize-space()][4]/descendant::text()[normalize-space()][4]", $root));
            $depAirport = $this->http->FindSingleNode("./descendant::td[normalize-space()][4]/descendant::text()[normalize-space()][5]", $root);

            if ($depCity !== null && $depAirport !== null) {
                $s->departure()
                    ->name($depAirport . ', ' . $depCity);
            }

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::td[normalize-space()][4]/descendant::text()[normalize-space()][3]", $root, null, "/^([A-Z]{3})$/"));

            $arrCity = preg_replace("/[\(\)]/", '', $this->http->FindSingleNode("./descendant::td[normalize-space()][5]/descendant::text()[normalize-space()][4]", $root));
            $arrAirport = $this->http->FindSingleNode("./descendant::td[normalize-space()][5]/descendant::text()[normalize-space()][5]", $root);

            if ($arrCity !== null && $arrAirport !== null) {
                $s->arrival()
                    ->name($arrAirport . ', ' . $arrCity);
            }

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::td[normalize-space()][5]/descendant::text()[normalize-space()][3]", $root, null, "/^([A-Z]{3})$/"));

            $depTerminal = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][descendant::table][4]/descendant::table[2]/descendant::tr[1]/descendant::td[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root, false, "/^{$this->t('Terminal')}\s+(?!NA)(.+)$/");

            if ($depTerminal !== null){
                $s->departure()
                    ->terminal($depTerminal);
            }

            $arrTerminal = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][descendant::table][4]/descendant::table[2]/descendant::tr[1]/descendant::td[normalize-space()][2]/descendant::text()[normalize-space()][2]", $root, false, "/^{$this->t('Terminal')}\s+(?!NA)(.+)$/");

            if ($arrTerminal !== null){
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $s->extra()
                ->cabin($this->http->FindSingleNode("./descendant::td[normalize-space()][1]/descendant::tr[2]/descendant::text()[normalize-space()][2]", $root, false, "/^\-?(.+)$/"));

            $seatsInfo = $this->http->XPath->query("//tr[normalize-space() = 'Seat Assignment Details']/following-sibling::tr[normalize-space()][1]/descendant::tr[not(contains(normalize-space(), 'Traveler'))]");

            foreach ($seatsInfo as $seat){
                $travellerName = $this->http->FindSingleNode("./descendant::td[normalize-space()][1]", $seat, false, "/^\d+\.\s+([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/");

                $seatNumber = $this->http->FindSingleNode("./descendant::td[normalize-space()][$segmentNum]/descendant::text()[normalize-space()][1]", $seat, false, "/^([0-9]+\s*[A-Z])$/");

                if ($travellerName !== null && $seatNumber !== null){
                    $s->extra()
                        ->seat($seatNumber, false, false, $travellerName);
                }
            }

            $segmentNum++;
        }

        if ($priceInfo = $this->http->FindSingleNode("//td[{$this->contains($this->t('Total charge equivalent in'))}]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", null, true, "/^(\D{1,3}\s*\d[\d\.\,\`]*(?:\s*\D{1,3})?)$/")){
            if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>\d[\d\.\,\']*)\s*(?<currency2>\D{1,3})?$/", $priceInfo, $m)
                || preg_match("/^(?<price>[\d\.\,\']+)\s*(?<currency>\D{1,3})$/", $priceInfo, $m)){

                if ($currencyText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total charge equivalent in'))}]", null, false, "/^{$this->t('Total charge equivalent in')}[ ]*(\D{1,3})[ ]*/")){
                    $currency = $this->normalizeCurrency($currencyText);
                } else if ($currencyText = $m['currency2'] !== null) {
                    $currency = $this->normalizeCurrency($currencyText);
                } else if ($m['currency'] !== null){
                    $currency = $this->normalizeCurrency($m['currency']);
                }

                $f->price()
                    ->total(PriceHelper::parse($m['price'], $currency))
                    ->currency($currency);
            }
        } else {
            $priceInfo = $this->http->FindSingleNode("//td[{$this->contains($this->t('Total Amount'))}]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", null, true, "/^(\D{1,3}\s*\d[\d\.\,\`]*(?:\s*\D{1,3})?)$/");

            $discountArray[] = 0;

            if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>\d[\d\.\,\']*)\s*(?<currency2>\D{1,3})?$/", $priceInfo, $m)
                || preg_match("/^(?<price>[\d\.\,\']+)\s*(?<currency>\D{1,3})$/", $priceInfo, $m)) {

                if ($currencyText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('All fares are quoted in'))}]", null, false, "/^{$this->t('All fares are quoted in')}[ ]*(\D{1,3})[ ]*/")){
                    $currency = $this->normalizeCurrency($currencyText);
                } else if ($currencyText = $m['currency2'] !== null) {
                    $currency = $this->normalizeCurrency($currencyText);
                } else if ($m['currency'] !== null){
                    $currency = $this->normalizeCurrency($m['currency']);
                }

                $f->price()
                    ->total(PriceHelper::parse($m['price'], $currency))
                    ->currency($currency);

                $taxes = $this->http->XPath->query("//tr[preceding-sibling::tr[{$this->starts($this->t('Flight Price'))}] and following-sibling::tr[{$this->starts($this->t('Total Amount'))}]]");

                if ($taxes !== null) {
                    foreach ($taxes as $root) {
                        $feeName = $this->http->FindSingleNode("./descendant::td[1]", $root);
                        $feeSum = $this->http->FindSingleNode("./descendant::td[2]", $root, true, '/^\-?\D{1,3}\s*(\d[\d\.\,\`]*)/');

                        if (preg_match("/{$this->opt($this->t('Discount'))}/", $feeName)){
                            $discountArray[] = PriceHelper::parse($feeSum, $currency);
                            continue;
                        }

                        else if ($feeName !== null && $feeSum !== null) {
                            $f->price()
                                ->fee($feeName, PriceHelper::parse($feeSum, $currency));
                        }
                    }
                }
            }

            if (array_sum($discountArray) !== 0){
                $f->price()
                    ->discount(array_sum($discountArray));
            }
        }
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectsProvider);
    }

    private function normalizeDate($str)
    {
        $in = [
            "/^\w+\,\s*(\d+)\s+(\w+)\s*\,\s*(\d{4})\s*(\d{1,2}\:\d{2}\s*[Aa]?[Pp]?[Mm]?)$/", // Fri, 17 January, 2025 06:30 AM
            "/^(\d+)\s+(\w+)\s*\,\s*(\d{4})\s*(\d{1,2}\:\d{2}\s*[Aa]?[Pp]?[Mm]?)$/", // 17 January, 2025 06:30 AM
        ];
        $out = [
            "$2 $1 $3 $4",
            "$2 $1 $3 $4",
        ];

        return strtotime(preg_replace($in, $out, $str));
    }


    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
                return str_replace(' ', '\s+', preg_quote($s, '/'));
            }, $field)) . ')';
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'RUB' => ['Руб.'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'CAD' => ['C$']
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
                return "normalize-space(.)=\"{$s}\"";
            }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
