<?php

namespace AwardWallet\Engine\sonder\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservationPdf extends \TAccountChecker
{
    public $mailFiles = "sonder/it-596808252-fr.eml, sonder/it-587818291-es.eml";

    public $lang = '';

    public static $dictionary = [
        'fr' => [
            'For your stay in' => 'Pour votre séjour à',
            'Reference #'      => 'No de référence',
            // 'at' => '',
            'confNumber'          => ['Code de confirmation'],
            'checkIn'             => ["Date d'arrivée"],
            'checkOut'            => ['Date de départ'],
            'Cancellation Policy' => "Politique d'annulation",
            'priceStart'          => ['Résumé des frais'],
            'priceEnd'            => ['Résumé des paiements'],
            'Total (incl. tax)'   => 'Total TTC',
            'Net amount'          => 'Montant net',
            // 'Taxes' => '',
        ],
        'es' => [
            'For your stay in' => 'Para tu estancia en',
            'Reference #'      => 'Número de referencia',
            // 'at' => '',
            'confNumber'          => ['Código de confirmación'],
            'checkIn'             => ['Fecha de entrada'],
            'checkOut'            => ['Fecha de salida'],
            'Cancellation Policy' => 'Política de cancelación',
            'priceStart'          => ['Resumen de Descuentos y Precios'],
            'priceEnd'            => ['Resumen de pago'],
            'Total (incl. tax)'   => 'Total (impuestos incluidos)',
            'Net amount'          => 'Importe neto',
            'Taxes'               => 'Tasas',
        ],
        'en' => [
            // 'For your stay in' => '',
            // 'Reference #' => '',
            // 'at' => '',
            'confNumber'  => ['Confirmation code'],
            'checkIn'     => ['Check-in date'],
            'checkOut'    => ['Check-out date'],
            // 'Cancellation Policy' => '',
            'priceStart' => ['Charges Summary'],
            'priceEnd'   => ['Payment Summary'],
            // 'Total (incl. tax)' => '',
            // 'Net amount' => '',
            // 'Taxes' => '',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@sonder.com') !== false || stripos($from, '//www.sonder.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (strpos($textPdf, 'Sonder |') === false && strpos($textPdf, 'Sonder at') === false
                && stripos($textPdf, '@sonder.com') === false
                && stripos($textPdf, 'Sonder Hospitality USA Inc') === false // en
                && stripos($textPdf, 'Hospitalité Sonder Canada Inc') === false // fr
                && stripos($textPdf, 'Sonder Stay Mexico S. de R.L. de') === false // es
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('YourReservationPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, $text): void
    {
        $h = $email->add()->hotel();

        $city = $hotelDescription = $roomType = null;

        if (preg_match("/^[ ]*{$this->opt($this->t('For your stay in'))}[ ]+(?<city>.{3,})\n+[ ]*(?<description>\S[\s\S]+\S)\n+[ ]*{$this->opt($this->t('Reference #'))}/m", $text, $m)) {
            $city = $m['city'];
            $hotelDescription = preg_replace('/\s+/', ' ', $m['description']);
        }
        $this->logger->debug('Hotel description: ' . $hotelDescription);

        if (preg_match("/^(?<name>Sonder(?:[ ]+{$this->opt($this->t('at'))}[ ]+|[ ]*\|[ ]*)[^|]{2,}?)[ ]*\|[ ]*(?<type>[^|]{2,})$/i", $hotelDescription, $m)) {
            /*
                Sonder at Battery Park | Spacious Queen Studio
                [or]
                Sonder | Solis | Welcoming 1BR + Sleep Sofa
            */
            $h->hotel()->name($m['name'])->address($city)->house();
            $roomType = $m['type'];
        } elseif ($hotelDescription) {
            /*
                NYC - W - 24TH37 - 1508
            */
            $h->hotel()->name($hotelDescription)->address($city)->house();
        }

        if ($roomType) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        if (preg_match("/\n[ ]*({$this->opt($this->t('confNumber'))})[: ]+([-A-Z\d]{7,16})\n+[ ]*{$this->opt($this->t('checkIn'))}\b/", $text, $m)) {
            $h->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $checkIn = strtotime($this->normalizeDate($this->re("/\n[ ]*{$this->opt($this->t('checkIn'))}[: ]+(.{3,})\n+[ ]*{$this->opt($this->t('checkOut'))}\b/", $text)));
        $checkOut = strtotime($this->normalizeDate($this->re("/\n[ ]*{$this->opt($this->t('checkIn'))}\b.*\n+[ ]*{$this->opt($this->t('checkOut'))}[: ]+(.{3,})$/m", $text)));
        $h->booked()->checkIn($checkIn)->checkOut($checkOut);

        $cancellation = preg_match("/\n[ ]*{$this->opt($this->t('Cancellation Policy'))}\n{0,2}((?:\n[ ]{0,29}\S.*){1,10}?)(?:\n\n|\n[ ]{30}|$)/", $text, $m)
            ? preg_replace('/\s+/', ' ', trim($m[1])) : null;
        $h->general()->cancellation($cancellation, false, true);

        if (preg_match("/Cancell? (?i)or modify more than (?<prior>\d{1,3} days?) before checkin to receive a 100% refund(?:\s*[.!]|$)/", $cancellation, $m) // en
        ) {
            $h->booked()->deadlineRelative($m['prior']);
        }

        $priceText = $this->re("/\n[ ]*{$this->opt($this->t('priceStart'))}\n+([\s\S]+)\n+[ ]*{$this->opt($this->t('priceEnd'))}(?:\n|$)/", $text);
        $totalPrice = preg_match_all("/^[ ]*{$this->opt($this->t('Total (incl. tax)'))}[ ]+(\S.*)$/m", $priceText, $totalMatches) > 0 && count($totalMatches[1]) === 1 ? $totalMatches[1][0] : null;

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // AED 24995.53    |    USD $1333.53
            $currency = preg_match('/^([A-Z]{3})(?:[ ]*[^A-Z])?$/', $matches['currency'], $m) ? $m[1] : $matches['currency'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $h->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $cost = preg_match_all("/^[ ]*{$this->opt($this->t('Net amount'))}[ ]+(\S.*)$/m", $priceText, $costMatches) > 0 && count($costMatches[1]) === 1 ? $costMatches[1][0] : null;

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $cost, $m)) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $taxes = preg_match_all("/^[ ]*{$this->opt($this->t('Taxes'))}[ ]+(\S.*)$/m", $priceText, $taxesMatches) > 0 && count($taxesMatches[1]) === 1 ? $taxesMatches[1][0] : null;

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $taxes, $m)) {
                $h->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['confNumber']) !== false
                && $this->strposArray($text, $phrases['checkIn']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^([[:alpha:]]+)[. ]*(\d{1,2})[ ]*,[ ]*(\d{4})$/u', $text, $m)) {
            // Dec. 12, 2023
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})[. ]*([[:alpha:]]+)[. ]*(\d{4})$/u', $text, $m)) {
            // 30 nov. 2023
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
}
