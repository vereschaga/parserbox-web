<?php

namespace AwardWallet\Engine\norwegiancruise\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Event extends \TAccountChecker
{
    public $mailFiles = "norwegiancruise/it-137318854.eml, norwegiancruise/it-137318952.eml, norwegiancruise/it-244695141.eml";

    public $lang = '';

    private $detectSubjects = [
        // en
        'Shore Excursion Confirmation for Reservation #',
        'Shore Excursion Confirmation for Reservation #',
        // de
        'Landausflugsbestätigung für die Reservierung #',
    ];

    private $pdfPattern = '.*confirmation.*pdf';
    private static $dictionary = [
        'en' => [
            'SHORE EXCURSION CONFIRMATION' => 'SHORE EXCURSION CONFIRMATION',
            //            'Reservation #:' => '',
            //            'Currency:' => '',
            //            'Grand Totals' => '',
            //            'Total Net Price:' => '',
            //            'Guest No.' => '',
            //            'Tour' => '',
            //            'Description' => '',
            'tourEnd' => [
                'Total Net Price:',
                'Total Discount Applied:',
                'Items purchased are not confirmed until payment has been received',
                'Your shore excursion vouchers will be delivered to your stateroom at or shortly after embarkation',
            ],
        ],
        'de' => [
            'SHORE EXCURSION CONFIRMATION' => 'AUSFLUGSBESTÄTIGUNG',
            'Reservation #:'               => 'Reservierung #:',
            'Confirmation Date:'           => 'Bestätigungsdatum:',
            'Currency:'                    => 'Währung:',
            'Grand Totals'                 => 'Grand Totals',
            'Total Net Price:'             => 'Gesamtbetrag:',
            'Guest No.'                    => 'Gast',
            'Tour'                         => 'Ausflug',
            'Description'                  => 'Beschreibung',
            'tourEnd'                      => 'Gesamtbetrag:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubjects) {
            if (stripos($headers['subject'], $dSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            // Detect Provider (PDF)
            if (stripos($textPdf, 'www.ncl.com') === false
                && stripos($textPdf, 'NCL (Bahamas) Ltd.') === false
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Norwegian Cruise Line') !== false
            || stripos($from, '@ncl.com') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($textPdf)) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    public function parsePdf(Email $email, $text): void
    {
        $email->ota()->confirmation($this->re("/" . $this->opt($this->t('Reservation #:')) . " *(\d{3,})\s*/", $text));

        $currencyMain = $this->re("/\b" . $this->opt($this->t('Currency:')) . " *([A-Z]{3})\s*\n/", $text);

        if ($this->re("/(" . $this->opt($this->t('Grand Totals')) . ")/", $text)) {
            $email->price()
                ->total(PriceHelper::parse($this->re("/\n\s*" . $this->opt($this->t('Grand Totals')) . ".*[\s\S]+?\n\s*" . $this->opt($this->t('Total Net Price:')) . "\s*\D{0,5}(\d[\d,. ]*)\D{0,5}(\n|$)/",
                    $text), $currencyMain))
                ->currency($currencyMain);
        }

        $guestsSegments = $this->split("/\n((?:[ ]*{$this->opt($this->t('Guest No.'))}[ ]*\d{1,3}\b.*\n+)+)/", $text);

        $events = [];
        $travellersAll = [];

        foreach ($guestsSegments as $gtext) {
            $traveller = null;

            if (preg_match_all("/^[ ]*{$this->opt($this->t('Guest No.'))}[ ]*\d{1,3}[ ]{3,}([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]\.?)(?:[ ]{3}|$)/mu", $gtext, $travellerMatches)) {
                $traveller = $travellerMatches[1][0];
                $travellersAll = array_merge($travellersAll, $travellerMatches[1]);
            }

            $toursText = $this->re("/\n[ ]*{$this->opt($this->t('Tour'))}[ ]+{$this->opt($this->t('Description'))}.+\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('tourEnd'))}/", $gtext);
            $tours = $this->split("/(?:^|\n)( {0,10}[A-Z\d]{4,} {2})/", $toursText);

            foreach ($tours as $ttext) {
                if (preg_match("/^[ ]*(?<confirmation>[A-Z\d]{5,})[ ]{2,}(?<name>\S.+?)[ ]{2,}(?<address>\S.+?)[ ]{2,}(?<date>\S.+?)[ ]{2,}.*?(?:[ ]{2}(?<price>\S.+?))?(?:\n|$)/", $ttext, $m)) {
                    if (!array_key_exists('price', $m)) {
                        $m['price'] = null;
                    }

                    $newEvent = true;
                    $m['date'] = strtotime($m['date']);

                    if (isset($events[$m['confirmation']])) {
                        foreach ($events[$m['confirmation']] as $i => $mevent) {
                            if ($mevent['name'] === $m['name'] && $mevent['address'] === $m['address'] && $mevent['date'] === $m['date']) {
                                $events[$m['confirmation']][$i]['travellers'][] = $traveller;
                                $events[$m['confirmation']][$i]['prices'][] = $m['price'];
                                $newEvent = false;

                                break;
                            }
                        }
                    }

                    if ($newEvent) {
                        $events[$m['confirmation']][] = [
                            'name'       => $m['name'],
                            'address'    => $m['address'],
                            'date'       => $m['date'],
                            'prices'     => [$m['price']],
                            'travellers' => $travellersAll,
                        ];
                    }
                }
            }
        }

        $bookingDate = strtotime($this->re("/" . $this->opt($this->t('Confirmation Date:')) . " {0,15}(\d[\d\\/]+)(?: {2,}|\n)/", $text));

        foreach ($events as $conf => $cevent) {
            foreach ($cevent as $event) {
                $ev = $email->add()->event();

                // General
                $ev->general()
                    ->confirmation($conf)
                    ->travellers($event['travellers'], true);

                if (!empty($bookingDate)) {
                    $ev->general()
                        ->date($bookingDate);
                }

                // Place
                $ev->place()
                    ->type(EVENT_EVENT)
                    ->address($event['address'])
                    ->name($event['name']);

                // Booked
                $ev->booked()
                    ->start($event['date'])
                    ->noEnd();

                if (count(array_filter($event['prices'], function ($item) { return $item !== null; })) === 0) {
                    continue;
                }

                $total = 0.0;
                $prices = preg_replace("/^\D+(\d[\d,. ]*)\D*$/", '$1', $event['prices']);
                $currency = $currencyMain ?? null;

                if (empty($currency)) {
                    $currencies = array_unique(preg_replace("/\s*(\d[\d,. ]*)\s*/", '', $event['prices']));

                    if (count($currencies) == 1) {
                        $currency = $this->currency(array_shift($currencies));
                    }
                }

                foreach ($prices as $price) {
                    $total += (float) PriceHelper::parse(trim($price), $currency);
                }

                $ev->price()
                    ->total($total)
                    ->currency($currency);
            }
        }
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!empty($phrases['SHORE EXCURSION CONFIRMATION']) && $this->strposArray($text, $phrases['SHORE EXCURSION CONFIRMATION']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = strpos($text, $phrase);

            if ($result !== false) {
                return $result;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
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

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
