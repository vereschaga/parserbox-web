<?php

namespace AwardWallet\Engine\hertz\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarRentalPDF extends \TAccountChecker
{
    public $mailFiles = "hertz/it-112046021.eml, hertz/it-157180500.eml, hertz/it-71570423.eml, hertz/it-72928737.eml";
    public $subjects = [
        '/^Car Rental$/',
        '/hertz rohde/',
    ];

    public $lang = 'en';

    public $detectLang = [
        'en' => ['Your Itinerary'],
        'pt' => ['Seu itinerário'],
    ];
    public static $dictionary = [
        "en" => [
            'Your reservation confirmation number is'     => ['Your reservation confirmation number is', 'Confirmation Number', 'Your Confirmation Number is'],
            'Thanks for Travelling at the Speed of Hertz' => ['Thanks for Travelling at the Speed of Hertz', 'Thanks for Traveling at the Speed of Hertz™', 'Thanks for Traveling at the Speed of Hertz®', 'Thank you,'],
            'Total'                                       => ['Total', 'Total Approximate Charge', 'Total Estimated Charge'],
            'Pick Up time'                                => ['Pick Up time', 'Pickup Time', 'Pick-up Time'],
            'Return time'                                 => ['Return time', 'Return Time'],
            'YOUR VEHICLE'                                => ['YOUR VEHICLE', 'Your selected car class', 'YOUR SELECTED CAR CLASS'],
            'PAYMENT METHOD'                              => ['PAYMENT METHOD', 'Details'],
            'Pickup and Return'                           => ['Pick-up and Return Location', 'Pickup and Return Location:', 'Pickup and Return', 'Pick-up and Return'],
            'Pick Up Location'                            => ['Pick Up Location', 'Pick-up Location'],
            'Discounts and rates'                         => ['Discounts and rates', 'Discounts'],
            'Phone'                                       => ['Phone Number', 'Phone'],
            'Fax'                                         => ['Fax Number', 'Fax'],
            'Hours of Operation'                          => ['Hours of Operation', 'Hours'],
            'Type of store'                               => ['Type of store', 'Location Type'],
        ],

        "pt" => [
            'Your reservation confirmation number is'     => ['Seu número de confirmação é'],
            'Thanks for Travelling at the Speed of Hertz' => ['Thanks for Travelling at the Speed of Hertz', 'Thanks for Traveling at the Speed of Hertz™', 'Thanks for Traveling at the Speed of Hertz®', 'Thank you,'],
            'Total'                                       => ['Total', 'Total Approximate Charge', 'Total Estimated Charge'],
            'Pick Up time'                                => ['Retirada'],
            'Return time'                                 => ['Devolução'],
            'YOUR VEHICLE'                                => ['VEÍCULO'],
            'PAYMENT METHOD'                              => ['FORMA DE PAGAMENTO'],
            'Pickup and Return'                           => ['Pick-up and Return Location', 'Pickup and Return Location:', 'Pickup and Return', 'Local de retirada e devolução'],
            'Your Itinerary'                              => 'Seu itinerário',
            'Pick Up Location'                            => 'Loja de Retirada',
            'Return Location'                             => 'Loja de Devolução',
            'Address'                                     => 'Endereço',
            'Hours of Operation'                          => 'Horário comercial',
            'Phone Number'                                => ['Phone Number', 'Tel'],
            'Type of store'                               => 'Tipo de loja',
            'Phone'                                       => 'Tel',
            'Fax'                                         => 'Número de fax',
            'Discounts and rates'                         => 'Descontos e tarifas',
            'or similar'                                  => 'ou similar',
            'EXTRAS'                                      => 'EQUIPAMENTO',
            'Child Seat'                                  => 'Assentos para crianças',
            'Tax'                                         => 'TAXA',
        ],
    ];

    private $patterns = [
        'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && (stripos($headers['from'], '@mosaictravel.com.au') !== false || stripos($headers['from'], '@stephenebanks.com') !== false)) {
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
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if (strpos($text, 'Speed of Hertz')
                && strpos($text, $this->t('Your Itinerary'))
                && (strpos($text, 'Pickup and Return') || strpos($text, 'Pick-up and Return Location'))) {
                return true;
            } elseif ((strpos($text, 'Speed of Hertz') || strpos($text, 'http://www.hertz.com'))
                && strpos($text, $this->t('Your Itinerary'))
                && strpos($text, $this->t('Address'))) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        if (preg_match('/[@.]mosaictravel\.com\.au$/', $from) > 0 || preg_match('/[@.]stephenebanks\.com$/', $from) > 0) {
            return true;
        }

        return false;
    }

