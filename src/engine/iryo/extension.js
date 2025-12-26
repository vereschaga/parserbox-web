var plugin = {

    hosts: {
        'auth.iryo.eu': true,
        'iryo.eu': true,
        'iryo-clubyo.loyaltysp.es': true
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://iryo-clubyo.loyaltysp.es';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);

            if (isLoggedIn !== null) {
                clearInterval(start);
                setTimeout(function() {
                    if (isLoggedIn) {
                        if (plugin.isSameAccount(params.account))
                            plugin.loginComplete(params);
                        else
                            plugin.logout(params);
                    } else
                        plugin.login(params);    
                }, 3000);
            }// if (isLoggedIn !== null)

            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)

            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");

        if ($('form#kc-login-form').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('div.data-img > div:nth-child(2)')) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");

        let number = util.findRegExp($('div.data-img > div:nth-child(2)').text(), /(.*)/i);
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.Number) != 'undefined')
                && (account.properties.Number !== '')
                && number
                && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://auth.iryo.eu/auth/realms/ilsa/protocol/openid-connect/logout?redirect_uri=https%3A%2F%2Firyo.eu%2F%3Fclient%3DeyJjbGllbnRJZCI6ImIyYyIsImNoYW5uZWwiOiJXRUIiLCJpc1JlcXVlc3RQYXJhbSI6dHJ1ZX0%253D';
        });
    },    

    login: function (params) {
        browserAPI.log("login");

        if (
            typeof (params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary');
            document.location.href = 'https://iryo.eu/en/';
            return;
        }

        let form = $('form#kc-login-form');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input#username').val(params.account.login);
        form.find('input#password').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('input#kc-login').get(0).click();
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div#input-error:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof (params.account.itineraryAutologin) === "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItinerariesPreparation', function () {
                document.location.href = 'https://iryo.eu/en/home';
            });
            return;
        }
        provider.complete();
    },

    toItinerariesPreparation: function(params) {
        browserAPI.log('toItinerariesPreparation');
        provider.setNextStep('toItineraries', function () {
            document.location.href = 'https://iryo.eu/en/my-bookings';
        });
    },

    toItineraries: function (params) {
        browserAPI.log('toItineraries');

        $(`div.ilsa-tabs__row.ilsa-tabs__longTabs > div:nth-of-type(1)`).click();
        setTimeout(function() {
            const el = $(`span:contains("${params.account.properties.confirmationNumber}")`);
            if (el.length) {
                el[0].scrollIntoView();
                provider.itLoginComplete();
            }// if (link)   
        }, 2000);

        setTimeout(function() {
            provider.setError(util.errorMessages.itineraryNotFound);
        }, 4000);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        let properties = params.account.properties.confFields;
        util.waitFor({
            selector: 'form#findReservationForm',
            success: function () {
                let form = $('form#findReservationForm');
                form.find('input[name *= "ConfirmationNumber"]').val(properties.ConfNo);
                form.find('input[name *= "LastName"]').val(properties.LastName);
                provider.setNextStep('itLoginComplete', function () {
                    $('input[name = "btnSubmit"]').get(0).click();
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            },
            timeout: 10
        });
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};