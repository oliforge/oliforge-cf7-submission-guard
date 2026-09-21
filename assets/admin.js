( function () {
    document.addEventListener( 'DOMContentLoaded', function () {
        var notices = document.querySelectorAll( '.settings-error.is-dismissible' );
        notices.forEach( function ( notice ) {
            setTimeout( function () {
                notice.style.transition = 'opacity 400ms ease';
                notice.style.opacity = '0';
                setTimeout( function () {
                    notice.remove();
                }, 400 );
            }, 5000 );
        } );
    } );
} )();
