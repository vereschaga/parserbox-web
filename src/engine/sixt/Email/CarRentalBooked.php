<?php

namespace AwardWallet\Engine\sixt\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CarRentalBooked extends \TAccountChecker
{
    public $mailFiles = "sixt/it-12374541.eml, sixt/it-149410197.eml";
    public $detectSubject = [
        // en
        'SIXT car rental booked for ',
    ];

    public $detectBody = [
        'en' => [
            'Your booking is confirmed!',
            'Your booking has been changed!',
        ],
        'fr' => [
            'Votre réservation est confirmée !',
        ],
    ];

    public $lang = '';
    public static $dict = [
        'en' => [
            'statusPhrases'  => ['Your booking is', 'Your booking has been'],
            'statusVariants' => ['confirmed', 'changed'],
            //            'Reservation ID' => '',
            //            'or similar' => '',
            //            'Pick up location' => '',
            //            'Return location' => '',
            //            'Opening hours:' => '',
        ],
        'fr' => [
            'statusPhrases'  => ['Votre réservation est'],
            'statusVariants' => ['confirmée'],
            //            'Reservation ID' => '',
            //            'or similar' => '',
            'Pick up location'                     => 'Lieu de prise en charge',
            'Return location'                      => 'Lieu de retour',
            'Opening hours:'                       => "Heures d'ouverture:",
            'Estimated total'                      => 'Total payé',
            'as a refundable deposit upon pick-up' => 'comme caution remboursable lors de la prise en charge',
        ],
    ];

    private $detectFrom = ["@sixt."];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->detectBody()) {
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
        if ($this->http->XPath->query("//img[contains(@alt,'Sixt Logo') or contains(@src,'.sixt.com')] | //a[contains(@href,'.sixt.com')]")->length > 0) {
            return $this->detectBody();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->detectSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false) {
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
        $r = $email->add()->rental();

        // General
        $status = null;
        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $r->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation ID'))}]");

        if (preg_match("/({$this->opt($this->t('Reservation ID'))})\s*(\d{5,})\s*$/", $confirmation, $m)) {
            $r->general()->confirmation($m[2], $m[1]);
        }

        $info = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Reservation ID'))}]/ancestor::*[count(.//img) = 3][1]//tr[not(.//tr)][normalize-space()]"));

        if (preg_match("/^\s*(?<cartype>.+)\n(?<carmodel>.+) {$this->opt($this->t('or similar'))}\n{$this->opt($this->t('Reservation ID'))} *\d+\n(?<pu>.+)\n(?<pudate>.*\d{4} *\|.*)\n(?<do>.+)\n(?<dodate>.*\d{4} *\|.*)/", $info, $m)) {
            $img = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation ID'))}]/ancestor::*[count(.//img) = 3][1]/descendant::img[1][@width='111']/@src");

            if (empty($img)) {
                $img = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Reservation ID')]/preceding::img[1]/@src");
            }
            $r->car()
                ->type($m['cartype'])
                ->model($m['carmodel'])
                ->image($img)
            ;

            $r->pickup()
                ->location($m['pu'])
                ->date($this->normalizeDate($m['pudate']));

            $r->dropoff()
                ->location($m['do'])
                ->date($this->normalizeDate($m['dodate']));
        }

        $pickUp = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Pick up location'))}]/ancestor::*[not({$this->eq($this->t('Pick up location'))})][1]//tr[not(.//tr)][normalize-space()]"));

        if (preg_match("/{$this->opt($this->t('Pick up location'))}\s+(?<location>[\s\S]+)\s*\n\s*{$this->opt($this->t('Opening hours:'))} *(?<hours>.+)/", $pickUp, $m)) {
            $r->pickup()
                ->location(preg_replace("/\s*\n\s*/", ', ', $m['location']))
                ->openingHours(trim($m['hours'], '-'), true, true);
        }
        $dropOff = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Return location'))}]/ancestor::*[not({$this->eq($this->t('Return location'))})][1]//tr[not(.//tr)][normalize-space()]"));

        if (preg_match("/{$this->opt($this->t('Return location'))}\s+(?<location>[\s\S]+)\s*\n\s*{$this->opt($this->t('Opening hours:'))} *(?<hours>.+)/", $dropOff, $m)) {
            $r->dropoff()
                ->location(preg_replace("/\s*\n\s*/", ', ', $m['location']))
                ->openingHours(trim($m['hours'], '-'), true, true);
        }

        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Estimated total'))}]/ancestor::*[not({$this->eq($this->t('Estimated total'))})][1]",
            null, true, "/{$this->opt($this->t('Estimated total'))}\s*(.+)/");

        if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*) *\+ \\1 *(?<amount2>\d[\d\., ]*)\s+[[:alpha:]]+\s*$/u", $total, $m)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*\+\s*(?<amount2>\d[\d\., ]*)\s*\\2\s+[[:alpha:]]+\s*$/u", $total, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $r->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['amount'], $currencyCode) + PriceHelper::parse($m['amount2'], $currencyCode))
            ;
        } elseif (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/u", $total, $m)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*\s*$/u", $total, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $r->price()->currency($currency)->total(PriceHelper::parse($m['amount'], $currencyCode));

            if (preg_match("/(?<deposit>[\d\.\,]+)\s*{$this->opt($this->t('as a refundable deposit upon pick-up'))}/u", $this->http->FindSingleNode("//text()[{$this->contains($this->t('as a refundable deposit upon pick-up'))}]"), $m)) {
                $r->price()->total($r->getPrice()->getTotal() - PriceHelper::parse($m['deposit'], $currencyCode));
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody(): bool
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('IN-'.$date);
        $in = [
            // Sun, Apr 24, 2022 | 2 PM
            '/^\s*\w+[,]?\s+([[:alpha:]]+)\s*(\d+)[,]?\s+(\d{4})\s*\|\s*(\d{1,2})\s*([AP]M)\s*$/iu',
            // Wed, Apr 13, 2022 | 10:30 AM
            '/^\s*\w+\S*\s+([[:alpha:]]+)\s*(\d+)[,.]?\s+(\d{4})\s*\|\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/iu',
        ];
        $out = [
            '$2 $1 $3, $4:00 $5',
            '$2 $1 $3, $4',
        ];

        $str = preg_replace($in, $out, $date);
        //$this->logger->debug('OUT-'.$str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'NOK' => ['kr'],
            'AUD' => ['A$'],
            'USD' => ['US$'],
            'ZAR' => ['R'],
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
