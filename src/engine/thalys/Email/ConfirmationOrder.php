<?php

namespace AwardWallet\Engine\thalys\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationOrder extends \TAccountChecker
{
    public $mailFiles = "thalys/it-224007911.eml, thalys/it-38218459.eml, thalys/it-46003551.eml, thalys/it-67975013.eml";

    public $reFrom = ["noreply@thalys.com"];
    public $reBody = [
        'fr' => ['CONFIRMATION DE VOTRE COMMANDE', 'CONFIRMATION DE VOTRE RÉSERVATION', 'VOTRE NOUVELLE RÉSERVATION'],
        'en' => [
            'CONFIRMATION OF YOUR ORDER', 'CONFIRMATION OF YOUR BOOKING',
            'CONFIRMATION OF YOUR CANCELLATION', 'CONFIRMATION OF YOUR CANCELATION',
            'YOUR NEW BOOKING',
        ],
        'nl' => [
            'BEVESTIGING VAN UW BOEKING',
            'BEVESTIGING VAN UW OMBOEKING',
        ],
    ];
    public $reSubject = [
        'fr' => 'Confirmation de votre commande',
        'en' => 'Confirmation of your order', 'Confirmation of your cancellation',
        'nl' => 'Bevestiging van uw bestelling', 'Bevestiging van uw omruiling'
    ];
    public $lang = 'en';
    public static $dict = [
        'fr' => [
            'VOTRE VOYAGE' => ['Votre Voyage', 'VOTRE VOYAGE', 'VOTRE RÉSERVATION', 'VOTRE NOUVELLE RÉSERVATION'],
//            'BOOKING %' => '', // alter VOTRE VOYAGE
            'Aller'        => 'Aller',
            //            'statusVariants' => '',
            //            'cancelledPhrases' => '',
        ],
        'en' => [
            'VOTRE VOYAGE' => [
                'Your Trip', 'YOUR TRIP', 'Your Booking', 'YOUR BOOKING',
                'Your Cancelled Reservation', 'YOUR CANCELLED RESERVATION',
                'Your Canceled Reservation', 'YOUR CANCELED RESERVATION',
                'YOUR NEW BOOKING'
            ],
            'BOOKING %' => 'BOOKING %', // alter VOTRE VOYAGE
            'Aller'                              => 'Outbound',
            'Votre référence de dossier (PNR)'   => 'Your booking reference (PNR)',
            'paiement'                           => 'Payment',
            'Bonjour'                            => ['Hello', 'Dear'],
            'Vous avez effectué une commande le' => ['You placed an order on the', "We are pleased to confirm your booking of"],
            'et nous vous en remercions'         => ['and we thank you', ''],
            //            'Train' => '',
            'Voiture' => 'Car',
            //            'Place' => '',
            'statusVariants'   => ['cancelled', 'canceled', 'CANCELLED', 'CANCELED', 'Cancelled', 'Canceled'],
            'cancelledPhrases' => [
                'We confirm the cancellation of your booking', 'We confirm the cancelation of your booking',
                'Your Cancelled Reservation', 'YOUR CANCELLED RESERVATION',
                'Your Canceled Reservation', 'YOUR CANCELED RESERVATION',
            ],
        ],
        'nl' => [
            'VOTRE VOYAGE' => [
                'UW BOEKING', 'UW NIEUWE BOEKING'
            ],
            'BOOKING %' => 'BOEKING %', // alter VOTRE VOYAGE
            'Aller'                              => 'Heen',
            'Votre référence de dossier (PNR)'   => 'Uw dossierreferentie (PNR)',
            'paiement'                           => 'BETALING',
            'Bonjour'                            => ['Beste'],
            'Vous avez effectué une commande le' => ['Met plezier bevestigen wij uw boeking van'],
            'et nous vous en remercions'         => '', // empty value(not error)
            'Train'                              => ['Trein', 'Trein Nr.'],
            'Voiture'                            => 'Rijtuig',
            'Place'                              => 'Zitplaats',
            //            'statusVariants' => [''],
            //            'cancelledPhrases' => [],
        ],
    ];
    private $keywordProv = 'Thalys';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[starts-with(@alt,'Thalys') or contains(@src,'.thalys.com')] | //a[contains(@href,'.thalys.com')]")->length > 0
            && $this->detectBody()
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->stripos($from, $this->reFrom);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true
            && $this->stripos($headers['subject'], $this->keywordProv) === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
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
        $xpath = "//text()[{$this->eq($this->t('VOTRE VOYAGE'))}]/following::td[count(./table)>=3][not({$this->contains($this->t('paiement'))})]/table[count(.//text()[contains(translate(., '0123456789', '##########'), '##:##')]) = 2]";
        $nodes = $this->http->XPath->query($xpath);
        if ($nodes->length === 0) {
            $xpath = "//text()[{$this->eq($this->t('BOOKING %'), "translate(normalize-space(),'0123456789', '%%%%%%%%%%')")}]/following::td[count(./table)>=3][not({$this->contains($this->t('paiement'))})]/descendant-or-self::table[preceding-sibling::table][count(.//text()[contains(translate(., '0123456789', '##########'), '##:##')]) = 2]";
            $nodes = $this->http->XPath->query($xpath);
        }
        $this->logger->debug("[XPATH]: " . $xpath);

