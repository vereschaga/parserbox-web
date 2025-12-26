<?php

namespace AwardWallet\Engine\hotels\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2452696 extends \TAccountCheckerExtended
{
    public $mailFiles = "hotels/it-2341036.eml, hotels/it-2452696.eml, hotels/it-27824489.eml, hotels/it-28008042.eml";

    public static $dictionary = [
        'en' => [
            "Hotels.com Confirmation Number:" => ["Hotels.com Confirmation Number:", "Confirmation Number:"],
            //			"The booking is in the name of" => "",
            //			"Room Type:" => "",
            //			"Check in date :" => "",
            //			"Check out date :" => "",
            //			"Guests:" => "",
            //			"adult" => "",
            //			"children" => "",
            //			"Free cancellation until" => "",
            //			"(?<time>\d{1,2}:\d{2}(?:\s*[AP]M\b)).*?(?<date>[\d\/]{6,})\s*\(" => "(?<time>\d{1,2}:\d{2}(?:\s*[AP]M\b)).*?(?<date>[\d\/]{6,})\s*\(",
            //			"Your cancellation is now confirmed" => "",
        ],
        'nl' => [
            "Hotels.com Confirmation Number:"                                 => "Bevestigingsnummer van Hotels.com:",
            "The booking is in the name of"                                   => "De boeking is op naam van",
            "Room Type:"                                                      => "Kamertype:",
            "Check in date :"                                                 => "Incheckdatum:",
            "Check out date :"                                                => "Uitcheckdatum:",
            "Guests:"                                                         => "Gasten:",
            "adult"                                                           => "volwassene",
            "children"                                                        => "kinderen",
            "Free cancellation until"                                         => "Gratis annuleren kan tot",
            "(?<time>\d{1,2}:\d{2}(?:\s*[AP]M\b)).*?(?<date>[\d\/]{6,})\s*\(" => "(?<time>\d{1,2}.\d{2}).*?(?<date>[\d\-]{6,})\s*\(",
            "Your cancellation is now confirmed"                              => "De annulering is nu bevestigd",
        ],
        'es' => [
            "Hotels.com Confirmation Number:"                                 => "Número de confirmación de Hoteles.com:",
            "The booking is in the name of"                                   => "La reserva se realizó a nombre de",
            "Room Type:"                                                      => "Tipo de habitación:",
            "Check in date :"                                                 => "Fecha de entrada:",
            "Check out date :"                                                => "Fecha de salida:",
            "Guests:"                                                         => "Huéspedes:",
            "adult"                                                           => "adult",
            "children"                                                        => "niño",
            "Free cancellation until"                                         => "Cancelación gratuita hasta el",
            "(?<time>\d{1,2}:\d{2}(?:\s*[AP]M\b)).*?(?<date>[\d\/]{6,})\s*\(" => "(?<date>[\d\/]{6,})\s*\(.*?\)[^.]*?(?<time>\d{1,2}:\d{2})",
            "Your cancellation is now confirmed"                              => 'Tu cancelación está confirmada',
        ],
    ];

    private $detectFrom = '@hotels.com';
    private $detectSubject = [
        'en' => 'Confirm Cancellation Hotels.com Confirmation',
        'nl' => 'De boeking is geannuleerd - bevestigingsnr:',
        'es' => 'Se ha cancelado tu reserva. Número de confirmación:',
    ];

    private $lang = 'en';

