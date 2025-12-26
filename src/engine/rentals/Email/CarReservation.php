<?php

namespace AwardWallet\Engine\rentals\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CarReservation extends \TAccountChecker
{
    public $mailFiles = "rentals/it-27342468.eml, rentals/it-3971962.eml, rentals/it-3971972.eml, rentals/it-59814913.eml, rentals/it-6372116.eml, rentals/it-60335101.eml";

    public $reSubject = [
        "en" => "Car Reservation",
        "fr" => "Votre réservation de voiture de location Autoescape",
        "de" => "Ihre CarDelMar Mietwagenbuchung",
    ];
    public $reBody = 'CarRentals.com';
    public $reBody2 = [
        "en" => "Reservation Details",
        "fr" => "Votre bon de réservation prépayé",
        "de" => "Your prepaid voucher",
    ];

    public static $dictionary = [
        "en" => [
            "format2" => "Reservation Reminder!",
            "format1" => "Your Reservation is Confirmed!",
            //			"Your Reservation is" => "",
            //			"Your" => "",
            "Confirmation Code" => ["Confirmation Code", "CONFIRMATION NUMBER"],
            //			"Reservation Details" => "",
            //			"Local partner:" => "", // no exsample, need to check
            //			"Customer name:" => "", // no exsample, need to check
            "Driver name:" => ["Driver name:", "Driver name"],
            //			"Phone:" => "", // no exsample, need to check
            //			"Fax:" => "", // no exsample, need to check
            //			"Opening times" => "", // no exsample, need to check
            //			"Pick-up" => "",
            //			"Drop-off" => "",
            //			"Contact Information" => "",
            //			"Total amount of your booking" => "",
            //			"Estimated taxes & fees" => "",
            "statusVariants" => ["confirmed"],
        ],
        "fr" => [
            //			"format2" => "",
            "format1"             => "Votre réservation est confirmée !",
            "Your Reservation is" => "Votre réservation est",
            //			"Your" => "",
            "Confirmation Code"   => ["Votre numéro de réservation"],
            "Reservation Details" => "Votre bon de réservation prépayé",
            "Local partner:"      => "Partenaire régional :",
            "Customer name:"      => "Nom du client :",
            "Driver name:"        => "Nom du conducteur :",
            "Phone:"              => "Téléphone :",
            "Fax:"                => "Fax :",
            "Opening times"       => "Heures d’ouverture",
            "Pick-up"             => "Prise en charge",
            "Drop-off"            => "Restitution",
            //			"Contact Information" => "",
            "Total amount of your booking" => "Montant total",
            //			"Estimated taxes & fees" => "",
            "statusVariants" => ["confirmée"],
        ],
        "de" => [
            //			"format2" => "",
            "format1"             => "Ihre Buchung wurde bestätigt!",
            "Your Reservation is" => "Ihre Buchung wurde",
            "Your"                => "Ihre",
            "Confirmation Code"   => "Voucher Nummer",
            "Reservation Details" => ["Your prepaid voucher", "Your prepaid voucher | Ihr Prepaid-Gutschein"],
            "Local partner:"      => ["Local partner:", "Local partner | Lokaler Vermietpartner:"],
            "Customer name:"      => ["Customer name:", "Customer name | Kunde:"],
            "Driver name:"        => ["Driver name:", "Driver name | Name des Fahrers:"],
            //			"Phone:" => "",
            //			"Fax:" => "",
            "Opening times" => ["Opening times | Öffnungszeiten", "Opening times"],
            "Pick-up"       => ["Pick-up", "Pick-up | Abholung"],
            "Drop-off"      => ["Drop-off", "Drop-off | Rückgabe"],
            //			"Contact Information" => "",
            "Total amount of your booking" => "Gesamtbetrag",
            //			"Estimated taxes & fees" => "",
            "statusVariants" => ["bestätigt"],
        ],
    ];

    public $lang = "en";

    private $rentalProviders = [
        'alamo'        => ['Alamo'],
        'avis'         => ['Avis'],
        'dollar'       => ['Dollar'],
        'europcar'     => ['Europcar'],
        'foxrewards'   => ['Fox'],
        'national'     => ['National'],
        'perfectdrive' => ['Budget'],
        'sixt'         => ['Sixt'],
        'thrifty'      => ['Thrifty'],
    ];

