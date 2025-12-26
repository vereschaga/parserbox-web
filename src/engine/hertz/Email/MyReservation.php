<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class MyReservation extends \TAccountChecker
{
    public $mailFiles = "hertz/it-42049678.eml";

    public $reFrom = ["@hertz."];
    public $reSubject = [
        '/Your Hertz Reservation \w+/',
        '/My Hertz Reservation \w+/',
        '/Hertz Internacional - Confirmação de Reserva/',
        '/Mi Reserva de Hertz \w+/',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'confirmation'    => ['Your Confirmation Number is:', 'Confirmation Number:', 'Your reservation for Confirmation Number'],
            'traveller'       => ['Thank you for your reservation,\s*(.+?)[.\n]', 'Thanks,\s*(.+?)[.\n]', '\n(.+?), You have successfully'],
            'pickupDate'      => ['Pick-Up Date', 'Pick Up', 'Pickup Time'],
            'dropoffDate'     => ['Return Date', 'Return', 'Return Time'],
            'pickupLocation'  => ['Pick-Up Location', 'Pick Up Location', 'Pickup Location', 'Pick-Up Location:'],
            'dropoffLocation' => ['Return Location', 'Return Location', 'Return Location:'],
            'phone'           => ['Phone:', 'Phone Number:'],
            'fax'             => ['Fax:', 'Fax Number:', 'Fax Number::'],
            'locationType'    => ['Location Type:'],
            'model'           => ['or similar'],
            'total'           => ['Total Approximate Charge', 'Total Estimated Charge'],
            'tax'             => ['Taxes', 'TAXES'],
        ],
        'pt' => [
            'confirmation'    => 'Codigo de Confirmação da sua reserva:',
            'traveller'       => ['Obrigado pela sua reserva,\s*(.+?)\n'],
            'pickupDate'      => ['Data da Retirada'],
            'dropoffDate'     => ['Data da Devolução'],
            'pickupLocation'  => ['Local de Retirada'],
            'dropoffLocation' => ['Local de Devolução'],
            'phone'           => ['Telefone:'],
            'fax'             => ['Fax:'],
            'locationType'    => [],
            'model'           => ['ou similar'],
            'total'           => ['Valor Total'],
            'tax'             => [],
        ],
        'de' => [
            'confirmation'    => 'Reservierungsnummer:',
            'traveller'       => ['Ihre Reservierung,\s*(.+?)\n'],
            'pickupDate'      => ['Anmietdatum'],
            'dropoffDate'     => ['Rückgabedatum'],
            'pickupLocation'  => ['Anmietstation:'],
            'dropoffLocation' => ['Rückgabestation:'],
            'phone'           => ['Telefon:'],
            'fax'             => ['Fax:'],
            'locationType'    => [],
            'model'           => ['oder ähnlich'],
            'total'           => ['Voraussichtlicher Mietpreis'],
            'tax'             => [],
        ],
        // TODO: Poor translation It1570475: hertz/it-1570475.eml, hertz/it-2468609.eml
        //        'es' => [
        //            'confirmation' => 'Tu Número de Confirmación es el siguiente:',
        //            'traveller' => ['\n(.+?), Usted se ha registrado con éxito', 'la velocidad de Hertz,\s*(.+?)\n'],
        //            'pickupDate' => ['Oficina de recogida:'],
        //            'dropoffDate' => ['Oficina de devolución:'],
        //            'pickupLocation' => ['Oficina de recogida y de devolución'],
        //            'dropoffLocation' => ['Direcciones hacia la oficina:'],
        //            'phone' => ['Número de telefono::'],
        //            'fax' => ['Número de fax::'],
        //            'locationType' => ['Tipo de oficina::'],
        //            'model' => ['o similar'],
        //            'total' => ['Cargo Total Estimado'],
        //            'tax' => []
        //        ],
    ];
    private $keywordProv = 'Hertz';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $text = $parser->getHTMLBody();
        $text = $this->htmlToText($text);

        $this->parseEmail($email, $text);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
                    && preg_match($reSubject, $headers["subject"]) > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'{$this->keywordProv}')]")->length > 0) {
            return $this->assignLang();
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

    protected function htmlToText($string)
    {
        return preg_replace('/<[^>]+>/', "\n", $string);
    }

    private function parseEmail(Email $email, string $text)
    {
        //$this->logger->debug($text);
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindPreg("/{$this->opt($this->t('confirmation'))}\s+(\w+)\b/si", false, $text));

        foreach ($this->t('traveller') as $regexp) {
            if (preg_match("/{$regexp}/", $text, $matches)) {
                $r->general()->traveller($matches[1]);

                break;
            }
        }

        // Car
        if ($image = $this->http->FindSingleNode("//img[contains(@src,'https://images.hertz.com/vehicles/')]/@src")) {
            $model = $this->http->FindSingleNode("(//img[contains(@src,'https://images.hertz.com/vehicles/')]/ancestor::td[1]/following-sibling::td[1]//span)[1]");

            if (empty($model)) {
                $model = $this->http->FindSingleNode("//td//text()[{$this->contains($this->t('model'))} and string-length(.) > 13][1]");
            }

            $type = $this->http->FindSingleNode("(//td//text()[{$this->contains($this->t('model'))}]/following::text()[normalize-space(.)!=''])[1]");

            $r->car()
                ->image($image)
                ->model($model)
                ->type($type, false, true);
        }

        $location = $this->http->FindSingleNode("//tr/td[{$this->eq($this->t('pickupLocation'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
        $r->pickup()
            ->location($this->http->FindPreg("/^(.+?)\s*{$this->opt(array_merge($this->t('locationType'), $this->t('phone'), $this->t('fax')))}/i", false, $location))
            ->date($this->normalizeDate($this->http->FindSingleNode("(//tr/td[{$this->eq($this->t('pickupDate'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1])[1]")))
            ->phone($this->http->FindPreg("/{$this->opt($this->t('phone'))}\s*([\d+.\-\s]+)/", false, $location))
            ->fax($this->http->FindPreg("/{$this->opt($this->t('fax'))}\s*([\d+.\-\s]+)/", false, $location), true, true);

        $location = $this->http->FindSingleNode("//tr/td[{$this->eq($this->t('dropoffLocation'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
        $r->dropoff()
            ->location($this->http->FindPreg("/^(.+?)\s*{$this->opt(array_merge($this->t('locationType'), $this->t('phone'), $this->t('fax')))}/i", false, $location))
            ->date($this->normalizeDate($this->http->FindSingleNode("(//tr/td[{$this->eq($this->t('dropoffDate'))}]/ancestor::tr[1]/following-sibling::tr[1]/td[2])[1]")))
            ->phone($this->http->FindPreg("/{$this->opt($this->t('phone'))}\s*([\d+.\-\s]+)/", false, $location))
            ->fax($this->http->FindPreg("/{$this->opt($this->t('fax'))}\s*([\d+.\-\s]+)/", false, $location), true, true);

        // Total
        if ($total = $this->http->FindSingleNode("//td[{$this->eq($this->t('total'))}]/following-sibling::td[1]")) {
            // 533,96 EUR
            $r->price()
                ->total(str_replace(',', '.', $this->http->FindPreg('/^([\d,.\s]+)/', false, $total)))
                ->currency($this->http->FindPreg('/([A-Z]{3}$)/', false, $total));

            if ($tax = $this->http->FindSingleNode("//td[{$this->eq($this->t('tax'))}]/following-sibling::td[1]", null, false, '/^([\d,.\s]+)/')) {
                $r->price()->tax($tax);
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            // en
            // Fri, 29 Jun, 2012 at 10:00
            '/^\w+, (\d+ \w+), (\d{4}) at (\d+:\d+(?:\s*[ap]m)?)$/i',
            // Fri, Oct 01, 2010 at 06:30 PM - en
            '/^\w+, (\w+) (\d+), (\d{4}) at (\d+:\d+(?:\s*[ap]m)?)$/i',

            // pt
            // Seg, 29 de Jul de 2019 às 12:30
            '/^\w+, (\d+) de (\w+) de (\d{4}) às (\d+:\d+(?:\s*[ap]m)?)$/ui',

            // de
            // Mo, 31 Okt, 2011 um 13:00
            '/^\w+, (\d+ \w+), (\d{4}) um (\d+:\d+(?:\s*[ap]m)?)$/u',

            // es
            // sáb, 01 mar, 2014  a  16:30
            // '/^\w+, (\d+ \w+), (\d{4})\s+a\s+(\d+:\d+(?:\s*[ap]m)?)$/u'
        ];
        $out = [
            '$1 $2, $3', // en
            '$2 $1 $3, $4', // en
            '$1 $2 $3, $4', // pt
            '$1 $2, $3', // es
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function assignLang()
    {
        //$this->logger->notice(__METHOD__);
        foreach (self::$dict as $lang => $words) {
            if (isset($words['confirmation'])) {
                if ($this->http->XPath->query("//text()[{$this->contains($words['confirmation'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return null;
        }

        return self::$dict[$this->lang][$s];
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
