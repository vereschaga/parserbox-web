var plugin = {

    hosts: {'www.vietnamairlines.com': true},

    getStartingUrl: function (params) {
        return 'https://www.vietnamairlines.com/us/en/lotusmiles/my-account';
    },

    start: function (params) {
        // IE not working properly
        if (!!navigator.userAgent.match(/Trident\/\d\./)) {
            provider.eval('jQuery.noConflict()');
        }
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
                } else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a#lbtnSignOut').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#formVna').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('span[id *= ltMemberNumberValue]:eq(0)').text();
        browserAPI.log("number: " + number);
        return (
            (typeof(account.properties) !== 'undefined') &&
            (typeof(account.properties.AccountNumber) !== 'undefined') &&
            (account.properties.AccountNumber !== '') &&
            (number == account.properties.AccountNumber)
        );
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a#lbtnSignOut').get(0).click();
        });
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('div#yourtrip-form');
        if (form.length > 0) {
            $('input#yorurtrip-reservation-code').val(properties.ConfNo);
            $('input#yorurtrip-last-name').val(properties.LastName);
            $('input#yorurtrip-email-address').val(properties.Email);
            $('div#confirm-yourtrip').click();
            provider.setNextStep('itLoginComplete', function(){
                $('input#submit-yourtrip').click();
            });
        } else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    login: function (params) {
        browserAPI.log("login");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId === 0   ) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = 'https://www.vietnamairlines.com/en/';
            });
            return;
        }

        var form = $('form#formVna');
        // open login form
        $('a[id = "hlLinkLogo"]').get(0).click();
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#lotusmile-login-acc').val(params.account.login);
            form.find('input#lotusmile-login-pass').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input#btnLogin').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }
        else
            provider.setError(util.errorMessages.unknownLoginState);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.login-error:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }

};
