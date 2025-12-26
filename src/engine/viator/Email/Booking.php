<?php

namespace AwardWallet\Engine\viator\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "viator/it-131936861-es.eml, viator/it-133548754-de.eml, viator/it-133682995.eml";

    private $devMode = 0;

    public $subjects = [
        // '/Su reserva \d{3,} está pagada y confirmada/iu', // es
        // '/Ihre Reservierung \d{3,}/i', // de
        '/Your payment for \d{3,} will be charged soon/i', // en
    ];

    public $lang = 'en';
    private $subject = '';

    public static $dictionary = [
        "es" => [
            'View details'             => 'Ver detalles',

            'Confirmation number:' => 'Número de confirmación:',
            //'Payment date:' => '',
            'Total amount:' => 'Importe pagado:',

            'Booking reference number:' => 'Número de referencia de la reserva:',
            'adult'                     => ['adulto', 'Adulto'],
            'Departure Point'           => ['Punto de partida'],
        ],
        "de" => [
            'View details'             => 'Details anzeigen',

            'Confirmation number:' => 'Bestätigungsnummer:',
            //'Payment date:' => '',
            'Total amount:' => 'Gesamtsumme:',

            'Booking reference number:' => 'Buchungsnummer:',
            'adult'                     => ['erwachsene', 'Erwachsene'],
            'Departure Point'           => ['Abreiseort'],
        ],
        "en" => [
            'adult' => ['adult', 'Adult'],
        ],
    ];

    private $detectLang = [
        'es' => ['Número de confirmación:', 'Número de referencia de la reserva:', 'Su confirmación de reserva'],
        'de' => ['Bestätigungsnummer:', 'Buchungsnummer:', 'Ihre Reservierung ist noch nicht bestätigt'],
        'en' => ['Confirmation number:', 'Booking reference number:', 'Your trip is almost here'],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (array_key_exists('subject', $headers) && preg_match($subject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Viator")]')->length === 0) {
            return false;
        }

        $this->assignLang();

        return $this->http->XPath->query("//text()[{$this->contains($this->t('View details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]t1\.viator\.com$/', $from) > 0;
    }

    private function ParseEmail(Email $email): void
    {
        $event = $email->add()->event();
        $event->type()->event();

        $date = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Payment date:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Payment date:'))}\s*(.+)/");

        if (!empty($date)) {
            $event->general()
                ->date($this->normalizeDate($date));
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation number:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation number:'))}[:\s]*(\d+)$/");
        $event->general()->confirmation($confirmation);

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference number:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking reference number:'))}[:\s]*(\d+)$/");
        $email->ota()->confirmation($otaConfirmation);

        $event->setName($this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation number:'))}]/preceding::text()[normalize-space()][1]"));

        if (empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Payment date:'))}]"))) {
            $dateStart = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference number:'))}]/ancestor::tr[1]/following::text()[normalize-space()][1]", null);

            if (!empty($dateStart)) {
                $dateStart = str_replace(['nachm.'], 'pm', $dateStart);

                $event->booked()
                    ->start($this->normalizeDate($dateStart))
                    ->noEnd();
            }
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->contains($this->t('adult'))}]", null, true, "/^(\d{1,3})\s*{$this->opt($this->t('adult'))}/i");

        if (!empty($guests)) {
            $event->booked()
                ->guests($guests);
        }

        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total amount:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Total amount:'))}\s*(.+)/");

        if (preg_match("/^\s*(?<currencyCode>[A-Z]{3})\D+(?<amount>\d[\d.,]*)$/", $price, $matches)) {
            $event->price()->currency($matches['currencyCode'])->total(PriceHelper::parse($matches['amount'], $matches['currencyCode']));
        }

        $http2 = clone $this->http;

        $url = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation number:'))}]/following::a[{$this->eq($this->t('View details'))}][1]/@href");

        if (!$url) {
            $this->logger->debug('StartURL not found!');

            return;
        }

        $this->logger->info('StartURL:');
        $this->logger->debug($url);
        $http2->GetURL($url);

        $text = implode("\n", $http2->FindNodes("//text()[normalize-space()]"));
        //$this->logger->error($text);

        $userID = $this->re("/\n(\d{8})\nBookingsLander/", $text);
        $startURL = $this->re("/(https?:\/\/\D+\/[a-z]{2}-[A-Z]{2}\/)/", $http2->currentUrl()) ?? 'https://www.viator.com/';

        if ($userID && $confirmation && $otaConfirmation) {
            $segmentURL = "{$startURL}account/booking/{$userID}/{$confirmation}/{$otaConfirmation}";
            $this->logger->info('SegmentURL:');
            $this->logger->debug($segmentURL);
            $http2->GetURL($segmentURL);
        }

        $endLINK = $otaConfirmation ? $http2->FindSingleNode("//div[contains(@data-item-id,'{$otaConfirmation}')]/@data-tour-url") : null;

        if ($endLINK) {
            $endURL = "https://www.viator.com" . $endLINK;
            $this->logger->info('EndURL:');
            $this->logger->debug($endURL);
            $http2->GetURL($endURL);
        }

        $address = $http2->FindSingleNode("//text()[{$this->eq($this->t('Departure Point'))}]/following::text()[normalize-space()][1]")
            ?? YourViatorBooking::getAddressByName($event->getName(), $this->devMode);

        if (!empty($address) && preg_match("/^(?:Travell?er pickup is offered|You have not selected a meeting\/pickup point)[,.;!?\s]*$/i", $address)) {
            $email->removeItinerary($event);
            $email->setIsJunk(true, 'You have not selected meeting point');

            return;
        } elseif (!empty($address)) {
            $event->setAddress($address);
        }

        //detects for subject
        if (empty($event->getStartDate()) && preg_match("/Your payment for \d+ will be charged soon/u", $this->subject)) { // WTF?
            $email->removeItinerary($event);
            $email->setIsJunk(true);

            return;
        }

        //detect for body
        if (empty($event->getStartDate()) && $this->http->XPath->query("//text()[normalize-space()='Ihre Reservierung ist noch nicht bestätigt']")->length > 0) {
            $email->removeItinerary($event);
            $email->setIsJunk(true, 'Your reservation is not yet confirmed');

            return;
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->devMode) {
            $this->logger->notice('Attention! Turn off the devMode!');
        }

        $this->assignLang();

        $this->subject = $parser->getSubject();

        $this->ParseEmail($email);

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

    private function opt($field): string
    {
        $field = (array)$field;
        if (count($field) === 0)
            return '';
        return '(?:' . implode('|', array_map(function($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
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

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        $this->logger->debug($date);
        $in = [
            // sábado, enero 08, 2022 at 07:00 a. m.
            "#^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*at\s*([\d\:]+)\s*(a?p?)\.\s*(m)\.$#u",

            //Montag, Mai 23, 2022 at 11:00 pm
            "#^\w+\,\s+(\w+)\s*(\d+)\,\s*(\d{4})\s*at\s*([\d\:]+\s*a?p?m)$#",
        ];
        $out = [
            "$2 $1 $3, $4 $5$6",
            "$2 $1 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
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
