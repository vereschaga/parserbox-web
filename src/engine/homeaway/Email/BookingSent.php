<?php

namespace AwardWallet\Engine\homeaway\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingSent extends \TAccountChecker
{
    public $mailFiles = "homeaway/it-85567595.eml, homeaway/it-95536025.eml";

    private $lang = '';
    private $reFrom = ['.homeaway.com'];
    private $reProvider = ['Vrbo'];
    private $reSubject = [
        'Your reservation was cancelled',
        'Here are the keys to your',
        'aqui estão as chaves das suas férias',
    ];
    private $detectLang = [
        'en' => [
            'Your reservation was cancelled',
            'Here are the keys to your',
            'has invited you to join a trip',
        ],
        'pt' => [
            'Endereço',
            'Informações de chegada',
        ],
    ];

    private static $dictionary = [
        'en' => [
            'addressText'   => ['Property ID', 'Reservation ID'],
            'cancelledText' => [
                'Your reservation was cancelled',
            ],
            'kid'   => ['kid', 'child'],
            "house" => [
                "House", "Hotel", "Townhome", "Farmhouse", "Villa", "Cottage", "Condo",
                "Bungalow", "Apartment", "Cabin", "Chalet", "Studio", "campground", "Lodge",
                "Townhouse", "Resort",
            ],
        ],

        'pt' => [
            'Address'        => 'Endereço',
            'Arrive'         => 'Chegada',
            'Depart'         => 'Saída',
            'Guests'         => 'Hóspedes',
            'adult'          => 'adultos',
            'kid'            => 'crianças',
            'Reservation ID' => 'N° da reserva',
            'cancelledText'  => [
                'Your reservation was cancelled',
            ],
            "house" => [
                "House", "Hotel", "Townhome", "Farmhouse", "Villa", "Cottage", "Condo",
                "Bungalow", "Apartment", "Cabin", "Chalet", "Studio", "campground", "Lodge",
                "Townhouse", "Resort", "Lugar",
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $this->parseHotel($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    protected function parseHotel(Email $email)
    {
        $r = $email->add()->hotel();
        $r->hotel()->house();
        $address = implode(', ', $this->http->FindNodes("(//*[{$this->eq($this->t('Address'))}]/following::*[normalize-space()])[1]//text()[normalize-space()]"));

        if (empty($address)) {
            $address = $this->http->FindSingleNode("(//*[{$this->contains($this->t('addressText'), 'text()')}])[1]/preceding::*[self::h2 or self::h3][normalize-space()][1]");
        }

        $urlPage = $this->http->FindSingleNode("(//*[{$this->contains($this->t('Property ID'))}]/following::a[1]/@href)[1]");

        if (empty($urlPage)) {
            $urlPage = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Arrive'))}])[1]/preceding::h2[1]/preceding::a[1]/@href");
        }
//        $this->logger->debug('$urlPage = '.$urlPage);
        if ($this->arrikey($urlPage, ['//link.messages.homeaway.com/', '//t.hmwy.io/', '//t.vrbo.io/', '%2F%2Ft.vrbo.io%2F', '%2F%2Ft.hmwy.io%2F']) !== false) {
            $browser = new \HttpBrowser("none", new \CurlDriver());
            $browser->GetURL($urlPage);
//             print_r($browser->Response['body']);
            $name = $browser->FindSingleNode("//h1[contains(@class,'property-headline') or ancestor::div[@class = 'property-headline-expanded__headline']]");

            if (empty($name)) {
                $keywords = [];

                foreach ((array) $this->t('house') as $phrase) {
                    $keywords[] = $phrase;
                    $keywords[] = mb_strtolower($phrase);
                    $keywords[] = mb_strtoupper($phrase);
                }

                if (count($keywords) === 0) {
                    return [];
                }
                $name = $browser->FindSingleNode("//text()[{$this->eq($keywords)} and ancestor::li]/preceding::h1");

                if (empty($address)) {
                    $address = $browser->FindSingleNode("//div[@class = 'Description--location']");
                }
            }

            if (!empty($name)) {
                $r->hotel()->name($name);
            }
        }

        if (empty($r->getHotelName())) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation ID'))}]/preceding::text()[string-length()>1][1]");

            if (!empty($hotelName)) {
                $r->hotel()
                    ->name($hotelName);
            }
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Arrive'))}])[1]/preceding::h2[1]");
        }

        $r->hotel()->address($address);

        $r->booked()->checkIn2($this->normalizeDate($this->http->FindSingleNode("(//*[{$this->eq($this->t('Arrive'))}]/following::text()[normalize-space()])[1]")));
        $r->booked()->checkOut2($this->normalizeDate($this->http->FindSingleNode("(//*[{$this->eq($this->t('Depart'))}]/following::text()[normalize-space()])[1]")));

        $time = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Check-in time'))}]/following::text()[normalize-space()])[1]");

        if (!empty($time) && !empty($r->getCheckInDate())) {
            $r->booked()->checkIn(strtotime($time, $r->getCheckInDate()));
        }

        $time = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Check-out time'))}]/following::text()[normalize-space()])[1]");

        if (!empty($time) && !empty($r->getCheckOutDate())) {
            $r->booked()->checkOut(strtotime($time, $r->getCheckOutDate()));
        }
        $guests = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Guests'))}]/following::text()[normalize-space()])[1]");

        if (preg_match("/\s*(\d+)\s*{$this->opt($this->t('adult'))}/", $guests, $m)) {
            $r->booked()->guests($m[1]);
        }

        if (preg_match("/\s*(\d+)\s*{$this->opt($this->t('kid'))}/", $guests, $m)) {
            $r->booked()->kids($m[1]);
        }

        $conf = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Reservation ID'))}]/following::text()[normalize-space()])[1]");

        if (empty($conf) && !empty($this->http->FindSingleNode("(//*[{$this->contains($this->t('has invited you to join a trip'))}])[1]"))) {
            $r->general()->noConfirmation();
        } else {
            $r->general()->confirmation($conf);
        }

        if ($this->http->FindSingleNode("//h1[{$this->contains($this->t('cancelledText'))}]")) {
            $r->general()->cancelled();
        }
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) {
            return "contains(" . $text . ", \"{$s}\")";
        }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '. $date);
        if (preg_match('/^\s*(\d{1,2}\/\d{1,2}\/\d{4})\s*$/', $date)
            && !empty($this->http->FindSingleNode("(//a[contains(@href, '.homeaway.com.au')])[1]"))
        ) {
            return str_replace('/', '.', $date);
        }

        return $date;
    }
}
