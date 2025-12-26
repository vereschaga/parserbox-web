<?php

namespace AwardWallet\Engine\mozio\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "mozio/it-139072483.eml, mozio/it-142386627.eml, mozio/it-23167889.eml, mozio/it-24287875.eml, mozio/it-33852483.eml, mozio/it-357709721.eml, mozio/it-43264393.eml, mozio/it-44002196.eml, mozio/it-465190739.eml, mozio/it-468831010.eml, mozio/it-649434277.eml, mozio/it-777674020.eml";

    public $reFrom = ["mozio.com"];
    public $reBody = [
        'en' => ['Pickup Instructions:', 'sorry you decided to cancel your trip and have'],
        'it' => ['Prelievo da:', 'sorry you decided to cancel your trip and have'],
        'fr' => ['Instructions de prise en charge:'],
    ];
    public $reSubject = [
        'Your Ground Transportation Reservation with',
        ', Has Been Successfully Cancelled',
        'Votre Réservation avec',
    ];
    public $subject;
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'Confirmation Number:' => ['Confirmation Number:', 'Return Confirmation Number:', 'Reservation Number:'],
            'Pickup From'          => ['Pickup From', 'Pick-up location'],
            'At:'                  => ['At:', 'Pick-up time:'],
            //            'Date:' => ['', ''],
            'Dropoff At' => ['Dropoff At', 'Drop-off location'],
            'For Flight' => ['For Flight', 'Flight'],
            //            'Arriving:' => '', // after For Flight
            'Return Itinerary' => ['Return Itinerary', 'Return'],
            //            'Provider:' => ['Provider:', ''],
            'Phone Number:' => ['Phone Number:', 'Phone number:'],
            'Vehicle Type:' => ['Vehicle Type:', 'Vehicle type:'],
            //            'Passenger Information' => ['', 'Passenger Information:'],
            'Name:' => ['Name:', 'Contact person:'],
            //            'Number of Passengers:' => ['Number of Passengers:', ''],
            'cancelledText' => ['sorry you decided to cancel your trip and have included'],
        ],
        'it' => [
            'Confirmation Number:' => ['Numero di Conferma:'],
            'Pickup From'          => ['Prelievo da'],
            'At:'                  => ['A:'],
            //'Date:' => ['A:'],
            'Dropoff At' => ['Deposito in'],
            'For Flight' => ['Per volo'],
            //'Arriving:' => 'Deposito in:', // after For Flight
            //'Return Itinerary' => ['Return Itinerary', 'Return'],
            'Provider:'             => ['Fornitore:'],
            'Phone Number:'         => ['Numero di Telefono:'],
            'Vehicle Type:'         => ['Tipo di veicolo:'],
            'Passenger Information' => ['Informazioni Passeggero'],
            'Name:'                 => ['Nome:'],
            'Number of Passengers:' => ['Numero di Passeggeri:'],
            'Total Price:'          => ['Prezzo Totale:'],
            //'cancelledText' => ['sorry you decided to cancel your trip and have included'],
        ],
        'fr' => [
            'Confirmation Number:' => ['Numéro de confirmation:', 'Numéro de confirmation pour le retour:'],
            'Pickup From'          => ['Transfert au départ de'],
            'At:'                  => ['À:', 'À:'],
            //'Date:' => ['A:'],
            'Dropoff At'            => ['À déposer à'],
            'For Flight'            => ['Pour le vol'],
            'Arriving:'             => 'À destination de:', // after For Flight
            'Return Itinerary'      => ['Itinéraire de retour'],
            'Provider:'             => ['Prestataire:'],
            'Phone Number:'         => ['Numéro de téléphone:'],
            'Vehicle Type:'         => ['Type de véhicule:'],
            'Passenger Information' => ['Informations voyageurs'],
            'Name:'                 => ['Prénom:'],
            'Number of Passengers:' => ['Nombre de passagers:'],
            'Total Price:'          => ['Prix total:'],
            //'cancelledText' => ['sorry you decided to cancel your trip and have included'],
        ],
    ];
    private $headersTA = [
        'mta' => [
            'from' => ['mtatravel.com.au'],
        ],
        'booking' => [
            'bodyUrl' => ['/booking.mozio.com'],
        ],
        'mileageplus' => [
            'bodyUrl' => ['/united.mozio.com'],
        ],
        'hertz' => [
            'bodyUrl' => ['/united.mozio.com'],
            'imgAlt'  => ['HertzDriveU'],
        ],
        'lanpass' => [
            'bodyUrl' => ['latamtravelbr.mozio.com'],
            'imgAlt'  => ['HertzDriveU'],
        ],
        'mozio' => [
            'from' => ['mozio.com'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $source = $this->http->Response['body'];

        if (!self::detectEmailByBody($parser)) {
            $this->logger->debug('can\'t determine body. wrong format. but try parse the source');
            $this->http->SetEmailBody($source);
        }

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $ota = $this->getOtaProvider($parser->getCleanFrom());

        if (empty($ota) || $ota === 'mozio') {
            foreach ($this->headersTA as $code => $arr) {
                if (isset($arr['bodyUrl']) && $this->http->XPath->query("//a[" . $this->contains($arr['bodyUrl'], '@href') . "]")->length > 0) {
                    $ota = $code;

                    break;
                }

                if (isset($arr['imgAlt']) && $this->http->XPath->query("//img[" . $this->contains($arr['imgAlt'], '@alt') . "]")->length > 0) {
                    $ota = $code;

                    break;
                }
            }
        }

        if (!empty($ota)) {
            $email->ota()->code($ota);
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function unitDetectEmailByBody()
    {
        if ($this->http->XPath->query("//a[contains(@href,'mozio.com')]/@href | //img[contains(@src,'mozio.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectBody = $this->unitDetectEmailByBody();

        if (!$detectBody) {
            $htmls = $this->getHtmlAttachments($parser);

            foreach ($htmls as $html) {
                $NBSP = chr(194) . chr(160);
                $html = str_replace($NBSP, ' ', html_entity_decode($html));
                $this->http->SetEmailBody($html);

                if ($this->unitDetectEmailByBody()) {
                    $detectBody = true;

                    break;
                }
            }
        }

        return $detectBody;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            if ($this->getOtaProvider($headers['from']) === null) {
                return false;
            }

            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers['subject'], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
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

    private function parseEmail(Email $email)
    {
        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("cancelledText")) . "])[1]"))) {
            $r = $email->add()->transfer();

            $r->general()
                ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Za-z\d\-\_]{5,})\s*$/"));

            $passenger = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Passenger Information'))} and ./following::text()[normalize-space()!=''][1][{$this->eq($this->t('Name:'))}]]/following::text()[normalize-space()!=''][2]");
            $r->general()->traveller($passenger);

            $r->general()
                ->status('Cancelled')
                ->cancelled();

            return $email;
        }
        $itinerariesHtml = $this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('Confirmation Number:'))}]/ancestor::*[ descendant::node()[{$this->starts($this->t('Dropoff At'))}] ][1]");
        $itinerariesText = $this->htmlToText($itinerariesHtml);

        $transferSegments = $this->splitText($itinerariesText, "#^[ ]*({$this->opt($this->t('Confirmation Number:'))}.+)#m", true);

        /*if (count($transferSegments) > 2) {
            $this->logger->debug('other format');

            return false;
        }*/

        $confSegments = [];

        foreach ($transferSegments as $key => $sText) {
            $r = $email->add()->transfer();

            if (preg_match("#^[ ]*({$this->opt($this->t('Confirmation Number:'))})\s*([-A-Za-z\d.\_]+)$#m", $sText, $m)) {
                $r->general()->confirmation($m[2], rtrim($m[1], ' :'));

                if (in_array($m[2], $confSegments)) {
                    $email->removeItinerary($r);

                    return $email;
                } else {
                    $confSegments[] = $m[2];
                }
            }

            $passenger = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Passenger Information'))} and ./following::text()[normalize-space()!=''][1][{$this->eq($this->t('Name:'))}]]/following::text()[normalize-space()!=''][2]");
            $r->general()->traveller($passenger);

            $r->program()
                ->phone($this->http->FindSingleNode("//text()[{$this->eq($this->t('Phone Number:'))} and ./preceding::text()[normalize-space(.)!=''][position() = 2 or position() = 5][{$this->eq($this->t('Provider:'))}]]/following::text()[normalize-space(.)!=''][1]"),
                    $this->http->FindSingleNode("//text()[{$this->eq($this->t('Phone Number:'))}]/preceding::text()[normalize-space(.)!=''][position() = 2 or position() = 5][{$this->eq($this->t('Provider:'))}]/following::text()[normalize-space(.)!=''][1]"));

            $s = $r->addSegment();

            $s->extra()
                ->adults($this->http->FindSingleNode("//text()[{$this->contains($this->t('Passenger Information'))} and ./following::text()[normalize-space()!=''][3][{$this->eq($this->t('Number of Passengers:'))}]]/following::text()[normalize-space()!=''][4]"));

            $mainText = preg_match("#((?:\s*^.+$){1,3}{$this->opt($this->t('Pickup From'))}[^\n]+(?:\s*^.+$){1,9})#mu", $sText, $m) ? $m[1] : $sText;
            $mainText = str_ireplace('&#144;', '', $mainText);
            $departure = preg_match("#{$this->opt($this->t('Pickup From'))}:[ ]*([\s\S]+?)\s+(?:{$this->opt($this->t('Dropoff At'))}|{$this->opt($this->t('At:'))})#u", $mainText, $m) ? $this->nice($m[1]) : null;

            if (preg_match("#^[A-Z]{3}$#", $departure)) {
                $s->departure()->code($departure);
            } else {
                $s->departure()->name($departure);
            }

            $patterns['arrivalEnd'] = "(?:{$this->opt($this->t('Provider:'))}|{$this->opt($this->t('Return Itinerary'))}|{$this->opt($this->t('For Flight'))})\:?";

            $arrival = preg_match("#{$this->opt($this->t('Dropoff At'))}:[ ]*([\s\S]+?)\s+{$patterns['arrivalEnd']}#u", $mainText, $m)
                || $key === 0 && preg_match("#{$this->opt($this->t('Dropoff At'))}:[ ]*([^:]+)$#u", $mainText, $m)
                ? $this->nice($m[1]) : null;

            if (preg_match("#^[A-Z]{3}$#", $arrival)) {
                $s->arrival()->code($arrival);
            } else {
                $s->arrival()->name($arrival);
            }

            if (preg_match("#{$this->opt($this->t('Pickup From'))}:\s*[\s\S]+?\n\s*{$this->opt($this->t('At:'))}\s*(.+)(?:\s*{$this->opt($this->t('Date:'))}\s*(.+))?#u", $mainText, $m)) {
                $dateDep = trim($m[1]);

                if (!empty($m[2])) {
                    $dateDep = trim($m[2] . ', ' . $dateDep);
                }
                $dateDep = $this->normalizeDate($this->nice($dateDep));

                if (!empty($dateDep)) {
                    $s->departure()->date($dateDep);
                    $s->arrival()->noDate();
                }
            } elseif (preg_match("#{$this->opt($this->t('For Flight'))}:\s*[\s\S]+?\n\s*{$this->opt($this->t('Arriving'))}:\s*(.+)\n\s*{$this->opt($this->t('Pickup From'))}\s*#", $mainText, $m)) {
                $date = $this->normalizeDate($this->nice($m[1]));

                if (!empty($date)) {
                    $s->departure()->date($date);
                    $s->arrival()->noDate();
                }
            }

            $s->extra()
                ->type($this->http->FindSingleNode("//text()[{$this->contains($this->t('Vehicle Type:'))}]/following::text()[normalize-space(.)!=''][1]"), true, true);

            if (preg_match('/You can make a change or cancel up to \d+ hours before your pickup time./', $sText, $m)) {
                $r->setCancellation($m[0]);
            }
        }

        // Total Price: $22.66
        if (preg_match("/{$this->opt($this->t('Total Price:'))}\s*(?<currency>[^\d\s]{1,5}) *(?<amount>\d[\d.,]+)(?: *\((?<currencyCode>[A-Z]{3})\))?\n/u", $sText, $m)
            || preg_match("/{$this->opt($this->t('Total Price:'))}\s*(?<amount>\d[\d.,]+) ?(?<currency>[^\d\s]+)(?: *\((?<currencyCode>[A-Z]{3})\))?\n/u", $sText, $m)
        ) {
            $currency = $m['currencyCode'] ?? $this->normalizeCurrency($m['currency']);
            $email->price()
                ->total(PriceHelper::parse($m['amount']), $currency)
                ->currency($currency);
        }

        return true;
    }

    private function getOtaProvider($from)
    {
        foreach ($this->headersTA as $code => $arr) {
            if (!isset($arr['from'])) {
                continue;
            }

            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $reBody) {
            if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            } elseif (stripos($this->subject, 'Numéro de Confirmation') !== false
                && ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, 'fr'))
            ) {
                //it-468831010.eml
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Saturday September 15 2018 - 15:55
            '#^[\w\-]+,?\s+(\w+)\s+(\d+),?\s+(\d{4})[\s\-]+(\d+:\d+(?:\s*[ap]m)?)$#ui',

            //mardi, 22 août 2023 - 10:35 Matin
            '#^\w+\,\s*(\d+\s*\w+\s*\d{4})[\s\-]+([\d\:]+\s*a?p?m?)\s+\D+$#ui',

            //mercredi, 19 avril 2023 - 09:00
            '#^\w+\,\s*(\d+\s*\w+\s*\d{4})[\s\-]+([\d\:]+\s*A?P?M?)$#',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$1, $2',
            '$1, $2',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function nice($str)
    {
        return preg_replace("#\s+#", ' ', $str);
    }

    private function htmlToText($s, $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function splitText($textSource = '', string $pattern, $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
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

    private function getHtmlAttachments(\PlancakeEmailParser $parser, $length = 6000)
    {
        $result = [];
        $altCount = $parser->countAlternatives();

        for ($i = 0; $i < $parser->countAttachments() + $altCount; $i++) {
            $html = $parser->getAttachmentBody($i);
            $info = $parser->getAttachmentHeader($i, 'content-type');

            if (preg_match("#^text/html;#", $info) && is_string($html) && strlen($html) > $length) {
                $result[] = $html;
            }
        }

        return $result;
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
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
