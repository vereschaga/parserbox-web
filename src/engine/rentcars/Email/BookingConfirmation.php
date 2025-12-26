<?php

namespace AwardWallet\Engine\rentcars\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "rentcars/it-119458836.eml, rentcars/it-120601163.eml, rentcars/it-131969955.eml, rentcars/it-133621269.eml, rentcars/it-49371823.eml";

    private static $detectors = [
        'pt' => ["Detalhes da sua reserva", "Clique aqui para imprimir sua reserva"],
        'es' => ["Detalles de tu Reserva"],
        'en' => ["Booking Details"],
    ];

    private static $dictionary = [
        'pt' => [
            "Reservation"                  => "Reserva:",
            "Sua reserva para"         => "Sua reserva para",
            "is confirmed"                 => "está confirmada",
            "Responsible for the lease"    => ["Responsável pela locação:", 'Responsável pelo aluguer:'],
            "Pickup"                       => ["Retirada:", "Levantamento:"],
            "at"                           => "às",
            "Dropoff"                      => "Devolução:",
            "ou similar"                        => "ou similar",
            "Total"                        => ["O valor de", "O valor integral de"],
//            "Você pagou antecipadamente" => "", // 2 total value: prepaid and must be paid
//            "O valor restante de" => "",// 2 total value: prepaid and must be paid
            "Phone to contact the rental:" => "Telefone para contato com a locadora:",
        ],
        'es' => [
            "Reservation"                  => "Reserva:",
            "Sua reserva para"         => "Tu reserva para",
            "is confirmed"                 => "sido confirmada",
            "Responsible for the lease"    => "Titular de la Reserva:",
            "Pickup"                       => "Recogida:",
            "at"                           => "a las",
            "Dropoff"                      => "Devolución:",
            "ou similar"                        => "ou similar",
            "Total"                        => "El valor integro de",
//            "Você pagou antecipadamente" => "", // 2 total value: prepaid and must be paid
//            "O valor restante de" => "",// 2 total value: prepaid and must be paid
            "Phone to contact the rental:" => "Teléfono de contacto de la compañía de alquiler:",
        ],
        'en' => [
            "Reservation"                  => "Booking:",
            "Sua reserva para"         => "Your booking for",
            "is confirmed"                 => "has been confirmed",
            "Responsible for the lease"    => "Booking Holder:",
            "Pickup"                       => "Pick-up:",
            "at"                           => "at",
            "Dropoff"                      => "Drop-off:",
            "ou similar" => "or similar",
            "Total"                        => ["Your booking´s full amount", "The amount of"],
//            "Você pagou antecipadamente" => "", // 2 total value: prepaid and must be paid
//            "O valor restante de" => "",// 2 total value: prepaid and must be paid
            "Phone to contact the rental:" => "Contact Number for rental company:",
        ],
    ];

    private $from = "bookings@rentcars.com";

    private $body = "Rentcars.com";

    private $subject = [
        // es, pt
        "Confirmação de Reserva",
        // en
        "Booking Confirmation - ",
    ];

    private $lang;

     private $providersImageNames = [
        'alamo' => ['alamo'],
        'avis' => ['avis'],
        'dollar' => ['dollar'],
        'europcar' => ['europcar'],
        'hertz' => ['hertz'],
        'national' => ['enterprise'],
        'rentacar' => ['national'],
        'sixt' => ['sixt'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, $this->body) === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->detectBody()) {
            return false;
        }


        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language");
            return $email;
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->rental();

        // General
        $r->general()->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation")) . "]/following::text()[normalize-space()][1]", null, true,'/^\s*([A-Z\d]+)\s*$/'),
            trim($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation")) . "]"), ':'));

        $status = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Sua reserva para")) . "]", null,
            false, "/" . $this->opt($this->t("is confirmed")) . "/");

        if (!empty($status)) {
            $r->general()->status($status);
        }

        $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Responsible for the lease")) . "]/ancestor::td[1]/text()[normalize-space()]");

        if (!empty($traveller)) {
            $r->general()->traveller($traveller, true);
        }

        // Dropp Off
        $dropoff = $this->http->FindSingleNode(" //text()[" . $this->starts($this->t("Dropoff")) . "]/ancestor::td[2]/descendant::td[2]");
        if (!empty($dropoff)) {
            if (preg_match("/{$this->opt($this->t('Dropoff'))}[\s]?(?<location>.+)\s(?<date>(?:\d{1,2}|[A-z]{3})-(?:\d{1,2}|[A-z]{3})-\d{4})\s{$this->opt($this->t("at"))}\s(?<time>\d{1,2}:\d{1,2})/", $dropoff, $m)
                || preg_match("/{$this->opt($this->t('Dropoff'))}\s*(?<date>\w+\,\s*\w+\/\w+\s*\d{4})\s*{$this->opt($this->t('at'))}\s*(?<time>[\d\:]+)\s*(?<location>.+)$/u", $dropoff, $m)) {
                $r->dropoff()
                    ->location($m['location'])
                    ->date($this->normalizeDate(str_replace(['-', '/'], ' ', $m['date']) . ", " . $m['time']));
            }
        }

        // Pick Up
        $pickup = $this->http->FindSingleNode(" //text()[" . $this->starts($this->t("Pickup")) . "]/ancestor::td[2]/descendant::td[2]");
        if (!empty($pickup)) {
            if (preg_match("/{$this->opt($this->t('Pickup'))}[\s]?(?<location>.+)\s(?<date>(?:\d{1,2}|[A-z]{3})-(?:\d{1,2}|[A-z]{3})-\d{4})\s{$this->opt($this->t("at"))}\s(?<time>\d{1,2}:\d{1,2})/", $pickup, $m)
                || preg_match("/{$this->opt($this->t('Pickup'))}\s*(?<date>\w+\,\s*\w+\/\w+\s*\d{4})\s*{$this->opt($this->t('at'))}\s*(?<time>[\d\:]+)\s*(?<location>.+)$/u", $pickup, $m)) {
                $r->pickup()
                    ->location($m['location'])
                    ->date($this->normalizeDate(str_replace(['-', '/'], ' ', $m['date']) . ", " . $m['time']));
            }
        }

        $phone = $this->http->FindSingleNode("//*[" . $this->starts($this->t("Phone to contact the rental:")) . "]/descendant::div[1]",
            null, false, "/" . $this->opt($this->t("Phone to contact the rental:")) . "\s([\d()\-\s+]+)[\s.]/");
        if (!empty($phone)) {
            $r->pickup()->phone($phone);
            if (!empty($r->getPickUpLocation()) && $r->getPickUpLocation() == $r->getDropOffLocation()) {
                $r->dropoff()->phone($phone);
            }
        }

        // Car
        $carModel = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("ou similar")) . "]");

        if (!empty($carModel)) {
            $r->car()->model($carModel);
        }

        $carImage = $this->http->FindSingleNode("//img[contains(@src,'imagens/carros/')]/@src");

        if (!empty($carImage)) {
            $r->car()->image($carImage);
        }

        $company = $this->http->FindSingleNode("//img[contains(@src,'/imagens/locadoras/')]/@src", null, true, '/\\/imagens\\/locadoras\\/([^.]+)/');
        if (!empty($company)) {
            foreach ($this->providersImageNames as $code => $pNames) {
                foreach ($pNames as $pName) {
                    if ($company === $pName) {
                        $r->program()->code($code);

                    }
                }
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//*[" . $this->starts($this->t("Total")) . "]/descendant::strong[1]");
        if (!empty($total)) {
            if (preg_match('/^(.+)\s(\d+[,.\d]+)$/', $total, $m)) {
                $r->price()
                    ->currency($this->currency($m[1]))
                    ->total($this->sumNormalization($m[2]));
            }
        } else {
            $total1 = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Você pagou antecipadamente")) . "]/following::strong[1]");
            $total2 = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("O valor restante de")) . "]/following::strong[1]");
            if (!empty($total1) && !empty($total2)
                && preg_match('/^\s*(\S.*?)\s+(\d+[,.\d]*)\s*$/', $total1, $m1)
                && preg_match('/^\s*(\S.*?)\s+(\d+[,.\d]*)\s*$/', $total2, $m2)
                && $m1[1] === $m2[1]
            ) {
                $currency = $this->currency($m1[1]);
                $r->price()
                    ->currency($currency)
                    ->total(PriceHelper::parse($m1[2], $currency) + PriceHelper::parse($m2[2], $currency));
            }
        }

        return $email;
    }

    private function sumNormalization($text)
    {
        $text = str_replace(',', '.', $text);

        if (substr_count($text, '.') > 1) {
            $pos = strpos($text, '.');

            return $pos !== false ? substr_replace($text, '', $pos, strlen('.')) : $text;
        } else {
            return $text;
        }
    }

    private function detectBody()
    {
        $this->assignLang();

        foreach (self::$detectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Reservation"], $words["Pickup"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Reservation'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Pickup'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function currency($s)
    {
        if ($code = $this->re("/^\s*([A-Z]{3})\s*$/", $s)) {
            return $code;
        }
        $sym = [
            'US$' => 'USD',
            'R$'  => 'BRL',
            '€'  => 'EUR',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "/^(\d+\s+[^\d\s]+\s+\d{4})\,?\s+(\d+:\d+)$/u",
            "/^\w*\,\s*(\d+\s+[^\d\s]+\s+\d{4})\,?\s+(\d+:\d+)$/u",
        ];
        $out = [
            "$1, $2",
            "$1, $2",
        ];
        $str = preg_replace($in, $out, $str);

        if ($this->lang !== 'en' && preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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
