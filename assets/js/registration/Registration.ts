import {
	PublicKeyCredentialCreationOptionsJSON,
	RegistrationResponseJSON,
} from '@simplewebauthn/typescript-types';
import { startRegistration } from '@simplewebauthn/browser';
import { ApiResponse, contextType } from '../WebauthnTypes';
import Utilities from '../Utilities';

interface RegistrationOptions {
  message: string;
  data: {
    credentials: PublicKeyCredentialCreationOptionsJSON;
    email?: string;
    username?: string;
  };
}

export default class Registration {
	private readonly registerWrapper: HTMLElement;
	private readonly context: contextType;
	private readonly headers: HeadersInit;
	private readonly isAdmin: boolean;

	constructor() {
		this.registerWrapper = document.querySelector(
			'#registerform',
		) as HTMLElement;
		this.context = pkUser as contextType;
		this.headers = {
			'Content-Type': 'application/json',
			...( this.context.nonce ? { 'X-WP-Nonce': this.context.nonce } : {} ),
		};
		this.isAdmin = Boolean( this.context.nonce );
	}

	handleNotification( message: string, type: 'Success' | 'Error' ) {
		Utilities.setNotification(
			message,
			type,
			this.registerWrapper,
			this.isAdmin ? 'admin' : '',
		);
	}

	async generateOptions(): Promise<RegistrationOptions> {
		const url = `${ this.context.restEndpoints.main }/register/options`;
		return this.fetchWrapper( url, { method: 'GET', headers: this.headers } );
	}

	async verifyOptions(
		attResp: RegistrationResponseJSON,
		email?: string,
		username?: string,
	): Promise<ApiResponse> {
		const url = `${ this.context.restEndpoints.main }/register/verify?email=${ email }&username=${ username }`;
		return this.fetchWrapper( url, {
			method: 'POST',
			headers: this.headers,
			body: JSON.stringify( attResp ),
		} );
	}

	async start(): Promise<ApiResponse | void> {
		try {
			const { code, data, message } = await this.generateOptions();
			if ( code === 202 ) {
				Utilities.setNotification( message, 'Success', this.registerWrapper );
			}

			const attResp = await startRegistration( data.credentials );
			const verificationJSON = await this.verifyOptions(
				attResp,
				data.email,
				data.username,
			);
			if (
				verificationJSON.message &&
        verificationJSON.code !== 200 &&
        verificationJSON.code !== 202
			) {
				this.handleNotification( verificationJSON.message, 'Error' );
				return;
			}

			// Handle success or redirection
			if ( verificationJSON.data?.redirectUrl && ! this.isAdmin ) {
				window.location.href = verificationJSON.data.redirectUrl;
			} else {
				this.handleNotification( verificationJSON.message, 'Success' );
			}
		} catch ( error ) {
			console.log( 'An error occurred during registration:', error );
		}
	}

	private async fetchWrapper( url: string, options: RequestInit ): Promise<any> {
		const response = await fetch( url, options );
		if ( ! response.ok ) {
			const errorResponse = await response.json();
			if ( response.status === 409 ) {
				Utilities.setNotification(
					errorResponse.message,
					'Error',
					this.registerWrapper,
				);

				if ( errorResponse.message.includes( 'Email' ) ) {
					const emailInput = this.registerWrapper.querySelector(
						'#user_email',
					) as HTMLInputElement;
					emailInput.classList.add( 'error' );
				}

				if ( errorResponse.message.includes( 'Username' ) ) {
					const usernameInput = this.registerWrapper.querySelector(
						'#user_login',
					) as HTMLInputElement;
					usernameInput.classList.add( 'error' );
				}
			} else if ( response.status === 426 ) {
				Utilities.setNotification(
					'Enable HTTPS to use WebAuthn.',
					'Error',
					this.registerWrapper,
				);
			}
			throw new Error(
				`Server returned ${ response.status }: ${ errorResponse.message }`,
			);
		}
		return response.json();
	}
}
