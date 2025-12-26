var plugin = {

    hosts: {'transaero.ru': true, 'members.transaero.ru': true},

    getStartingUrl: function (params) {
		//return 'https://members.transaero.ru/ui/profile/login?locale=en';
		return 'https://members.transaero.ru/ui/loyalty/status?locale=en';
    },

    getFocusTab: function (account, params) {
        return true;
    },

    start: function (params) {
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[id = "loginForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Logout"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        browserAPI.log("Can't determine login state");
        provider.setError(["Can't determine login state", util.errorCodes.providerError]);
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('p#cardNumber').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start');
        $('a:contains("Logout"):visible').get(0).click();
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[id = "loginForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function() {
                    var captcha = util.findRegExp( form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
                    if (captcha.length > 0) {
                        provider.reCaptchaMessage();
                        browserAPI.log("waiting...");
                        setTimeout(function() {
                            provider.setError(['We could not recognize captcha. Please try again later.', util.errorCodes.providerError], true);
                        }, 30000);
                        //form.find('button[name = "login"]').get(0).click();#11315
                    }// if (captcha.length > 0)
                    else
                        browserAPI.log("captcha is not found");
                }, 3000)
            });
        }
        else {
            browserAPI.log("Login form not found");
            provider.setError(["Login form not found", util.errorCodes.providerError]);
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function (params) {
        var errors = $('div.alert-danger:visible');
        if (errors.length > 0)
            provider.setError(errors.text().trim());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        plugin.loadAccount(params);
    },

    loadAccount: function (params) {
        browserAPI.log("loadAccount");
        browserAPI.log('Current URL: ' + document.location.href);
        if (params.autologin) {
            provider.complete();
            return;
        }
        browserAPI.log("Loading account");
        if (document.location.href != 'https://members.transaero.ru/ui/loyalty/status?locale=en') {
            browserAPI.log('>> Opening Account page...');
            provider.setNextStep('parse', function(){
                document.location.href = 'https://members.transaero.ru/ui/loyalty/status?locale=en';
            });
        } else {
            plugin.parse(params);
        }
    },

    parse: function(params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);

        var data = {};
        // Balance - Points Balance
        var balance = $('p#balance').text();
        balance = util.findRegExp( balance, /([\d\.\,]+)/i);
        if (balance.length > 0) {
            browserAPI.log("Balance: " + balance );
            data.Balance = util.trim(balance);
        }else
            browserAPI.log("Balance is not found");
        // Card number
        var cardNumber = $('p#cardNumber');
        if (cardNumber.length > 0) {
            browserAPI.log("Card number: " + cardNumber.text() );
            data.Number = util.trim(cardNumber.text());
        }else
            browserAPI.log("Card number not found");
        // Card type
        var cardtype = $('p#cardtype');
        if (cardtype.length > 0) {
            browserAPI.log("Card type: " + cardtype.text() );
            data.CardType = util.trim(cardtype.text());
        }else
            browserAPI.log("Card type not found");
        // Kvalification point
        var qualificationPoints = $('p#kvalifpoint');
        if (qualificationPoints.length > 0) {
            browserAPI.log("QualificationPoints: " + qualificationPoints.text() );
            data.QualificationPoints = util.trim(qualificationPoints.text());
        }else
            browserAPI.log("QualificationPoints not found");
        // Count of kvalification flight
        var qualificationFlights = $('p#kvalifcountflight');
        if (qualificationFlights.length > 0) {
            browserAPI.log("QualificationFlights: " + qualificationFlights.text() );
            data.QualificationFlights = util.trim(qualificationFlights.text());
        }else
            browserAPI.log("QualificationFlights not found");
        // Last paid flight
        var latestFlight = $('p#lastpaidflight');
        if (latestFlight.length > 0) {
            browserAPI.log("Last paid flight: " + latestFlight.text() );
            data.LatestFlight = util.trim(latestFlight.text());
        }else
            browserAPI.log("Last paid flight not found");
        // Number point to upgrade
        var pointsToNextLevel = $('label:contains("Number point to upgrade") + div');
        if (pointsToNextLevel.length > 0) {
            browserAPI.log("PointsToNextLevel: " + pointsToNextLevel.text() );
            data.PointsToNextLevel = util.trim(pointsToNextLevel.text());
        }else
            browserAPI.log("PointsToNextLevel not found");
        // Number flight to upgrade level
        var flightsToNextLevel = $('label:contains("Number flight to upgrade level") + div');
        if (flightsToNextLevel.length > 0) {
            browserAPI.log("FlightsToNextLevel: " + flightsToNextLevel.text() );
            data.FlightsToNextLevel = util.trim(flightsToNextLevel.text());
        }else
            browserAPI.log("FlightsToNextLevel not found");
        // Point to confirm current level
        var pointsToRetainLevel = $('label:contains("Point to confirm current level") + div');
        if (pointsToRetainLevel.length > 0) {
            browserAPI.log("PointsToRetainLevel: " + pointsToRetainLevel.text() );
            data.PointsToRetainLevel = util.trim(pointsToRetainLevel.text());
        }else
            browserAPI.log("PointsToRetainLevel not found");
        // Number flight to confirm current level
        var flightsToRetainLevel = $('label:contains("Number flight to confirm current level") + div');
        if (flightsToRetainLevel.length > 0) {
            browserAPI.log("FlightsToRetainLevel: " + flightsToRetainLevel.text() );
            data.FlightsToRetainLevel = util.trim(flightsToRetainLevel.text());
        }else
            browserAPI.log("FlightsToRetainLevel not found");

        params.data.properties = data;
        // Parsing LastActivity
        provider.setNextStep('parseName', function () {
            document.location.href = 'https://members.transaero.ru/ui/profile/profileData?locale=en';
        });
    },

    parseName: function(params) {
        browserAPI.log("parse");
        provider.updateAccountMessage();
        browserAPI.log('Current URL: ' + document.location.href);

        // Name
        var name = $('p#name');
        var surname = $('p#surname');
        if (name.length > 0 && surname.length > 0) {
            name = util.beautifulName( util.trim(name.text()) + ' ' + util.trim(surname.text()) );
            browserAPI.log("Name: " + name );
            params.data.properties.Name = name;
        }else
            browserAPI.log("Name not found");

        params.account.properties = params.data.properties;
        //console.log(params.account.properties);
        provider.saveProperties(params.account.properties);
        provider.complete();
    }

}
