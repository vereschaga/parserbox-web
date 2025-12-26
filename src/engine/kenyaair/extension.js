var plugin = {

    hosts: {
        'asante.kenya-airways.com': true
    },

    cashbackLink: '',

    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        // return 'https://asante.kenya-airways.com/login';
        return 'https://asante.kenya-airways.com/dashboard?greet=true';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);

            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    $('button.mat-focus-indicator.member-menu-button.mat-icon-button.mat-button-base.ng-star-inserted').click();
                    setTimeout(function () {
                        if (plugin.isSameAccount(params.account)) {
                            setTimeout(function() {
                                plugin.loginComplete(params);
                            }, 3000);
                        } else {
                            setTimeout(function() {
                                plugin.logout(params);
                            }, 3000);
                        }
                    }, 3000);
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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");

        if ($('cmpviews-login-form:visible').length && $('input#mat-input-0:visible').length && $('input#mat-input-1:visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('button[aria-label=Logout], button.mat-focus-indicator.navigation-menu-button.mat-icon-button.mat-button-base').length) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");

        let number = util.findRegExp($('div.name-container > div.labeled-item-container.ng-star-inserted > div.value').text(), /(.*)/i);
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && number
            && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('toLoginPage', function () {
            $('mat-icon[data-mat-icon-name="LOGOUT"],button[aria-label=Logout]')[0].click();
        });
        setTimeout(function() {
            plugin.toLoginPage(params);
        }, 3000);
    },

    toLoginPage: function (params) {
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    login: function (params) {
        browserAPI.log("login");

        function createNewEvent(eventName) {
            var event;
            if (typeof (Event) === "function") {
                event = new Event(eventName);
            } else {
                event = document.createEvent("Event");
                event.initEvent(eventName, true, true);
            }
            return event;
        }

        if (
            typeof (params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary');
            document.location.href = 'https://asante.kenya-airways.com/';
            return;
        }

        if ($('input#mat-input-0, input#mat-input-1').length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");

        $('input#mat-input-0').val(params.account.login);
        $('input#mat-input-1').val(params.account.password);

        const email = document.querySelector('input#mat-input-0');
        email.dispatchEvent(createNewEvent('input')); email.dispatchEvent(createNewEvent('change'));
        const pass = document.querySelector('input#mat-input-1');
        pass.dispatchEvent(createNewEvent('input')); pass.dispatchEvent(createNewEvent('change'));

        provider.setNextStep('checkLoginErrors', function () {
            $('button.mat-flat-button').click();
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");

        const authErrorInterval = setInterval(() => {
            const errors = $('div.snack-bar-message:visible');

            if (errors.length > 0 && util.filter(errors.text()) !== '') {
                provider.setError(errors.text());
                return;
            }

        }, 1000);

        setTimeout(() => {
            clearInterval(authErrorInterval);
            plugin.loginComplete(params);
        }, 7000);

    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        // if (typeof (params.account.itineraryAutologin) === "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
        //     provider.setNextStep('toItineraries', function () {
        //         document.location.href = 'https://asante.kenya-airways.com/trips';
        //     });
        //     return;
        // }

        provider.complete();
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};