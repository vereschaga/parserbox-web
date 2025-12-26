<?php

namespace AwardWallet\Engine\goibibo\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Pdf extends \TAccountChecker
{
    public $mailFiles = "goibibo/it-45380610.eml";

    private $lang = '';

    private $reBody = [
        'en' => ['Hotel Name', 'CHECK-IN:'],
    ];

    private $from = '/[\.@]goibibo\.com/i';

    private $prov = 'ibiboGroup';

    private $pdfNamePattern = '.*pdf';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    } else {
                        if (!$this->parseEmail($email, $text)) {
                            $this->logger->alert('method: ' . __METHOD__ . 'exited with false result');

                            return null;
                        }
                    }
                }
            }
        }
        $email->setType('Pdf' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (false === stripos($text, $this->prov)) {
                return false;
            }

            if ($this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(Email $email, string $text): bool
    {
        //$this->logger->debug($text);
        $h = $email->add()->hotel();

        if ($conf = $this->re('/Booking ID:[ ]+(\w+)/', $text)) {
            $h->general()
                ->confirmation($conf);
        }

        $totalText = $this->findCutSection($text, 'Net Amount Paid', 'Total has been');

        if (preg_match('/([a-z\.]+)[ ]+(\d+)/i', $totalText, $m)) {
            $h->price()
                ->currency(str_replace('Rs.', 'INR', $m[1]))
                ->total($m[2]);
        }

        $pax = $this->findCutSection($text, 'Hotel Name', 'Guest Email');

        if ($guest = $this->re('/Guest Name:[ ]+(.+?)\s{2,}/', $pax)) {
            $h->general()
                ->traveller($guest);
        }

        $hotelInfo = $this->findCutSection($text, 'Description', 'Total has been rounded off to next rupee value');

        if ($hName = $this->re('/Hotel Name:[ ]+(.+)/', $hotelInfo)) {
            $h->hotel()
                ->name($hName)
                ->noAddress();
        }

        //  CHECK-IN: Feb. 27, 2020 CHECK-OUT: March 2, 2020
        // CHECK-IN: March 11, 2020 CHECK-OUT: March 12, 2020
        $re = '/CHECK-IN:[ ]+(?<IMonth>\w+)\.?[ ]*(?<IDay>\d{1,2}),[ ]*(?<IYear>\d{2,4})[ ]+CHECK-OUT:[ ]+(?<OMonth>\w+)\.?[ ]*(?<ODay>\d{1,2}),[ ]*(?<OYear>\d{2,4})/i';

        if (preg_match($re, $hotelInfo, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['IDay'] . ' ' . $m['IMonth'] . ' ' . $m['IYear']))
                ->checkOut(strtotime($m['ODay'] . ' ' . $m['OMonth'] . ' ' . $m['OYear']));
        }

        if ($rooms = $this->re('/No\. of Rooms:[ ]*(\d{1,2})/', $hotelInfo)) {
            $h->booked()
                ->rooms($rooms);
        }

        $r = $h->addRoom();

        if ($type = $this->re('/Room Type:[ ]+(.+)/', $hotelInfo)) {
            $r->setType($type);
        }

        return true;
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function findCutSection($input, $searchStart, $searchFinish = null): string
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return '';
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }
}
