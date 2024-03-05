import {
	PublicKeyCredentialCreationOptionsJSON,
	RegistrationResponseJSON,
} from '@simplewebauthn/typescript-types';
import { startRegistration } from '@simplewebauthn/browser';
import { VerificationResponse, contextType } from '../WebauthnTypes';
import Utilities from '../Utilities';

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
		};

		this.isAdmin = false;

		if ( this.context.nonce ) {
			this.isAdmin = true;
			this.headers[ 'X-WP-Nonce' ] = this.context.nonce;
		}
	}

	async generateOptions(): Promise<PublicKeyCredentialCreationOptionsJSON> {
		try {
			const response: Response = await fetch(
				this.context.restEndpoints.main + '/register/options',
				{
					method: 'GET',
					headers: this.headers,
				},
			);
			if ( ! response.ok ) {
				console.error(
					`Server returned ${ response.status }: ${ response.statusText }`,
				);
			}
			return await response.json();
		} catch ( error: any ) {
			console.log( error );
			console.error( 'Error fetching registration options:', error );
			throw error;
		}
	}

	async verify(
		attResp: RegistrationResponseJSON,
	): Promise<VerificationResponse> {
		try {
			const response: Response = await fetch(
				this.context.restEndpoints.main + '/register/verify',
				{
					method: 'POST',
					headers: this.headers,
					body: JSON.stringify( attResp ),
				},
			);
			if ( ! response.ok ) {
				console.error(
					`Server returned ${ response.status }: ${ response.statusText }`,
				);
			}
			return await response.json();
		} catch ( error: any ) {
			console.log( error );
			console.error( 'Error verifying registration:', error );
			throw error;
		}
	}

	async start(): Promise<VerificationResponse> {
		try {
			const options: PublicKeyCredentialCreationOptionsJSON =
        await this.generateOptions();
			const attResp: RegistrationResponseJSON =
        await startRegistration( options );
			let verificationJSON: VerificationResponse = {
				code: '',
				message: '',
			};
			try {
				verificationJSON = await this.verify( attResp );
				if ( verificationJSON.data?.redirectUrl && ! this.isAdmin ) {
					window.location.href = verificationJSON.data.redirectUrl;
				}
			} catch ( error: any ) {
				console.error( 'Error in registration verification:', error );
			}

			if ( verificationJSON?.code === 200 ) {
				Utilities.setNotification(
					verificationJSON?.message,
					'Success',
					this.registerWrapper,
					this.isAdmin ? 'admin' : '',
				);
			} else {
				Utilities.setNotification(
					verificationJSON?.message,
					'Error',
					this.registerWrapper,
					this.isAdmin ? 'admin' : '',
				);
			}

			return verificationJSON;
		} catch ( error: any ) {
			throw error;
		}
	}
}
