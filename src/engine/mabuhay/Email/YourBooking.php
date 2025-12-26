<?php

namespace AwardWallet\Engine\mabuhay\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourBooking extends \TAccountChecker
{
    public $mailFiles = "mabuhay/it-675302517.eml, mabuhay/it-675547661.eml, mabuhay/it-678357594.eml, mabuhay/it-803148955-vi-bambooair.eml, mabuhay/it-847593202.eml";
    public $subjects = [
        'your Booking',
        'Your order',
    ];

    public $providerCode = '';
    public static $detectProvider = [
        'mabuhay' => [
            'from'    => '@philippineairlines.com',
            'subject' => [
                'your Booking',
                'Your order',
            ],
            'detectBodyLink'    => 'philippineairlines.com',
            'detectBodyImgLink' => '&dcxorg=PR&', // logo img
        ],
        'china' => [
            'from'    => 'cal-reservation@amadeus.com',
            'subject' => [
                'China Airlines Reservation Record',
            ],
            'detectBodyLink'    => '.china-airlines.com',
            'detectBodyImgLink' => '&dcxorg=CI&', // logo img
        ],
        'vietnam' => [
            'from'    => '@vietnamairlines.com', //no-reply@service.vietnamairlines.com>
            'subject' => [
                'Your order',
                'Vietnam Airlines - Rebooking Confirmation',
            ],
            'detectBodyLink'    => '.vietnamairlines.com',
            'detectBodyImgLink' => '&dcxorg=VN&', // logo img
        ],
        'bambooair' => [
            'from'    => '@bambooairways.com', // no-reply@bambooairways.com>
            'subject' => [
                'Your order',
            ],
            // 'detectBodyLink' => '',
            'detectBodyImgLink' => '&dcxorg=QH&', // logo img
        ],
    ];
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Your booking reference is' => ['Your booking reference is', 'Reservation number', 'Reservation Number', 'Booking reference', 'Booking Reference', 'Booking reference does not change', 'Your booking reference does not change'],
            'Your Flights'              => ['Your Flights', 'Flight details', 'Flight Summary'],
            // 'Duration:' => '',
            'Itinerary Details' => ['Itinerary Details', 'Your itinerary', 'Iternary Details'],
            // 'Stop' => '',
            // 'Terminal' => '',
            'Operated By'       => ['Operated By', 'Operated by'],
            'Passenger Details' => ['Passenger Details', 'Passengers and contact', 'Passenger(s)', 'Passengers and Contact details'],
            'passengerType'     => ['Adult', 'RFXPBCNFPR.person with disability', 'person with disability', 'RFXPBCNFPR', 'Child'],
            'Frequent flyer:'   => ['Frequent flyer:', 'Frequent Flyer number:'],
            // 'seats selected' => '',
            // 'to' => '',
            // 'Total Price:' => '',
            'Air Transportation Charges' => ['Air Transportation Charges', 'Base fare'],
            // 'Surcharges' => '',
            // 'Taxes Fees and Charges' => '',
            'extraFees' => ['myPAL Travel Boost', 'Extra Services', 'Additional services'], // after Taxes Fees and Charges
        ],
        "zh" => [
            'Your booking reference is'  => '訂位代號：',
            'Your Flights'               => '您的航班',
            'Duration:'                  => '總時數:',
            'Itinerary Details'          => '航班详细',
            // 'Stop' => '',
            'Terminal'                   => '航廈',
            'Operated By'                => '實際承運',
            'Passenger Details'          => '乘客及聯繫方式',
            // 'passengerType' => [''],
            'Frequent flyer:'            => '會員卡號:',
            'seats selected'             => '選擇座位',
            'to'                         => '至',
            'Total Price:'               => '總價:',
            'Air Transportation Charges' => '基本票價',
            'Surcharges'                 => '附加費',
            'Taxes Fees and Charges'     => '稅費和收費',
            // 'extraFees' => '', // after Taxes Fees and Charges
        ],
        "vi" => [
            'Your booking reference is'  => 'Mã đặt chỗ của quý khách là',
            'Your Flights'               => 'Các chuyến bay của quý khách',
            'Duration:'                  => 'Thời hạn:',
            'Itinerary Details'          => 'Thông tin hành trình',
            // 'Stop' => '',
            'Terminal'                   => 'Nhà ga',
            'Operated By'                => 'Khai thác từ',
            'Passenger Details'          => 'Hành khách và thông tin liên hệ',
            // 'passengerType' => [''],
            // 'Frequent flyer:' => '',
            // 'seats selected' => '',
            'to'                         => 'đến',
            'Total Price:'               => 'Giá tổng:',
            'Air Transportation Charges' => 'Phí vận chuyển hàng không',
            'Surcharges'                 => 'Các phụ phí',
            'Taxes Fees and Charges'     => 'Thuế phí và phụ phí',
            // 'extraFees' => '', // after Taxes Fees and Charges
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers['subject'])) {
            return false;
        }

        foreach (self::$detectProvider as $detect) {
            if (empty($detect['from']) || empty($detect['subject']) || stripos($headers['from'], $detect['from']) === false) {
                continue;
            }

            foreach ($detect['subject'] as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$detectProvider as $detect) {
            if ((empty($detect['detectBodyLink'])
                || $this->http->XPath->query("//a/@href[{$this->contains($detect['detectBodyLink'])}]")->length == 0)
                && (empty($detect['detectBodyImgLink'])
                    || $this->http->XPath->query("//img/@src[{$this->contains($detect['detectBodyImgLink'])}]")->length == 0)
            ) {
                continue;
            }

            foreach (self::$dictionary as $dict) {
                if (!empty($dict['Your Flights']) && !empty($dict['Passenger Details'])
                    && $this->http->XPath->query("//text()[{$this->contains($dict['Your Flights'])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($dict['Passenger Details'])}]")->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]philippineairlines\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$detectProvider as $code => $detect) {
            if (!empty($detect['from']) && stripos($parser->getSubject(), $detect['from']) !== false) {
                $this->providerCode = $code;

                break;
            }

            if (!empty($detect['detectBodyLink']) && $this->http->XPath->query("//a/@href[{$this->contains($detect['detectBodyLink'])}]")->length > 0) {
                $this->providerCode = $code;

                break;
            }

            if (!empty($detect['detectBodyImgLink']) && $this->http->XPath->query("//img/@src[{$this->contains($detect['detectBodyImgLink'])}]")->length > 0) {
                $this->providerCode = $code;

                break;
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your Flights']) && !empty($dict['Passenger Details'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Your Flights'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Passenger Details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function niceTravellers($name)
    {
        return preg_replace("/^\s*(Mr|Ms|Mstr|Miss|Mrs)\s+/", '', $name);
    }

    public function ParseFlight(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM  |  2:00 p. m.
        ];

        $f = $email->add()->flight();

        // General
        $travellers = $this->niceTravellers(array_filter(
            $this->http->FindNodes("//tr[{$this->eq($this->t('Passenger Details'))}]/following-sibling::tr[normalize-space()]/descendant::text()[normalize-space()][1]"
                . "[ancestor::*[contains(@style, 'bold')] or ancestor::b][not(contains(., '@'))][not({$this->contains($this->t('All Passengers'))})]")));

        if (count(array_filter($travellers)) === 0) {
            $travellers = $this->niceTravellers($this->http->FindNodes("//tr[descendant::text()[normalize-space()][2][{$this->starts($this->t('Ticket Number:'))}]]/preceding-sibling::tr[1]"));
        }

        if (count(array_filter($travellers)) === 0 && $this->http->XPath->query("//text()[{$this->eq($this->t('passengerType'))}]")->length > 0) {
            $travellers = $this->niceTravellers($this->http->FindNodes("//tr[{$this->eq($this->t('Passenger Details'))}]/following::text()[{$this->eq($this->t('passengerType'))}]/preceding::text()[normalize-space()][1]"));
        }

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking reference is'), "translate(.,':：','')")}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,8})\s*$/"))
            ->travellers($travellers);

        // Program
        $accountsNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Frequent flyer:'))}]");

        foreach ($accountsNodes as $aRoot) {
            $f->program()
                ->account($this->http->FindSingleNode(".", $aRoot, true, "/{$this->opt($this->t('Frequent flyer:'))}\s*([A-Z]*\d{5,})$/u"), false,
                    $this->niceTravellers($this->http->FindSingleNode("ancestor::table[1]/descendant::tr[normalize-space()][1]", $aRoot, "/^\s*[[:alpha:] \-]+\s*$/u")));
        }

        //Tickets
        $tickets = $this->http->FindNodes("//text()[{$this->starts($this->t('Ticket Number:'))}]", null, "/\:\s*(\d{5,})$/");

        foreach ($tickets as $ticket) {
            $pax = $this->http->FindSingleNode("//text()[{$this->contains($ticket)}]/preceding::text()[normalize-space()][2]");

            if (!empty($pax)) {
                $f->addTicketNumber($ticket, false, $this->niceTravellers($pax));
            } else {
                $f->addTicketNumber($ticket, false);
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Price:'))}]/ancestor::tr[1]/td[2]");

        $currency = null;

        if (preg_match("/^\s*(?<currencyCode>[A-Z]{3})\s*(?<total>\d[\d\.\,]*)\s*$/", $total, $m)) {
            // EUR 722.24    |    VND 1,653,000.    |    VND 4.686.000
            $currency = $m['currencyCode'];
            $f->price()->currency($currency)->total($this->normalizeAmount($m['total'], $m['currencyCode']));
        }

        $costAmounts = [];
        $fees = [];
        $priceXpath = "//tr[*[1][{$this->eq($this->t('Surcharges'))}]]";
        $priceNodes = $this->http->XPath->query($priceXpath);

        foreach ($priceNodes as $pRoot) {
            $travellersCount = $this->http->FindSingleNode("preceding-sibling::tr[position()<3][{$this->starts($this->t('Air Transportation Charges'))}]/preceding-sibling::tr[normalize-space()][1]/*[1]", $pRoot, true, "/^\s*(\d{1,2})\s*[[:alpha:]][[:alpha:]\s]*$/u");

            $costAmount = $this->http->FindSingleNode("preceding-sibling::tr[position()<3][{$this->starts($this->t('Air Transportation Charges'))}]/*[2]", $pRoot, true, "/^\D*(\d[,.\d ]*?)\D*$/");

            if ($travellersCount && $costAmount !== null) {
                $costAmounts[] = $travellersCount * $this->normalizeAmount($costAmount, $currency);
            }

            $priceXpath2 = "//text()[{$this->eq($this->t('Surcharges'))}]/ancestor::tr[1]/following-sibling::*[position() < 20]";

            $foundEnd = false;

            foreach ($this->http->XPath->query($priceXpath2, $pRoot) as $pRoot2) {
                $feeName = $this->http->FindSingleNode("*[1]", $pRoot2, false);
                $feeAmount = $this->http->FindSingleNode("*[2]", $pRoot2, true, "/^\D*(\d[,.\d ]*?)\D*$/");

                if ($travellersCount && $feeName && $feeAmount !== null) {
                    $feeCharge = $travellersCount * $this->normalizeAmount($feeAmount, $currency);
                    $fees[$feeName] = array_key_exists($feeName, $fees) ? $fees[$feeName] + $feeCharge : $feeCharge;
                }

                if (preg_match("/{$this->opt($this->t('Taxes Fees and Charges'))}/", $feeName)) {
                    $foundEnd = true;

                    break;
                }
            }

            if ($foundEnd === false) {
                $fees = [];

                break;
            }
        }

        if (count($costAmounts) > 0) {
            $f->price()->cost(array_sum($costAmounts));
        }

        foreach ($fees as $name => $fee) {
            $f->price()
                ->fee($name, $fee);
        }

        foreach ((array) $this->t('extraFees') as $feeName) {
            $extra = $this->normalizeAmount($this->http->FindSingleNode("//text()[{$this->eq($feeName)}]/ancestor::tr[1]/td[2]", null, true, "/^\D*(\d[,.\d ]*?)\D*$/"), $currency);

            if (!empty($extra)) {
                $f->price()
                    ->fee($feeName, $extra);
            }
        }

        // Segments
        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Duration:'))}]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding::tr[1]", $root, true, "/-\s*(\w[\w\s]*,.+\d{4})\b/u")));

            $segNodes = $this->http->XPath->query("./following::tr[{$this->eq($this->t('Itinerary Details'))}][1]/following-sibling::tr", $root);

            foreach ($segNodes as $segNode) {
                $s = $f->addSegment();

                $segText = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $segNode));

                // remove garbage
                $segText = preg_replace("/^(.+?)\n\d{1,3}\s*{$this->opt($this->t('Stop'))}.*$/is", '$1', $segText);

                $this->logger->debug($segText);

                if (preg_match("/^(?<duration>\d.+)\n{$patterns['time']}/", $segText, $m)) {
                    $s->extra()->duration($m['duration']);
                }

                $segParts = $this->splitText($segText, "/^({$patterns['time']}\s+\S{2}.+)/m", true);

                if (count($segParts) !== 2) {
                    $this->logger->debug('Wrong text segment!');

                    continue;
                }

                $cityDep = $cityArr = null;
                $subInfo = '';

                /*
                    23:55 Manila
                    NINOY AQUINO INTL(MNL)
                    Terminal 2
                    2P 0 Operated By
                    DE HAVILLAND DHC-8 DASH 8
                */
                $pattern = "/^(?<time>{$patterns['time']})\s+(?<city>\S{2}.+)\n(?<name>.*?)\s*\(\s*(?<code>[A-Z]{3})\s*\)(?:\n{$this->opt($this->t('Terminal'))}\s*(?<terminal>\S+))?(?<subInfo>\n(?:(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,5}\b|[0]+\s*(?i){$this->opt($this->t('Operated By'))})[\s\S]*)?$/u";

                if (preg_match($pattern, $segParts[0], $m)) {
                    $cityDep = $m['city'];

                    $s->departure()
                        ->name($m['city'] . ', ' . $m['name'])
                        ->code($m['code'])
                        ->date(strtotime($m['time'], $date));

                    if (!empty($m['terminal'])) {
                        $s->departure()->terminal($m['terminal']);
                    }

                    if (!empty($m['subInfo'])) {
                        $subInfo = $m['subInfo'];
                    }
                }

                if (preg_match($pattern, $segParts[1], $m)) {
                    $cityArr = $m['city'];

                    $s->arrival()
                        ->name($m['city'] . ', ' . $m['name'])
                        ->code($m['code'])
                        ->date(strtotime($m['time'], $date));

                    if (!empty($m['terminal'])) {
                        $s->arrival()->terminal($m['terminal']);
                    }

                    if (!empty($m['subInfo'])) {
                        $subInfo = $m['subInfo'];
                    }
                }

                if (preg_match("/^\s*[0]+\s*(?i){$this->opt($this->t('Operated By'))}/", $subInfo, $m)) {
                    $s->airline()->noName()->noNumber();
                } elseif (preg_match("/^\s*(?<aN>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fN>\d{1,5})\b/", $subInfo, $m)) {
                    if ($m['aN'] === '2P' && $m['fN'] === '0') {
                        $s->airline()->noName()->noNumber();
                    } else {
                        $s->airline()->name($m['aN'])->number($m['fN']);
                    }
                }

                if (preg_match("/{$this->opt($this->t('Operated By'))} (?<operator>.+)/", $subInfo, $m)
                    && !preg_match("/^\d+ ?[[:alpha:]]+ ?\d+ ?[[:alpha:]]+ ?$/", $m['operator'])
                ) {
                    $s->airline()->operator($m['operator']);

                    if (preg_match("/{$this->opt($this->t('Operated By'))} .+\n(?<aircraft>.+)/", $subInfo, $m)
                        && !preg_match("/^\d+ ?[[:alpha:]]+ ?\d+ ?[[:alpha:]]+ ?$/", $m['aircraft'])
                    ) {
                        $s->extra()->aircraft($m['aircraft']);
                    }
                } elseif (preg_match("/{$this->opt($this->t('Operated By'))}\n(?<aircraft>.+)/", $subInfo, $m)
                    && !preg_match("/^\d+ ?[[:alpha:]]+ ?\d+ ?[[:alpha:]]+ ?$/", $m['aircraft'])
                ) {
                    $s->extra()->aircraft($m['aircraft']);
                }

                // seats

                if (empty($cityDep) || empty($cityArr)) {
                    continue;
                }

                $routeVariants = [];

                foreach ((array) $this->t('to') as $t) {
                    $routeVariants[] = $cityDep . ' ' . $t . ' ' . $cityArr;
                }

                if (count($routeVariants) === 0) {
                    continue;
                }

                $seatsNodes = $this->http->XPath->query("//*[ *[1][{$this->eq($routeVariants)}] and *[2][{$this->contains($this->t('seats selected'))} or normalize-space()=''] ]/following-sibling::*//tr[count(*)=2]");

                foreach ($seatsNodes as $sRoot) {
                    $v = $this->http->FindNodes(".//td[not(.//tr[normalize-space()])]", $sRoot);

                    if (count($v) > 1 && preg_match("/^\s*\d{1,3}[A-Z]\s*$/", $v[0])
                        && preg_match("/^\s*[[:alpha:] \-]+\s*$/", $v[1])
                    ) {
                        $s->extra()->seat($v[0], false, false, $this->niceTravellers($v[1]));
                    }
                }
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function normalizeAmount(?string $amount, ?string $currency): ?float
    {
        if (empty($amount)) {
            return null;
        }
        $amount = trim($amount);

        if (preg_match("/^\d{1,3}(,\d{3})*\.$/", $amount)) {
            // 51,458. -> 51,458.00
            $amount .= '00';
        }

        $amount = PriceHelper::parse($amount, $currency);

        if (is_numeric($amount)) {
            return $amount;
        }

        return null;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})[.\s]*tháng[.\s]*(\d{1,2})[.\s]+(\d{4})$/iu', $text, $m)) {
            // Chủ Nhật,12 tháng 1 2025
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/([[:alpha:]]+)[.\s]+(\d{1,2})[,.\s]+(\d{4})$/u', $text, $m)) {
            // 星期二, 七月 2 2024    |    Thursday, June 13 2024
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
