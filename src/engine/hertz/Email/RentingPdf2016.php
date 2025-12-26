<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Schema\Parser\Email\Email;

class RentingPdf2016 extends \TAccountChecker
{
    public $mailFiles = "hertz/it-163874672.eml, hertz/it-47627071.eml, hertz/it-5261858.eml, hertz/it-5410545.eml, hertz/it-648990170.eml, hertz/it-65480366.eml, hertz/it-65790139.eml";
    public $detectSubject = [
        'de'  => 'Ihre Aufstellung der angefallenen Kosten',
        'en'  => 'E-Mail Statement of Charges',
        'en2' => 'Hertz E-Print Invoice',
    ];
    public static $dict = [
        'en' => [
            'confirmation' => ['Rental Agreement No:', 'No de Contrat:'],
            'phone'        => 'Phone:',
            'traveller'    => ['Renter:', 'Locataire:'],
            'ftNo'         => 'Fqt Trvl/Gd Voyageur',
            'accountNo'    => ['Account/Compte:', 'Account No.:'],
            'pickup'       => ['Rented On:', 'Location:'],
            'dropoff'      => ['Returned On:', 'Retour:'],
            'carModel'     => ['Car Description:', 'Voiture:'],
            'total'        => ['TOTAL CHARGES', 'TOTAL DES FRAIS', 'INVOICE AMOUNT'],
            'discount'     => ['DISCOUNT', 'RABAIS'],
        ],
        'fr' => [
            'confirmation' => ['Reservation:'],
            'phone'        => ['Telephone:'],
            'traveller'    => 'Conducteur:',
            //'ftNo' => 'Fqt Trvl/Gd Voyageur',
            'accountNo' => 'Client No.:',
            'pickup'    => ['Lieu de Depart:'],
            'dropoff'   => ['Lieu de Retour:'],
            'carModel'  => ['Vehicule:'],
            'total'     => ['MONTANT FACTURE'],
            'discount'  => ['REMISE'],
        ],
        'de' => [
            'confirmation' => ['Mietvertrags-Nr.:'],
            'phone'        => 'Telefon:',
            'traveller'    => 'Fahrer:',
            //            'ftNo' => '',
            'accountNo' => 'Kundennummer:',
            'pickup'    => ['Anmietung am:'],
            'dropoff'   => ['Rueckgabe am:'],
            'carModel'  => ['KFZ-Beschreibung:'],
            'total'     => ['GESAMTBETRAG'],
            'discount'  => ['TOTAL CHARGES'],
        ],
    ];

    private $providerCode = '';
    private $lang = '';
    private $currency = '';
    private $detectBody = [
        'de' => ['Ihre Anmietung bei Hertz. Im Anhang finden Sie Ihr eReceipt'],
        'fr' => ['Adresser vos reglements a:'],
        'en' => [
            'This electronic message contains legally privileged and confidential information intended only',
            'You will find your eReceipt attached for your convenience.',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        if (count($pdfs) === 0) {
            $this->logger->alert('Pdf is not found or is empty!');

            return $email;
        }

        $textPdf = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));
        $this->assignProvider($textPdf, $parser->getHeaders());
        $this->assignLang($parser->getBodyStr());
        $this->parseCar($email, str_replace(' ', ' ', substr($textPdf, 0, 10000)));

