// eslint-disable-next-line import/named
import { AuthenticationResponseJSON } from '@simplewebauthn/typescript-types';
import {
	browserSupportsWebAuthn,
	startAuthentication,
} from '@simplewebauthn/browser';
import { AuthenticatorInterface, contextType } from '../WebauthnTypes';
import Utilities from '../Utilities';

export default class Authentication implements AuthenticatorInterface {
	private readonly loginWrapper: HTMLElement;
	private readonly context: contextType;

	constructor() {
		this.loginWrapper = document.querySelector( '#loginform' ) as HTMLElement;
		this.context = pkUser as contextType;
	}

	async generateOptions(): Promise<any> {
		const response: Response = await fetch(
			this.context.restEndpoints.main + '/authenticator/options',
		);
		if ( ! response.ok ) {
			Utilities.setNotification(
				`${ response.status }: ${ response.statusText }`,
				'Error',
				this.loginWrapper,
			);
		}
		return await response.json();
	}

	async verify(
		authResp: AuthenticationResponseJSON,
		id?: string,
	): Promise<any> {
		const verificationResp: Response = await fetch(
			this.context.restEndpoints.main +
        '/authenticator/verify' +
        ( id ? '?id=' + id : '' ),
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( authResp ),
			},
		);

		if ( verificationResp.status === 204 ) {
			Utilities.setNotification(
				`User with this credential ID does not exist in the database.`,
				'Error',
				this.loginWrapper,
			);
			return;
		}

		return await verificationResp.json();
	}

	async init( isAutofill: boolean ): Promise<AuthenticationResponseJSON> {
		if ( ! browserSupportsWebAuthn() ) {
			Utilities.setNotification(
				'This browser does not support WebAuthn. You must use login and password.',
				'Error',
				this.loginWrapper,
			);
			return Promise.resolve( null as unknown as AuthenticationResponseJSON );
		}
		try {
			const authOptions = await this.generateOptions();
			const authResp = await startAuthentication( authOptions, isAutofill );
			if ( authResp ) {
				const { id } = authResp;
				await this.start( authResp, id );
				return authResp;
			}
			// In case authResp is falsy, we return a default value
			return Promise.resolve( null as unknown as AuthenticationResponseJSON );
		} catch ( error: any ) {
			console.log( error );
			if ( error.name === 'NotAllowedError' ) {
				Utilities.setNotification(
					'The request for passkeys login was denied',
					'Info',
					this.loginWrapper,
				);
			} else {
				Utilities.setNotification(
					`${ error.message }`,
					'Error',
					this.loginWrapper,
				);
			}
			return Promise.resolve( null as unknown as AuthenticationResponseJSON );
		}
	}

	async start(
		authResp: AuthenticationResponseJSON,
		id?: string,
	): Promise<void> {
		try {
			const verificationJSON = await this.verify( authResp, id );

			if ( verificationJSON === undefined ) {
				return;
			}

			if ( verificationJSON?.code === 200 ) {
				Utilities.setNotification(
					verificationJSON?.message,
					'Success',
					this.loginWrapper,
				);
			} else {
				Utilities.setNotification(
					verificationJSON?.message,
					'Error',
					this.loginWrapper,
				);
			}

			const redirectUrl = verificationJSON?.data.redirectUrl;

			if ( redirectUrl ) {
				window.location.href = redirectUrl;
			}
		} catch ( error ) {
			console.log( error );
		}
	}
}