    public function parseHtml(Email $email): void
    {
        //Ota
        $email->obtainTravelAgency();
        $otaConfirmation = $this->nextText($this->t("Partner reference no. | Referenznr.:"));

        if (!empty($otaConfirmation)) {
            $email->ota()
                ->confirmation($otaConfirmation);
        }

        $car = $email->add()->rental();

        $status = $this->http->FindSingleNode("//h1[{$this->contains($this->t("Your Reservation is"))}]", null, true, "#{$this->opt($this->t("Your Reservation is"))}\s+({$this->opt($this->t("statusVariants"))})(?:\s*[,.;!?]|$)#i");

        if ($status) {
            $car->general()->status($status);
        }

        // Confirmation Number
        $confirmation = $this->http->FIndSingleNode("//text()[" . $this->contains($this->t("Confirmation Code")) . "]/following::text()[normalize-space(.)][1]");

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        // Traveller
        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Customer name:"))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Driver name:"))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/");
        }

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Contact Information"))}]/following::text()[normalize-space()][1][following::text()[normalize-space()][1][contains(.,'Phone')]]", null, true, "/^{$patterns['travellerName']}$/");
        }

        $car->general()
            ->confirmation($confirmation)
            ->traveller($traveller);

        $dateReservation = strtotime($this->normalizeDate($this->nextText($this->t("Issuing date | Ausstellungsdatum:"))));

        if (!empty($dateReservation)) {
            $car->general()->date($dateReservation);
        }

        $totalPrice = $this->nextText($this->t("Total amount of your booking"));

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)
            || preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $m)
        ) {
            // USD 882.77    |    882.77 USD
            $car->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));

            $taxes = $this->nextText($this->t("Estimated taxes & fees"));

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d]*)$/', $taxes, $matches)
                || preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?:' . preg_quote($m['currency'], '/') . ')?$/', $taxes, $matches)
            ) {
                $car->price()->tax($this->normalizeAmount($matches['amount']));
            }
        }

        $patterns['dateOther'] = '/^\s*(?<date>.{6,}?)[ ]*\n+[ ]*(?<other>[\s\S]+)/';
        $patterns['location'] = "#^([\s\S]+?)\s*((?:{$this->opt($this->t("Map and Directions"))}|{$this->opt($this->t("Phone:"))}|{$this->opt($this->t("Fax:"))})[\s\S]*)$#";
        $patterns['phone'] = "#{$this->opt($this->t("Phone:"))}\s*([+(\d][-. \d)(]{5,}[\d)])[,\s]*(?:{$this->opt($this->t("Fax:"))}|$)#";
        $patterns['fax'] = "#{$this->opt($this->t("Fax:"))}\s*([+(\d][-. \d)(]{5,}[\d)])[,\s]*$#";
        $patterns['hours'] = "#{$this->opt($this->t("Opening times"))}[,\s]*([\s\S]+)#";

        $pickUpHtml = $this->http->FindHTMLByXpath("//text()[{$this->eq($this->t("Pick-up"))}]/ancestor::h6[1]/following-sibling::p[normalize-space()][1]");
        $pickUp = preg_replace("/^[ ]*{$this->opt($this->t("Go to"))}.+/m", '', $this->htmlToText($pickUpHtml));

        if (preg_match($patterns['dateOther'], $pickUp, $matches)) {
            $car->pickup()->date(strtotime($this->normalizeDate($matches['date'])));

            if (preg_match($patterns['location'], $matches['other'], $m)) {
                $car->pickup()->location(preg_replace('/[ ]*\n+[ ]*/', ', ', $m[1]));

                if (preg_match($patterns['phone'], $m[2], $mat)) {
                    $car->pickup()->phone($mat[1]);
                }

                if (preg_match($patterns['fax'], $m[2], $mat)) {
                    $car->pickup()->fax($mat[1]);
                }
            } else {
                $car->pickup()->location(preg_replace('/[ ]*\n+[ ]*/', ', ', $matches['other']));
            }
        }

        $pickUpHoursHtml = $this->http->FindHTMLByXpath("//text()[{$this->eq($this->t("Pick-up"))}]/ancestor::h6[1]/following-sibling::p[{$this->starts($this->t("Opening times"))}][1]");
        $pickUpHours = $this->htmlToText($pickUpHoursHtml);

        if (preg_match($patterns['hours'], $pickUpHours, $m)) {
            $car->pickup()->openingHours(preg_replace('/[ ]*\n+[ ]*/', ', ', $m[1]));
        }

        $dropOffHtml = $this->http->FindHTMLByXpath("//text()[{$this->eq($this->t("Drop-off"))}]/ancestor::h6[1]/following-sibling::p[normalize-space()][1]");
        $dropOff = preg_replace("/^[ ]*{$this->opt($this->t("Go to"))}.+/m", '', $this->htmlToText($dropOffHtml));

        if (preg_match($patterns['dateOther'], $dropOff, $matches)) {
            $car->dropoff()->date(strtotime($this->normalizeDate($matches['date'])));

            if (preg_match($patterns['location'], $matches['other'], $m)) {
                $car->dropoff()->location(preg_replace('/[ ]*\n+[ ]*/', ', ', $m[1]));

                if (preg_match($patterns['phone'], $m[2], $mat)) {
                    $car->dropoff()->phone($mat[1]);
                }

                if (preg_match($patterns['fax'], $m[2], $mat)) {
                    $car->dropoff()->fax($mat[1]);
                }
            } else {
                $car->dropoff()->location(preg_replace('/[ ]*\n+[ ]*/', ', ', $matches['other']));
            }
        }

        $dropOffHoursHtml = $this->http->FindHTMLByXpath("//text()[{$this->eq($this->t("Drop-off"))}]/ancestor::h6[1]/following-sibling::p[{$this->starts($this->t("Opening times"))}][1]");
        $dropOffHours = $this->htmlToText($dropOffHoursHtml);

        if (preg_match($patterns['hours'], $dropOffHours, $m)) {
            $car->dropoff()->openingHours(preg_replace('/[ ]*\n+[ ]*/', ', ', $m[1]));
        }

        $rentalCompany = $this->http->FIndSingleNode("//text()[" . $this->eq($this->t("Local partner:")) . "]/following::text()[normalize-space()][1]");

        if (empty($rentalCompany)) {
            $rentalCompany = $this->http->FIndSingleNode("//text()[" . $this->contains($this->t("Confirmation Code")) . "]", null, true, "#" . $this->opt($this->t("Your")) . "\s+(.+)\s+" . $this->opt($this->t("Confirmation Code")) . "#");
        }

        if (!empty($rentalCompany)) {
            $foundCode = false;

            foreach ($this->rentalProviders as $code => $names) {
                foreach ($names as $name) {
                    if (stripos($name, $rentalCompany) === 0) {
                        $car->program()->code($code);
                        $foundCode = true;

                        break 2;
                    }
                }
            }

            if ($foundCode === false) {
                $car->extra()->company($rentalCompany);
            }
        }

        // CarModel
        $carModel = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation Details")) . "]/following::text()[normalize-space(.)][1]/..");

        if (!empty($carModel)) {
            $car->car()
                ->model($carModel);
        }

        // CarImageUrl
        $carImageUrl = $this->http->FindSingleNode("//img[contains(@src, '/images-new/cars/')]/@src");

        if (empty($carImageUrl)) {
            $carImageUrl = $this->http->FindSingleNode("//img[contains(@class,'car-img')]/@src");
        }

        if (empty($carImageUrl)) {
            $carImageUrl = $this->http->FindSingleNode("//img[contains(@src, '/budget/')]/@src");
        }

        if (!empty($carImageUrl) && preg_match('/^https?:\/\/\S+$/', $carImageUrl)) {
            $car->car()
                ->image($carImageUrl);
        }
    }

    public function parseHtml2(Email $email): void
    {
        $car = $email->add()->rental();

        $confirmation = $this->http->FIndSingleNode("//text()[" . $this->contains($this->t("Confirmation Code")) . "]/following::text()[normalize-space(.)][1]");

        $car->general()
            ->confirmation($confirmation);

        $car->pickup()
            ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Pick-up")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[normalize-space(.)][1]"))))
            ->location($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Pick-up")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][2]/td[normalize-space(.)][1]"));

        $car->dropoff()
            ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Drop-off")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[normalize-space(.)][2]"))))
            ->location($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Drop-off")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][2]/td[normalize-space(.)][2]"));

        $car->car()
            ->model($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation Details")) . "]/ancestor::table[1]", null, true, "#" . $this->opt($this->t("Reservation Details")) . "\s+(.+)#"))
            ->image($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation Details")) . "]/ancestor::table[1]/following-sibling::table[1]//img/@src"));

        $totalPrice = $this->nextText($this->t("Total amount of your booking"));

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)
            || preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $m)
        ) {
            $car->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@carrentals.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'CarRentals.com') === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = true;

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $type = '';

        if ($this->http->XPath->query("//text()[" . $this->contains($this->t("format2")) . "]")->length > 0) {
            $this->parseHtml2($email);
            $type = '2';
        } elseif ($this->http->XPath->query("//text()[" . $this->contains($this->t("format1")) . "]")->length > 0) {
            $this->parseHtml($email);
            $type = '1';
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('IN ' . $str);
        $in = [
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})$#",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        $this->logger->debug('OUT ' . $str);

        return $str;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '#');
        }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
