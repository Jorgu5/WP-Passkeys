import Utilities from '../Utilities';

class EmailConfirmation {
	private readonly registerWrapper: HTMLElement;

	constructor() {
		this.registerWrapper = document.querySelector(
			'#registerform',
		) as HTMLElement;
	}

	init(): void {
		this.verifyUserEmail().then( () => {
			console.log( 'Email verification complete' );
		} );
	}

	async verifyUserEmail(): Promise<any> {
		try {
			const urlParams = new URLSearchParams( window.location.search );
			const email = urlParams.get( 'email' );
			const pkEmailToken = urlParams.get( 'pkEmailToken' );

			if ( ! pkEmailToken || ! email ) {
				throw new Error(
					'Email verification token or email address is missing.',
				);
			}

			const url = `${ pkUser.restEndpoints.main }/register/user/email?pkEmailToken=${ pkEmailToken }&email=${ email }`;
			const response = await fetch( url, { method: 'GET' } );
			const data = await response.json();

			if ( ! response.ok ) {
				throw new Error(
					data.message || 'An error occurred during email verification.',
				);
			}

			if ( data.data?.redirectUrl ) {
				window.location.href = data.data.redirectUrl;
			} else {
				const type = response.status === 200 ? 'Success' : 'Error';
				Utilities.setNotification( data.message, type, this.registerWrapper );
			}

			return data;
		} catch ( error ) {
			Utilities.setNotification( error.message, 'Error', this.registerWrapper );
			console.error( 'An error occurred during email verification:', error );
		}
	}
}

export default EmailConfirmation;
