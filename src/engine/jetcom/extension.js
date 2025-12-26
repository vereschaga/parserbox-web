
var plugin = {
    // keepTabOpen: true,
    hosts: {
        'www.jet2.com': true,
        'reservations.jet2.com': true
    },

    getStartingUrl: function (params) {
        return 'https://reservations.jet2.com/Jet2.Reservations.Web.Portal/secure/loggedin/MyJet2HomePage.aspx';
    },

    getToItinerariesUrl: function (params) {
        return 'https://www.jet2.com/en/my-bookings';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function() {
            browserAPI.log("waiting... " + counter);
            if (counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)

            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('input#ctl00_MainContent_LoginControl_FullLogin_LoginButton').length > 0
            || $('button.button--login').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[data-dialogue-id = "dialogue-logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        // browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('span#ctl00_MainContent_membershipnumberspan').text(), /number:\s*(\d+)/i);
        browserAPI.log("number: " + number);
        return (
            (typeof(account.properties) !== 'undefined') &&
            (typeof(account.properties.AccountNumber) !== 'undefined') &&
            (account.properties.AccountNumber !== '') &&
            (number === account.properties.AccountNumber)
        );
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('logout2', function() {
            $('a[data-dialogue-id = "dialogue-logout"]').get(0).click();
        });
    },

    logout2: function () {
        browserAPI.log("logout2");
        util.waitFor({
            selector: 'input[name = "ctl00$pageHeader$loginViewControl$Button3"]:visible',
            success: function(elem) {
                provider.setNextStep('logout3', function() {
                    elem.get(0).click();
                });
            },
            fail: function() {
                browserAPI.log('Failed to log out');
            }
        });
    },

    logout3: function () {
        browserAPI.log("logout2");
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#aspnetForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#ctl00_MainContent_LoginControl_FullLogin_UserName').val(params.account.login);
            form.find('input#ctl00_MainContent_LoginControl_FullLogin_Password').val(params.account.password);
            form.find('#__EVENTARGUMENT').val('LoginButton');
            form.find('#__EVENTTARGET').val('ctl00$MainContent$LoginControl$FullLogin$LoginButton');
            setTimeout(function () {
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('input#ctl00_MainContent_LoginControl_FullLogin_LoginButton').get(0).click();
                });
            }, 200);
        }// if (form.length > 0)
        else {
            form = $('form[data-selector="myjet2login-form"]');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input#emailaddress').val(params.account.login);
                form.find('input#password').val(params.account.password);
                setTimeout(function () {
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('button.button--login').get(0).click();
                    });
                }, 200);
            }// if (form.length > 0)
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.errormessage:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function() {
        browserAPI.log('loginComplete');
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId > 0    ) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = plugin.getToItinerariesUrl(params);
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('h1[data-pnr = "' + confNo +'"] + div + div.flight-details__view-booking > a[data-href = "/en/manage-my-booking"]');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function(){
                    link.get(0).click();
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    itLoginComplete: function() {
        browserAPI.log('itLoginComplete');
        provider.complete();
    }

};
