<?php

namespace AwardWallet\Engine\ouigo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "ouigo/it-133456737.eml, ouigo/it-147258510.eml";

    public $lang = '';

    public static $dictionary = [
        'fr' => [
            'confNumber'     => ['Votre numéro de réservation est:', 'Votre numéro de réservation est :'],
            'passengerTypes' => ['Adulte', 'Enfant'],
            'feeNames'       => ['Bagage(s) supplémentaire',
                'Place(s) Avec prise',
                'CHOIX DE LA PLACE',
                'Coupe(s) File',
                'OUIFI inclus dans',
                'Place(s) Standard(s)', ],
        ],
    ];

    private $subjects = [
        'fr' => ['Modification de votre réservation'],
    ];

    private $detectors = [
        'fr' => ['TRAJET ALLER', 'Trajet Aller', 'Trajet aller'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]ouigo\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".ouigo.com/") or contains(@href,"www.ouigo.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"OUIGO")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourReservation' . ucfirst($this->lang));

        $this->parseTrain($email);

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

    private function parseTrain(Email $email): void
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")'; // 13h17

        $patterns = [
            'time'          => '\d{1,2}[:：Hh]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    13h17
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $train = $email->add()->train();

        $confirmation = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('confNumber'))}]/following-sibling::*[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $train->general()->confirmation($confirmation, $confirmationTitle);
        }

        $passengers = $passengerValues = [];
        $passengerNodes = $this->http->XPath->query("//tr/*[normalize-space()][1][ descendant::text()[normalize-space()][1][{$this->contains($this->t('passengerTypes'))}] ]");

        foreach ($passengerNodes as $pNode) {
            $pText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $pNode));
            $pText = preg_replace("/^\s*.*{$this->opt($this->t('passengerTypes'))}.*\n+[ ]*([\s\S]{2,})\s*$/", '$1', $pText);
            $pRows = preg_split("/[ ]*\n+[ ]*/", $pText);
            $passengerValues = array_merge($passengerValues, $pRows);
        }

        foreach ($passengerValues as $pValue) {
            if (preg_match("/^({$patterns['travellerName']})(?:\s+-\s+\d{4}|$)/u", $pValue, $m)) {
                $passengers[] = $m[1];
            } else {
                $this->logger->debug('Found wrong passenger name!');
                $passengers = [];
                $train->general()->travellers([]);

                break;
            }
        }

        if (count($passengers) > 0) {
            $train->general()->travellers($passengers, true);
        }

        $xpath = "//img[contains(@src, 'picto-train.jpg')]/ancestor::tr[.//text()[{$this->starts($this->t('TRAIN N°'))}]][1]/ancestor::*[count(.//text()[normalize-space()]) < 3][preceding-sibling::*[normalize-space()]][1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $segments = $this->http->XPath->query("//img[contains(@src, 'picto-train.jpg') or contains(@src, 'train.png')]/ancestor::table[.//text()[(contains(normalize-space(),'TRAIN N°'))]][1]");
        }
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        foreach ($segments as $segment) {
            $s = $train->addSegment();

            $dateValue = $this->http->FindSingleNode("preceding-sibling::*[normalize-space()][2]", $segment);
            $date = strtotime($this->normalizeDate($dateValue));

            $xpathPoint = "preceding-sibling::*[normalize-space()][1]/descendant::tr[ not(.//tr) and *[normalize-space()][1][{$xpathTime}] and *[normalize-space()][2] ]";

            if ($this->http->XPath->query($xpathPoint, $segment)->length === 2) {
                $timeDep = $this->http->FindSingleNode($xpathPoint . "[1]/*[normalize-space()][1][{$xpathTime}]", $segment, true, "/^{$patterns['time']}$/");

                if ($date && $timeDep) {
                    $s->departure()->date(strtotime($this->normalizeTime($timeDep), $date));
                }

                $timeArr = $this->http->FindSingleNode($xpathPoint . "[2]/*[normalize-space()][1][{$xpathTime}]", $segment, true, "/^{$patterns['time']}$/");

                if ($date && $timeArr) {
                    $s->arrival()->date(strtotime($this->normalizeTime($timeArr), $date));
                }

                $nameDep = $this->http->FindSingleNode($xpathPoint . "[1]/*[normalize-space()][2][not({$xpathTime})]", $segment);
                $s->departure()->name($nameDep);

                $nameArr = $this->http->FindSingleNode($xpathPoint . "[2]/*[normalize-space()][2][not({$xpathTime})]", $segment);
                $s->arrival()->name($nameArr);
            }

            $flight = $this->http->FindSingleNode('.', $segment);

            if (
                preg_match("/^(?<service>.+)\s*\|\s*{$this->opt($this->t('TRAIN N°'))}\s*(?<number>\d+)$/", $flight, $m)
                || preg_match("/^{$this->opt($this->t('TRAIN N°'))}\s*(?<number>\d+)$/", $flight, $m)
            ) {
                $s->extra()
                    ->number($m['number']);

                if (isset($m['service'])) {
                    $s->extra()
                        ->service($m['service']);
                }
            }

            $carValues = $seats = [];

            foreach ($passengers as $pName) {
                $seatValue = $this->http->FindSingleNode("following-sibling::*/descendant::text()[{$this->starts($pName)} and {$this->contains($this->t('Place'))}]", $segment);

                if (preg_match("/{$this->opt($this->t('Voiture'))}\s+(\d+)\s*,\s*{$this->opt($this->t('Place'))}\s+(\d+)[,\s]/", $seatValue, $m)) {
                    $carValues[] = $m[1];
                    $seats[] = $m[2];
                }
            }

            if (count(array_unique($carValues)) === 1) {
                $s->extra()->car($carValues[0]);

                foreach ($seats as $seat) {
                    $pax = $this->http->FindSingleNode("//text()[{$this->contains($seat)}]", null, true, "/^(.+)\s+\:/");

                    if (!empty($pax)) {
                        $s->extra()
                            ->seat($seat, true, true, $pax);
                    } else {
                        $s->addSeat($seat);
                    }
                }
            }
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ not(.//tr) and count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('TOTAL'))}] ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/");

        if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+?)$/', $totalPrice, $matches)) {
            // 77.00€
            $currencyCode = $this->normalizeCurrency($matches['currency']);
            $train->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($currencyCode);

            $feeRows = $this->http->XPath->query("//tr[ not(.//tr) and count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->contains($this->t('feeNames'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow, true, '/^(.+?)\s*(?:\(|$)/');

                if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $feeCharge, $m)) {
                    $train->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                } elseif (preg_match("/^{$this->opt($this->t('Gratuit'))}$/i", $feeCharge)) {
                    $train->price()->fee($feeName, 0);
                }
            }
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^[-[:alpha:]]{2,}[,.\s]+(\d{1,2})(?:\s+de)?\s+([[:alpha:]]{3,})\s+(?:de\s+)?(\d{4})$/u', $text, $m)) {
            // Mercredi 12 janvier 2022
            $day = $m[1];
            $month = $m[2];
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

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace('/(\d)[ ]*[Hh][ ]*(\d)/', '$1:$2', $s); // 01h55    ->    01:55

        return $s;
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
