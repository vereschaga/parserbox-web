<?php

namespace AwardWallet\Engine\perfectdrive\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2088170 extends \TAccountChecker
{
    public $mailFiles = "perfectdrive/it-142252621.eml, perfectdrive/it-2086459.eml, perfectdrive/it-2088170.eml, perfectdrive/it-2093201.eml, perfectdrive/it-2099519.eml, perfectdrive/it-2579184.eml, perfectdrive/it-2857180.eml, perfectdrive/it-3048395.eml, perfectdrive/it-791712472.eml";

    public $lang = '';

    public static $dictionary = [
        "en" => [
            ', your vehicle has been' => [', your vehicle has been', ', your car has been', ', your reservation has been', 'you\'re all set to go', ', your prepaid reservation has been'],
            'Your Vehicle'            => ['Your Vehicle', 'Your Car', 'YOUR CAR'],
            //CONFIRMATION NUMBER
            'Your Confirmation Number:' => ['Your Confirmation Number:', 'CONFIRMATION NUMBER'],
            'Pick up:'                  => ['Pick up:', 'PICK UP DETAILS'],
            'Drop off:'                 => ['Drop off:', 'RETURN DETAILS'],
            'Estimated Total:'          => ['Estimated Total:', 'ESTIMATED TOTAL'],
        ],

        "fr" => [
            ', your vehicle has been'   => [', vous êtes prêt à partir. Merci d\'avoir choisi Budget.', ', votre voiture a ete'],
            'Your Vehicle'              => 'Your Car',
            'Pick up:'                  => 'Recueillir',
            'Drop off:'                 => 'Deposer / Retourner',
            'Your Confirmation Number:' => 'Votre numero de confirmation',
            'Pick Up Location'          => 'Emplacements de recueillir',
            'Drop Off Location'         => 'Emplacements retourner',
            'Thank you'                 => 'Merci',
            'Estimated Total:'          => ['Estimated Total:', 'Totale estimatif :'],
            'Base Rate:'                => ['Base Rate:', 'Tarif de base :'],
        ],

        "es" => [
            ', your vehicle has been'   => [', Estás listo para ir. Gracias por elegir Budget.', ', su coche ha sido reservado.'],
            'Your Vehicle'              => 'Your Car',
            'Pick up:'                  => 'Retiro:',
            'Drop off:'                 => 'Devolución/Sitio de entrega:',
            'Your Confirmation Number:' => 'Su número de confirmación',
            'Pick Up Location'          => 'Oficina de recogida o de salida',
            'Drop Off Location'         => 'Lugar de devolución',
            'Thank you'                 => 'Gracias',
            'Estimated Total:'          => ['Estimated Total:', 'Estimado total:'],
            'Base Rate:'                => ['Base Rate:', 'Tarifa Base:'],
        ],
    ];

    private $subjects = [
        'Budget Rent A Car: Reservation Confirmation',
        'Budget Rent A Car: Reservation Modify',
        'Budget Rent A Car: Reservation Reminder',
    ];

    private $LangDetect = [
        'en'  => 'Pick Up Location',
        'en2' => 'PICK UP DETAILS',
        'fr'  => 'Emplacements de recueillir',
        'es'  => 'Retiro',
    ];

    private $detects = [
        'Thank you for not smoking. Budget maintains a 100% smoke-free fleet',
        'budget@e.budget.com',
        'Budget does not accept digital driver',
    ];

    private $from = '/[@\.e]{1,2}\.budget\.com/i';

    private $prov = 'budget';