        if ($nodes->length > 0) {
            $r = $email->add()->train();

            // general info
            if ($this->http->XPath->query("//text()[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
                $r->general()->cancelled();
            }

            $dateEnds = $this->t('et nous vous en remercions');
            $end = '\s+' . $this->opt($dateEnds);

            if (is_string($dateEnds) && empty($dateEnds)) {
                $end = '\s*$';
            } elseif (is_array($dateEnds) && in_array('', $dateEnds)) {
                $end = '(?:\s+' . $this->opt(array_filter($dateEnds)) . '.*|\s*$)\s*$';
            }
            $date = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Vous avez effectué une commande le'))}]", null, true,
                "#{$this->opt($this->t('Vous avez effectué une commande le'))}\s+(\S.+?){$end}#"));

            if (!empty($date)) {
                $r->general()->date($date);
            }

            $statuses = array_unique(array_filter($this->http->FindNodes("//div[{$this->eq($this->t('cancelledPhrases'))}]", null, "/\b{$this->opt($this->t('statusVariants'))}\b/i")));

            if (count($statuses) === 1) {
                $r->general()->status(array_values($statuses)[0]);
            }
        } else {
            return;
        }

        $confirmations = [];
        $travellers = [];
        $totals = [];

        foreach ($nodes as $root) {
            // segment
            $s = $r->addSegment();

            $node = explode(' - ',
                $this->http->FindSingleNode("./preceding-sibling::table[last()]/descendant::text()[normalize-space()!=''][1]", $root));

            if (count($node) !== 2) {
                $this->logger->debug("other format");

                return;
            }

            // total charge
            $node = $this->http->FindSingleNode("./preceding-sibling::table[1][not(preceding-sibling::table)]/descendant::text()[normalize-space()!=''][last()]",
                $root);

            if (!empty($node)) {
                $totals[] = $this->getTotalCurrency($node);
            }

            // dates
            $data = $this->http->FindNodes("./descendant::td[not(.//td)]", $root);

            if (count($data) == 5) {
                $date = $this->normalizeDate($data[1]);

                if (preg_match("#^\s*(?<dTime>\d+:\d+)\s+\-\s+(?<dCity>.+?\D)\s*(?<aTime>\d+:\d+)\s+\-\s+(?<aCity>.+)#", $data[2], $m)) {
                    if (!preg_match("#\b(\d{2}:\d{2})\b#", $m['dCity'])) {
                        $s->departure()
                            ->name($m['dCity']);
                    }

                    if (!empty($date)) {
                        $s->departure()
                            ->date(strtotime($m['dTime'], $date));
                    }

                    if (!preg_match("#\b(\d{2}:\d{2})\b#", $m['aCity'])) {
                        $s->arrival()
                            ->name($m['aCity']);
                    }

                    if (!empty($date)) {
                        $s->arrival()
                            ->date(strtotime($m['aTime'], $date));
                    }

                    if ($s->getArrDate() < $s->getDepDate()) {
                        $s->arrival()->date(strtotime("+1 day", $s->getArrDate()));
                    }
                }
            }

            // cabin
            $node = $this->http->FindSingleNode("./descendant::img/@src", $root);

            if (preg_match("#\b(Premium|Comfort|Standart)\.[a-z]{3,4}$#i", $node, $m)) {
                $s->extra()->cabin($m[1]);
            }

            // number
            $train = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][last()]",
                $root);

            if (preg_match("#^{$this->opt($this->t('Train'))}\s*(\d+)$#", $train, $m)) {
                $s->extra()->number($m[1]);
            }

            // extra (car, seats, cabin)
            $seats = [];
            $nodes = $this->http->FindNodes("following-sibling::table[1]/descendant::text()[{$this->starts($this->t('Voiture'))}]/ancestor::td[{$this->contains($this->t('Place'))}][1]",
                $root);

            foreach ($nodes as $node) {
                if (preg_match("#{$this->opt($this->t('Voiture'))}\s+(\d+)\s*\-\s*{$this->opt($this->t('Place'))}\s+([\d+ ,]+)(?:\s+.+)?\s+\-\s+(.+)$#",
                    $node, $m)) {
                    if (false !== strpos($m[2], ',')) {
                        $seats = array_merge($seats, array_map("trim", explode(",", $m[2])));
                    } else {
                        $seats[] = $m[2];
                    }
                    $s->extra()
                        ->car($m[1])
                        ->seats($seats)
                        ->cabin($m[3]);
                }
            }

            $confirmations[] = $this->http->FindSingleNode("ancestor::table[1]/following::table[1]/descendant::text()[{$this->starts($this->t('Votre référence de dossier (PNR)'))}]/following::text()[normalize-space()][1]", $root, true, '/^[A-Z\d]{5,}$/');

            $travellers = array_merge($travellers,
                $this->http->FindNodes("following-sibling::table[1]/descendant::td[not(.//td)][normalize-space()][{$this->starts($this->t('Voiture'))}]/preceding-sibling::td[normalize-space()][1]", $root,
                    "/^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*$/u")
                );
        }

        $travellers = array_unique(array_filter($travellers));

        if (!empty($travellers)) {
            $r->general()
                ->travellers($travellers, true);
        } else {
            $r->general()->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Bonjour'))}]", null,
                true,
                "#{$this->opt($this->t('Bonjour'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*(?:[,?!]+|$)#u"));
        }

        $confirmations = array_unique(array_filter($confirmations));

        if (count($confirmations) > 0) {
            foreach ($confirmations as $conf) {
                $r->general()->confirmation($conf);
            }
        } elseif ($r->getCancelled()) {
            $r->general()->noConfirmation();
        }

        // Price
        $t = array_column($totals, 'Total');
        $c = array_column($totals, 'Currency');

        if (!empty($totals) && count($t) == count(array_filter($t)) && count(array_filter($t)) == count(array_filter($c)) && count(array_unique($c)) == 1) {
            $r->price()
                ->total(array_sum($t))
                ->currency(array_shift($c));
        }
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            //mercredi 12 juin 2019
            '#^(\w+)\s+(\d+)\s+(\w+)\.?\s+(\d{4})$#u',
            //29/05/2019 à 22:18    |    02/10/2019 at 14:25
            '#^\s*(\d+)\/(\d+)\/(\d{4})\s+(?:à|at)\s+(\d+:\d+)\s*$#u',
        ];
        $out = [
            '$2 $3 $4',
            '$3-$2-$1, $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
//        $this->logger->debug('$str = '.print_r( $str,true));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['VOTRE VOYAGE'], $words['Aller'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['VOTRE VOYAGE'])}]")->length > 0
                    && $this->http->XPath->query("//td[{$this->eq($words['Aller'])}]")->length > 0
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $node = str_replace("₹", "INR", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
