<?php

namespace AwardWallet\Engine\priceline\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Cancellation extends \TAccountChecker
{
    public $mailFiles = "priceline/it-42957632.eml, priceline/it-43656092.eml, priceline/it-58318193.eml, priceline/it-77058247.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Trip Number:' => ['Trip Number:'],
            'intro'        => [
                "We've confirmed the cancellation of your priceline rental car reservation. View this email as web page We've confirmed the cancellation of your",
                "We've confirmed the cancellation of your",
                'Thank you for contacting Priceline regarding the cancellation of your',
            ],
        ],
    ];

    private $travellerName = '';
    private $cancellationPolicy = '';

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Priceline.com') !== false
            || stripos($from, '@priceline.com') !== false
            || stripos($from, '@travel.priceline.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'priceline.com') === false
        ) {
            return false;
        }

        return stripos($headers['subject'], 'Cancellation Confirmation for') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $htmlBody = $parser->getHTMLBody();

        if (empty($htmlBody)) {
            $htmlBody = $parser->getPlainBody();
            $this->http->SetEmailBody($htmlBody);
        }

        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"//www.priceline.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"email from priceline.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $htmlBody = $parser->getHTMLBody();

        if (empty($htmlBody)) {
            $htmlBody = $parser->getPlainBody();
            $this->http->SetEmailBody($htmlBody);
        }

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $tripNumber = $this->http->FindSingleNode("//tr/td[2][{$this->starts($this->t('Trip Number:'))}]");

        if (empty($tripNumber)) {
            $tripNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Trip Number:')]/ancestor::*[1]");
        }

        if (empty($tripNumber)) {
            $tripNumber = $parser->getSubject();
        }

        if (preg_match("/(?<title>{$this->opt($this->t('Trip Number:'))})\s*(?<number>[A-Z\d\-]{5,})\)?$/", $tripNumber, $m)
            || preg_match("/\(\s*(?<number>\d{3}-\d{3}-\d{3}-\d{2})\s*\)/", $tripNumber, $m)
        ) {
            $email->ota()->confirmation($m['number'], empty($m['title']) ? null : rtrim($m['title'], ': '));
        }

        $this->travellerName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('intro'))}]/preceding::text()[{$this->starts($this->t('Hey'))}]", null, true, "/{$this->opt($this->t('Hey'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*[,!]$/u");

        $this->cancellationPolicy = $this->http->FindSingleNode("//text()[{$this->contains($this->t('cancellation policy'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()]");

        $xpathIntro = "//text()[{$this->contains($this->t('intro'))}]/ancestor::tr[1]";

        $introTexts = $this->http->FindNodes($xpathIntro . "/descendant::text()[normalize-space()]");
        $introText = implode(' ', $introTexts);

        $detailsLinkType = $this->http->FindSingleNode($xpathIntro . "/following-sibling::tr/descendant::a[normalize-space(@href)]/@href", null, true, "/(?:\?|&)product=(air|rc|hotel)(?:&|$)/i");

        if (strcasecmp($detailsLinkType, 'air') === 0 || $this->http->XPath->query('//text()[normalize-space()="We\'ve confirmed the cancellation of your flight."]')->length > 0) {
            $this->parseFlight($email);
        } elseif (preg_match("/{$this->opt($this->t('intro'))}\s+(?<comp>.{3,}) {$this->opt($this->t('rental car'))} -\s*\-* (?<loc>.{3,}) {$this->opt($this->t('on'))} (?<date>.{6,})/", $introText, $m)) {
            /*
                We've confirmed the cancellation of your
                Hertz rental car - Reno Tahoe Intl Airport (RNO) on 8/21/19
            */
            $this->parseCar($email, $m);
        } elseif (preg_match("/{$this->opt($this->t('intro'))}\s+{$this->opt($this->t('booking at the'))}\s+(?<hotel>.{3,}?)\s+(?<date1>\d{1,2}\/\d{1,2}\/\d{2,4}) - (?<date2>\d{1,2}\/\d{1,2}\/\d{2,4})/", $introText, $m)) {
            /*
                We've confirmed the cancellation of your booking at the
                Baymont by Wyndham Bridgeport/Frankenmuth 08/25/2019 - 08/26/2019
            */
            $this->parseHotel($email, $m);
        }

        $email->setType('Cancellation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 3; // flight + car + hotel
    }

    private function parseFlight(Email $email): void
    {
        $this->logger->error('FLIGHT');
        // it-58318193.eml

        $f = $email->add()->flight();

        if ($this->travellerName) {
            $f->general()->traveller($this->travellerName);
        }

        $f->general()
            ->cancelled()
            ->noConfirmation();
    }

    private function parseCar(Email $email, $m): void
    {
        // it-42957632.eml

        $car = $email->add()->rental();

        if ($this->travellerName) {
            $car->general()->traveller($this->travellerName);
        }

        if ($this->cancellationPolicy) {
            $car->general()->cancellation($this->cancellationPolicy);
        }

        $car->extra()->company($m['comp']);

        $car->pickup()
            ->location(trim($m['loc'], ','))
            ->date2($m['date']);

        $car->dropoff()
            ->noLocation()
            ->noDate();

        $car->general()->cancellationNumber($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Details'))}]/following::tr[{$this->contains($this->t('Cancellation Number'))}]", null, true, "/{$this->opt($this->t('Cancellation Number'))}[: ]+([-A-Z\d]{5,})$/"), false, true);

        $carType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Details'))}]/following::tr[{$this->starts($m['comp'])} or {$this->starts($this->t('Your Rental Car Reservation'))}]/following-sibling::tr[normalize-space()][1][{$this->eq($m['date'])}]/following-sibling::tr[normalize-space()][1]");

        if (empty($carType)) {
            $carType = $this->http->FindSingleNode("//text()[{$this->eq($this->t('priceline cancellation policy'))}]/preceding::text()[normalize-space()][1]");
        }
        $car->car()->type($carType);

        $car->general()
            ->cancelled()
            ->noConfirmation();
    }

    private function parseHotel(Email $email, $m): void
    {
        // it-43656092.eml

        $h = $email->add()->hotel();

        if ($this->travellerName) {
            $h->general()->traveller($this->travellerName);
        }

        if ($this->cancellationPolicy) {
            $h->general()->cancellation($this->cancellationPolicy);
        }

        $xpathDetails = "//text()[{$this->eq($this->t('Cancellation Details'))}]/following::tr[{$this->eq($m['date1'] . ' - ' . $m['date2'])}]/following-sibling::tr[normalize-space()][1][{$this->contains($m['hotel'])}]";

        $address = $this->http->FindSingleNode($xpathDetails);

        $h->hotel()
            ->name($m['hotel'])
            ->address($address);

        $h->booked()->checkIn2($m['date1']);
        $h->booked()->checkOut2($m['date2']);

        $roomsCount = $this->http->FindSingleNode($xpathDetails . '/following-sibling::tr[normalize-space()][1]', null, true, "/\b(\d{1,3})\s+{$this->opt($this->t('room'))}/");
        $h->booked()->rooms($roomsCount);

        $h->general()
            ->cancelled()
            ->noConfirmation();
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Trip Number:']) || empty($phrases['intro'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Trip Number:'])}]")->length > 0
                || $this->http->XPath->query("//node()[{$this->contains($phrases['intro'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