    public function parseRental(Email $email): void
    {
        $r = $email->add()->rental();

        $status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick up:'))}]/preceding::text()[{$this->contains($this->t(', your vehicle has been'))}][1]", null, true, "/{$this->opt($this->t(', your vehicle has been'))}\s*(\D{5,20})\./");

        if (!empty($status)) {
            $r->general()
                ->status($status);
        }

        if ($status == $this->t('canceled')) {
            $r->general()
                ->cancelled();
        }

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Confirmation Number:'))}]/following::text()[normalize-space()][1]"));

        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick up:'))}]/preceding::text()[{$this->contains($this->t(', your vehicle has been'))}][1]", null, true, "/^{$this->opt($this->t('Thank you'))}?\s*(\D+)\,/");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thanks'))}]", null, true, "/^{$this->opt($this->t('Thanks'))}(.+)\.$/");
        }

        if (!empty($traveller) && strlen(trim($traveller)) > 1) {
            $r->general()
                ->traveller($traveller, false);
        }

        $pickupDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick up:'))}]/following::text()[normalize-space()][1]");

        if (stripos($pickupDate, ':') === false) {
            $pickupDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick up:'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");
        }
        $r->pickup()->date($this->normalizeDate($pickupDate));

        $dropOffDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Drop off:'))}]/following::text()[normalize-space()][1]");

        if (stripos($dropOffDate, ':') === false) {
            $dropOffDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Drop off:'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");
        }
        $r->dropoff()->date($this->normalizeDate($dropOffDate));

        /*
            San Jose Intl Airport,SJC
            1659 Airport Boulevard, Ste 3,
            San Jose, CA 95110 US
            012-6927070X2517
            Sun - Sat 6:30 AM - 10:00 PM
        */
        $patterns['location'] = "/^\s*(?<location>[\s\S]{3,}?)[ ]*\n+[ ]*(?:CALL: *)?(?<phone>[+(\d][-+. \d\/)(X]{5,}[\d)])[ ]*(?:[A-Z]{1,})?\n+[ ]*(?<hourse>[\s\S]{3,}?)\s*$/i";

        $pickupText = implode("\n", $this->http->FindNodes("//tr[{$this->eq($this->t('Pick Up Location'))}]/following-sibling::tr[normalize-space()]"));

        if (empty($pickupText)) {
            $pickupText = implode("\n", $this->http->FindNodes("//tr[{$this->eq($this->t('Pick up:'))}]/ancestor::table[1]/descendant::tr[normalize-space()='Get Directions']/preceding::tr[1]/descendant::text()[normalize-space()]"));
        }

        if (!$pickupText) {
            // it-2093201.eml
            $pickupText = implode("\n", $this->http->FindNodes("//table[{$this->eq($this->t('Pick Up Location'))}]/following-sibling::table[normalize-space()][1]/descendant-or-self::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]"));
        }

        if (preg_match($patterns['location'], $pickupText, $m)) {
            $m['location'] = str_replace(' Pick Up Dropoff', '', $m['location']);

            $r->pickup()
                ->location(preg_replace('/\s+/', ' ', str_replace('Car Pickup:', '', $m['location'])))
                ->phone($m['phone'])
                ->openingHours(preg_replace('/[ ]*\n+[ ]*/', '; ', $m['hourse']));
        }

        $dropoffText = implode("\n", $this->http->FindNodes("//tr[{$this->eq($this->t('Drop Off Location'))}]/following-sibling::tr[normalize-space()]"));

        if (empty($dropoffText)) {
            $dropoffText = implode("\n", $this->http->FindNodes("//tr[{$this->eq($this->t('Drop off:'))}]/ancestor::table[1]/descendant::tr[normalize-space()='Get Directions']/preceding::tr[1]/descendant::text()[normalize-space()]"));
        }

        if (!$dropoffText) {
            $dropoffText = implode("\n", $this->http->FindNodes("//table[{$this->eq($this->t('Drop Off Location'))}]/following-sibling::table[normalize-space()][1]/descendant-or-self::*[ tr[normalize-space()][2] ][1]/tr[normalize-space()]"));
        }

        if (preg_match($patterns['location'], $dropoffText, $m)) {
            $m['location'] = str_replace(' Pick Up Dropoff', '', $m['location']);

            $r->dropoff()
                ->location(preg_replace('/\s+/', ' ', str_replace('Car Pickup:', '', $m['location'])))
                ->phone($m['phone'])
                ->openingHours(preg_replace('/[ ]*\n+[ ]*/', '; ', $m['hourse']));
        }

        $carModel = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Vehicle'))}]/following::text()[normalize-space()][1]");

        if (!empty($carModel)) {
            $r->car()
                ->model($carModel);
        }

        $image = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Vehicle'))}]/following::img[1]/@src", null, true, "/^.*(?:http|www\.|\.com\/|\/\/).*$/i");

        if (!empty($image) && strlen($image) > 20) {
            $r->car()
                ->image($image);
        }

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated Total:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/([\d\.\,]+)$/");

        if ($total === null) {
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated Total:'))}]/ancestor::tr[1]/following::tr[1]", null, true, "/([\d\.\,]+)$/");
        }

        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated Total:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\D+)\s*/");

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated Total:'))}]/ancestor::tr[1]/following::tr[1]", null, true, "/^(\D+)\s*/");
        }

        if (!empty($total) && !empty($currency)) {
            $r->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);
        }

        $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Base Rate:'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/([\d\.\,]+)$/");

        if (!empty($cost)) {
            $r->price()
                ->cost(PriceHelper::parse($cost, $currency));
        }

        $feesNode = $this->http->XPath->query("//text()[{$this->eq($this->t('Base Rate:'))}]/ancestor::tr[1]/following-sibling::tr[not ({$this->contains($this->t('Base Rate:'))} and not(contains(normalize-space(), 'View complete summary of charges')))][not({$this->contains($this->t('Surcharges/Fees'))})]");

        foreach ($feesNode as $root) {
            $feeName = $this->http->FindSingleNode("./descendant::td[1]", $root);

            if ($feeName === trim($feeName, ':')) {
                continue;
            }
            $feeName = trim($feeName, ':');
            $feeSum = $this->http->FindSingleNode("./descendant::td[2]", $root, true, "/([\d\.]+)$/");

            if (!empty($feeSum) && preg_match('/^[^<>{}]+$/', $feeName)) {
                $r->price()
                    ->fee($feeName, $feeSum);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseRental($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'], $headers['from'])) {
            if (!preg_match($this->from, $headers['from'])) {
                return false;
            }

            foreach ($this->subjects as $subject) {
                if (false !== stripos($headers['subject'], $subject)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();
        $body = !empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\s+(\w+)\s+(\d+)\,\s+(\d{4})\s+\w+\s+([\d\:]+\s+A?P?M)$#", //Wed Oct 29, 2014 at 07:00 AM
            "#^\w+\s+(\w+)\s+(\d+)\,\s+(\d{4})\s+\w+\s+([\d\:]+)\s+\w+$#", //Fri Oct 23, 2020 at 09:00 hours
            "#^\w+\s+(\d+)\s+(\w+)\,\s+(\d{4})\s+\w+\s*(\d{2})(\d{2})$#u", //Dim 20 Sept, 2020 à 1000
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
            "$1 $2 $3, $4:$5",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function assignLang(): void
    {
        foreach ($this->LangDetect as $lang => $word) {
            if (strpos($this->http->Response["body"], $word) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
