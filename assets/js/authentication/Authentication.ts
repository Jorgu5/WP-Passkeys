// eslint-disable-next-line import/named
import { AuthenticationResponseJSON, PublicKeyCredentialRequestOptionsJSON } from '@simplewebauthn/typescript-types';
import {
	browserSupportsWebAuthn,
	startAuthentication,
} from '@simplewebauthn/browser';
import { AuthenticatorInterface, ContextType, NotificationStatus, ApiResponse } from '../types';
import Utilities from '../Utilities';

/**
 * Authentication class for handling WebAuthn/Passkey authentication
 */
export default class Authentication implements AuthenticatorInterface {
	private readonly loginWrapper: HTMLElement | null;
	private readonly context: ContextType;
	private readonly loadingElement: HTMLElement | null;
	private readonly statusElement: HTMLElement | null;
	private isAuthenticationInProgress = false;
	private abortController: AbortController | null = null;

	constructor() {
		this.loginWrapper = document.querySelector( '#loginform' );
		this.context = ( window as any ).pkUser as ContextType || {
			restEndpoints: { main: '' },
			nonce: '',
		};
		this.loadingElement = document.querySelector( '#passkeys-loading' );
		this.statusElement = document.querySelector( '#passkeys-status' );

		// Log initialization status for debugging
		if ( ! this.loginWrapper ) {
			console.warn( 'Login form wrapper element not found' );
		}
		if ( ! this.context || ! this.context.restEndpoints ) {
			console.error( 'Context or REST endpoints not properly initialized' );
		}
		if ( ! this.loadingElement ) {
			console.warn( 'Loading element not found' );
		}
		if ( ! this.statusElement ) {
			console.warn( 'Status element not found' );
		}
	}

	/**
	 * Generate authentication options from the server
	 * @return Promise with the authentication options
	 */
	async generateOptions(): Promise<PublicKeyCredentialRequestOptionsJSON> {
		try {
			const response: Response = await fetch(
				this.context.restEndpoints.main + '/authenticator/options',
				{
					headers: {
						'X-WP-Nonce': this.context.nonce || '',
					},
				},
			);

			if ( ! response.ok ) {
				this.showStatus(
					`${ response.status }: ${ response.statusText }`,
					'error',
				);
				throw new Error( `Server returned ${ response.status }: ${ response.statusText }` );
			}

			// Check if the response is JSON
			const contentType = response.headers.get( 'content-type' );
			if ( ! contentType || ! contentType.includes( 'application/json' ) ) {
				// Handle non-JSON response
				const text = await response.text();
				console.error( 'Server returned non-JSON response:', text );
				this.showStatus(
					'Server returned an invalid response format. Please check server logs.',
					'error',
				);
				throw new Error( 'Invalid response format' );
			}

			return await response.json();
		} catch ( error ) {
			console.error( 'Error generating options:', error );
			throw error;
		}
	}

	/**
	 * Verify the authentication response with the server
	 * @param authResp - The authentication response from the browser
	 * @param id       - Optional credential ID
	 * @return Promise with the API response
	 */
	async verify(
		authResp: AuthenticationResponseJSON,
		id?: string,
	): Promise<ApiResponse> {
		try {
			console.log( 'Verifying authentication with server...', id ? `ID: ${ id }` : 'No ID provided' );

			const endpoint = this.context.restEndpoints.main +
				'/authenticator/verify' +
				( id ? '?id=' + encodeURIComponent( id ) : '' );

			console.log( 'Sending request to:', endpoint );

			const verificationResp: Response = await fetch(
				endpoint,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': this.context.nonce || '',
					},
					body: JSON.stringify( authResp ),
				},
			);

			console.log( 'Server response status:', verificationResp.status );

			// Handle 404 Not Found responses
			if ( verificationResp.status === 404 ) {
				this.showStatus(
					`No user found with this credential. Please register a passkey first.`,
					'error',
				);
				return { code: 404, message: 'User not found' };
			}

			// Handle 204 No Content responses (legacy)
			if ( verificationResp.status === 204 ) {
				this.showStatus(
					`User with this credential ID does not exist in the database.`,
					'error',
				);
				return { code: 404, message: 'User not found' };
			}

			// Handle other error responses
			if ( ! verificationResp.ok ) {
				const errorText = await verificationResp.text();
				console.error( 'Server error response:', errorText );
				this.showStatus(
					`Authentication failed: ${ verificationResp.status } ${ verificationResp.statusText }`,
					'error',
				);
				return {
					code: verificationResp.status,
					message: `Server error: ${ verificationResp.statusText }`,
				};
			}

