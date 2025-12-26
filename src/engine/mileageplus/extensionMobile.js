var plugin = {
    flightStatus:{
	url: 'https://mobile.united.com/FlightStatus',
    match: /^(?:UA)?\d+/i,

	start: function () {
        var input = document.getElementById('FlightNumber');
       	if (input !== null){
       		input.value = params.flightNumber.replace(/UA/gi, '');

            var depDateElem = $('option[value*="' + $.format.date(api.getDepDate(), 'M/d/yyyy') + '"]');
            if(depDateElem.length == 1){
                $('#FlightDate').val(depDateElem.val());
                api.setNextStep('finish', function () {
    				document.forms[0].submit();
    			});
            }else{
                api.errorDate();
            }

       	}        const switchAccountsLink = $('button#switch-account-button');
           if(switchAccountsLink.length) {
               switchAccountsLink.click();
           };   
	},

	finish: function () {
        if($('#VUID_FlightStatus_FlightDetails').length > 0){
            api.complete();
        }else{
            api.error($('.red-text').text().trim())
        }
	}
    },

    autologin: {
        url: "https://mobile.united.com/FrequentFlyer",

        start: function(){
            //api.setNextStep('login');
            if(this.isLoggedIn())
                if(this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            else
                this.login();
        },

        login: function(){
            var submitForm = $('#mainContent form').eq(0);
            var submitBtn = $('input#btnSubmit');
            if(submitForm.length == 1){
                $('input#UserName').val(params.login);
                $('input#Password').val(params.pass);
                if($('#html_element iframe').length > 0) {
                    provider.reCaptchaMessage();
                    submitBtn.removeAttr('onclick');
                    submitBtn.unbind('click');
                    submitBtn.bind('click', function(event){
                        api.setNextStep('checkLoginErrors', function(){
                            browserAPI.log("captcha entered by user");
                            submitForm.submit();
                        });
                        event.preventDefault();
                    });
                }else{
                    api.setNextStep('checkLoginErrors', function(){
                        submitForm.submit();
                    });
                }
            }else{
                api.error("can't find login form");
            }
        },

        isSameAccount: function(){
            var elem = $('li:contains("MileagePlus Number")');
            return (typeof(params.properties) != 'undefined' &&
                typeof(params.properties.Number) != 'undefined' &&
                params.properties.Number != '' &&
                elem.length == 1 &&
                elem.text().indexOf(params.properties.Number) > -1);
        },


        isLoggedIn: function(){
            const switchAccountsLink = $('button#switch-account-button');
            if(switchAccountsLink.length) {
                switchAccountsLink.click();
            };    

            if($('input[value="Sign In"]').length > 0){
                return false;
            }
            if($('form[action*="LogOn"]').length > 0){
                return false;
            }
            if($('form[action*="LogOff"]').length > 0){
                return true;
            }
            api.error("can't determine login state");
            return false;
        },

        checkLoginErrors: function(){
            var error = $('.validation-summary-errors');
            if(error.length > 0){
                api.error(error.text());
            }else{
                this.finish();
            }
        },

        logout: function(){
            api.setNextStep('openLoginPage', function(){
                $('form[action*="LogOff"]').submit();
            });
        },

        openLoginPage: function(){
            var url = this.url;
            api.setNextStep('login', function(){
                document.location.href = url;
            });
        },

        finish: function(){
            api.complete();
        }
    }
};