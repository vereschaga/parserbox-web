<?php

namespace AwardWallet\Engine\viator\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class GetTicket extends \TAccountChecker
{
    use ProxyList;

    // + example ticket: Ticket _ it-295785487
    public $mailFiles = "viator/it-295785487.eml, viator/it-295892428.eml, viator/it-297369174.eml";

    public $detectSubject = [
        // en
        'Confirmed: Viator Booking',
        'Your Viator Booking',
        // es
        'Su reservación de Viator BR-',
        // pt
        ' Sua reserva da Viator BR',
    ];
    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Get ticket'      => ['Get ticket', 'Get Ticket', 'Get your ticket', 'Get voucher', 'View and print voucher', 'Get your tickets'],
            'Booking ref.:'   => ['Booking ref.:', 'Booking ref. /'],
            // 'Meeting and pickup' => [''],
            // 'Departure point:' => [''],
        ],
        'fr' => [
            'Get ticket'           => ['Accéder au billet', 'Télécharger le bon'],
            'Booking ref.:'        => ['Référence de la réservation /', 'Référence de la réservation :', 'Référence de la réservation:'],
            'Meeting and pickup'   => ['Rendez-vous et prise en charge'],
            // 'Departure point:' => [''],
        ],
        'es' => [
            'Get ticket'           => ['Obtener boleto'],
            'Booking ref.:'        => ['Ref. de la reserva /', 'Ref. de la reserva:'],
            'Meeting and pickup'   => ['Encuentro y recogida'],
            // 'Departure point:' => [''],
        ],
        'pt' => [
            'Get ticket'           => ['Obter ingresso'],
            'Booking ref.:'        => ['Referência da reserva /', 'Referência da reserva:'],
            'Meeting and pickup'   => ['Ponto de encontro e traslado'],
            // 'Departure point:' => [''],
        ],
    ];

    private $devMode = 0;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->devMode) {
            $this->logger->notice('Attention! Turn off the devMode!');
        }

        $this->assignLang();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHtml($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // Detect Format
        if (!$this->assignLang()) {
            return false;
        }

        // Detect Provider
        return $this->detectEmailFromProvider($parser->getCleanFrom()) === true
            || $this->http->XPath->query("//a[contains(@href,'.viator.com/') or contains(@href,'www.viator.com') or {$this->eq($this->t('Get ticket'))} and contains(@href,'viator')]")->length > 0
            || $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and contains(normalize-space(),"Viator")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thanks for booking on Viator")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.viator.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ((!array_key_exists('from', $headers) || $this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true)
            && (!array_key_exists('subject', $headers) || !preg_match('/\bViator\b/', $headers['subject']))
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
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

    private function parseHtml(Email $email): void
    {
        $urls = $this->http->FindNodes("//a[{$this->eq($this->t('Get ticket'))} and contains(@href, 'viator')]/@href");

        if (empty($urls)) {
            $urls = $this->http->FindNodes("//a[{$this->eq($this->t('Get ticket'))}]/@href");
        }

        foreach ($urls as $i => $url) {
            $http2 = clone $this->http;
            $this->http->brotherBrowser($http2);

            if (stripos($url, 'urldefense.com') !== false) {
                $url = preg_replace([
                    '/^https:\/\/urldefense\.com\/v3\/__(.+)__;.*/i',
                    '/\*(20|2B|2F|3D|3F)/',
                ], [
                    '$1',
                    '%$1',
                ], $url);
            }

            if (stripos($url, '.safelinks.protection.outlook.com') !== false) {
                $http2->GetURL($url);

                if (isset($http2->Response['headers']['location'])) {
                    $url = $http2->Response['headers']['location'];
                }
            }

            if (!$this->devMode) {
                $http2->SetProxy($this->proxyDOP());
            }
            $http2->setDefaultHeader('Accept-Encoding', 'gzip');
            $http2->setDefaultHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:109.0) Gecko/20100101 Firefox/112.0');

            $http2->GetURL($url);

            if (isset($http2->Response['headers']['location'])) {
                $url2 = $http2->Response['headers']['location'];
                $url2 = str_replace('travelagents', 'www', $url2);
                $url2 = str_replace('viatorapi', 'www', $url2);

                $http2->setMaxRedirects(5);
                $http2->GetURL($url2);
            }

            if (stripos($http2->currentUrl(), 'travelagents') !== false) {
                $http2->GetURL(str_replace('travelagents', 'www', $http2->currentUrl()));
            }

            if (stripos($http2->currentUrl(), 'viatorapi') !== false) {
                $http2->GetURL(str_replace('viatorapi', 'www', $http2->currentUrl()));
            }

            $event = $email->add()->event();

            $names = array_unique($http2->FindNodes("//div[@class = 'product-name']"));

            if (count($names) !== 1) {
                $email->add()->event();
                // no examples
                $this->logger->debug('empty event name or more then 1 events in tickets');

                return;
            }
            $name = $names[0];

            $jsonFields = YourViatorBooking::getFieldsFromJSON($http2);

            // Travel Agency
            $conf = $http2->FindSingleNode("(//*[@data-attraction-itinerary-id])[1]/@data-attraction-itinerary-id");

            if (empty($conf)) {
                $conf = $http2->FindSingleNode("(//a[contains(, 'download-pdf?code=')])[1]/@href", null, true,
                    "/download-pdf\?code=(\d{5,})/");
            }

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Itinerary number:'))}]",
                    null, true, "/{$this->opt($this->t('Itinerary number:'))}\s*(\d{5,})$/");
            }
            $event->ota()
                ->confirmation($conf);

            // General
            $eventConf = $http2->FindSingleNode("(//text()[{$this->starts($this->t('Booking ref.:'))}])[1]",
                null, true, "/:\s*([A-Z\-\d]{5,})\s*$/");
            $event->general()
                ->confirmation($eventConf,
                    trim($http2->FindSingleNode("(//text()[{$this->starts($this->t('Booking ref.:'))}])[1]",
                        null, true, "/^\s*{$this->opt($this->t('Booking ref.:'))}/"), ':/'))
                ->travellers(array_filter(preg_replace("/^\s*passenger.*/i", '', $http2->FindNodes("//div[@class = 'traveller-name']",
                    null))), true)
            ;
            // Tour operator confirmation no: 204137
            $conf = $http2->FindSingleNode("(//text()[{$this->starts($this->t('Tour operator confirmation no:'))}])[1]",
                null, true, "/:\s*([A-Z\-\d]{5,})\s*$/");

            if (!empty($conf) && ($eventConf !== $conf)) {
                $event->general()
                    ->confirmation($conf,
                        trim($http2->FindSingleNode("(//text()[{$this->starts($this->t('Tour operator confirmation no:'))}])[1]",
                            null, true, "/^\s*{$this->opt($this->t('Tour operator confirmation no:'))}/"), ':'));
            }

            // Place
            $address = $http2->FindSingleNode("//text()[{$this->eq($this->t('Meeting and pickup'), "translate(.,':','')")}]/following::text()[position()<5]/ancestor::a[contains(@href,'maps.google.com')]/@href", null, true, "/[?&]q=(.{0,160}?)(?:&[_\w]+=|$)/i")
                ?? $http2->FindSingleNode("//text()[{$this->eq('Redemption Point', "translate(.,':','')")}]/following::text()[normalize-space()][1]/ancestor::a[contains(@href,'maps.google.com')]/@href", null, true, "/[?&]q=(.{0,160}?)(?:&[_\w]+=|$)/i")
            ;

            if (!$address) {
                $addressTexts = [];

                $nextNodes = $http2->XPath->query("//text()[{$this->eq(['Pickup Point', 'PICKUP POINT'], "translate(.,':','')")}]/following::text()[normalize-space()]");

                foreach ($nextNodes as $node) {
                    if ($http2->XPath->query("self::node()[ancestor::*[contains(@class,'info-sub-title')] or {$this->eq(['Opening hours', 'OPENING HOURS', 'Additional details', 'ADDITIONAL DETAILS'], "translate(.,':','')")}]", $node)->length > 0) {
                        break;
                    }

                    $addressTexts[] = $http2->FindSingleNode('.', $node);
                }

                if (count($addressTexts) > 0) {
                    $address = implode(' ', $addressTexts);
                }
            }

            if (!$address && $http2->XPath->query("//text()[{$this->starts($this->t('Departure point:'))}]/ancestor::*[contains(@class,'info-content')][1]/descendant::br")->length > 0) {
                $addressText = $this->htmlToText( $http2->FindHTMLByXpath("//text()[{$this->starts($this->t('Departure point:'))}]/ancestor::*[contains(@class,'info-content')][1]") );

                if (preg_match("/^[ ]*{$this->opt($this->t('Departure point:'))}[: ]*(.{3,130}?)[ ]*$/m", $addressText, $m)) {
                    $address = $m[1];
                }
            }

            if (!$address && !empty($jsonFields['address'])) {
                $address = $jsonFields['address'];
            }
            
            $event->place()
                ->name($name)
                ->address($address)
                ->type(Event::TYPE_EVENT);

            // Booked
            $event->booked()
                ->start($this->normalizeDate($http2->FindSingleNode("(//div[@class = 'tour-time'])[1]",
                    null, true, "/^\s*(.+?)(?:\\/|$)/")))
                ->noEnd()
            ;
            $guests = implode(" , ", $http2->FindNodes("//div[contains(@class, 'tour-pax')]"));

            if (preg_match_all("/\b(\d+)\b/", $guests, $m)) {
                $event->booked()
                    ->guests(array_sum($m[1]));
            }

            $name = $event->getName();

            if (!empty($name)) {
                $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Amount paid'))}][preceding::text()[normalize-space()][1][{$this->starts($name)}]]",
                    null, true, '/:\s*(.+)/');

                if (empty($total)) {
                    $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price:'))}][preceding::text()[normalize-space()][position() < 10][{$this->starts($name)}]]/following::text()[normalize-space()][1][count(preceding::a[{$this->eq($this->t('Get ticket'))} and contains(@href, 'viator')]) = {$i}]");
                }

                if (preg_match('/^(?<currency>[A-Z]{3})[^\-)(\w]*?[ ]*(?<amount>\d[,.\'\d ]*)$/', $total, $m)
                    || preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $total, $m)
                    || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\-\d)(]+?)$/', $total, $m)
                ) {
                    $event->price()
                        ->currency($m['currency'])
                        ->total(PriceHelper::parse($m['amount'], $m['currency']));
                }
            }
        }

        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total:'))}]",
            null, true, '/:\s*(.+)/');

        if (preg_match('/^(?<currency>[A-Z]{3})[^\-)(\w]*?[ ]*(?<amount>\d[,.\'\d ]*)$/', $total, $m)
                    || preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $total, $m)
                    || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\-\d)(]+?)$/', $total, $m)
                ) {
            // USD $573.12
            $email->price()
                        ->currency($m['currency'])
                        ->total(PriceHelper::parse($m['amount'], $m['currency']));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        if ( !isset(self::$dictionary, $this->lang) ) {
            return false;
        }
        foreach (self::$dictionary as $lang => $phrases) {
            if ( !is_string($lang) || empty($phrases['Get ticket']) ) {
                continue;
            }
            if ($this->http->XPath->query("//a[{$this->eq($phrases['Get ticket'])}]")->length > 0) {
                $this->lang = $lang;
                return true;
            }
        }
        return false;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // sam. 02 sept. 2023, 3:30 PM
            "/^\s*[[:alpha:]\-]+[,.\s]\s*(\d{1,2})\s+([[:alpha:]]+)[,.\s]\s*(\d{4})\s*[,\s]\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)?\s*$/ui",
            // vie., 22 sept. 2023, 8:00 a. m.
            "/^\s*[[:alpha:]\-]+[,.\s]+\s*(\d{1,2})\s+([[:alpha:]]+)[,.\s]\s*(\d{4})\s*[,\s]\s*(\d{1,2}:\d{2})\s*([AP])\.?\s*m\.?\s*$/ui",
            // sex, 12 de mai de 2023
            "/^\s*[[:alpha:]\-]+[,.\s]\s*(\d{1,2})\s+de\s+([[:alpha:]]+)\s+de\s+(\d{4})\s*$/ui",
            // qui, 11 de mai de 2023, 10:00 AM
            "/^\s*[[:alpha:]\-]+[,.\s]\s*(\d{1,2})\s+de\s+([[:alpha:]]+)\s+de\s+(\d{4})\s*[,\s]\s*(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/ui",
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1 $2 $3, $4 $5m',
            '$1 $2 $3',
            '$1 $2 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