			// Parse the JSON response
			try {
				const jsonResponse = await verificationResp.json();
				console.log( 'Server response:', jsonResponse );
				return jsonResponse;
			} catch ( jsonError ) {
				console.error( 'Error parsing JSON response:', jsonError );
				return {
					code: 500,
					message: 'Invalid response format from server',
				};
			}
		} catch ( error ) {
			console.error( 'Error verifying authentication:', error );
			this.showStatus(
				`Network error during authentication. Please try again.`,
				'error',
			);
			throw error;
		}
	}

	/**
	 * Initialize the authentication process
	 * @param isAutofill - Whether this is an autofill request
	 * @return Promise with the authentication response
	 */
	async init( isAutofill: boolean ): Promise<AuthenticationResponseJSON> {
		if ( this.isAuthenticationInProgress ) {
			if ( this.abortController ) {
				this.abortController.abort();
			}
			console.log( 'The previous authentication operation was aborted.' );
			await new Promise( ( resolve ) => setTimeout( resolve, 100 ) ); // Short delay
			this.isAuthenticationInProgress = false;
		}

		// Check for WebAuthn support
		if ( ! browserSupportsWebAuthn() ) {
			this.showStatus(
				'This browser does not support WebAuthn. You must use login and password.',
				'error',
			);
			return Promise.resolve( null as unknown as AuthenticationResponseJSON );
		}

		// Debug WebAuthn input detection
		console.log( 'Checking for WebAuthn input field...' );
		const webauthnInputs = document.querySelectorAll( 'input[autocomplete="webauthn"]' );
		console.log( 'Found WebAuthn inputs:', webauthnInputs.length );
		webauthnInputs.forEach( ( input, index ) => {
			console.log( `WebAuthn input ${ index }:`, {
				id: input.id,
				name: input.name,
				type: input.type,
				autocomplete: input.getAttribute( 'autocomplete' ),
				hidden: input.hidden,
				style: input.getAttribute( 'style' ),
				parent: input.parentElement?.tagName,
			} );
		} );

		// If no WebAuthn input is found, try to add one
		if ( webauthnInputs.length === 0 ) {
			console.log( 'No WebAuthn input found, adding one dynamically...' );
			const loginForm = document.querySelector( '#loginform' );
			if ( loginForm ) {
				const container = document.createElement( 'div' );
				container.id = 'webauthn-container';
				container.style.position = 'relative';

				const input = document.createElement( 'input' );
				input.type = 'text';
				input.name = 'webauthn-input';
				input.id = 'webauthn-input';
				input.setAttribute( 'autocomplete', 'webauthn' );
				input.style.position = 'absolute';
				input.style.top = '0';
				input.style.left = '0';
				input.style.opacity = '0.01';
				input.style.width = '100%';
				input.style.height = '100%';
				input.style.zIndex = '1';
				input.tabIndex = -1;
				input.setAttribute( 'aria-hidden', 'true' );

				container.appendChild( input );
				loginForm.insertBefore( container, loginForm.firstChild );
				console.log( 'Added WebAuthn input dynamically' );
			} else {
				console.error( 'Could not find #loginform to add WebAuthn input' );
			}
		}

		this.isAuthenticationInProgress = true;
		this.abortController = new AbortController();
		this.showLoading( true );

		try {
			console.log( 'Starting authentication process...' );

			// Check if the REST API endpoint is properly configured
			if ( ! this.context.restEndpoints || ! this.context.restEndpoints.main ) {
				console.error( 'REST API endpoints not properly configured' );
				this.showStatus(
					'Authentication configuration error. Please contact the administrator.',
					'error',
				);
				return Promise.resolve( null as unknown as AuthenticationResponseJSON );
			}

			console.log( 'Generating authentication options...' );
			const authOptions = await this.generateOptions();
			console.log( 'Authentication options received:', authOptions );

			// Fix the startAuthentication call to match the expected format
			console.log( 'Starting browser authentication...' );
			const authResp = await startAuthentication( {
				optionsJSON: authOptions,
				useBrowserAutofill: isAutofill,
			} );
			console.log( 'Browser authentication completed:', authResp ? 'success' : 'failed' );

			if ( authResp ) {
				const { id } = authResp;
				console.log( 'Starting server verification with ID:', id );
				await this.start( authResp, id );
				return authResp;
			}
			return Promise.resolve( null as unknown as AuthenticationResponseJSON );
		} catch ( error: unknown ) {
			console.error( 'Authentication error:', error );
			this.handleAuthenticationError( error as Error );
			return Promise.resolve( null as unknown as AuthenticationResponseJSON );
		} finally {
			this.isAuthenticationInProgress = false;
			this.abortController = null;
			this.showLoading( false );
		}
	}

	/**
	 * Handle authentication errors
	 * @param error - The error to handle
	 */
	private handleAuthenticationError( error: any ): void {
		// Log the full error object for debugging
		console.error( 'Full authentication error:', error );

		if ( error.name === 'AbortError' ) {
			console.log( 'The authentication operation was aborted.' );
		} else if ( error.name === 'NotAllowedError' ) {
			this.showStatus(
				'The request for passkey login was denied',
				'info',
			);
		} else if ( error.name === 'SecurityError' ) {
			this.showStatus(
				'A security error occurred. Make sure you are using HTTPS.',
				'error',
			);
		} else if ( error.name === 'InvalidStateError' ) {
			this.showStatus(
				'The passkey is already registered with this site.',
				'error',
			);
		} else if ( error.name === 'NotSupportedError' ) {
			this.showStatus(
				'This authentication method is not supported by your browser or device.',
				'error',
			);
		} else if ( error.name === 'NetworkError' ) {
			this.showStatus(
				'A network error occurred. Please check your connection and try again.',
				'error',
			);
		} else if ( error.message && error.message.includes( 'JSON' ) ) {
			this.showStatus(
				'Invalid response from server. Please try again or contact support.',
				'error',
			);
		} else {
			// More detailed error message with error name and message
			const errorDetails = error.name && error.message
				? `${ error.name }: ${ error.message }`
				: error.toString();

			console.error( `Authentication error details: ${ errorDetails }` );
			this.showStatus(
				`Authentication error: ${ errorDetails }. Please try again.`,
				'error',
			);
		}
	}

	/**
	 * Start the authentication process
	 * @param authResp - The authentication response
	 * @param id       - Optional credential ID
	 */
	async start(
		authResp: AuthenticationResponseJSON,
		id?: string,
	): Promise<void> {
		try {
			console.log( 'Starting verification with server...', { id, authResp } );

			const verificationJSON = await this.verify( authResp, id );
			console.log( 'Verification response:', verificationJSON );

			if ( verificationJSON === undefined ) {
				console.warn( 'Verification returned undefined' );
				this.showStatus( 'Server returned an empty response', 'error' );
				return;
			}

			if ( verificationJSON?.code === 200 ) {
				console.log( 'Authentication successful, response:', verificationJSON );
				this.showStatus(
					verificationJSON?.message || 'Authentication successful',
					'success',
				);
				const redirectUrl = verificationJSON?.data?.redirectUrl;

				if ( redirectUrl ) {
					console.log( 'Redirecting to:', redirectUrl );
					window.location.href = redirectUrl;
				} else {
					console.warn( 'No redirect URL provided in successful response' );
				}
			} else {
				console.error( 'Authentication failed, response:', verificationJSON );
				this.showStatus(
					verificationJSON?.message || 'Authentication failed',
					'error',
				);
			}
		} catch ( error ) {
			console.error( 'Error in start method:', error );

			// Try to extract more meaningful error information
			let errorMessage = 'An unexpected error occurred';
			if ( error instanceof Error ) {
				errorMessage = `Error: ${ error.name } - ${ error.message }`;
			} else if ( error && typeof error === 'object' ) {
				errorMessage = `Error: ${ JSON.stringify( error ) }`;
			} else if ( error ) {
				errorMessage = `Error: ${ String( error ) }`;
			}

			this.showStatus( errorMessage, 'error' );
		}
	}

	/**
	 * Show or hide the loading indicator
	 * @param show - Whether to show the loading indicator
	 */
	private showLoading( show: boolean ): void {
		if ( this.loadingElement ) {
			this.loadingElement.hidden = ! show;
		}
	}

	/**
	 * Show a status message
	 * @param message - The message to show
	 * @param type    - The type of message
	 */
	private showStatus( message: string, type: 'success' | 'error' | 'info' ): void {
		if ( this.statusElement ) {
			this.statusElement.textContent = message;
			this.statusElement.className = `passkeys-status ${ type }`;
		} else {
			// Fallback to the old notification method
			Utilities.setNotification(
				message,
				type === 'error' ? 'Error' : type === 'success' ? 'Success' : 'Info',
				this.loginWrapper,
			);

			// Also log the message to console for debugging
			if ( type === 'error' ) {
				console.error( 'Passkeys status:', message );
			} else if ( type === 'success' ) {
				console.log( 'Passkeys status:', message );
			} else {
				console.info( 'Passkeys status:', message );
			}
		}
	}
}
