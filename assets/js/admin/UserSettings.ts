import Registration from '../registration/Registration';
import { ApiResponse, contextType } from '../WebauthnTypes';
import Utilities from '../Utilities';

export default class UserSettings {
	private readonly passkeysContainer = document.querySelector<HTMLDivElement>(
		'.passkeys-cards-container',
	);
	private readonly registerForm =
		document.querySelector<HTMLFormElement>( '#registerform' );
	private readonly passkeysRegisterButton =
		document.querySelector<HTMLButtonElement>( '.passkeys__button--add' );
	private readonly passkeysRemoveButton =
		document.querySelectorAll<HTMLButtonElement>(
			'.passkey-card__button--remove',
		);
	private readonly context: contextType;

	constructor() {
		this.context = pkUser as contextType;
	}

	public static init(): void {
		const userSettings = new UserSettings();
		userSettings.attachEventListeners();
	}

	async startRegistration(): Promise<ApiResponse> {
		const regHandler = new Registration();
		return await regHandler.start();
	}

	/**
	 * Asynchronously removes a passkey for a user.
	 * @param {string} pkId - The ID of the passkey to remove.
	 * @return {Promise<void>} - A promise that resolves when the operation is complete.
	 */
	async removePasskey( pkId: string ): Promise<ApiResponse> {
		try {
			// Prepare headers with optional nonce for security
			const headers: HeadersInit = {};
			if ( this.context.nonce ) {
				headers[ 'X-WP-Nonce' ] = this.context.nonce;
			}

			// Perform the DELETE request to the specified endpoint with the pkId
			const response: Response = await fetch(
				`${ this.context.restEndpoints.user }/remove/${ pkId }`,
				{
					method: 'DELETE',
					headers,
				},
			);

			// Check for a successful response
			if ( ! response.ok ) {
				const errorMessage = `Server returned ${ response.status }: ${ response.statusText }`;
				console.error( errorMessage );
				Utilities.setNotification(
					errorMessage,
					'Error',
          this.registerForm as HTMLElement,
          'admin',
				);
				return; // Early exit to avoid further processing
			}
			return await response.json();
		} catch ( error ) {
			const fetchErrorMessage = `There was a problem with the fetch operation: ${ error }`;
			console.error( fetchErrorMessage );
			Utilities.setNotification(
				fetchErrorMessage,
				'Error',
        this.registerForm as HTMLElement,
        'admin',
			);
			throw error;
		}
	}

	public attachEventListeners(): void {
		if ( this.passkeysRegisterButton ) {
			this.passkeysRegisterButton.addEventListener( 'click', ( event ) => {
				this.startRegistration().then( ( r ) => {
					this.passkeysContainer?.insertAdjacentHTML(
						'beforeend',
						r.data?.cardHtml || '',
					);
					if ( event.target instanceof HTMLButtonElement ) {
						event.target.disabled = true;
					}
				} );
			} );
		}

		this.passkeysRemoveButton.forEach( ( button ) => {
			button.addEventListener( 'click', ( event ) => {
				const pkId = button.value;
				this.removePasskey( pkId ).then( ( r ) => {
					if ( r.code !== 200 ) {
						Utilities.setNotification(
							r.message,
							'Error',
              this.registerForm as HTMLElement,
              'admin',
						);

						return;
					}

					if ( event.target instanceof HTMLButtonElement ) {
						event.target.closest( '.passkey-card' )?.remove();
						Utilities.setNotification(
							r.message,
							'Success',
              this.registerForm as HTMLElement,
              'admin',
						);
					}
				} );
			} );
		} );
	}
}