    private $detectBody = [
        'en' => 'Your cancellation is now confirmed',
        'nl' => 'De annulering is nu bevestigd',
        'es' => 'Tu cancelación está confirmada',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $dBody) {
            if (stripos($body, $dBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $dSubject) {
            if (stripos($dSubject, $this->detectFrom) !== false) {
                if (strpos($headers['subject'], $dSubject) !== false) {
                    return true;
                }
            } else {
                if (stripos($headers['from'], $this->detectFrom) !== false && strpos($headers['subject'], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (stripos($body, 'hotels.com') === false) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if (stripos($body, $dBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    private function parseHtml(Email $email)
    {
        // Travel agency
        $conf = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Hotels.com Confirmation Number:")) . "]/following::text()[normalize-space()][1]", null, true, "#^\s*([\dA-Z]{5,})\s*$#");
        $email->ota()->confirmation($conf);

        $h = $email->add()->hotel();

        // General
        $h->general()->noConfirmation();
        $guestName = trim(implode(" ", $this->http->FindNodes("//text()[" . $this->eq($this->t("The booking is in the name of")) . "]/following::text()[normalize-space()][1]/ancestor::*[self::strong or self::b]//text()")));
        $h->general()->traveller($guestName);

        if ($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Your cancellation is now confirmed")) . "]")) {
            $h->general()->cancelled();
        }

        // Hotel
        $hotelName = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Room Type:")) . "]/ancestor::tr[2]/preceding-sibling::tr[normalize-space()][2]");
        $h->hotel()->name($hotelName);

        $address = preg_replace("#\s*,\s*#", ', ', implode(" ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Room Type:")) . "]/ancestor::tr[2]/preceding-sibling::tr[normalize-space()][1]//text()[normalize-space()]")));
        $h->hotel()->address($address);

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->normalizeDate($this->http->FindSingleNode("(//td[" . $this->eq($this->t("Check in date :")) . "])[1]/following-sibling::td[1]"))))
            ->checkOut(strtotime($this->normalizeDate($this->http->FindSingleNode("(//td[" . $this->eq($this->t("Check out date :")) . "])[1]/following-sibling::td[1]"))))
            ->guests($this->http->FindSingleNode("(//td[" . $this->eq($this->t("Guests:")) . "])[1]/following-sibling::td[1]", null, true, "#\b(\d+)\s*" . $this->t("adult") . "#"))
            ->kids($this->http->FindSingleNode("(//td[" . $this->eq($this->t("Guests:")) . "])[1]/following-sibling::td[1]", null, true, "#\b(\d+)\s*" . $this->t("children") . "#"))
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->http->FindSingleNode("//td[" . $this->eq($this->t("Room Type:")) . "]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]"))
        ;

        // CancellationPolicy
        $cancellationPolicy = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Free cancellation until")) . "])/following::*[position()<5][self::ul]");

        if (!empty($cancellationPolicy) & preg_match("#^[^\d]+" . $this->t("(?<time>\d{1,2}:\d{2}(?:\s*[AP]M\b)).*?(?<date>[\d\/]{6,})\s*\(") . "#", $cancellationPolicy, $m)
                && !empty($m['date']) && !empty($m['time'])) {
            if (preg_match("#(\d{1,2})/(\d{1,2})/(\d{4})#", $m['date'], $mat)
                    && ($this->lang !== 'en' || ($this->lang === 'en' && $this->http->FindSingleNode("(//a[contains(@href, 'ssl-uk.hotels.com')])[1] | (//text()[contains(., 'ssl-uk.hotels.com')])[1]")))) {
                $m['date'] = str_replace($mat[0], $mat[1] . '.' . $mat[2] . '.' . $mat[3], $m['date']);
            }
            $h->general()->cancellation($cancellationPolicy);
            $h->booked()->deadline(strtotime($this->normalizeDate($m['date'] . ' ' . $m['time'])));
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //		 $this->logger->info($str);
        $in = [
            "#^\s*(\d{1,2})[-/](\d{1,2})[-/](\d{4})\s*(\d+)[:.](\d+)\s*$#", //23-10-2018 23.59
            "#^\s*(\d{1,2})[-/](\d{1,2})[-/](\d{4})\s*$#", //23-10-2018
        ];
        $out = [
            '$1.$2.$3 $4:$5',
            '$1.$2.$3',
        ];
        $str = preg_replace($in, $out, $str);
        //		if ( $this->lang !== 'en' && preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m) ) {
        //			if ( ($en = MonthTranslate::translate($m[1], $this->lang)) || ($en = MonthTranslate::translate($m[1], 'da')) )
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return $str;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