        $email->setProviderCode($this->providerCode);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'Hertz') === false
            && stripos($headers['from'], '@email.thrifty.com') === false
            && strpos($headers['subject'], 'Thrifty') === false
            && stripos($headers['from'], '@email.dollar.com') === false
            && strpos($headers['subject'], 'Dollar') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $phrase) {
            if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $htmlBody = $parser->getHTMLBody();

        if (empty($htmlBody)) {
            $htmlBody = $parser->getBodyStr();
        }

        return (stripos($htmlBody, 'Hertz') !== false
                || stripos($htmlBody, 'Thrifty') !== false
                || stripos($htmlBody, 'HERTZ') !== false)
            && (stripos($htmlBody, 'Rental Agreement -') !== false
                || stripos($htmlBody, 'Mietvertrag -') !== false
                || stripos($htmlBody, 'MERCI D\'AVOIR CHOISI HERTZ') !== false)
            && $this->assignLang($parser->getBodyStr());
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@hertz.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return ['hertz', 'thrifty', 'dollar'];
    }

    private function parseCar(Email $email, $text): void
    {
        $r = $email->add()->rental();

        if (preg_match("#{$this->opt($this->t('confirmation'))}\s+([\dA-Z]{5,})(?:[ ]{2}|$)#m", $text, $matches)) {
            $r->general()->confirmation($matches[1]);
        }

        if (preg_match("/{$this->opt($this->t('total'))}\s+([\d.]+)\s*([A-Z]{3})/", $text, $matches)) {
            $r->price()->total($matches[1]);
            $r->price()->currency($matches[2]);
            $this->currency = $matches[2];
        }

        if (preg_match("/{$this->opt($this->t('discount'))}[\d.%\s]+-([\d.]+)/", $text, $matches)) {
            $r->price()->discount($matches[1]);
        }

        if (preg_match("#\bDate:\s+(\d+\/\d+\/\d+)(?:[ ]{2}|$)#m", $text, $matches)) {
            $r->general()->date($this->normalizeDate($matches[1]));
        }

        if (preg_match("#^[ ]*(.{10,40}?)(?:[ ]{2}|\n)#", $text, $matches)) {
            $r->extra()->company($matches[1]);
        }

        if (preg_match("#{$this->opt($this->t('phone'))}\s+([+(\d][-. \d)(]{5,}[\d)])(?:[ ]{2}|$)#m", $text, $matches)) {
            $r->program()->phone($matches[1]);
        }

        if (preg_match("#{$this->opt($this->t('traveller'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\n#", $text, $matches)) {
            $r->general()->traveller($matches[1]);
        }

        if (preg_match("#{$this->opt($this->t('ftNo'))}\s+(\w[\w\d\s]+?)(?:\s{3,}|\n)#", $text, $matches)
            || preg_match("#{$this->opt($this->t('accountNo'))}\s+([*\w][*\w\d\s]+?)(?:\s{3,}|\n)#", $text, $matches)
        ) {
            // Fqt Trvl/Gd Voyageur AC0145207726    |    Account No.: ************7957 MC
            $r->program()->account($matches[1], strpos($matches[1], '***') !== false);
        }

//        $this->logger->debug($text);

        /*
         Anmietung am:    28/10/2019 08:52
         IATA/TACO:           39570893                                                              FRANKFURT AIRPORT

        Rented On:       12/13/2016 00:16 LOC# 124115
                                                                             SAN FRANCISCO AP, CA
         */
        $reg = "{$this->opt($this->t('pickup'))}\s+(\d+/\d+/\d+ \d+:\d+)\s*(?:LOC\#\s+\d+\s*)?\n.*?\s{20,}(.+?)\s*\n";
        //$this->logger->debug($reg);
        if (preg_match("#{$reg}#", $text, $matches)) {
            $r->pickup()->date($this->normalizeDate($matches[1]));
            $r->pickup()->location($matches[2]);
        }
        $reg = "{$this->opt($this->t('dropoff'))}\s+(\d+/\d+/\d+ \d+:\d+)\s*(?:LOC\#\s+\d+\s*)?\n.*?\s{20,}(.+?)\s*\n";

        if (preg_match("#{$reg}#", $text, $matches)) {
            $r->dropoff()->date($this->normalizeDate($matches[1]));
            $r->dropoff()->location($matches[2]);
        }

        if (preg_match("#{$this->opt($this->t('carModel'))}\s+(.+?)\s*\n#", $text, $matches)) {
            $r->car()->model($matches[1]);
        }
    }

    private function assignProvider($text, $headers): bool
    {
        if (stripos($headers['from'], '@hertz.com') !== false
            || stripos($headers['subject'], 'Hertz E-Mail Statement') !== false
            || stripos($text, 'www.hertz.com') !== false
            || stripos($text, 'THANK YOU FOR RENTING FROM HERTZ') !== false
        ) {
            $this->providerCode = 'hertz';

            return true;
        }

        if (stripos($headers['from'], '@email.thrifty.com') !== false
            || stripos($headers['subject'], 'Thrifty E-Mail Statement') !== false
            || stripos($text, 'www.thrifty.com') !== false
            || stripos($text, 'THANK YOU FOR RENTING FROM THRIFTY') !== false
        ) {
            $this->providerCode = 'thrifty';

            return true;
        }

        if (stripos($headers['from'], '@email.dollar.com') !== false
            || stripos($headers['subject'], 'Dollar E-Mail Statement') !== false
            || stripos($text, 'www.dollar.com') !== false
            || stripos($text, 'THANK YOU FOR RENTING FROM DOLLAR') !== false
        ) {
            $this->providerCode = 'dollar';

            return true;
        }

        return false;
    }

    private function assignLang($bodyStr): bool
    {
        if (!empty($this->lang)) {
            return true;
        }

        foreach ($this->detectBody as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0
                || stripos($bodyStr, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    //========================================
    // Auxiliary methods
    //========================================

    private function normalizeDate($str)
    {
        // 29/10/2019
        if ($this->lang == 'de') {
            return strtotime($this->ModifyDateFormat($str), false);
        }

        // 29/10/2019
        if ($this->lang == 'fr') {
            return strtotime($this->ModifyDateFormat($str), false);
        }

        if ($this->lang == 'en' && $this->currency === 'EUR') {
            return strtotime(str_replace('/', '.', $str), false);
        }

        return strtotime($str, false);
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . "),'" . $s . "')";
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