    public function ParsePDF(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('Your reservation confirmation number is'))}\s*\:\s*([A-Z\d]+)/u", $text), 'confirmation number');

        $traveller = $this->re("/{$this->opt($this->t('Thanks for Travelling at the Speed of Hertz'))}[,\s]+({$this->patterns['travellerName']})(?:\s*[;!]|$)/mu", $text);

        if (!empty($traveller)) {
            $r->general()->traveller($traveller);
        }

        $total = $this->re("/{$this->opt($this->t('Total'))}\s*([\d\.]+)\s*[A-Z]{3}/su", $text);
        $currency = $this->re("/{$this->opt($this->t('Total'))}\s*[\d\.]+\s*([A-Z]{3})/su", $text);

        if (!empty($total) && !empty($currency)) {
            $r->price()
                ->total($total)
                ->currency($currency);
        }

        $retalBlock = $this->re("/({$this->opt($this->t('Pickup and Return'))}.+){$this->opt($this->t('YOUR VEHICLE'))}/msu", $text);
        $table = $this->splitCols($retalBlock);

        $r->pickup()
            ->location(preg_replace("/\s+/", " ", str_replace($this->t('Address'), " ", $this->re("/\s*{$this->opt($this->t('Pickup and Return'))}(.*?)\s+(?:Hours of Operation|Location Type|Horário comercial)/ims", $table[0]))))
            ->openingHours($this->re("/\s+{$this->opt($this->t('Hours of Operation'))}\s*:*\s*([^\n]+?)[ ]*(?:Location Type|Phone Number|$)/m", $table[0]))
            ->phone($this->re("/\s+{$this->opt($this->t('Phone Number'))}[\s:]+({$this->patterns['phone']})/s", $table[0]))
            ->date(strtotime($this->normalizeDate($this->re("/\s*{$this->opt($this->t('Pick Up time'))}\s*\:?\s*([^\n]+)/us", $table[1]))));

        $fax = $this->re("/\s+Fax Number[:\s]+([+(\d][-. \d)(]{5,}[\d)])/", $table[0]);

        if (!empty($fax)) {
            $r->pickup()
                ->fax($fax);
        }

        $r->dropoff()
            ->date(strtotime($this->normalizeDate($this->re("/\n\s*{$this->opt($this->t('Return time'))}\s*\:*\s*([^\n]+)/us", $table[1]))))
            ->same();

        if (preg_match("/{$this->opt($this->t('YOUR VEHICLE'))}\s*\n(.+)\n(.+\s*{$this->opt($this->t('or similar'))})/u", $text, $m)) {
            $r->car()
                ->type($m[1])
                ->model($m[2]);
        }

        if (preg_match("/EXTRAS\s*(Child Seat)\s*([\d\.]+)\s*[A-Z]{3}/s", $text, $m)) {
            $r->addEquipment($m[1], $m[2]);
        }
    }

    public function ParsePDF2(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('Your reservation confirmation number is'))}\s*\:\s*([A-Z\d]+)/u", $text), 'confirmation number');

        $traveller = $this->re("/{$this->opt($this->t('Thanks for Travelling at the Speed of Hertz'))}[,\s]+({$this->patterns['travellerName']})(?:\s*[;!]|$)/mu", $text);

        if (!empty($traveller)) {
            $r->general()->traveller($traveller);
        }

        $r->price()
            ->total($this->re("/{$this->opt($this->t('Total'))}\s*([\d\.]+)\s*[A-Z]{3}/su", $text))
            ->currency($this->re("/{$this->opt($this->t('Total'))}\s*[\d\.]+\s*([A-Z]{3})/su", $text));

        $retalBlock = $this->re("/({$this->opt($this->t('Pick Up Location'))}.+){$this->opt($this->t('Discounts and rates'))}/msu", $text);

        $table = $this->splitCols($retalBlock, [0, 50]);

        if (preg_match("/^(\s*{$this->opt($this->t('Pick Up Location'))}[\s\S]+)\n( {0,10}{$this->opt($this->t('Return Location'))}[\s\S]+)(\n\n {0,5}{$this->opt($this->t('Pick Up time'))}[\s\S]+)/", $table[0], $m)) {
            $table[0] = $m[1] . $m[3];
            $table[1] = $m[2] . "\n\n" . $table[1];
        }

        $location = $this->re("/{$this->opt($this->t('Pick Up Location'))}\:?(.+){$this->opt($this->t('Address'))}/s", $table[0]);
        $address = $this->re("/{$this->opt($this->t('Address'))}(.+){$this->opt($this->t('Hours of Operation'))}/s", $table[0]);

        $r->pickup()
            ->location(preg_replace("/\s+/", " ", $location . ' ' . $address))
            ->openingHours($this->re("/{$this->opt($this->t('Hours of Operation'))}(.+){$this->opt($this->t('Type of store'))}/s", $table[0]))
            ->phone($this->re("/{$this->opt($this->t('Phone'))}[:\s]*({$this->patterns['phone']})\s*(?:{$this->opt($this->t('Fax'))}|{$this->opt($this->t('Pick Up time'))})/", $table[0]))
            ->fax($this->re("/{$this->opt($this->t('Fax'))}[:\s]*({$this->patterns['phone']})\s*{$this->opt($this->t('Pick Up time'))}/", $table[0]), false, true)
            ->date(strtotime($this->normalizeDate($this->re("/{$this->opt($this->t('Pick Up time'))}\s*\n(.+)/", $table[0]))));

        $location2 = $this->re("/{$this->opt($this->t('Return Location'))}\:?(.+){$this->opt($this->t('Address'))}/s", $table[1]);
        $address2 = $this->re("/{$this->opt($this->t('Address'))}(.+){$this->opt($this->t('Hours of Operation'))}/s", $table[1]);
        $r->dropoff()
            ->location(preg_replace("/\s+/", " ", $location2 . ' ' . $address2))
            ->openingHours(str_replace("\n", " ", $this->re("/{$this->opt($this->t('Hours of Operation'))}(.+){$this->opt($this->t('Type of store'))}/s", $table[1])))
            ->phone($this->re("/{$this->opt($this->t('Phone'))}[:\s]*({$this->patterns['phone']})\s*(?:{$this->opt($this->t('Fax'))}|{$this->opt($this->t('Return time'))})/", $table[1]))
            ->fax($this->re("/{$this->opt($this->t('Fax'))}[:\s]*({$this->patterns['phone']})\s*{$this->opt($this->t('Return time'))}/", $table[1]), false, true)
            ->date(strtotime($this->normalizeDate($this->re("/{$this->opt($this->t('Return time'))}\s*\n(.+)/", $table[1]))));

        if (preg_match("/{$this->opt($this->t('YOUR VEHICLE'))}\s*\n(.+)\n(.+\s*{$this->opt($this->t('or similar'))})/u", $text, $m)) {
            $r->car()
                ->type($m[1])
                ->model($m[2]);
        }

        if (preg_match("/{$this->opt($this->t('EXTRAS'))}\s*({$this->opt($this->t('Child Seat'))})\s*([\d\.]+)\s*[A-Z]{3}/s", $text, $m)) {
            $r->addEquipment($m[1], $m[2]);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->assignLang($pdf);

            if (!preg_match("/{$this->opt($this->t('Pick Up Location'))}/", $text)) {
                $this->ParsePDF($email, $text);
            } else {
                $this->ParsePDF2($email, $text);
            }
        }
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function normalizeDate(?string $str): string
    {
        //$this->logger->debug('IN ' . $str);
        $in = [
            "#^\w+\,\s*(\d+)\s*(\w+)\,\s*(\d{4})\s*\w+\s*([\d\:]+)\s*$#u", //Wed, 30 Dec, 2020 at 16:40
            "#^\w*\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*at\s*([\d\:]+\s*A?P?M)$#u", //Oct 17, 2021 at 01:30 PM
        ];
        $out = [
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //$this->logger->debug('OUT ' . $str);

        return $str;
    }

    private function assignLang($text): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
