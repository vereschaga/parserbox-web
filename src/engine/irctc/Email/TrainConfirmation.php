<?php

namespace AwardWallet\Engine\irctc\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TrainConfirmation extends \TAccountChecker
{
    public $mailFiles = "irctc/it-483861439.eml, irctc/it-484794036.eml, irctc/it-666521356.eml, irctc/it-669655477.eml, irctc/it-669657365.eml";
    public $subjects = [
        'Booking Confirmation on IRCTC, Train:',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Ticket Confirmation'   => ['Ticket Confirmation', 'IRCTC E-Ticketing'],
            'Passenger Details'     => ['Passenger Details', 'PASSENGER DETAILS:'],
            'PNR No. :'             => ['PNR No. :', 'PNR :'],
            'Scheduled Departure :' => ['Scheduled Departure :', 'Scheduled Departure* :'],
            'Sl. No.'               => ['Sl. No.', 'S.No.'],
            'Train No. / Name :'    => ['Train No. / Name :', 'Train number and Name :'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@irctc.co.in') !== false) {
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
        $this->lang = 'en';

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Thank you for using IRCTC')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Ticket Confirmation'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Passenger Details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Train No. / Name :'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]irctc\.co\.in$/', $from) > 0;
    }

    public function ParseTrain(Email $email)
    {
        // true когда в верхней таблице все поля в виде: <td>Train No. / Name :</td><td>12623 / MAS TVC MAIL</td>
        // false когда в верхней таблице поля: <td>Train No. / Name : 12623 / MAS TVC MAIL</td>
        $typeNextTd = true;

        if (!empty($this->http->FindSingleNode("//td[not(.//td)][{$this->starts($this->t('Train No. / Name :'))}]", null, true,
            "/^\s*({$this->opt($this->t('Train No. / Name :'))}\s*(?:\S ?){3,})/"))
        ) {
            $typeNextTd = false;
        }

        $depDate = $this->getValue($this->t('Scheduled Departure :'), $typeNextTd);
        $arrDate = $this->getValue($this->t('Scheduled Arrival :'), $typeNextTd);

        if ($depDate === 'N.A.' && $arrDate === 'N.A.') {
            //it-484794036.eml
            $email->setIsJunk(true);

            return $email;
        }

        $t = $email->add()->train();

        $dateBooking = $this->getValue($this->t('Date & Time of Booking :'), $typeNextTd);

        $t->general()
            ->date($this->normalizeDate($dateBooking))
            ->confirmation($this->getValue($this->t('PNR No. :'), $typeNextTd, "/^([A-Z\d]{6,})$/"))
            ->travellers($this->http->FindNodes("//tr[{$this->starts($this->t('Sl. No.'))}][contains(normalize-space(), 'Name')]/following-sibling::tr[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd')]/descendant::td[2]"));

        $s = $t->addSegment();

        $trainInfo = $this->getValue($this->t('Train No. / Name :'), $typeNextTd);

        if (preg_match("/^(?<number>\d+)\s*\/\s*(?<serviceName>.+)$/", $trainInfo, $m)) {
            $s->setNumber($m['number'])
                ->setServiceName($m['serviceName']);
        }

        $cabin = $this->getValue($this->t('Class :'), $typeNextTd);

        if (!empty($cabin)) {
            $s->setCabin($cabin);
        }

        $depName = $this->getValue($this->t('From :'), $typeNextTd);

        if (preg_match($pattern = "/^\s*(?<name>\S.*?\S)\s*\(\s*(?<code>[A-Z]{1,4})\s*\)/", $depName, $m)) {
            $s->departure()->name($m['name'] . ', India')->geoTip('in')->code($m['code']);
        } elseif ($depName) {
            $s->departure()->name(trim($depName) . ', India')->geoTip('in');
        }

        $arrName = $this->getValue($this->t('To :'), $typeNextTd);

        if (preg_match($pattern, $arrName, $m)) {
            $s->arrival()->name($m['name'] . ', India')->geoTip('in')->code($m['code']);
        } elseif ($arrName) {
            $s->arrival()->name(trim($arrName) . ', India')->geoTip('in');
        }

        $s->departure()
            ->date($this->normalizeDate($depDate));

        $s->arrival()
            ->date($this->normalizeDate($arrDate));

        $seatsCol = count($this->http->FindNodes("//tr[{$this->starts($this->t('Sl. No.'))}][contains(normalize-space(), 'Name')][following-sibling::tr[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd')]]"
            . "/*[{$this->contains($this->t('Seat'))}][last()]/preceding-sibling::*"));
        $coachCol = count($this->http->FindNodes("//tr[{$this->starts($this->t('Sl. No.'))}][contains(normalize-space(), 'Name')][following-sibling::tr[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd')]]"
            . "/*[{$this->contains($this->t('Coach'))}][last()]/preceding-sibling::*"));

        if (!empty($seatsCol) && !empty($coachCol) && $seatsCol !== $coachCol) {
            $seatsCol++;
            $seats = array_unique(array_filter($this->http->FindNodes("//tr[{$this->starts($this->t('Sl. No.'))}][contains(normalize-space(), 'Name')]/following-sibling::tr[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd')]"
                . "/*[{$seatsCol}]", null, "/^\s*(\d+)\s*$/")));

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }
            $coachCol++;
            $coaches = array_unique(array_filter($this->http->FindNodes("//tr[{$this->starts($this->t('Sl. No.'))}][contains(normalize-space(), 'Name')]/following-sibling::tr[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd')]"
                . "/*[{$coachCol}]", null, "/^\s*([A-Z\d]+)\s*$/")));

            if (!empty($coaches)) {
                $s->extra()
                    ->car(implode(', ', $coaches));
            }
        } elseif (!empty($seatsCol) && !empty($coachCol) && $seatsCol == $coachCol) {
            $seatsCol++;
            $seatsText = $this->http->FindNodes("//tr[{$this->starts($this->t('Sl. No.'))}][contains(normalize-space(), 'Name')]/following-sibling::tr[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd')]"
                . "/*[{$seatsCol}]");
            $seats = [];

            foreach ($seatsText as $st) {
                $seats[] = explode('/', $st);
            }

            if (isset($seats[0]) && count($seats[0]) > 2
                && preg_match("/^\s*([A-Z\d]+)\s*$/", $seats[0][1])
                && preg_match("/^\s*(\d+)\s*$/", $seats[0][2])
            ) {
                $s->extra()
                    ->car(implode(', ', array_unique(array_column($seats, 1))))
                    ->seats(array_column($seats, 2))
                ;
            }
        }

