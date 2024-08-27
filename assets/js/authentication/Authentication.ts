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
	private isAuthenticationInProgress = false;
	private abortController: AbortController | null = null;

	constructor() {
		this.loginWrapper = document.querySelector('#loginform') as HTMLElement;
		this.context = pkUser as contextType;
	}

	async generateOptions(): Promise<any> {
		const response: Response = await fetch(
			this.context.restEndpoints.main + '/authenticator/options',
		);
		if (!response.ok) {
			Utilities.setNotification(
				`${response.status}: ${response.statusText}`,
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
			(id ? '?id=' + id : ''),
			{
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify(authResp),
			},
		);

		if (verificationResp.status === 204) {
			Utilities.setNotification(
				`User with this credential ID does not exist in the database.`,
				'Error',
				this.loginWrapper,
			);
			return;
		}

		return await verificationResp.json();
	}

	async init(isAutofill: boolean): Promise<AuthenticationResponseJSON> {
		if (this.isAuthenticationInProgress) {
			if (this.abortController) {
				this.abortController.abort();
			}
			console.log("The previous authentication operation was aborted.");
			await new Promise(resolve => setTimeout(resolve, 100)); // Krótkie opóźnienie
			this.isAuthenticationInProgress = false;
		}

		if (!browserSupportsWebAuthn()) {
			Utilities.setNotification(
				'This browser does not support WebAuthn. You must use login and password.',
				'Error',
				this.loginWrapper,
			);
			return Promise.resolve(null as unknown as AuthenticationResponseJSON);
		}

		this.isAuthenticationInProgress = true;
		this.abortController = new AbortController();

		try {
			const authOptions = await this.generateOptions();
			const authResp = await startAuthentication(authOptions, isAutofill);
			if (authResp) {
				const { id } = authResp;
				await this.start(authResp, id);
				return authResp;
			}
			return Promise.resolve(null as unknown as AuthenticationResponseJSON);
		} catch (error: any) {
			if (error.name === 'AbortError') {
				console.log("The authentication operation was aborted.");
			} else if (error.name === 'NotAllowedError') {
				Utilities.setNotification(
					'The request for passkeys login was denied',
					'Info',
					this.loginWrapper,
				);
			} else {
				console.error(`Błąd uwierzytelniania: ${error.message}`);
				Utilities.setNotification(
					'An error occurred during authentication. Please try again.',
					'Error',
					this.loginWrapper,
				);
			}
			return Promise.resolve(null as unknown as AuthenticationResponseJSON);
		} finally {
			this.isAuthenticationInProgress = false;
			this.abortController = null;
		}
	}

	async start(
		authResp: AuthenticationResponseJSON,
		id?: string,
	): Promise<void> {
		try {
			const verificationJSON = await this.verify(authResp, id);

			if (verificationJSON === undefined) {
				return;
			}

			if (verificationJSON?.code === 200) {
				Utilities.setNotification(
					verificationJSON?.message,
					'Success',
					this.loginWrapper,
				);
				const redirectUrl = verificationJSON?.data.redirectUrl;

				if (redirectUrl) {
					window.location.href = redirectUrl;
				}
			} else {
				Utilities.setNotification(
					verificationJSON?.message,
					'Error',
					this.loginWrapper,
				);
			}
		} catch (error) {
			console.log(error);
		}
	}
}
