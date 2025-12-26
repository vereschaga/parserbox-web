var plugin = {
    flightStatus:{
        url: 'http://flights.virginaustralia.com/flight-status/#flight_number',
        match: /^(?:VA)?\d+/i,

        start: function () {
            browserAPI.log("start");
            var input = $('input[name = "flightnumber"]');
            var form = input.parents('form[name = "flight"]');
            if (input.length == 1 && form.length == 1) {
                input.val(params.flightNumber.replace(/VA/gi, ''));
                var dateInput = form.find('select[name = "date"]');

                var date = api.getDepDate().getDate();
                browserAPI.log("Date: " + date + " / " + api.getDepDate());
                var depDateName = dateInput.find('option[label ^= "' + date + '"]');

                if (depDateName.length > 0) {
                    dateInput.val(depDateName.attr('value'));
                    api.setNextStep('finish', function(){
                        form.find('button[type = "submit"]').get(0).click();
                    });
                } else {
                    api.errorDate();
                }
            }
        },

        finish: function () {
            browserAPI.log("finish");
            var error = $('p.error');
            if (error.length > 0)
                api.error(error.text().trim());
            else
                api.complete();
        }
    },

    autologin: {
        url: "https://mobile.virginaustralia.com/manage/index.html",

        start: function () {
            if(this.isLoggedIn())
                if(this.isSameAccount())
                    api.complete();
                else
                    this.logout();
            else
                this.login();
        },

        isLoggedIn: function () {
            if($('#loggedOut[class = ""]').length > 0){
                return false;
            }

            if($('.member-number-details').length > 0){
                return true;
            }

            throw "can't determine login state";
        },

        login: function () {
            var form = $('form[action *= "login.html"]');
            var showFormButton = $('#loyalty-login');
            if(form.length == 1 && showFormButton.length == 1){
                showFormButton.click();
                setTimeout(function(){
                    var button = $('#continueLogin');
                    button.removeAttr('disabled');
                    form.find('input#velocityLogin').val(params.login);
                    form.find('input#passwordLogin').val(params.pass);
                    api.setNextStep('checkLoginErrors', function () {
                        button.click();
                    });
                }, 2000);
            } else {
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.Number) !== 'undefined')
                && ($('.number-value').length > 0)
                && ($('.number-value').eq(0).text().indexOf(params.properties.Number) !== -1)
        },

        checkLoginErrors: function () {
            var error = $('.alert.error');
            if(error.length > 0){
                api.error(error.text().trim());
            } else {
                api.complete();
            }
        },

        logout: function (){
            api.setNextStep('login', function () {
                $('#continueLogout').click();
            });
        },

        finish: function () {
            api.complete();
        }
    }
};