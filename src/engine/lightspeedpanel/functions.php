<?php

class TAccountCheckerLightspeedpanel extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FormURL = "http://us.lightspeedpanel.com/US/member/login.us";
        $this->http->Form["loginEmailAddress"] = $this->AccountFields['Login'];
        $this->http->Form["loginPassword"] = $this->AccountFields['Pass'];
        $this->http->Form["midInfo"] = '[navigator.appCodeName=Mozilla][navigator.appMinorVersion=undefined][navigator.appName=Netscape][navigator.appVersion=5.0 (X11; en-US)][navigator.browserLanguage=undefined][navigator.cookieEnabled=true][navigator.cpuClass=undefined][navigator.javaEnabled()=false][navigator.language=en-US][navigator.onLine=true][navigator.platform=Linux x86_64][navigator.systemLanguage=undefined][navigator.userAgent=Mozilla/5.0 (X11; U; Linux x86_64; en-US; rv:1.9.2.12) Gecko/20101027 Ubuntu/10.10 (maverick) Firefox/3.6.12][navigator.userLanguage=undefined][navigator.userProfile=undefined][window.screen.colorDepth=24][window.screen.width=1280][window.screen.height=1024][window.screen.availWidth=1280][window.screen.availHeight=974]Skype Buttons;;VLC Multimedia Plugin;VLC Multimedia Plugin;VLC Multimedia Plugin;Ogg multimedia file;Ogg multimedia file;Ogg Audio;Ogg Audio;Ogg Video;Ogg Video;Annodex exchange format;Annodex Audio;Annodex Video;MPEG video;WAV audio;WAV audio;MP3 audio;NullSoft video;Flash video;WebM video;Totem Multimedia plugin;AVI video;ASF video;AVI video;ASF video;Windows Media video;Windows Media video;Windows Media video;Windows Media video;Windows Media video;Windows Media video;Windows Media video;Microsoft ASX playlist;Windows Media audio;AVI video;QuickTime video;MPEG-4 video;MacPaint Bitmap image;Macintosh Quickdraw/PICT drawing;MPEG-4 video;Shockwave Flash;FutureSplash Player[navigator.plugins=Skype Buttons for Kopete;iTunes Application Detector;VLC Multimedia Plugin (compatible Totem 2.32.0);Windows Media Player Plug-in 10 (compatible; Totem);DivXÂ® Web Player;QuickTime Plug-in 7.6.6;Shockwave Flash]';

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        $error = $this->http->FindSingleNode('//li[@class = "nono"]');

        if (isset($error) && !empty($error)) {
            $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
            $this->ErrorMessage = $error;

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $this->http->getURL('http://us.lightspeedpanel.com/US/member/points.us');
        $this->SetProperty("LightspeedSweepstakesEntries", $this->http->FindPreg("/Lightspeed[\s]*Sweepstakes[\s]*Entries[\s]*:[^<]*<[^>]*>[^<]*<[^>]*>[^<]*<[^>]*>[^<]*<[^>]*>([^<]*)</ims"));
        $this->SetBalance($this->http->FindPreg("/Unredeemed[\s]*Lightspeed[\s]*Points[\s]*:[^<]*<[^>]*>[^<]*<[^>]*>[^<]*<[^>]*>[^<]*<[^>]*>([^<]*)</ims"));
    }
}
