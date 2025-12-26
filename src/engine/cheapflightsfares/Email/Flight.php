<?php

namespace AwardWallet\Engine\cheapflightsfares\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
	public $mailFiles = "cheapflightsfares/it-822336572.eml, cheapflightsfares/it-823315157.eml, cheapflightsfares/it-828781015.eml, cheapflightsfares/it-830797764.eml, cheapflightsfares/it-831362203.eml, cheapflightsfares/it-831844836.eml";
    public $subjects = [
        'e-ticket Confirmation From',
        'Booking Reference number:',
        'E-Ticket Confirmation From',

    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'Flight Price' => ['Flight Price', 'Total Flight Price'],
            'Total Travel Cost' => ['Total Travel Cost', 'Pay Later'],
            'Discount' => ['Discount']
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
                'Greetings from TravelsnTickets',

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

           if ($this->http->XPath->query("//text()[{$this->contains($this->t('Booking Reference No'))}]")->length > 0
               && $this->http->XPath->query("//text()[{$this->contains($this->t('Traveler(s) Details'))}]")->length > 0) {
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

        $this->Flight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Reference No'))}]/following::text()[normalize-space()][1]", null, false, "/^([\dA-Z\-]+)$/"), $this->t('Booking Reference No'));

        $confNumbersArray  = [];

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
                $confNumbersArray[$confNumber][] = $airCode;
            }
        }

        foreach ($confNumbersArray as $confNum => $confCode){

            if (!empty(array_unique(array_filter($confCode)))){
                $f->general()
                    ->confirmation($confNum, implode(', ', array_unique(array_filter($confCode))));
            } else {
                $f->general()
                    ->confirmation($confNum);
            }

        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Airline Confirmation Number(s)'))}]")->length == 0){
            $f->general()
                ->noConfirmation();
        }

        $bookingDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Date:'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<date>[\w\d]+\s*[\d\w]+\,\s*\d{4})\s*\|\s*(?<time>\d{1,2}\:\d{2}\s*[Aa]?[Pp]?[Mm]?)/", $bookingDate, $m)){
            $f->general()
                ->date($this->normalizeDate($m['date'] . ' ' . $m['time']));
        }

        $travellersInfo = $this->http->XPath->query("//text()[{$this->eq($this->t('Traveler(s) Details'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::tr[not({$this->contains($this->t('Traveler(s) Name'))})][count(./td) > 1]");

        foreach ($travellersInfo as $travellerNode){
            $travellerName = $this->http->FindSingleNode("./descendant::td[1]/descendant::text()[normalize-space()][1]", $travellerNode, false, "/^\d+\.\s*([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u");

            if (preg_match("/{$this->t('Infant on Lap')}/", $this->http->FindSingleNode("./descendant::td[2]", $travellerNode))){
                $f->addInfant($travellerName, true);
            } else {
                $f->addTraveller($travellerName, true);
            }

            $ticketNumber = $this->http->FindSingleNode("./descendant::td[3]/descendant::text()[normalize-space()][1]", $travellerNode, false, "/^([\d\-\/]+)$/");

            if ($ticketNumber !== null){
                foreach (preg_split("/\//", $ticketNumber) as $value){
                    $f->addTicketNumber($value, false, $travellerName);
                }
            }
        }

        $segmentNodes = $this->http->XPath->query("//img[contains(@src, '/plane-stop.png')]/ancestor::tr[2]");

        foreach ($segmentNodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::td[descendant::tr][1]/descendant::tr[normalize-space()][1]", $root))
                ->number($this->http->FindSingleNode("./descendant::td[descendant::tr][1]/descendant::tr[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root, false, "/{$this->t('Flight')}\s*(\d{1,4})\s*(?:\||$)/"));

            $flightDate = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[normalize-space()][last()]/descendant::td[1]/descendant::text()[normalize-space()][1]", $root, false, "/^(?:{$this->t('Departure')}|{$this->t('Return')})?\s*(\w+\,\s*[\d\w]+\s*[\d\w]+\,\s*\d{4})$/");

            $depTime = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][descendant::table][4]/descendant::table[2]/descendant::tr[1]/descendant::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root, false, "/^(\d{1,2}\:\d{2}\s*[Aa]?[Pp]?[Mm]?)$/");

            if ($flightDate !== null && $depTime !== null){
                $s->departure()
                    ->date($this->normalizeDate($flightDate . ' ' . $depTime));
            }

            $arrTime = implode(" ", $this->http->FindNodes("./ancestor::tr[1]/descendant::td[normalize-space()][descendant::table][4]/descendant::table[2]/descendant::tr[1]/descendant::td[normalize-space()][2]/descendant::text()[normalize-space()][not({$this->contains($this->t('Terminal'))})]", $root));

            if (preg_match("/^\s*(?<nextDay>{$this->opt($this->t('Next Day'))})?\s*(?<time>\d{1,2}\:\d{2}\s*[Aa]?[Pp]?[Mm]?)$/", $arrTime, $m) && $flightDate !== null){
                $s->arrival()
                    ->date($this->normalizeDate($flightDate . ' ' . $m['time']) + (!empty($m['nextDay'])? 86400 : 0));
            }

            $depCity = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][descendant::table][4]/descendant::tr[normalize-space()][1]/descendant::td[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root);
            $depAirport = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][descendant::table][4]/descendant::tr[normalize-space()][2]/descendant::td[normalize-space()][1]", $root);

            if ($depCity !== null && $depAirport !== null) {
                $s->departure()
                    ->name($depAirport . ', ' . $depCity);
            }

            $s->departure()
                ->code($this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][descendant::table][4]/descendant::tr[normalize-space()][1]/descendant::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root, null, "/^([A-Z]{3})$/"));

            $arrCity = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][descendant::table][4]/descendant::tr[normalize-space()][1]/descendant::td[normalize-space()][2]/descendant::text()[normalize-space()][2]", $root);
            $arrAirport = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][descendant::table][4]/descendant::tr[normalize-space()][2]/descendant::td[normalize-space()][3]", $root);

            if ($arrCity !== null && $arrAirport !== null) {
                $s->arrival()
                    ->name($arrAirport . ', ' . $arrCity);
            }

            $s->arrival()
                ->code($this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][descendant::table][4]/descendant::tr[normalize-space()][1]/descendant::td[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root, null, "/^([A-Z]{3})$/"));

            $operatedBy = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[normalize-space()][1]", $root, false, "/{$this->t('Operated By')}\s*(.+)$/");

            if ($operatedBy !== null){
                $s->airline()
                    ->operator(preg_replace("/\s*(?:For|As|Dba)\b.*/", "", $operatedBy));
            }

            $depTerminal = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][descendant::table][4]/descendant::table[2]/descendant::tr[1]/descendant::td[1]/descendant::text()[normalize-space()][{$this->starts($this->t('Terminal'))}]", $root, false, "/^{$this->t('Terminal')}\s+(?!NA)(.+)$/");

            if ($depTerminal !== null){
                $s->departure()
                    ->terminal($depTerminal);
            }

            $arrTerminal = $this->http->FindSingleNode("./ancestor::tr[1]/descendant::td[normalize-space()][descendant::table][4]/descendant::table[2]/descendant::tr[1]/descendant::td[3]/descendant::text()[normalize-space()][{$this->starts($this->t('Terminal'))}]", $root, false, "/^{$this->t('Terminal')}\s+(?!NA)(.+)$/");

            if ($arrTerminal !== null){
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $durationInfo = $this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[normalize-space()][2]/descendant::td[1]/descendant::td[normalize-space()][2]", $root, false, "/^{$this->t('Duration')}\s*\:\s*(\d+\s*\D+\s*\d+\s*\D+)$/");

            if ($durationInfo !== null){
                $s->extra()
                    ->duration($durationInfo);
            }

            $seatsInfo = explode(", ", $this->http->FindSingleNode("./ancestor::tr[1]/descendant::text()[{$this->eq($this->t('Seats:'))}]/following::text()[normalize-space()][1]", $root, false, "/^[0-9]+\s*[A-Z].+/"));

            foreach ($seatsInfo as $seat){
                if (preg_match("/^([0-9]+\s*[A-Z])$/", $seat)){
                    $s->extra()
                        ->seat($seat);
                }
            }

            $aircraftInfo = $this->http->FindSingleNode("./descendant::td[descendant::tr][1]/descendant::tr[normalize-space()][2]/descendant::text()[normalize-space()][2]", $root, false, "/{$this->t('Aircraft')}\s*([\d\D\s]+)$/");

            if ($aircraftInfo !== null){
                $s->extra()
                    ->aircraft($aircraftInfo);
            }

            $s->extra()
                ->cabin($this->http->FindSingleNode("./descendant::td[descendant::tr][1]/descendant::tr[normalize-space()][3]/descendant::text()[normalize-space()][2]", $root));
        }

        $priceInfo = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total Travel Cost'))}]/following-sibling::td[normalize-space()][1]", null, true, "/^(\D{1,3}\s*\d[\d\.\,\`]*)$/");

        $discountArray[] = 0;

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<price>\d[\d\.\,\']*)$/", $priceInfo, $m)
            || preg_match("/^(?<price>[\d\.\,\']+)\s*(?<currency>\D{1,3})$/", $priceInfo, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $f->price()
                ->currency($currency);

            $taxes = $this->http->XPath->query("//tr[preceding-sibling::tr[{$this->starts($this->t('Flight Price'))}] and following-sibling::tr[{$this->starts($this->t('Total Travel Cost'))}]]");

            if ($taxes !== null) {
                foreach ($taxes as $root) {
                    $feeName = $this->http->FindSingleNode("./descendant::td[1]", $root);
                    $feeSum = $this->http->FindSingleNode("./descendant::td[2]", $root, true, '/^\-?\D{1,3}\s*(\d[\d\.\,\`]*)/');

                    if (preg_match("/{$this->opt($this->t('Discount'))}/", $feeName)){
                        $discountArray[] = PriceHelper::parse($feeSum, $currency);
                        continue;
                    }

                    if (preg_match("/{$this->t('Price Lock')}/", $feeName)){
                        $priceLock = PriceHelper::parse($feeSum, $currency);
                        continue;
                    }

                    else if ($feeName !== null && $feeSum !== null) {
                        $f->price()
                            ->fee($feeName, PriceHelper::parse($feeSum, $currency));
                    }
                }
            }

            if (isset($priceLock)){
                $f->price()
                    ->total(PriceHelper::parse($m['price'] + $priceLock, $currency));
            } else {
                $f->price()
                    ->total(PriceHelper::parse($m['price'], $currency));
            }
        }

        if (array_sum($discountArray) !== 0){
            $f->price()
                ->discount(array_sum($discountArray));
        }
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectsProvider);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
