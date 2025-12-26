<?php

namespace AwardWallet\Engine\portobay\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservationAt extends \TAccountChecker
{
    public $mailFiles = "portobay/it-583131873.eml, portobay/it-583132092.eml, portobay/it-595648009.eml, portobay/it-598010368.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            // 'Dear Sir/Madam:' => '',
            'reservation has been cancelled' => 'reservation has been cancelled',
            // 'check-in' => '',
            // 'check-out' => '',
            // 'n.' => '',
            // 'adult(s)' => '',
            // 'child(ren)' => '',
            // 'baby(ies)' => '',
            'TOTAL CHARGE' => 'TOTAL CHARGE',
            // 'YOUR INFORMATION' => '',
            // 'HOTEL' => '',
        ],
        'fr' => [
            'Dear Sir/Madam:'                => 'Madame, Monsieur,',
            'reservation has been cancelled' => 'votre réservation a été annulée',
            'check-in'                       => 'check-in',
            'check-out'                      => 'check-out',
            'n.'                             => 'n.',
            'adult(s)'                       => 'adulte(s)',
            'child(ren)'                     => 'enfant(s)',
            'baby(ies)'                      => 'bébé(s)',
            'TOTAL CHARGE'                   => 'MONTANT TOTAL',
            'YOUR INFORMATION'               => 'VOS INFORMATIONS',
            'HOTEL'                          => 'HOTEL',
        ],
    ];

    private $detectFrom = "reservations@portobay.pt";
    private $detectSubject = [
        // en
        'Your reservation at ',
        // fr
        'Votre réservation à ',
        'Votre Réservation au',
    ];
    private $detectBody = [
        'en' => [
            'your reservation is confirmed, please check your details below',
            'your reservation has been cancelled',
        ],
        'fr' => [
            'votre réservation est confirmée. veuillez vérifier les détails ci-dessous',
            'votre réservation a été annulée',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]portobay\.(?:pt|com)/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        // TODO choose case

        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'PORTOBAY') === false
        ) {
            return false;
        }
        // case 1: from and subject

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if ($this->http->XPath->query("//a[{$this->contains(['.portobay.'], '@href')}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["TOTAL CHARGE"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['TOTAL CHARGE'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }

            if (!empty($dict["reservation has been cancelled"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['reservation has been cancelled'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('n.'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('YOUR INFORMATION'))}]/following::text()[normalize-space()][1]")
                ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear Sir/Madam:'))}]", null, true, "/{$this->opt($this->t('Dear Sir/Madam:'))}\s*(.+)/"))
        ;

        if ($this->http->FindSingleNode("(//text()[{$this->contains($this->t('reservation has been cancelled'))}])[1]")) {
            $h->general()
                ->status('Cncelled')
                ->cancelled();
        }

        // Hotel
        $hotelInfo = implode("\n", $this->http->FindNodes("//*[{$this->eq($this->t('HOTEL'))}]/following-sibling::*[normalize-space()][1]//text()[normalize-space()]"));
        // $this->logger->debug('$hotelInfo = '.print_r( $hotelInfo,true));
        if (preg_match("/^\s*(?<name>.+)\n\s*W \d+[°º].+?N \d+.+?(?:”|'')\n\s*(?<address>[\s\S]+?)\n(?<phone>[\d\-\+ \(\)]+)\n\S+@\S+/u", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace('/\s+/', ' ', trim($m['address'])))
                ->phone($m['phone'])
            ;
        }

        // Booked
        $bookedInfo = implode("\n", $this->http->FindNodes("//td[not(.//td)][{$this->contains($this->t('check-in'))}][{$this->contains($this->t('check-out'))}]//text()[normalize-space()]"));
        // $this->logger->debug('$bookedInfo = '.print_r( $bookedInfo,true));
        if (preg_match("/^\s*(?<inDate>.+)-(?<outDate>.+)\n\s*{$this->opt($this->t('check-in'))}\s+(?<inTime>.+?)\s*\.\s*{$this->opt($this->t('check-out'))}\s*(?<outTime>.+?)\s*$/", $bookedInfo, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m['inDate'] . ', ' . $this->normalizeTime($m['inTime'])))
                ->checkOut($this->normalizeDate($m['outDate'] . ', ' . $this->normalizeTime($m['outTime'])))
            ;
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->contains($this->t('adult(s)'))}]");
        // $this->logger->debug('$guests = '.print_r( $guests,true));
        $h->booked()
            ->guests($this->re("/(\d+) {$this->opt($this->t('adult(s)'))}/", $guests))
            ->kids($this->re("/(\d+) {$this->opt($this->t('child(ren)'))}/", $guests)
                + $this->re("/(\d+) {$this->opt($this->t('baby(ies)'))}/", $guests))
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->http->FindSingleNode("//text()[{$this->contains($this->t('adult(s)'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1]/td[1]",
                null, true, "/^\s*\d (\D+.*)/"));

        if ($h->getCancelled() !== true) {
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL CHARGE'))}]/following::text()[normalize-space()][1]");

            if (preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)
                || preg_match("#^\s*(?<currency>[A-Z]{3})\s+(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            ) {
                $h->price()
                    ->total(PriceHelper::parse($m['amount'], $m['currency']))
                    ->currency($m['currency'])
                ;
            } else {
                $h->price()
                    ->total(true);
            }
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

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date): ?int
    {
        $this->logger->debug('date begin = ' . print_r($date, true));

        if (empty($date)) {
            return null;
        }

        $in = [
            // 23 décembre, 2023, 2:00PM
            '/^\s*(\d+)\s+([[:alpha:]]+)\s*[,\s]+\s*(\d{4})[\s,]+\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        $this->logger->debug('date replace = ' . print_r($date, true));

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        $this->logger->debug('date end = ' . print_r($date, true));

        return strtotime($date);
    }

    private function normalizeTime(?string $str): string
    {
        $this->logger->debug('$str = ' . print_r($str, true));
        $in = [
            //12 noon
            '#^(\d+)\s+noon$#ui',
            //3 pm  |  03 P.M.
            '#^(\d+)\s*([ap])(m)$#ui',
        ];
        $out = [
            '$1:00',
            '$1:00$2$3',
        ];
        $str = preg_replace($in, $out, $str);
        $str = str_replace(".", ":", $str);

        if (preg_match("/((\d+):\d+)\s*pm/", $str, $m) && ($h = (int) $m[2]) > 12) {
            $str = $m[1];
        }

        $this->logger->debug('$str = ' . print_r($str, true));

        return $str;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
