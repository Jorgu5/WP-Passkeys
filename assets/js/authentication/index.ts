import Authentication from './Authentication';

document.addEventListener( 'DOMContentLoaded', () => {
	const authHandler = new Authentication();
	authHandler.init( true ).then( ( r ) => console.info( 'You rock!' ) );
} );
