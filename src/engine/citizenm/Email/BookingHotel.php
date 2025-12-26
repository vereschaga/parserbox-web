<?php

namespace AwardWallet\Engine\citizenm\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingHotel extends \TAccountChecker
{
    public $mailFiles = "citizenm/it-12579701.eml, citizenm/it-836160674.eml";
    public $subjects = [
        //en
        '/Booking code\s*[A-Z\-\d]+\s*in/u',
        //fr
        '/Numéro de réservation\s*[A-Z\-\d]+\s*à/u',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ['reservation details'],
        "fr" => ['détails de la réservation'],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "fr" => [
            'reservation details' => 'détails de la réservation',
            'booking code'        => 'numéro de réservation',
            'booker'              => 'personne effectuant la réservation',
            'cancellation'        => 'annulation',
            'hotel address'       => 'adresse de l’hôtel',
            'nights / guests'     => 'nuits / personnes',
            'guest(s)'            => 'personne(s)',
            'total price'         => 'prix total',
            'check-in'            => 'check-in',
            'after'               => 'après',
            'check-out'           => 'check-out',
            'before'              => 'avant',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@service.citizenm.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'citizenM')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('reservation details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('hotel address'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('booker'))}]")->length > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]service\.citizenm\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('booking code'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d\-]+)$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('booker'))}]/following::text()[normalize-space()][1]", null, true, "/^([[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]])$/u"))
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('cancellation'))}]/following::text()[normalize-space()][1]"));

        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('hotel address'))}]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<hotelName>.+)\n(?<hotelAddress>.+)$/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['hotelName'])
                ->address($m['hotelAddress']);
        }

        $guestsInfo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('nights / guests'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/\,\s*(?<guests>\d+)\s*{$this->opt($this->t('guest(s)'))}$/", $guestsInfo, $m)) {
            $h->booked()
                ->guests($m['guests']);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('total price'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<total>[\d\.\,\']+)\s*(?<currency>[A-Z]{3})$/", $price, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $inDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('check-in'))}]/following::text()[normalize-space()][1]");
        $inTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('check-in'))}]/following::text()[normalize-space()][2]", null, true, "/{$this->opt($this->t('after'))}\s*([\d\.\:]+\s*A?P?M?h?)$/ui");

        $outDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('check-out'))}]/following::text()[normalize-space()][1]");
        $outTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('check-out'))}]/following::text()[normalize-space()][2]", null, true, "/{$this->opt($this->t('before'))}\s*([\d\.\:]+\s*A?P?M?h?)$/ui");

        $h->booked()
            ->checkIn($this->normalizeDate($inDate . ', ' . $inTime))
            ->checkOut($this->normalizeDate($outDate . ', ' . $outTime));

        $this->detectDeadLine($h);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^\D+\,\s*(\d+\s*\w+\s*\d{4}\,\s*\d+)h$#u", //Lundi, 13 Janvier 2025, 14h
        ];
        $out = [
            "$1:00",
        ];
        $str = preg_replace($in, $out, $str);
        $this->logger->debug('$str = ' . print_r($str, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/cancel free until (?<hours>\d+\s*A?P?M?) day of arrival/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative('0 day', $m['hours']);
        }

        if (preg_match("/annulation gratuite jusqu'à (?<hours>\d+) heures le jour de l'arrivée/u", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative('0 day', $m['hours'] . ':00');
        }

        if (preg_match("/cancel free up to midnight before arrival day/u", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative('0 day', '12:00 AM');
        }
    }
}
