var plugin = {
    url: 'https://google.com',
    getStartingUrl: function (params) {
        return this.url;
    },
    start: function (params) {
        provider.setNextStep('login', function(){
            document.location.href = 'https://yahoo.com';
        });
    },
    login: function(){
        provider.showFader('Test Message for Display Fader');
        setTimeout(() => {
            provider.setNextStep('parse', function(){
                document.location.href = 'https://yandex.ru';
            });
        }, 30 * 1000)

    },
    parse: function(params){
        var properties = params.account.properties;
        properties.Balance = 1000;
        provider.complete();
    }
};