        $pXpath = "//tr[not(.//tr)][*[normalize-space()][1][normalize-space() = 'Ticket Fare'] and *[normalize-space()][normalize-space() = 'Total Fare']]";

        if ($this->http->XPath->query($pXpath)->length > 0) {
            // Ticket Fare      Convenience Fee     Total Fare
            // Rs. 790.00       Rs. 23.60           Rs. 813.60 *
            $pXpath2 = $pXpath . "/following::tr[normalize-space()][1]";

            if (preg_match("/^\s*(?<currency>\D{1,3})\s*(?<cost>\d[\d\.\,]*?)\s*$/", $this->http->FindSingleNode($pXpath2 . "/*[1]"), $m)) {
                $currency = $this->normalizeCurrency($m['currency']);

                $t->price()
                    ->cost(PriceHelper::parse($m['cost'], $currency))
                    ->currency($currency);
            }

            $col = count($this->http->FindNodes($pXpath . "/*[{$this->contains('Total Fare')}]/preceding-sibling::*"));

            if (!empty($col)
                && preg_match("/^\s*(?<currency>\D{1,3})\s*(?<total>\d[\d\.\,]*?)[\s\W]*\s*$/",
                    $this->http->FindSingleNode($pXpath2 . "/*[" . ($col + 1) . "]"), $m)) {
                $currency = $this->normalizeCurrency($m['currency']);

                $t->price()
                    ->total(PriceHelper::parse($m['total'], $currency))
                    ->currency($currency);
            }

            if ($col > 2) {
                for ($i = 2; $i <= $col; $i++) {
                    if (preg_match("/^\s*(?<currency>\D{1,3})\s*(?<total>\d[\d\.\,]*?)\s*$/", $this->http->FindSingleNode($pXpath2 . "/*[{$i}]"), $m)) {
                        $currency = $this->normalizeCurrency($m['currency']);

                        $t->price()
                            ->fee($this->http->FindSingleNode($pXpath . "/*[{$i}]"), PriceHelper::parse($m['total'], $currency));
                    }
                }
            }
        } else {
            $pXpath = "//tr[not(.//tr)][*[normalize-space()][2][starts-with(normalize-space(), 'Ticket Fare')]]/following-sibling::tr[not(.//tr)][*[normalize-space()][2][starts-with(normalize-space(), 'Total Fare')]]/ancestor::*[1]";

            if ($this->http->XPath->query($pXpath)->length > 0) {
                // S.No.  Description                   Amount (In Rupees)  Amount (In Words)
                // 1      Ticket Fare **                ₹ 2970              Rupees Two thousand nine hundred seventy Paise Only
                // 2      Convenience Fee (incl of GST) ₹ 35.4              Rupees Thirty-five and four Paise Only
                // 3      Travel Insurance Premium      ₹ 0                 Rupees Zero Only
                // 4      SmartBuy charges#             ₹ 0                 Rupees zero Only
                // 4      SmartBuy discount             ₹ null
                // 6      Total Fare*                   ₹ 3005.4            Rupees Three thousand five and four Paise Only
                $currency = $this->normalizeCurrency($this->http->FindSingleNode("//td[not(.//td)][starts-with(normalize-space(), 'Amount (In ')][1]",
                    null, true, "/Amount \(In (.+?)\)\s*$/"));
                $t->price()
                    ->currency($currency);

                $value = $this->http->FindSingleNode($pXpath . "/*[*[normalize-space()][2][starts-with(normalize-space(), 'Ticket Fare')]]/*[normalize-space()][3]");

                if (preg_match("/^\s*(?<currency>\D{1,3})\s*(?<cost>\d[\d\.\,]*?)\s*$/", $value, $m)) {
                    $t->price()
                        ->cost(PriceHelper::parse($m['cost'], $currency));
                }
                $value = $this->http->FindSingleNode($pXpath . "/*[*[normalize-space()][2][starts-with(normalize-space(), 'Total Fare')]]/*[normalize-space()][3]");

                if (preg_match("/^\s*(?<currency>\D{1,3})\s*(?<total>\d[\d\.\,]*?)\s*$/", $value, $m)) {
                    $t->price()
                        ->total(PriceHelper::parse($m['total'], $currency));
                }

                $value = $this->http->FindSingleNode($pXpath . "/*[*[normalize-space()][2][starts-with(normalize-space(), 'Convenience Fee')]]/*[normalize-space()][3]");

                if (preg_match("/^\s*(?<currency>\D{1,3})\s*(?<total>\d[\d\.\,]*?)\s*$/", $value, $m)) {
                    $t->price()
                        ->fee('Convenience Fee', PriceHelper::parse($m['total'], $currency));
                }
                $value = $this->http->FindSingleNode($pXpath . "/*[*[normalize-space()][2][starts-with(normalize-space(), 'Travel Insurance Premium')]]/*[normalize-space()][3]");

                if (preg_match("/^\s*(?<currency>\D{1,3})\s*(?<total>\d[\d\.\,]*?)\s*$/", $value, $m)) {
                    $add = 0.0;

                    if (preg_match("/^(\d+\.\d\d)(\d)\\2{10,}\d$/", trim($m['total']), $nm)) {
                        // 1.0499999999999998
                        $m['total'] = $nm[1];

                        if ((int) $nm[2] > 5) {
                            $add = (float) '0.01';
                        }
                    }
                    $value = PriceHelper::parse($m['total'], $currency);

                    if (!empty($add) && !empty($value)) {
                        $value += $add;
                    }
                    $t->price()
                        ->fee('Travel Insurance Premium', $value);
                }

                $value = $this->http->FindSingleNode($pXpath . "/*[*[normalize-space()][2][starts-with(normalize-space(), 'SmartBuy charges') or starts-with(normalize-space(), 'SmartBuy Charges')]]/*[normalize-space()][3]");

                if (preg_match("/^\s*(?<currency>\D{1,3})\s*(?<total>\d[\d\.\,]*?)\s*$/", $value, $m)) {
                    $t->price()
                        ->fee('SmartBuy charges', PriceHelper::parse($m['total'], $currency));
                }
                $value = $this->http->FindSingleNode($pXpath . "/*[*[normalize-space()][2][starts-with(normalize-space(), 'SmartBuy discount')]]/*[normalize-space()][3]");

                if (preg_match("/^\s*(?<currency>\D{1,3})\s*(?<total>\d[\d\.\,]*?)\s*$/", $value, $m)) {
                    $t->price()
                        ->discount(PriceHelper::parse($m['total'], $currency));
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseTrain($email);

        if ($this->http->XPath->query("//*[{$this->contains(['HDFC SmartBuy', '@smartbuyoffers.co'])}]")->length > 0
            || stripos($parser->getCleanFrom(), '@smartbuyoffers.co') !== false
        ) {
            $email->setProviderCode('hdfc');
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailProviders()
    {
        return ['irctc', 'hdfc'];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    private function getValue($field, $typeNextTd, $regexp = null)
    {
        if ($typeNextTd) {
            return $this->http->FindSingleNode("//text()[{$this->eq($field)}]/ancestor::td[1]/following-sibling::td[1]", null, true, $regexp);
        } else {
            $value = $this->http->FindSingleNode("//td[not(.//td)][{$this->starts($field)}]", null, true,
                "/^\s*{$this->opt($field)}\s*(.+)\s*/");

            if ($regexp) {
                if (preg_match($regexp, $value, $m) && isset($m[1])) {
                    return $m[1];
                }
            } else {
                return $value;
            }
        }

        return null;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^(\d+)\-(\w+)\-(\d{4})\s*(\d+\:\d+)\:\d+\s*(A?P?M?)\s*HRS$#u", //23-Aug-2023 07:33:32 PM HRS
            //2022-05-12 11:52:04 HRS; 2023-07-17 20:48 HRS
            "#^\s*(\d{4}\-\d{1,2}\-\d{1,2})\s+(\d+\:\d+)(?:\:\d+)?\s*HRS$#u",
            "#^(\d+)\-(\w+)\-(\d{4})\s*(\d+\:\d+)$#u", //04-Sep-2023 19:45
        ];
        $out = [
            "$1 $2 $3, $4$5",
            "$1, $2",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
            'INR' => ['Rs.', 'Rupees'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
