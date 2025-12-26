<?php

namespace AwardWallet\Engine\iberostar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "iberostar/it-797321906.eml";
    public $subjects = [
        'BOOKING CONFIRMATION',
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            "namePrefix" => '^(?:Mr\/Ms|Mrs|Mr|Ms)',
        ],
        'es' => [
            'Iberostar Hotels'      => 'hoteles Iberostar',
            'Reservations summary'  => 'Resumen de su reserva',
            'Type of accommodation' => 'Tipo de alojamiento',
            'Cancellation Policy'   => 'Política de Cancelación',

            'Dear'                => 'Apreciado/a',
            'Membership number'   => 'Número de membresía',
            'Confirmation Number' => 'Número de confirmación',
            'Date'                => 'Fecha',
            'Hotel'               => 'Hotel',
            'Nr. of guests'       => 'Nº de personas',
            'Check-in date'       => 'Fecha de entrada',
            'Check-out date'      => 'Fecha de salida',
            'Total'               => 'Total',
            'Adults'              => 'Adultos',
        ],
    ];

    public $detectLang = [
        "es" => ['Resumen de su reserva'],
        "en" => ['Reservations summary'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@iberostar.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]iberostar\.com$/', $from) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Iberostar Hotels'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Reservations summary'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Type of accommodation'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Cancellation Policy'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]/ancestor::tr[1]", null, true, "#^{$this->opt($this->t('Dear'))}\s*(.+)\,#");

        if (!empty($traveller)) {
            $h->general()
                ->traveller(preg_replace("/^(?:Mr\/Ms|Mrs|Mr|Ms|Sr\.\/a)/", "", $traveller));
        }

        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Membership number'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (!empty($account)) {
            $h->addAccountNumber($account, false, preg_replace("/^(?:Mr\/Ms|Mrs|Mr|Ms|Sr\.\/a)/", "", $traveller));
        }

        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Confirmation Number'))}]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $bookingInfo = $this->http->FindSingleNode(".", $root);

            if (preg_match("/^\s*(?<desc>{$this->opt($this->t('Confirmation Number'))})\s*(?<number>[A-Z\d]+)\s*$/mi", $bookingInfo, $m)) {
                $h->general()
                    ->confirmation($m['number'], $m['desc'])
                    ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][1]"))
                    ->date(strtotime($this->http->FindSingleNode("//text()[{$this->eq($this->t('Date'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]")));
            }

            $name = $this->http->FindSingleNode("./following-sibling::tr[1][{$this->contains($this->t('Hotel'))}]", $root, true, "/{$this->opt($this->t('Hotel'))}\s*(.+)/");

            if (!empty($name)) {
                $h->setHotelName($name);
            }

            $address = '';

            if (stripos($name, 'Iberostar Waves Paraíso del Mar') !== false) {
                $address = 'Carretera Chetumal Puerto Juarez, Km. 309, Playa Paraiso, Playa del Carmen 77710 Mexico';
            }

            if (stripos($name, 'Iberostar Selection Bávaro Suites') !== false || stripos($name, 'JOIA Bávaro by Iberostar') !== false) {
                $address = 'Carr. El Macao - Arena Gorda, Bavaro, Punta Cana 23000 Dominican Republic';
            }

            if (stripos($name, 'Iberostar Selection Rose Hall Suites') !== false) {
                $address = 'Rose Hall Main Road, Montego Bay Jamaica';
            }

            if (!empty($address)) {
                $h->setAddress($address);
            }

            $guestsCount = $this->http->FindSingleNode("./following-sibling::tr[2][{$this->contains($this->t('Nr. of guests'))}][1]", $root, true, "/{$this->opt($this->t('Nr. of guests'))}\s*(\d+)\s+{$this->opt($this->t('Adults'))}$/m");

            if (!empty($guestsCount)) {
                $h->setGuestCount(intval($h->getGuestCount()) + intval($guestsCount));
            }

            $checkIn = $this->http->FindSingleNode("./following-sibling::tr[2][{$this->contains($this->t('Check-in date'))}]/descendant::td[1]/following-sibling::td[normalize-space()][1]", $root, true, "/^([\d\/]+)$/");
            $checkOut = $this->http->FindSingleNode("./following-sibling::tr[3][{$this->contains($this->t('Check-out date'))}]/descendant::td[1]/following-sibling::td[normalize-space()][1]", $root, true, "/^([\d\/]+)$/");

            if (!empty($checkIn) && !empty($checkOut)) {
                $h->booked()
                    ->checkIn(strtotime($checkIn))
                    ->checkOut(strtotime($checkOut));
            }

            // collect total
            $totalText = $this->http->FindSingleNode("./following-sibling::tr[5][{$this->contains($this->t('Total'))}]/descendant::td[1]/following-sibling::td[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,\']+)\s*$/m", $totalText, $m)
                || preg_match("/^\s*(?<total>[\d\.\,\']+)\s*(?<currency>[A-Z]{3})\s*$/m", $totalText, $m)) {
                $h->price()
                    ->currency($m['currency'])
                    ->total(floatval($h->getPrice()->getTotal()) + floatval(PriceHelper::parse($m['total'], $m['currency'])));
            }

            $roomType = $this->http->FindSingleNode("./following-sibling::tr[4][{$this->contains($this->t('Type of accommodation'))}]/descendant::td[1]/following-sibling::td[1]", $root);

            if (!empty($roomType)) {
                $h->addRoom()->setType($roomType);
            }

            $this->detectDeadLine($h);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();
        $this->parseHotel($email);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): bool
    {
        $cancellationText = $h->getCancellation();

        if (empty($cancellationText)) {
            return false;
        }

        if (
            preg_match("#If notice of cancellation is received more than (?<day>\d+) days? before arrival\/check-in date\, 0 nights#i", $cancellationText, $m)
            || preg_match("#Para cancelaciones realizadas con más de (?<day>\d+) días? de antelación a la fecha de entrada\, se facturará el importe de 0 noches\.#i", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['day'] . ' day');

            return true;
        }

        return false;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function AssignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
