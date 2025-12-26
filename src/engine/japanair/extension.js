var plugin = {

    hosts: {
        'www.jal.co.jp': true,
        'www121.jal.co.jp': true,
        'www.ar.jal.co.jp': true,
        'www.aor.jal.com': true,
        'www.er.jal.com': true,
        '/www\\.\\w+\\.jal\\.com/': true,
        '/www\\.\\w+\\.jal\\.co\\.jp/': true,
        '/[\\w-]+.jal\\.co\\.jp/': true,
       // 'jallogin.jal.co.jp': true

    },

	getStartingUrl: function(params){
        var url;
        switch (params.account.login2) {
            case 'Europe'://deprecated
                url = 'https://www.de.jal.co.jp/er/en/jmb/';
                params.account.login2 = 'er';
                break;
            case 'br':
                // url = 'https://www.br.jal.co.jp/brl/pt/';
                url = 'https://www.jal.co.jp/arl/en/jmb/';
                break;
            case 'be':
                url = 'https://www.nl.jal.co.jp/nll/en/?country=be';
                break;
            case 'cz':
                url = 'https://www.at.jal.co.jp/atl/en/?country=cz';
                break;
            case 'dk':
                url = 'https://www.nl.jal.co.jp/nll/en/?country=dk';
                break;
            case 'es':
                url = 'https://www.es.jal.co.jp/esl/en/?country=es';
                break;
                case 'Japan'://deprecated
            case 'ja':
                url = 'https://www.jal.co.jp/en/jmb/';
                break;
            case 'Asia'://deprecated
            case 'hl':
                url = 'https://www.ar.jal.co.jp/arl/en/jmb/?country=hl';
                break;
            case 'ie':
                url = 'https://www.uk.jal.co.jp/ukl/en/?city=DUB';
                break;
            case 'mx':
                url = 'https://www.ar.jal.co.jp/arl/en/?city=MEX';
                break;
            case 'pl':
                url = 'https://www.at.jal.co.jp/atl/en/?country=pl';
                break;
            case 'ru':
                url = 'https://www.uk.jal.co.jp/ukl/en/?country=ru';
                break;
            case 'se':
                url = 'https://www.nl.jal.co.jp/nll/en/?country=se';
                break;
            case 'sg':
                url = 'https://www.sg.jal.co.jp/sgl/en/';
                break;
            case 'America':
            case 'ar':
            case 'ca':
            case '':
            case null:
            case undefined:
                url = 'https://www.ar.jal.co.jp/arl/en/jmb/';
                params.account.login2 = 'ar';
                break;
            case 'hk':
            case 'au':
                url = "https://www." + params.account.login2 + ".jal.co.jp/" + params.account.login2 + "l/en/index_b.html";
                break;
            default:
                url = "https://www." + params.account.login2 + ".jal.co.jp/" + params.account.login2 + "l/en/";
                break;
        }// switch ($this->AccountFields['Login2'])

		return url;
	},

    loadLoginForm: function (params) {
        browserAPI.log("logout");
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
                else {
                    provider.setNextStep('login', function(){
                        $('button.JS_memLoginSubmit, a[data-event="JS_loginBtn"]').get(0).click();
                    });
                    // plugin.login(params);
                }
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
        if (
            $('a:contains("Logout"):visible, span#JS_logout:visible, span.JS_hdrLogout:visible').length > 0
            || $('div#JS_121_jmbStsTimeout:visible').length > 0
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        const form = $('form[name = "jmb_log_in"], form[id *= "memberLogin"], form[action *= "JMBmemberTop_en"]');
        if (
            form.length > 0
            || $('button.JS_memLoginSubmit, a[data-event="JS_loginBtn"]').length > 0
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var name = $('span[id *= "dispMemName"], #JS_121_dispMemName');
        browserAPI.log("name: " + name.text());
            return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.length > 0)
            && (name.text().toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            switch (params.account.login2) {
                case 'Japan':
                    document.location.href = 'https://www121.jal.co.jp/JmbWeb/JR/LogOut_en.do';
                    break;
                case 'America':
                case 'Europe':
                case 'Asia':
                default:
                    document.location.href = 'https://www121.jal.co.jp/JmbWeb/LogOut_en.do';
                    break;
            }// switch ($this->AccountFields['Login2'])
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('toItinerariesRedirect', function(){
                params.data.goTo = 'getConfNoItinerary';
                provider.saveTemp(params.data);
                plugin.toItinerariesUrl(params);
            });
            return;
        }
        const form = $('form#JS_jmbForm');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        // form.find('input[id = "LA_input-number-01"]').val(params.account.login);
        // form.find('input[id = "LA_input-password"]').val(params.account.password);

        // reactjs
        provider.eval(
            "function triggerInput(selector, enteredValue) {\n" +
            "      let input = document.querySelector(selector);\n" +
            "      input.dispatchEvent(new Event('focus'));\n" +
            "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
            "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
            "      nativeInputValueSetter.call(input, enteredValue);\n" +
            "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
            "      input.dispatchEvent(inputEvent);\n" +
            "}\n" +
            "triggerInput('#LA_input-number-01', '" + params.account.login + "');\n" +
            "triggerInput('#LA_input-password', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors', function(){
            form.find('button[name = "__dummy__"]').get(0).click();

            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const error = $('div#JS_error p:visible');

        if (error.length > 0 && util.filter(error.text()) !== '') {
            provider.setError(util.filter(error.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItinerariesRedirect', function () {
                params.data.goTo = 'toItineraries';
                provider.saveTemp(params.data);
                plugin.toItinerariesUrl(params);
            });
            return;
        }
        provider.complete();
    },

    toItinerariesUrl: function (params) {
        var country = 'ar';
        switch (params.account.login2) {
            case 'ja':
                country = 'jp';
                break;
            default:
                country = params.account.login2;
                break;
        }
        document.location.href = 'https://www.jal.co.jp/' + country + '/en/';
    },

    toItinerariesRedirect: function (params) {
        provider.setNextStep(params.data.goTo, function () {
            let panel, btn;
            switch (params.account.login2) {
                case 'ja':
                    panel = $('a:contains("Manage Bookings"):visible');
                    if (panel.length > 0) {
                        panel.get(0).click();
                        plugin.itLoginComplete(params);

                        /*btn = $('a:contains("International Flights"):visible');
                        if (btn.length > 0) {
                            btn.get(0).click();
                        }*/
                    }
                    break;
                default:
                    panel = $('span:contains("MANAGE BOOKING")');
                    if (panel.length > 0) {
                        panel.get(0).click();
                        btn = $('#TOPtab_managebooking_01');
                        if (btn.length > 0) {
                            btn.get(0).click();
                        }
                    }
                    break;
            }
        });
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var radio = $('td[id^="text-pnr-reference"]:contains("'+ confNo +'")').closest('tr').find('td:eq(0)').find('label[for^="input-select-pnr"]');
            if (radio.length > 0) {
                radio.get(0).click();
                provider.setNextStep('itLoginComplete', function(){
                    var btn = $('form #button-select');
                    if (btn.length > 0) {
                        btn.get(0).click();
                    }
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form:contains("NON JMB MEMBER")');
        if (form.length > 0) {
            console.log(properties.DepartureDate);
            let date = new Date(properties.DepartureDate);
            let boardingDate = [date.getDate().toString().padStart(2, '0'), (date.getMonth() + 1).toString().padStart(2, '0')].join('/')
            // reactjs
            provider.eval(
                "function triggerInput(selector, enteredValue) {\n" +
                "      let input = document.querySelector(selector);\n" +
                "      input.dispatchEvent(new Event('focus'));\n" +
                "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
                "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
                "      nativeInputValueSetter.call(input, enteredValue);\n" +
                "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
                "      input.dispatchEvent(inputEvent);\n" +
                "}\n" +
                "triggerInput('#input-airlineCode', '" + properties.AirlineCode + "');\n" +
                "triggerInput('#input-flightNumber', '" + properties.FlightNumber + "');\n" +
                "triggerInput('#input-boardingDate', '" + boardingDate + "');\n" +
                "triggerInput('#input-lastName', '" + properties.LastName + "');\n" +
                "triggerInput('#input-firstName', '" + properties.FirstName + "');\n" +
                "triggerInput('#input-bookingRef', '" + properties.ConfNo + "');\n"
            );

            util.sendEvent(form.find('#input-flightNumber').get(0), 'blur');
            util.sendEvent(form.find('#input-boardingDate').get(0), 'blur');
            util.sendEvent(form.find('#input-lastName').get(0), 'blur');
            util.sendEvent(form.find('#input-firstName').get(0), 'blur');
            util.sendEvent(form.find('#input-bookingRef').get(0), 'blur');

            provider.setNextStep('itLoginComplete', function() {
                setTimeout(function () {
                    form.find('button#button-bookingDetails').click();
                }, 1000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },


    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};