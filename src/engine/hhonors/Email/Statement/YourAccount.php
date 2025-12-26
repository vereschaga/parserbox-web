<?php

namespace AwardWallet\Engine\hhonors\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourAccount extends \TAccountChecker
{
    public $mailFiles = "hhonors/statements/it-61882551.eml, hhonors/statements/it-62083998.eml, hhonors/statements/it-61904793.eml, hhonors/statements/it-64797968.eml";

    private $subjects = [
        'en' => ['Hilton Honors Monthly Statement', 'Hilton Honors Statement'],
    ];

    private $patterns = [
        'boundary' => '(?:[&"%\s]|$)',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'Hilton Honors') === false) {
            return false;
        }

        $partBody = $this->http->FindHTMLByXpath('//img[contains(@src,"send_date") or contains(@src,"POINTS_AS_OF_DATE") or contains(@src,"hh_num") or contains(@alt,"Sign-in Honors#") or contains(@alt, "We’re upgrading your upcoming stay")]/ancestor::table[1]');

        if (preg_match("/(?:POINTS_AS_OF_DATE|send_date)=(\d{4}.\d+.\d+)?{$this->patterns['boundary']}/i", $partBody) // it-61882551.eml
            && preg_match("/(?:_point_balance|_points|_pointsexp)=(\d+){$this->patterns['boundary']}/u", $partBody)
            && preg_match("/tier=([A-Z]{1}){$this->patterns['boundary']}/u", $partBody)
            || preg_match("/hh_num=(\d+){$this->patterns['boundary']}/u", $partBody)
        ) {
            return true;
        }

        if (preg_match("/mi_fname[=]/i", $partBody)
            && preg_match("/mi_hotel_name[=]/u", $partBody)
            && preg_match("/tier=([A-Z]{1}){$this->patterns['boundary']}/u", $partBody)
            && preg_match("/country_residence=[A-Z]{2}/u", $partBody)
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]h\d+\.hilton\.com/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

//        $rawData = $this->http->FindHTMLByXpath('.');
        $rawData = implode(' ', array_unique($this->http->FindNodes("//a[normalize-space(@href)]/@href | //img[normalize-space(@src)]/@src", null, "/.+?\?(.+)/")));

        // mi_interaction_point=Banner.Bottom
        $rawData = preg_replace("/[-_A-z\d]*interaction_point=\D.*?{$this->patterns['boundary']}/i", '', $rawData);

        $this->logger->warning($rawData);

        if (preg_match_all("/name=([[:alpha:]][-.[:alpha:]]*[[:alpha:]]){$this->patterns['boundary']}/ui", $rawData, $m)
            && count(array_unique($m[1])) === 1
        ) {
            // it-61904793.eml
            $st->addProperty('Name', $m[1][0]);
        } elseif (preg_match("/&mi_FNAME=(.*?){$this->patterns['boundary']}/ui", $rawData, $m1)
            && preg_match("/&mi_LNAME=(.*?){$this->patterns['boundary']}/ui", $rawData, $m2)
        ) {
            $st->addProperty('Name', $m1[1] . ' ' . $m2[1]);
        } elseif (preg_match("/&mi_FNAME=(.*?){$this->patterns['boundary']}/ui", $rawData, $m1)
        ) {
            $st->addProperty('Name', $m1[1] . ' ' . $m2[1]);
        }

        if (preg_match_all("/total_points=(\d+){$this->patterns['boundary']}/iu", $rawData, $m)
            && count(array_unique($m[1])) === 1
        ) {
            // it-61904793.eml
            $st->setBalance($m[1][0]);
        } elseif (preg_match_all("/(?:_point_balance|_points|_pointsexp)=(\d+){$this->patterns['boundary']}/iu", $rawData, $m)
            && count(array_unique($m[1])) === 1
        ) {
            $st->setBalance($m[1][0]);
        } elseif (!preg_match_all("/point/i", $rawData)) {
            // it-64797968.eml
            $st->setNoBalance(true);
        }

        if (preg_match_all("/hh_num=(\d+){$this->patterns['boundary']}/u", $rawData, $m)
            && count(array_unique($m[1])) === 1
        ) {
            $st->setNumber($m[1][0])
                ->setLogin($m[1][0])
            ;
        }

        $statusVariants = [
            'D' => 'Diamond',
            'G' => 'Gold',
            'S' => 'Silver',
            'B' => 'Member',
        ];

        $status = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'Your tier is')][1]", null, true, "/Your tier is\s+({$this->opt($statusVariants)})(?:\s*[.,;!?]|$)/i");

        if ($status) {
            $st->addProperty('Status', $status);
        } elseif (preg_match_all("/tier=([A-Z]{1}){$this->patterns['boundary']}/u", $rawData, $m)
            && count(array_unique($m[1])) === 1
        ) {
            foreach ($statusVariants as $key => $sVariant) {
                if (strcasecmp($m[1][0], $key) === 0) {
                    $st->addProperty('Status', $sVariant);

                    break;
                }
            }
        }

        if (preg_match_all("/(?:POINTS_AS_OF_DATE|send_date)=(\d{4}.\d+.\d+){$this->patterns['boundary']}/ui", $rawData, $m)
            && count(array_unique($m[1])) === 1
        ) {
            $st->setBalanceDate(strtotime($m[1][0]));
        } elseif (($activityAs = $this->http->FindSingleNode("//img[contains(@alt,'Activity as of')]/@alt", null, true, "/Activity as of\s+(.{6,})$/i"))) {
            // it-61904793.eml
            $st->parseBalanceDate($activityAs);
        }

        // Login field is not email!
        $termsAndConditions = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(),'This email was delivered to') or starts-with(normalize-space(),'This email advertisement was delivered to')]/ancestor::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^[ ]*(?:This email was delivered to|This email advertisement was delivered to)\s+(\S+@\S+)(?:[,.;! ]|$)/im", $termsAndConditions, $m)) {
//            $st->addProperty('Login', $m[1]);
            $email->setUserEmail($m[1]);
        }
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
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
