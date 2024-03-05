import Registration from '../registration/Registration';
import { VerificationResponse, contextType } from '../WebauthnTypes';
import Utilities from '../Utilities';

export default class UserSettings {
	private readonly registerForm =
		document.querySelector<HTMLFormElement>( '#registerform' );
	private readonly passkeysRegisterButton =
		document.querySelector<HTMLInputElement>( '.passkeys-login__button--add' );
	private readonly passkeysRemoveButton =
		document.querySelector<HTMLInputElement>( '.passkeys-login__button--remove' );
	private readonly passkeyRow = document.querySelector( '#pk_credential_id' );
	private readonly context: contextType;

	constructor() {
		this.context = pkUser as contextType;
	}

	public static init(): void {
		const userSettings = new UserSettings();
		userSettings.attachEventListeners();
	}

	async startRegistration(): Promise<VerificationResponse> {
		const regHandler = new Registration();
		return await regHandler.start();
	}

	async removePasskey(): Promise<void> {
		try {
			const headers: HeadersInit = {};

			if ( this.context.nonce ) {
				headers[ 'X-WP-Nonce' ] = this.context.nonce;
			}

			const response: Response = await fetch(
				this.context.restEndpoints.user + '/remove',
				{
					method: 'DELETE',
					headers,
				},
			);
			if ( ! response.ok ) {
				Utilities.setNotification(
					`Server returned ${ response.status }: ${ response.statusText }`,
					'Error',
          this.registerForm as HTMLElement,
          'admin',
				);
			}

			Utilities.setNotification(
				'Passkey removed sucessfully',
				'Success',
        this.registerForm as HTMLElement,
        'admin',
			);
			return await response.json();
		} catch ( error: any ) {
			Utilities.setNotification(
				`There was a problem with the fetch operation: ${ error }`,
				'Error',
        this.registerForm as HTMLElement,
        'admin',
			);
			throw error;
		}
	}

	public attachEventListeners(): void {
		if ( this.passkeysRegisterButton ) {
			this.passkeysRegisterButton.addEventListener( 'click', () => {
				this.startRegistration().then( ( r ) => {
					if ( this.passkeyRow ) {
						this.passkeyRow.innerHTML = <string>r.data?.pk_credential_id;
					}
				} );
			} );
		}

		if ( this.passkeysRemoveButton ) {
			this.passkeysRemoveButton.addEventListener( 'click', () => {
				this.removePasskey().then( () => {
					if ( this.passkeyRow ) {
						this.passkeyRow.innerHTML = '';
					}
				} );
			} );
		}
	}
}
