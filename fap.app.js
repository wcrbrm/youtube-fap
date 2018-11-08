angular.module('app', [] );
angular.module('app').controller('AppController',       AppController );

angular.module('app').filter('pct', function() {
    return function( fltAmount ) {
        if ( fltAmount === '' || isNaN( fltAmount ) ) return "";
        var fltAmt =  parseFloat( fltAmount ).toFixed(1);
        return fltAmt + "%";
    };
});


function AppController( $scope, $rootScope, $http ) {
   console.log('controller initialized');
   $scope.videos = Object.values(window.VIDEOS);
   var queue = Object.values(window.VIDEOS);

   var next = function() {
     if (queue.length == 0) return;

     var id = queue[0].id;
     var url ='./fap.php?id=' + id;
     console.log('starting ', id);
     $http.get(url).then(function(response) {
        console.log('ended ', id);
        window.VIDEOS[id] = Object.assign(window.VIDEOS[id], response.data);
        $scope.videos = Object.values(window.VIDEOS);          
        queue.shift();
	next();
     });
   };
   next();
}

console.log('initialized?');

AppController.$inject = [ '$scope',  '$rootScope', '$http' ];
