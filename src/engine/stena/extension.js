var plugin = {

    hosts: {
        'stenaline.co.uk'        : true,
        'www.stenaline.co.uk'    : true,
        'booking.stenaline.co.uk': true,
        'stenaline.de'           : true,
        'booking.stenaline.de'   : true,
        'www.stenaline.de'       : true,
    },

    getStartingUrl: function (params) {
        switch (params.account.login2) {
            case 'Germany':
                return 'https://booking.stenaline.de/my-pages';
            case 'UK':
            default:
                return 'https://booking.stenaline.co.uk/my-pages';
        }
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
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
        if ($('div#pnlTopLogin').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('input#main_1_maincol1_0_btnLogOut').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = $('label:contains("Loyalty member:"), label:contains("Extra-Nummer:")').next('label').text().trim();
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.LoyaltyMember) != 'undefined')
            && (account.properties.LoyaltyMember !== '')
            && (number === account.properties.LoyaltyMember));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('input#main_1_maincol1_0_btnLogOut').click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('div#pnlTopLogin');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        const loginInput = form.find('input[name = "Email"]');
        const passwordInput = form.find('input[name = "Password"]');
        loginInput.val(params.account.login);
        passwordInput.val(params.account.password);
        util.sendEvent(loginInput.get(0), 'blur');
        util.sendEvent(passwordInput.get(0), 'blur');
        provider.setNextStep('checkLoginErrors', function() {
            $('a#lbSubmit').get(0).click();
        });
        setTimeout(function() {
            plugin.checkLoginErrors(params);
        }, 8000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.tpl_validationCallout-target div.tooltip-content');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId > 0
        ) {
            provider.setNextStep('toItineraries', function () {
                switch (params.account.login2) {
                    case 'Germany':
                        document.location.href = 'https://booking.stenaline.de/my-pages/my-bookings';
                        break;
                    case 'UK':
                    default:
                        document.location.href = 'https://booking.stenaline.co.uk/my-pages/my-bookings';
                        break;
                }
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var itinUrl = null;
            switch (params.account.login2) {
                case 'Germany':
                    itinUrl = 'https://booking.stenaline.de/book/Confirmation?ResCode=';
                    break;
                case 'UK':
                default:
                    itinUrl = 'https://booking.stenaline.co.uk/book/Confirmation?ResCode=';
                    break;
            }
            itinUrl += confNo;
            provider.setNextStep('itLoginComplete', function() {
                document.location.href = itinUrl;
            });
        }, 2000);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};