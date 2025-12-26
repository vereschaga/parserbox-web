<?php

namespace AwardWallet\Engine\transavia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationYour extends \TAccountChecker
{
    public $mailFiles = "transavia/it-174479933-en.eml, transavia/it-48784878.eml, transavia/it-49300959.eml, transavia/it-656143631.eml";

    public $reSubject = [
        'Boekingsbevestiging: je reis naar', // nl
        'Gewijzigde boekingsbevestiging: je reis naar',
        'Ready for departure to', // en
        'Booking confirmation: your trip to',
        'Updated booking confirmation: your trip to',
        // pt
        'Confirmação de reserva: a sua viajem para',
        // es
        'Confirmación de reserva: tu viaje a',
        'Modificación de confirmación de reserva: tu viaje a',
        // it
        'Conferma di prenotazione: il tuo viaggio a',
        // fr
        'Confirmation de réservation : Votre voyage à destination de',
        // de
        'Geänderte Buchungsbestätigung: Ihre Reise nach',
    ];
    public $lang = '';
    public static $dict = [
        'nl' => [
            'Booking number:'         => 'Boekingsnummer:',
            'Your flight'             => 'Jouw vlucht',
            'Gender'                  => 'Geslacht',
            'Total'                   => 'Totaal',
            'Including'               => 'Inclusief',
            'in taxes and surcharges' => 'belastingen en toeslagen',
            'Flight number:'          => 'Vluchtnummer:',
            'Seat reservation'        => 'Stoelreservering',
            //'discount' => '',
            //'Hello' => ''
        ],
        'en' => [
            'Booking number:' => 'Booking number:',
            'Your flight'     => 'Your flight',
            // 'Gender' => '',
            // 'Total' => '',
            // 'Including' => '',
            // 'in taxes and surcharges' => '',
            // 'Flight number:' => '',
            // 'Seat reservation' => '',
            'discount' => 'Correction on',
            'Hello'    => ['Hello', 'Dear'],
        ],
        'pt' => [
            'Booking number:'         => 'Número de reserva:',
            'Your flight'             => 'O seu voo',
            'Gender'                  => 'Sexo',
            'Total'                   => 'Total',
            'Including'               => 'Inclui',
            'in taxes and surcharges' => 'de impostos e suplementos',
            'Flight number:'          => 'Número de voo:',
            'Seat reservation'        => 'Reserva de assentos',
            //'discount' => '',
            //'Hello' => '',
        ],
        'es' => [
            'Booking number:'         => 'Número de reserva:',
            'Your flight'             => 'Tu vuelo',
            'Gender'                  => 'Sexo',
            'Total'                   => 'Total',
            'Including'               => 'Inclusive',
            'in taxes and surcharges' => 'de impuestos y recargos',
            'Flight number:'          => 'Número de vuelo:',
            // 'Seat reservation' => 'Reserva de assentos',
            //'discount' => '',
            //'Hello' => '',
        ],
        'it' => [
            'Booking number:'         => 'Numero di prenotazione:',
            'Your flight'             => 'Il tuo volo',
            'Gender'                  => 'Sesso',
            'Total'                   => 'Totale',
            'Including'               => 'Inclusi',
            'in taxes and surcharges' => 'di tasse e supplementi',
            'Flight number:'          => 'Numero del volo:',
            'Seat reservation'        => 'Prenotazione del posto',
            'discount'                => 'modifica volo flex limited o full',
            //'Hello' => '',
        ],
        'fr' => [
            'Booking number:'         => ['Numéro de réservation :', 'Référence de réservation :'],
            'Your flight'             => 'Votre vol',
            'Gender'                  => 'Sexe',
            'Total'                   => 'Total',
            'Including'               => 'Y compris',
            'in taxes and surcharges' => 'de taxes et de suppléments',
            'Flight number:'          => 'Numéro de vol:',
            'Seat reservation'        => 'Réservation de siège',
            //'discount' => '',
            //'Hello' => '',
        ],
        'de' => [
            'Booking number:'         => 'Buchungsnummer:',
            'Your flight'             => 'Ihr Flug',
            'Gender'                  => 'Geschlecht',
            'Total'                   => 'Total',
            'Including'               => 'Inkl.',
            'in taxes and surcharges' => 'Steuern und Zuschlägen',
            'Flight number:'          => 'Flugnummer:',
            'Seat reservation'        => 'Sitzplatzreservierung', // to check
            //'discount' => '',
            //'Hello' => '',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $href = ['.transavia.com/', 'e.transavia.com'];

        if ($this->http->XPath->query("//img[contains(@src,'.transavia.com')] | //a[{$this->contains($href, "@href")} or {$this->contains($href, "@originalsrc")}]")->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        foreach (['@e.transavia.com'] as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $r = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking number:'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
        $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking number:'))}]", null, true, '/^(.+?)[\s:：]*$/u');
        $r->general()->confirmation($confirmation, $confirmationTitle);

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Gender'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[(count(*[normalize-space()]) = 1 and not(.//img)) or (count(*[normalize-space()]) = 2 and *[normalize-space()][1][not(.//img)] and *[normalize-space()][2][.//img])][1]/*[1]");

        $infants = $this->http->FindNodes("//text()[{$this->eq($this->t('Gender'))}]/preceding::tr[1][.//img/@src[{$this->contains('/baby-blue_4.png')}]][count(.//text()[normalize-space()]) = 1]");

        if (count($travellers) > 0) {
            if (!empty($infants)) {
                $travellers = array_diff($travellers, $infants);
                $r->general()->infants($infants, true);
            }
            $r->general()->travellers($travellers, true);
        } else {
            $travellerNames = array_unique(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hello'))}]", null, "/^{$this->opt($this->t('Hello'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u")));

            if (count(array_unique($travellerNames)) === 1) {
                $r->general()->travellers(preg_replace("/(?:Mr\.|Mrs\.|Ms\.)/", "", array_unique($travellerNames)), false);
            } else {
                $r->general()->travellers(null);
            }
        }

        $xpathFee = "//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()!=''][not({$this->contains($this->t('discount'))})]";
        $rootFee = $this->http->XPath->query($xpathFee);

        $this->logger->debug("fee: " . $xpathFee);

        $discount = $this->http->FindSingleNode("//text()[{$this->contains($this->t('discount'))}]/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}\s*\-\s*([\d\.\,]+)$/u");

        if (!empty($discount)) {
            $r->price()
                ->discount($discount);
        }

        foreach ($rootFee as $root) {
            $fee = $this->http->FindSingleNode("./td[1]", $root);
            $sum = $this->getTotalCurrency($this->http->FindSingleNode("./td[2]", $root));

            if (!empty($fee) && !empty($sum['total'])) {
                $r->price()->fee($fee, $sum['total']);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//td[2][{$this->starts($this->t('Total'))}]", null, true, '/^.*\d.*$/');

        if ($totalPrice !== null) {
            $total = $this->getTotalCurrency($totalPrice);

            $tax = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Including'))} and {$this->contains($this->t('in taxes and surcharges'))}]", null, false, "/{$this->opt($this->t('Including'))}\s*(.+?)\s+{$this->opt($this->t('in taxes and surcharges'))}/"));
            $r->price()
                ->total($total['total'])
                ->currency($total['currency'])
                ->tax($tax['total'])
            ;
        }

        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';
        $xpathSegments = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$xpathTime}] and *[normalize-space()][2][{$xpathTime}] ]/ancestor::*[ preceding-sibling::*[normalize-space()] and following-sibling::*[normalize-space()] ][1]/..";
        $this->logger->debug("segments: " . $xpathSegments);
        $roots = $this->http->XPath->query($xpathSegments);

        foreach ($roots as $root) {
            $s = $r->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("*[normalize-space()][1]", $root));

            $depTime = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/following::tr[normalize-space()!=''][1]/td[1]",
                $root);
            $depName = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/following::tr[normalize-space()!=''][2]/td[1]",
                $root);
            $depCode = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/following::tr[normalize-space()!=''][3]/td[1]",
                $root);
            $arrTime = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/following::tr[normalize-space()!=''][1]/td[last()]",
                $root);
            $arrName = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/following::tr[normalize-space()!=''][2]/td[last()]",
                $root);
            $arrCode = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/following::tr[normalize-space()!=''][3]/td[last()]",
                $root);

            $s->departure()
                ->date(strtotime($depTime, $date))
                ->code($depCode);

            if (!empty($depName)) {
                $s->departure()->name($depName);
            }
            $s->arrival()
                ->date(strtotime($arrTime, $date))
                ->code($arrCode);

            if (!empty($arrName)) {
                $s->arrival()->name($arrName);
            }

            $node = $this->http->FindSingleNode("*[normalize-space()][last()]", $root, true, "/^\s*(?:{$this->opt($this->t('Flight number:'))})?\s*(.{3,})$/");

            if (preg_match('/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/', $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $route = trim($s->getDepName() . ' - ' . $s->getArrName());
            $seats = array_filter($this->http->FindNodes("//text()[normalize-space()=\"" . $route . "\"]/ancestor::*[self::td or self::th][1]/descendant::text()[{$this->eq($this->t('Seat reservation'))}]/following::text()[normalize-space()!=''][1]",
                null, "/^(\d+[A-z])$/"));

            foreach ($seats as $seat) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($seat)}]/preceding::text()[{$this->eq($this->t('Gender'))}][1]/preceding::text()[normalize-space()][1]");
                $s->extra()->seat($seat, false, true, $pax);
            }
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            // donderdag 26 maart 2020
            '/^[-[:alpha:]]+[,.\s]+(\d{1,2})[,.\s]+([[:alpha:]]+)[,.\s]+(\d{4})$/u',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Booking number:'], $words['Your flight'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booking number:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Your flight'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace(["€", "£", "₹"], ["EUR", "GBP", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#^(?<c>[^\s\d])\s*(?<t>\d[\.\d\,\s]*\d*)$#", trim($node), $m)
            || preg_match("#^(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[^\s\d])$#", trim($node), $m)
        ) {
            $cur = $m['c'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['c']) ? $m['c'] : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
        }

        return ['total' => $tot, 'currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return 'false()';
        return '(' . implode(' or ', array_map(function($s) use($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';
            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
