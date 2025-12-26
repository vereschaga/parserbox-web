<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class AllSetForYourTrip extends \TAccountChecker
{
    public $mailFiles = "expedia/it-767228869.eml, expedia/it-769480361.eml, expedia/it-769769696.eml, expedia/it-770474849.eml, expedia/it-770484078.eml, expedia/it-772991887.eml, expedia/it-773133069.eml, expedia/it-773618926.eml";

    public $detectFrom = ["mail@eg.expedia.com", 'mail@eg.hotels.com'];
    public $detectSubject = [
        // en
        'All set for your trip',
        // es
        'Todo listo para tu viaje a',
        // zh
        '的行程已经准备好了',
        // fr
        'Préparez-vous pour votre voyage à',
        // it
        'È tutto pronto per il tuo viaggio a',
        // de
        // Deine Reise nach Alicante kann losgehen
        'Deine Reise nach',
        // fi
        'Kaikki valmista kohteen',
        // sv
        'Allt är klart för din resa till',
        'Redo för din resa till',
        // no
        'Gjør deg klar for reisen til',
        'Alt er klart for reisen din til',
    ];

    public $date;

    public $lang;
    public static $dictionary = [
        'en' => [
            'textAfterTraveller' => [
                ', your trip at a glance', ', as a Silver member of One Key™, you earn',
                ', as a Blue member of One Key™, you earn',
            ],
            'Your trip so far'     => ['Your trip so far', 'What you\'ve booked'],
            'View my trip in full' => 'View my trip in full',
            // Flight
            // 'Departs' => '',
            ' to ' => [' to ', ' undefined '],
            // 'Airline confirmation' => '',
            // Hotel
            // 'Check-in' => '',
            // 'Check-out' => '',
        ],
        'es' => [
            'textAfterTraveller' => [', como socio Silver de One Key™, acumulas crédito', ', avanza para alcanzar el nivel Platinum.',
                ', como socio Blue de One Key™, acumulas crédito', ', un resumen de tu viaje', ', echa un vistazo a tu viaje',
                ', como socio Platinum de One Key™, tienes grandes',
            ],
            'Your trip so far'     => ['Tu viaje hasta este momento', 'Tu reservación'],
            'View my trip in full' => 'Ver viaje completo',
            // Flight
            'Departs'              => 'Salida',
            ' to '                 => ' to ',
            'Airline confirmation' => 'Confirmación de la aerolínea',
            // Hotel
            'Check-in'  => 'Check-in',
            'Check-out' => 'Check-out',
        ],
        'zh' => [
            'textAfterTraveller'   => ['您好,这是您的行程一览', ', 您好，作为 One Key™'],
            'Your trip so far'     => ['您截至目前的行程'],
            'View my trip in full' => '完整查看我的行程',
            // Flight
            'Departs'              => '出发',
            ' to '                 => [' 到 ', ' to '],
            'Airline confirmation' => '航空公司确认',
            // Hotel
            // 'Check-in' => '',
            // 'Check-out' => '',
        ],
        'fr' => [
            'textAfterTraveller'   => [', consultez votre voyage', ', votre voyage en bref'],
            'Your trip so far'     => ['Vos réservations'],
            'View my trip in full' => ['Afficher mon voyage', 'Voir mon voyage'],
            // Flight
            'Departs'              => 'Départ le',
            ' to '                 => [' to '],
            'Airline confirmation' => 'Confirmation de la compagnie aérienne',
            // Hotel
            'Check-in'  => 'Arrivée le',
            'Check-out' => 'Départ le',
        ],
        'it' => [
            'textAfterTraveller'   => [', i tuoi dettagli di viaggio'],
            'Your trip so far'     => ['Cos\'hai prenotato'],
            'View my trip in full' => 'Vai al tuo viaggio',
            // Flight
            'Departs'              => 'Partenza il giorno',
            ' to '                 => [' to '],
            // 'Airline confirmation' => '航空公司确认',
            // Hotel
            'Check-in'  => 'Check-in',
            'Check-out' => 'Check-out',
        ],
        'de' => [
            'textAfterTraveller'   => [', deine Reise auf einen Blick',
                ', hier findest du noch mal einen Überblick darüber', ],
            'Your trip so far'     => ['Das hast du gebucht', 'Was du bisher gebucht hast', 'So sieht deine Reise bisher aus'],
            'View my trip in full' => 'Zu den Reisedetails',
            // Flight
            'Departs'              => 'Abflug',
            ' to '                 => [' to '],
            // 'Airline confirmation' => '航空公司确认',
            // Hotel
            'Check-in'  => 'Check-in',
            'Check-out' => 'Check-out',
        ],
        'fi' => [
            'textAfterTraveller'   => [', tässä näet helposti ja nopeasti,'],
            'Your trip so far'     => ['Matkasi tähän mennessä'],
            'View my trip in full' => 'Näytä koko matka',
            // Flight
            // 'Departs'              => '',
            // ' to '                 => [' to '],
            // 'Airline confirmation' => '航空公司确认',
            // Hotel
            'Check-in'  => 'Tulopäivä',
            'Check-out' => 'Lähtöpäivä',
        ],
        'sv' => [
            'textAfterTraveller'   => [', en översikt över din resa',
                ', här kan du se vad du har bokat hittills.', ],
            'Your trip so far'     => ['Vad du har bokat hittills', 'Din resa såhär långt'],
            'View my trip in full' => 'Se hela min resa',
            // Flight
            // 'Departs'              => '',
            // ' to '                 => [' to '],
            // 'Airline confirmation' => '航空公司确认',
            // Hotel
            'Check-in'  => 'Incheckning',
            'Check-out' => 'Utcheckning',
        ],
        'no' => [
            // 'textAfterTraveller'   => [''],
            'Your trip so far'     => ['Dette har du bestilt så langt', 'Reisen din så langt'],
            'View my trip in full' => 'Se hele reisen din',
            // Flight
            // 'Departs'              => '',
            // ' to '                 => [' to '],
            // 'Airline confirmation' => '航空公司确认',
            // Hotel
            'Check-in'  => 'Innsjekking',
            'Check-out' => 'Utsjekking',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'expedia.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->containsText($headers['from'], $this->detectFrom) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a/@href[{$this->contains(['.expedia.com', '.eg.hotels.com'])}] | //node()[{$this->contains(['Expedia, Inc'])}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (
                !empty($dict['Your trip so far']) && !empty($dict['View my trip in full'])
                && $this->http->XPath->query("//*[{$this->eq($dict['Your trip so far'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->eq($dict['View my trip in full'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                !empty($dict['Your trip so far']) && !empty($dict['View my trip in full'])
                && $this->http->XPath->query("//*[{$this->eq($dict['Your trip so far'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->eq($dict['View my trip in full'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $dateText = $this->http->FindSingleNode("(//a[contains(@href, '=DATE') or contains(@href, '-3DDATE')]/@href)[1]", null, true, "/(?:=|-3D)DATE(\d{8})-/");

        if (empty($dateText) && preg_match("/=DATE(\d{8})-/", $parser->getHTMLBody(), $m)) {
            $dateText = $m[1];
        }

        if (preg_match("/^(20\d{2})(\d{2})(\d{2})$/", $dateText, $m)) {
            $this->date = strtotime($m[3] . '.' . $m[2] . '.' . $m[1]);
        }

        // $this->logger->debug('$this->date = '.print_r( $this->date,true));

        $this->parseEmailHtml($email);

        $traveller = $this->http->FindSingleNode("//text()[{$this->contains($this->t('textAfterTraveller'))}]",null, true,
            "/^\s*([[:alpha:] \-\.]+)\s*{$this->opt($this->t('textAfterTraveller'))}/u");

        if (!empty($traveller)) {
            foreach ($email->getItineraries() as $it) {
                $it->general()
                    ->traveller($traveller, false);
            }
        }

        $code = null;

        if ($this->http->XPath->query("//a/@href[{$this->contains(['.eg.hotels.com'])}] | //node()[{$this->contains(['Hotels.com'])}]")->length > 0) {
            $code = 'hotels';
        } elseif ($this->http->XPath->query("//a/@href[{$this->contains(['.expedia.com'])}] | //node()[{$this->contains(['Expedia, Inc'])}]")->length > 0) {
            $code = 'expedia';
        }
        $email->setProviderCode($code);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailProviders()
    {
        return ['expedia', 'hotels'];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmailHtml(Email $email)
    {
        $email->obtainTravelAgency();

        // Flight
        $flightXpath = "//img[@src[{$this->contains(['icon__lob_flights', 'FLIGHTS_Secondary_Image'])}] or @alt[{$this->eq(['Ícono de vuelo', 'Flight icon'])}]]/ancestor::tr[normalize-space()][1]"
            . "[following::text()[{$this->eq($this->t('View my trip in full'))}]]";
        $nodes = $this->http->XPath->query($flightXpath);

        if ($nodes->length > 0) {
            $f = $email->add()->flight();
        }
        $confs = [];

        foreach ($nodes as $root) {
            $text = "\n\n" . implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));
            // $this->logger->debug('$text = '.print_r( $text,true));

            $status = trim($this->re("/^\s*(?<status>\S.+\n)? *\S.+?\s*\([A-Z]{3}\)\s*{$this->opt($this->t(' to '))}\s*\S.+?\s*\([A-Z]{3}\)\n/u", $text));

            if (!empty($status)) {
                $text = preg_replace("/\n{$status}\n/u", "\n", $text);
            }

            $fsegments = $this->split("/\n *(\S.+?\s*\([A-Z]{3}\)\s*{$this->opt($this->t(' to '))}\s*\S.+?\s*\([A-Z]{3}\)\n)/", $text);
            // $this->logger->debug('$fsegments = '.print_r( $fsegments,true));

            foreach ($fsegments as $fText) {
                if (preg_match("/^\s*(?<dName>.+?)\s*\((?<dCode>[A-Z]{3})\)\s*{$this->opt($this->t(' to '))}\s*(?<aName>.+?)\s*\((?<aCode>[A-Z]{3})\)\n"
                    . "\s*.+?\((?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\)\n\s*{$this->opt($this->t("Departs"))}\s*:?\s+(?<date>.+?(?<time>\d{1,2}(?:\W| h )\d{2}.*)?)\s*(?:\n|$)/u",
                    $fText, $m)
                ) {
                    $s = $f->addSegment();

                    $s->airline()
                        ->name($m['al'])
                        ->noNumber();

                    $s->departure()
                        ->name($m['dName'])
                        ->code($m['dCode']);

                    if (!empty($m['time'])) {
                        $s->departure()
                            ->date($this->normalizeDate($m['date']));
                    } else {
                        $s->departure()
                            ->noDate()
                            ->day($this->normalizeDate($m['date']));
                    }
                    $s->arrival()
                        ->code($m['aCode'])
                        ->noDate();

                    if ($m['aName'] !== $m['dName']) {
                        $s->arrival()
                            ->name($m['aName']);
                    }
                }

                if (preg_match("/\n\s*{$this->opt($this->t("Airline confirmation"))}:?\s+([A-Z\d]{5,})\s*(?:\n|$)/u", $fText, $m)
                ) {
                    $confs[] = $m[1];
                }

                $segments = $f->getSegments();

                foreach ($segments as $segment) {
                    if ($segment->getId() !== $s->getId()) {
                        if (serialize($segment->toArray()) === serialize($s->toArray())) {
                            $f->removeSegment($s);

                            break;
                        }
                    }
                }
            }
        }

        if ($nodes->length > 0) {
            if (count($confs) > 0) {
                foreach (array_unique($confs) as $conf) {
                    $f->general()
                        ->confirmation($conf);
                }
            } else {
                $f->general()
                    ->noConfirmation();
            }
        }

        // Hotel
        $hotelXpath = "//img[@src[{$this->contains(['icon__lob_hotels', 'Hotels_Secondary_Image'])}] or @alt[{$this->eq(['Hotel icon'])}]]/ancestor::tr[normalize-space()][1]"
            . "[following::text()[{$this->eq($this->t('View my trip in full'))}]]";
        // $this->logger->debug('$hotelXpath = ' . print_r($hotelXpath, true));
        $nodes = $this->http->XPath->query($hotelXpath);

        foreach ($nodes as $root) {
            $text = "\n\n" . implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));
            // $this->logger->debug('$text = '.print_r( $text,true));

            $status = trim($this->re("/^\s*(?<status>\S.+\n)?\S.+\n\s*\S.+\n{$this->opt($this->t("Check-in"))}:?/u", $text));

            if (!empty($status)) {
                $text = preg_replace("/\n{$status}\n/u", "\n", $text);
            }

            $hsegments = $this->split("/(\n.+\n.+\n\s*{$this->opt($this->t("Check-in"))}:?\s*)/", $text);
            // $this->logger->debug('$hsegments = '.print_r( $hsegments,true));

            foreach ($hsegments as $hText) {
                $h = $email->add()->hotel();

                // General
                $h->general()
                    ->noConfirmation();

                if (preg_match("/^\s*(.+\n)?(?<name>.+)\n\s*(?<address>.+)\n\s*" .
                    "{$this->opt($this->t("Check-in"))}:?\s*(?<ciDate>.+?)\s+" . $this->opt($this->t("Check-out")) . ":?\s*(?<coDate>.+)\s*$/u", $hText, $m)) {
                    $h->hotel()
                        ->name($m['name'])
                        ->address($m['address']);

                    $h->booked()
                        ->checkIn($this->normalizeDate($m['ciDate']))
                        ->checkOut($this->normalizeDate($m['coDate']));
                }
            }
        }

        // Junk Reservation Type
        // Rental
        $rentalXpath = "//img[@src[{$this->contains(['icon__directions_car', 'GT2_Secondary_Image'])}] or @alt[{$this->eq(['Car icon'])}]]/ancestor::tr[normalize-space()][1]"
            . "[following::text()[{$this->eq($this->t('View my trip in full'))}]]";
        // $this->logger->debug('$rentalXpath = '.print_r( $rentalXpath,true));
        $rentalNodes = $this->http->XPath->query($rentalXpath);
        // $this->logger->debug('$rentalNodes = '.print_r( $rentalNodes->length,true));

        // Actuvity
        $activityXpath = "//img[@src[{$this->contains(['icon__lob_activities'])}] or @alt[{$this->eq(['Activities icon'])}]]/ancestor::tr[normalize-space()][1]"
            . "[following::text()[{$this->eq($this->t('View my trip in full'))}]]";
        // $this->logger->debug('$activityXpath = '.print_r( $activityXpath,true));
        $activityNodes = $this->http->XPath->query($activityXpath);
        // $this->logger->debug('$activityNodes = '.print_r( $activityNodes->length,true));

        $images = $this->http->XPath->query("//text()[{$this->eq($this->t('Your trip so far'))}]/following::text()[normalize-space()][1]/ancestor::*[not({$this->contains($this->t('Your trip so far'))})][last()]//img"
            . "[following::text()[{$this->eq($this->t('View my trip in full'))}]]");
        // $this->logger->debug('$images = '.print_r( $images->length,true));

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Your trip so far'))}]")->length > 0
            && empty($email->getItineraries()) && $images->length === ($rentalNodes->length + $activityNodes->length)
        ) {
            $email->setIsJunk(true, 'Not enought info for reservation');
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $string)
    {
        if (empty($string)) {
            return null;
        }
        $this->logger->debug('$string = ' . print_r($string, true));

        $in = [
            // Oct 10 at 9:05 PM
            // Oct 10 at 9.05 PM
            // Oct 16 2:00 PM
            '/^\s*([[:alpha:]]+)\s+(\d+)(?:\s+[[:alpha:]]+)?\s+(\d{1,2})\W(\d{2}(\s*[ap]m)?)\s*$/ui',
            // Oct 10
            '/^\s*([[:alpha:]]+)\s+(\d+)\s*$/ui',
            // Oct 17, 2024
            '/^\s*([[:alpha:]]+)\s+(\d+)\s*,\s*(\d{4})\s*$/ui',
            '/^\s*([[:alpha:]]+)\s+(\d+)\s*,\s*(\d{4})(?:\s+[[:alpha:]]+)?\s+(\d{1,2})\W(\d{2}(?:\s*[ap]m)?)\s*$/ui',
            // 16 oct. 2024; 23. Okt. 2024
            '/^\s*(\d+)\.?\s+([[:alpha:]]+)\.?\s+(\d{4})\s*$/ui',
            // 16 oct. 2024 , 13:15
            // 23 oct. 2024 à 13 h 25
            // 22. Okt. 2024 um 06:05 Uhr
            '/^\s*(\d+)\.?\s+([[:alpha:]]+)\.?\s+(\d{4})(?:\s*,\s*|\s+[[:alpha:]]+\s+)(\d{1,2})(?:\W| h )(\d{2}(?:\s*[ap]m)?)(?:\s*Uhr)?\s*$/ui',
        ];

        $out = [
            '$2 $1 %year%, $3:$4',
            '$2 $1 %year%',
            '$2 $1 $3',
            '$2 $1 $3, $4:$5',
            '$1 $2 $3',
            '$1 $2 $3, $4:$5',
        ];

        $string = preg_replace($in, $out, trim($string));

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $string, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $string = str_replace($m[1], $en, $string);
            }
        }
        $this->logger->debug('$string = ' . print_r($string, true));

        if (!empty($this->date) && $this->date > strtotime('01.01.2000')
            && strpos($string, '%year%') !== false && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s*,\s(?<time>\d{1,2}:\d{1,2}.*))?$/', $string, $m)) {
            // $this->logger->debug('$date (no week, no year) = '.print_r( $m['date'],true));
            $string = EmailDateHelper::parseDateRelative($m['date'], $this->date);

            if (!empty($string) && !empty($m['time'])) {
                return strtotime($m['time'], $string);
            }

            return $string;
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}(,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $string)) {
            // $this->logger->debug('$date (year) = '.print_r( $str,true));
            return strtotime($string);
        } else {
            return null;
        }

        return null;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function split($re, $text): array
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
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
