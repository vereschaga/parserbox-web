<?php

namespace AwardWallet\Engine\marriottvacationclub\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Hotel extends \TAccountChecker
{
    public $mailFiles = "marriottvacationclub/it-97456954.eml, marriottvacationclub/it-99459422.eml";
    public $subjects = [
        '/Your Vacation Details/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'addressEnd' => ['Please go', 'Please check', 'After check', 'Low floor'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email1.marriott-vacations.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Marriott Vacation Club International')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Confirmation Details:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Accommodations Details:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email1\.marriott\-vacations\.com$/', $from) > 0;
    }

    public function ParseFormat1(Email $email): void
    {
        $this->logger->debug('function ParseFormat1');

        $h = $email->add()->hotel();

        $otaConf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reference Number:')]", null, true, "/(\d+)$/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf, 'Reference Number');
        }

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }

        $h->general()
            ->traveller($traveller, true)
            ->noConfirmation()
        ;

        $confDetailsText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('Your Accommodations Details:'))}]/ancestor::p[1]"));

        if (preg_match("/{$this->opt($this->t('Your Accommodations Details:'))}[ ]*\n+[ ]*(?<name>.{2,}?)[, ]*\n+[ ]*(?<address>[\s\S]{3,}?)[ ]*(?:\n[ ]*\n|\n+[ ]*{$this->opt($this->t('addressEnd'))})/", $confDetailsText, $m)) {
            $h->hotel()->name($m['name'])->address(preg_replace('/[ ]*\n+[ ]*/', ' ', $m['address']));
        }

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Arrival Date:')]", null, true, "/{$this->opt($this->t('Arrival Date:'))}\s*([\d\/]+)/")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Departure Date:')]", null, true, "/{$this->opt($this->t('Departure Date:'))}\s*([\d\/]+)/")));

        $h->price()
            ->total(str_replace(',', '', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Paid:')]", null, true, "/{$this->opt($this->t('Total Paid:'))}\s*\D([\d\.\,]+)/")))
            ->currency($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total Paid:')]", null, true, "/{$this->opt($this->t('Total Paid:'))}\s*(\D)/"));

        $detailsOfParticipation = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->eq($this->t('Details of Participation'))}]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]"));

        if (preg_match("/(Cancellation of reservation,\s*.+)\s*For complete/", $detailsOfParticipation, $m)
            || preg_match("/^[ ]*Rescheduling And Cancellations[ ]*:[ ]*(.+?)[ ]*$/im", $detailsOfParticipation, $m)
        ) {
            $h->general()->cancellation($m[1]);
        }
    }

    public function ParseFormat2(Email $email): void
    {
        $this->logger->debug('function ParseFormat2');

        $h = $email->add()->hotel();

        $otaConf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Accommodations Details:'))}]/preceding::text()[starts-with(normalize-space(),'Reference Number:')][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Reference Number:'))}\s*(\d+)$/");

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf, 'Reference Number');
        }

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }

        $h->general()
            ->traveller($traveller, true)
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Accommodations Details:'))}]/preceding::text()[starts-with(normalize-space(),'Confirmation Number:')][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation Number:'))}\s*(\d+)$/"), 'Confirmation Number')
        ;

        $accommodationsDetailsText = $this->htmlToText($this->http->FindHTMLByXpath("//p[{$this->eq($this->t('Your Accommodations Details:'))}]/following-sibling::p[normalize-space()][1]"));

        if (preg_match("/^\s*(?<name>.{2,}?)[, ]*\n+[ ]*(?<address>[\s\S]{3,}?)\s*$/", $accommodationsDetailsText, $m)) {
            $h->hotel()->name($m['name'])->address(preg_replace('/[ ]*\n+[ ]*/', ' ', $m['address']));
        }

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Arrival Date:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Arrival Date:'))}\s*([\d\/]+)/")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Departure Date:')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Departure Date:'))}\s*([\d\/]+)/")));
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Reference Number:')]")->length == 1) {
            $this->ParseFormat1($email);
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Reference Number:')]")->length == 2) {
            $this->ParseFormat2($email);
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
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
