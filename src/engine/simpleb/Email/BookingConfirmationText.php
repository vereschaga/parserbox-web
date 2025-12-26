<?php

namespace AwardWallet\Engine\simpleb\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmationText extends \TAccountChecker
{
    public $mailFiles = "simpleb/it-281533209.eml, simpleb/it-302054527.eml";

    public $lang = 'en';

    public $reSubject;

    public $detectLang = [
        "it" => ["La prenotazione "],
        "en" => ["your reservation"],
    ];

    public static $dictionary = [
        "en" => [
            'Room #' => ['Room #', 'Apartment #'],
            //'Free cancellation until:' => '',
            'Adult' => ['Adult', 'Adults'],
            //'Cancellation policiy:' => '',
        ],

        "it" => [
            'Here are the details:'    => 'Di seguito il riepilogo:',
            'Customer\'s information:' => 'Informazioni di pagamento:',
            'Name:'                    => 'Titolare:',
            'Reservation Nr.:'         => 'N. prenotazione:',
            //'Free cancellation until:' => '',
            'Cancellation policiy:' => 'Termini di cancellazione:',
            'NOTE:'                 => 'Note:',
            'Booking Confirmation'  => 'Conferma prenotazione',
            'Tel.:'                 => 'Tel.:',
            'Arrival:'              => 'Data Arrivo:',
            'Departure:'            => 'Data Partenza:',
            'Room #'                => 'Camera #',
            'guests:'               => 'ospiti:',
            'Adult'                 => 'Adulti',
            'Total amount :'        => 'Importo totale prenotazione:',
            //'Total additional services' => '',
            'Total rooms:' => 'Totale camere:',
            'daily rates:' => 'tariffe giornaliere:',
            'night'        => 'notte',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        $text = $parser->getBody();

        if ((stripos($text, 'SimpleBooking') !== false || stripos($text, 'simplebooking.it') !== false)
            && stripos($text, $this->t('Here are the details:')) !== false
            && stripos($text, '#1') !== false
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]simplebooking\.it$/', $from) > 0;
    }

    public function ParseHotel(Email $email, string $text)
    {
        $h = $email->add()->hotel();

        $traveller = $this->re("#{$this->opt($this->t('Customer\'s information:'))}\s*\n\-+\s*\n{$this->opt($this->t('Name:'))}\s*([[:alpha:]][-&.\'’[:alpha:] ]*[[:alpha:]])#u", $text);

        $h->general()
            ->confirmation($this->re("/{$this->opt($this->t('Reservation Nr.:'))}\s*(\d+)/", $text))
            ->traveller($traveller);

        if (stripos($text, $this->t('Free cancellation until:')) !== false) {
            $h->general()
                ->cancellation($this->re("/({$this->opt($this->t('Free cancellation until:'))}\s*.+)/", $text));
        } else {
            $cancellation = $this->re("/[-]+\s*\n*{$this->opt($this->t('Cancellation policiy:'))}\s*\n*[-]+\s*\n*(.+)\n*\-+\s*\n*{$this->opt($this->t('NOTE:'))}/u", $text);

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation(str_replace($this->t('Cancellation Policy:'), '', strip_tags($cancellation)));
            }
        }

        $hotelName = $this->re("/^\s*(.+)\s*\-\s*{$this->opt($this->t('Booking Confirmation'))}/iu", $this->reSubject);

        if (!empty($hotelName)) {
            $h->hotel()
                ->name($hotelName);
        }

        if (preg_match("/(?:{$h->getHotelName()}|\D*)\s*\n(?<address>(?:.+\n){1,2}){$this->opt($this->t('Tel.:'))}\s*\n*(?<phone>[+\d\s\)\(\-\/]+)\n/u", $text, $m)) {
            $h->hotel()
                ->address(str_replace("\n", " ", $m['address']))
                ->phone($m['phone']);
        }

        if (preg_match("/{$this->opt($this->t('Arrival:'))}\s+(?<checkIn>.+)\s+\-\s+{$this->opt($this->t('Departure:'))}\s*(?<checkOut>.+)/", $text, $m)) {
            $h->booked()->checkIn($this->normalizeDate($m['checkIn']))
                ->checkOut($this->normalizeDate($m['checkOut']));
        }

        $this->detectDeadLine($h);

        if (preg_match_all("/{$this->opt($this->t('Room #'))}\d+\:\s*(.+)/", $text, $m)) {
            $h->booked()
                ->rooms(count($m[1]));

            $rate = $this->re("#\n*.*\[([\d\.\,]+\s*[A-Z]{3}\s*\/\s*{$this->opt($this->t('night'))})\]#u", $text);

            foreach ($m[1] as $roomType) {
                $h->addRoom()->setType($roomType)
                ->setRate($rate);
            }
        }

        if (preg_match_all("/{$this->opt($this->t('guests:'))}\s*(\d+)\s*{$this->opt($this->t('Adult'))}/", $text, $m)) {
            $h->booked()
                ->guests(array_sum($m[1]));
        }

        $priceText = $this->re("/{$this->opt($this->t('Total amount :'))}\s*([\d\.\,]+\s*[A-Z]{3})/", $text);

        if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})$/", $priceText, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        $tax = $this->re("/{$this->opt($this->t('Total additional services'))}\s*([\d\.\,]+)/", $text);

        if ($tax !== null) {
            $h->price()
                ->tax(PriceHelper::parse($tax, $m['currency']));
        }

        $cost = $this->re("/{$this->opt($this->t('Total rooms:'))}\s*([\d\.\,]+)/", $text);

        if ($cost !== null) {
            $h->price()
                ->cost(PriceHelper::parse($cost, $m['currency']));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $text = $parser->getBody();

        $this->reSubject = $parser->getSubject();

        $this->ParseHotel($email, $text);

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/^If cancelled up to (\d+\s*days) before date of arrival/', $cancellationText, $m)
            || preg_match('/If canceled or modified up to (\d+\s*days) before date of arrival/', $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m[1]);
        }

        if (preg_match('/^Free cancellation until\:\s*(\d+\s*\w+\s*\d{4}\s*\d+\:\d+)/', $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m[1]));
        }
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $key => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $key;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+\s*\w+\s*\d{4})$#u", //08 ott 2023
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
