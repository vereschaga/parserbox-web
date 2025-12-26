<?php

// bcdtravel, screenshots there is

namespace AwardWallet\Engine\mandalay\Email;

class BeginsHtml2017En extends \TAccountChecker
{
    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its[] = $this->parseHotel($parser->getHTMLBody());

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'BeginsHtml2017En',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@hotel.mandalaybay.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'It\'s time to start packing!') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Your Mandalay Bay escape begins in ') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@hotel.mandalaybay.com') !== false;
    }

    protected function parseHotel($html)
    {
        $i = ['Kind' => 'R'];

        $hotel = array_filter($this->http->FindNodes('//a[text()="Unsubscribe"]/following-sibling::text()'));

        if (array_pop($hotel) !== 'mandalaybay.com') {
            $this->http->Log('Verify address is correct', LOG_LEVEL_ERROR);

            return;
        }

        $i['Status'] = stripos($html, 'RESERVATION CONFIRMATION') !== false ? 'confirmation' : null;
        $i['HotelName'] = array_shift($hotel);
        $i['Address'] = join(', ', $hotel);
        $i['ConfirmationNumber'] = $this->http->FindSingleNode('//strong[.="RESERVATION NUMBER:"]/following-sibling::span[1]');
        $i['CheckInDate'] = strtotime($this->http->FindSingleNode('//strong[.="CHECK-IN:"]/following-sibling::span[1]'), false);
        $i['CheckOutDate'] = strtotime($this->http->FindSingleNode('//strong[.="CHECK-OUT:"]/following-sibling::span[1]'), false);
        $i['RoomType'] = $this->http->FindSingleNode('//strong[.="ROOM TYPE:"]/following-sibling::span[1]');

        return $i;
    }
}
