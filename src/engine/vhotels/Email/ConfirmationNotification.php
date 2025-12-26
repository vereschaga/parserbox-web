<?php

namespace AwardWallet\Engine\vhotels\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationNotification extends \TAccountChecker
{
    public $mailFiles = "vhotels/it-150744667.eml";

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'vhlv-reservations@virginhotels.com') !== false || strpos($from, 'vhlv-reservations@vh-lv.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], 'vhlv-reservations@virginhotels.com') !== false
            || strpos($headers["from"], 'vhlv-reservations@vh-lv.com') !== false
            || stripos($headers["subject"], 'Virgin Hotels Reservation Confirmation') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '/virginhotels.com')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query(
                "//text()[normalize-space() = 'Date']"
                ."/following::text()[normalize-space()][1][normalize-space() = 'Rate']"
                ."/following::text()[normalize-space()][1][normalize-space() = 'Nights']"
            )->length > 0
        ) {
            return true;
        }

        return false;
    }

    private function parseHotel(Email $email): void
    {
        $mainContentHtml = $this->http->FindHTMLByXpath("//text()[starts-with(normalize-space(),'Confirmation Number')]/ancestor::*[ descendant::text()[normalize-space() = 'Nights'] ][1]");
        $mainContent = $this->htmlToText($mainContentHtml);

//        $this->logger->debug('$mainContent = '.print_r( $mainContent,true));

        $h = $email->add()->hotel();

        if (preg_match("/Confirmation Number *:\s+(?:[[:alpha:] ]+:\s*){4}([\s\S]+?)\s+Date\s*Rate\s*Nights\b/", $mainContent, $m)) {

            if (preg_match("/^\s*(?<conf>[A-Z\d]{4,})\s*\n\s*(?<ci>.*\d{4}.*)\s*\n\s*(?<co>.*\d{4}.*)\s*\n\s*(?<type>\S.+)\s*$/", $m[1], $v)) {
                $h->general()
                    ->confirmation($v['conf'])
                    ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hello ')]", null, true, "/^\s*Hello ([[:alpha:] \-]+?)\s*,\s*$/"))
                ;

                $h->booked()
                    ->checkIn(strtotime($v['ci']))
                    ->checkOut(strtotime($v['co']));

                $h->addRoom()
                    ->setType($v['type']);
            }
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your ') and contains(., ' Team')]",
                null, true, "/^\s*Your ([[:alpha:] ]+?) Team\s*$/"))
        ;
        $xpath = "//*[self::div or self::p][normalize-space() = 'www.virginhotelslv.com']";
        $address = $this->http->FindSingleNode($xpath . "/preceding::text()[normalize-space()][1]/ancestor::*[self::div or self::p][1]",
            null, true, '/.*\d+.*/');
        $phone = $this->http->FindSingleNode($xpath . "/following::text()[normalize-space()][1]/ancestor::*[self::div or self::p][1]",
            null, true, '/^\s*[\d \.\-\+\(\)]{6,}\s*$/');
        if (strlen(preg_replace("#[^\d]+#", '', $phone)) < 7) {
            $phone = null;
        }
        if (!empty($address) && !empty($phone)) {
            $h->hotel()
                ->address($address)
                ->phone($phone);
        }

        return;
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
            $s = preg_replace('/<br\b[^>]*>/i', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/(<p\b[^>]*>)/', "\n".'$1', $s); // opening tags p
        $s = strip_tags($s);
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
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
