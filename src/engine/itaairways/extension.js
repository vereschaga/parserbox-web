var plugin = {

    hosts: {
        'itab2c.force.com': true,
        'www.ita-airways.com': true,
        'www.volare.ita-airways.com': true,
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.volare.ita-airways.com/myloyalty/s/login/?language=en_US&ec=302&startURL=%2Fmyloyalty%2Fs%2F';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(async function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);

            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (await plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");

        if (document.querySelector('c-ita-login-form') != null) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if (document.querySelector('c-loyalty-user-board') != null) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: async function (account) {
        browserAPI.log("isSameAccount");
        browserAPI.log('searching for account number');
        let number = util.findRegExp($('p.card__body-code').text(), /(\d+)/);
        if (number != null) {
            browserAPI.log("number: " + number);
            return ((typeof (account.properties) != 'undefined')
                    && (typeof (account.properties.Number) != 'undefined')
                    && (account.properties.Number !== '')
                    && number
                    && (number === account.properties.Number));
        }

        browserAPI.log('searching for account number in shadowDOM');
        let counter = 0;
        do {
            if (counter === 10) break;
            browserAPI.log("waiting 1 sec");
            await new Promise(res => setTimeout(res, 1000));
            number = document.querySelector('c-loyalty-user-board').shadowRoot.querySelector('.points-container-mobile > p:nth-child(2) > span:nth-child(2)').textContent;
            counter++;
        }
        while (number === null || number === '')
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
            $('section.section-logout > a').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('start', function () {
            window.location.href = plugin.getStartingUrl();
        });
    },

    login: function (params) {
        browserAPI.log("login");

        if (
            typeof (params.account.itineraryAutologin) == 'boolean'
            && params.account.itineraryAutologin
            && params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary');
            document.location.href = 'https://www.ita-airways.com/en_it/';
            return;
        }

        let form = $('c-ita-login-form');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log('searching for form elements');
        let email = form.get(0).querySelector('input[name = "email"]');
        let pwd = form.get(0).querySelector('input[name = "password"]');
        let btn = form.get(0).querySelector('.btn-primary');
        if (email == null || pwd == null || btn == null) {
            browserAPI.log('searching for form elements in shadowDOM');
            let shadows = form.get(0).shadowRoot;
            email = shadows.querySelector('input[name = "email"]');
            pwd = shadows.querySelector('input[name = "password"]');
            btn = shadows.querySelector('.btn-primary');
        }
        if (email == null || pwd == null || btn == null) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log('submitting saved credentials');
        email.value = params.account.login;
        email.dispatchEvent(new Event('input', {bubbles: true}));
        email.dispatchEvent(new Event('change', {bubbles: true}));
        email.dispatchEvent(new Event('blur', {bubbles: true}));

        pwd.value = params.account.password;
        pwd.dispatchEvent(new Event('input', {bubbles: true}));
        pwd.dispatchEvent(new Event('change', {bubbles: true}));
        pwd.dispatchEvent(new Event('blur', {bubbles: true}));

        provider.setNextStep('checkLoginErrors', function () {
            btn.click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('p[data-id = "bk-errors"]:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        $('a#my-flights').get(0).click();
        let properties = params.account.properties.confFields;
        util.waitFor({
            selector: 'form#form-myFlightSearch',
            success: function () {
                let form = $('form#findReservationForm').get(0);

                form.code.dispatchEvent(new Event('focus', {bubbles: true}));
                form.code.value = properties.ConfNo;
                form.code.dispatchEvent(new Event('keydown', {bubbles: true}));
                form.code.dispatchEvent(new Event('change', {bubbles: true}));
                form.code.dispatchEvent(new Event('blur', {bubbles: true}));

                form.surname.dispatchEvent(new Event('focus', {bubbles: true}));
                form.surname.value = properties.LastName;
                form.surname.dispatchEvent(new Event('change', {bubbles: true}));
                form.surname.dispatchEvent(new Event('blur', {bubbles: true}));

                provider.setNextStep('itLoginComplete', function () {
                    form.cercamyFlightSubmit.click();
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